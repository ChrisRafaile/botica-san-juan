<?php

namespace App\Services;

use App\Exceptions\PedidoNoCancelableException;
use App\Models\ComprobanteElectronico;
use App\Models\EventoOperacion;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Usuario;
use App\Services\Pagos\EstadoPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de orquestación de ciclo de vida de pedidos web:
 * confirmación con reserva de stock, cancelación pesimista y liberación de expirados.
 */
class PedidoService
{
    public function __construct(
        private readonly VentaService $ventas,
        private readonly InventarioFefoService $inventarioFefo,
        private readonly ComprobanteService $comprobantes,
    ) {
    }

    /**
     * Confirma un pedido originado en el portal cliente dentro de una transacción con reintentos.
     *
     * Fija `expira_en` con el TTL configurado SOLO si el cliente cuenta con sesión
     * y eligió 'tarjeta' como método de pago. Invitados o pagos en mostrador quedan con
     * `expira_en = null` para no cancelar prematuramente a quien acude a recoger en botica.
     *
     * @param  array<int, array{producto_id: int, cantidad: int, unidad_venta?: string}>  $items
     * @param  array{cliente_nombre?: string|null, cliente_documento?: string|null, cliente_tipo_documento?: string|null, cliente_telefono?: string|null, observacion?: string|null}  $datosCliente
     * @return array{0: Pedido, 1: ComprobanteElectronico}
     */
    public function confirmarDelPortal(
        array $items,
        array $datosCliente,
        ?Usuario $cliente,
        ?string $metodoPago = null,
    ): array {
        return DB::transaction(function () use ($items, $datosCliente, $cliente, $metodoPago) {
            $pedido = $this->ventas->registrar(
                items: $items,
                datosCliente: $datosCliente,
                usuarioClienteId: $cliente?->id,
                origen: Pedido::ORIGEN_WEB,
            );

            // TTL de reserva: solo aplica a cliente con sesión y pago con tarjeta
            if ($cliente !== null && $metodoPago === 'tarjeta') {
                $ttlMinutos = (int) config('pedidos.ttl_reserva_minutos', 15);
                $pedido->expira_en = now()->addMinutes($ttlMinutos);
                $pedido->save();
            }

            $comprobante = $this->comprobantes->emitir($pedido);

            return [$pedido, $comprobante];
        }, 3);
    }

    /**
     * Cancela un pedido abierto devolviendo stock a sus lotes de origen.
     *
     * ORDEN ESTRICTO DE BLOQUEO PESIMISTA:
     *   1. Fila de `pedidos` (id asc)
     *   2. Fila de `pagos` (id asc)
     *   3. Filas de `productos` (id asc)
     *   4. Filas de `lotes` (id asc)
     *
     * @return array{pedido: Pedido, comprobante: ?ComprobanteElectronico, stock_restituido: array}
     *
     * @throws PedidoNoCancelableException
     */
    public function cancelar(Pedido $pedido, string $motivo, ?int $actorId): array
    {
        return DB::transaction(function () use ($pedido, $motivo, $actorId) {
            // 1. Bloquear pedido con FOR UPDATE
            $pedidoBloqueado = Pedido::whereKey($pedido->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Bloquear fila de pagos asociada con FOR UPDATE
            $pago = Pago::where('pedido_id', $pedidoBloqueado->id)
                ->lockForUpdate()
                ->first();

            // 3 y 4. Bloquear productos y lotes en orden global ascendente
            $pedidoBloqueado->loadMissing(['pedidoDetalles.lotesAsignados', 'comprobanteElectronico']);

            $productoIds = $pedidoBloqueado->pedidoDetalles
                ->pluck('producto_id')
                ->unique()
                ->sort()
                ->values()
                ->all();

            $this->inventarioFefo->bloquearParaVenta($productoIds);

            // Validar origen: solo pedidos web pueden ser cancelados
            if ($pedidoBloqueado->origen !== Pedido::ORIGEN_WEB) {
                throw new PedidoNoCancelableException(
                    'venta_mostrador',
                    'Las ventas de mostrador nacen completadas y no admiten cancelación por este medio.'
                );
            }

            // Validar estado del pedido
            if ($pedidoBloqueado->estado === Pedido::ESTADO_ANULADO) {
                throw new PedidoNoCancelableException(
                    'ya_anulado',
                    'El pedido ya se encuentra anulado.'
                );
            }

            if (! in_array($pedidoBloqueado->estado, Pedido::ESTADOS_ABIERTOS, true)) {
                throw new PedidoNoCancelableException(
                    'estado_invalido',
                    "El pedido se encuentra en estado '{$pedidoBloqueado->estado}' y no admite cancelación."
                );
            }

            // Validar estado del pago
            /* Se mira la fila de `pagos` ademas de la columna del pedido: es la
               que la notificacion de la pasarela escribe primero y, con la fila
               ya bloqueada arriba, la unica lectura que no puede quedar vieja.
               Fiarse solo de `pedidos.estado_pago` dejaba una ventana en la que
               el cobro ya estaba confirmado y el pedido aun decia «procesando». */
            if ($pedidoBloqueado->estado_pago === EstadoPago::PAGADO || $pago?->estado === EstadoPago::PAGADO) {
                throw new PedidoNoCancelableException(
                    'pago_confirmado',
                    'El pedido ya registra un pago confirmado y no puede ser cancelado directamente.'
                );
            }

            $estadosPagoPermitidos = [
                EstadoPago::PENDIENTE,
                EstadoPago::PROCESANDO,
                EstadoPago::FALLIDO,
            ];

            if ($pedidoBloqueado->estado_pago !== null && ! in_array($pedidoBloqueado->estado_pago, $estadosPagoPermitidos, true)) {
                throw new PedidoNoCancelableException(
                    'pago_invalido',
                    "El estado de pago '{$pedidoBloqueado->estado_pago}' no permite la cancelación."
                );
            }

            // Validar comprobante electrónico
            $comprobante = $pedidoBloqueado->comprobanteElectronico;
            if ($comprobante !== null) {
                if ($comprobante->estado_sunat === 'aceptada') {
                    throw new PedidoNoCancelableException(
                        'comprobante_aceptado',
                        'El comprobante ya fue aceptado por SUNAT. Requiere emisión de nota de crédito.'
                    );
                }

                if (in_array($comprobante->estado_sunat, ['pendiente', 'rechazada'], true)) {
                    $comprobante->estado_sunat = 'anulada';
                    $comprobante->anulado_en = now();
                    $comprobante->motivo_anulacion = $motivo;
                    $comprobante->save();
                }
            }

            // Devolver stock a cada lote de origen
            $stockRestituido = $this->inventarioFefo->devolverDePedido(
                $pedidoBloqueado,
                $motivo,
                $actorId,
            );

            // Cancelar el pago si existe
            if ($pago !== null) {
                $pago->estado = EstadoPago::CANCELADO;
                $pago->save();
            }

            // Anular pedido
            $pedidoBloqueado->estado = Pedido::ESTADO_ANULADO;
            $pedidoBloqueado->estado_pago = EstadoPago::CANCELADO;
            $pedidoBloqueado->anulado_en = now();
            $pedidoBloqueado->motivo_anulacion = $motivo;
            $pedidoBloqueado->expira_en = null;
            $pedidoBloqueado->save();

            // Registrar en bitácora de auditoría
            EventoOperacion::info(
                'PEDIDO_CANCELADO',
                "Pedido #{$pedidoBloqueado->id} cancelado ({$motivo}). Stock restituido.",
                [
                    'pedido_id'  => $pedidoBloqueado->id,
                    'usuario_id' => $actorId,
                    'contexto'   => [
                        'motivo'           => $motivo,
                        'stock_restituido' => $stockRestituido,
                    ],
                ]
            );

            return [
                'pedido'           => $pedidoBloqueado,
                'comprobante'      => $comprobante,
                'stock_restituido' => $stockRestituido,
            ];
        }, 3);
    }

    /**
     * Libera reservas de pedidos expirados ejecutando cada cancelación en su propia transacción.
     *
     * @return array{encontrados: int, liberados: int, saltados: int, dry_run: bool, detalles: array}
     */
    public function liberarExpirados(?int $limite = null, bool $dryRun = false): array
    {
        $query = Pedido::query()
            ->where('origen', Pedido::ORIGEN_WEB)
            ->whereIn('estado', Pedido::ESTADOS_ABIERTOS)
            ->where('estado_pago', '!=', EstadoPago::PAGADO)
            ->whereNotNull('expira_en')
            ->where('expira_en', '<=', now())
            ->orderBy('id', 'asc');

        if ($limite !== null && $limite > 0) {
            $query->limit($limite);
        }

        $pedidosExpirados = $query->get();

        if ($dryRun) {
            return [
                'encontrados' => $pedidosExpirados->count(),
                'liberados'   => 0,
                'saltados'    => 0,
                'dry_run'     => true,
                'detalles'    => $pedidosExpirados->map(fn ($p) => [
                    'id'        => $p->id,
                    'expira_en' => $p->expira_en?->toIso8601String(),
                ])->all(),
            ];
        }

        $liberados = 0;
        $saltados = 0;
        $detalles = [];

        foreach ($pedidosExpirados as $p) {
            try {
                $this->cancelar($p, 'expirado', null);
                $liberados++;
                $detalles[] = ['id' => $p->id, 'estado' => 'liberado'];
            } catch (\Throwable $e) {
                $saltados++;
                Log::warning("liberarExpirados: no se pudo cancelar pedido #{$p->id}: {$e->getMessage()}");
                $detalles[] = ['id' => $p->id, 'estado' => 'saltado', 'error' => $e->getMessage()];
            }
        }

        return [
            'encontrados' => $pedidosExpirados->count(),
            'liberados'   => $liberados,
            'saltados'    => $saltados,
            'dry_run'     => false,
            'detalles'    => $detalles,
        ];
    }
}
