<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use App\Models\EventoOperacion;
use App\Models\Pedido;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sincronización de las ventas cobradas sin conexión.
 * ---------------------------------------------------------------------------
 * LA REGLA QUE GOBIERNA TODO ESTE SERVICIO
 *
 * El producto ya salió del anaquel. Cuando una venta llega aquí, el cliente
 * se fue hace rato con la mercadería en la mano. El servidor no decide si la
 * venta ocurrió: ya ocurrió, y su trabajo es registrarla.
 *
 * De ahí sale la decisión que más llama la atención al leer el código: una
 * venta sincronizada NO se rechaza porque el stock central ya no alcance. Si
 * otra caja vendió las mismas unidades mientras este equipo estaba sin red,
 * la venta se registra igual y la diferencia queda como incidencia de
 * inventario para el conteo por ciclos. Rechazarla dejaría al sistema
 * afirmando que algo no pasó cuando sí pasó, y el descuadre aparecería igual
 * en el anaquel, pero sin rastro de dónde salió.
 *
 * `VentaService` ya se comporta así para el origen mostrador: entrega lo que
 * hay y registra la incidencia. Por eso aquí se reutiliza tal cual en vez de
 * escribir un camino paralelo.
 *
 * UNA TRANSACCION POR VENTA, NO UNA POR LOTE
 *
 * Envolver el bucle entero en una transacción sería más cómodo de escribir y
 * un desastre de operar: una venta con un producto borrado tumbaría las otras
 * diecinueve del lote, que son correctas, y la caja volvería a enviarlas una
 * y otra vez sin que ninguna entre nunca. Cada venta se confirma o se revierte
 * sola, y el resultado se informa venta por venta.
 */
class SincronizacionVentasService
{
    public const ESTADO_CREADA    = 'creada';
    public const ESTADO_DUPLICADA = 'duplicada';
    public const ESTADO_RECHAZADA = 'rechazada';

    public function __construct(
        private readonly VentaService $ventas,
        private readonly ComprobanteService $comprobantes,
    ) {
    }

    /**
     * Procesa un lote completo.
     *
     * @param  array<int, array<string, mixed>>  $ventas
     * @return array<int, array<string, mixed>>  un resultado por venta, en el mismo orden
     */
    public function procesarLote(array $ventas, Usuario $operador, ?string $dispositivo = null): array
    {
        $resultados = [];

        foreach ($ventas as $venta) {
            $resultados[] = $this->procesarUna($venta, $operador, $dispositivo);
        }

        $conteo = array_count_values(array_column($resultados, 'estado'));

        EventoOperacion::info(
            EventoOperacion::SYNC_LOTE,
            sprintf(
                'Lote sincronizado desde %s: %d creadas, %d duplicadas, %d rechazadas.',
                $dispositivo ?? 'caja sin identificar',
                $conteo[self::ESTADO_CREADA] ?? 0,
                $conteo[self::ESTADO_DUPLICADA] ?? 0,
                $conteo[self::ESTADO_RECHAZADA] ?? 0,
            ),
            [
                'dispositivo' => $dispositivo,
                'usuario_id'  => $operador->id,
                'contexto'    => ['total' => count($ventas)] + $conteo,
            ],
        );

        return $resultados;
    }

    /**
     * @param  array<string, mixed>  $venta
     * @return array<string, mixed>
     */
    private function procesarUna(array $venta, Usuario $operador, ?string $dispositivo): array
    {
        $uuid = (string) $venta['uuid_local'];

        /* Comprobación barata antes de abrir transacción. No es la garantía
           —esa la da el índice único, más abajo—, sino el atajo para el caso
           habitual: la caja reenvía su cola entera y casi todo ya entró. */
        $existente = Pedido::where('uuid_local', $uuid)->first();

        if ($existente !== null) {
            return $this->respuestaDuplicada($existente, $uuid, $operador, $dispositivo);
        }

        try {
            return DB::transaction(function () use ($venta, $uuid, $operador, $dispositivo) {
                $momentoVenta = CarbonImmutable::parse($venta['registrada_en']);

                $pedido = $this->ventas->registrar(
                    items: $venta['items'],
                    datosCliente: [
                        'cliente_nombre'         => $venta['cliente']['nombre'] ?? null,
                        'cliente_documento'      => $venta['cliente']['documento'] ?? null,
                        'cliente_tipo_documento' => $venta['cliente']['tipo_documento'] ?? 'sin_documento',
                        'observacion'            => $venta['observacion'] ?? null,
                    ],
                    vendedorId: $operador->id,
                    pagos: $venta['pagos'] ?? [],
                    origen: Pedido::ORIGEN_POS,
                );

                /* La fecha de la venta es la del mostrador, no la del envío.
                   `registrar()` pone `now()` porque la venta normal ocurre
                   mientras se registra; aquí no, y usar la hora de la
                   sincronización metería la venta en el día equivocado del
                   registro de ventas. */
                $pedido->forceFill([
                    'fecha'                 => $momentoVenta,
                    'fecha_pedido'          => $momentoVenta,
                    'uuid_local'            => $uuid,
                    'nota_contingencia'     => $venta['nota_contingencia'] ?? null,
                    'registrado_offline_en' => $momentoVenta,
                    'dispositivo_origen'    => $dispositivo,
                ])->save();

                /* El correlativo se asigna aquí y no en la caja: otra caja o
                   el portal pueden haber emitido mientras este equipo estaba
                   sin red, y el número lo reparte quien ve la serie entera.
                   `emitirConDatos` bloquea la fila de la serie. */
                $comprobante = $this->comprobantes->emitirConDatos(
                    $pedido,
                    $this->tipoComprobante($pedido),
                    [
                        'cliente_nombre'    => $pedido->cliente_nombre ?: 'Cliente sin identificar',
                        'cliente_documento' => $pedido->cliente_documento,
                        'total'             => $pedido->total,
                        'fecha_emision'     => $momentoVenta,
                    ],
                );

                $incidencias = $pedido->incidencias()->get()
                    ->map(fn ($i) => [
                        'producto_id' => (int) $i->producto_id,
                        'tipo'        => $i->tipo,
                        'solicitada'  => (int) $i->cantidad_solicitada,
                        'atendida'    => (int) $i->cantidad_atendida,
                    ])
                    ->all();

                EventoOperacion::info(
                    EventoOperacion::SYNC_CREADA,
                    "Venta offline {$uuid} registrada como pedido {$pedido->id}.",
                    [
                        'dispositivo' => $dispositivo,
                        'uuid_local'  => $uuid,
                        'usuario_id'  => $operador->id,
                        'pedido_id'   => $pedido->id,
                        'contexto'    => [
                            'comprobante'    => $this->identificador($comprobante),
                            'vendida_el'     => $momentoVenta->toIso8601String(),
                            'horas_de_atraso' => $momentoVenta->diffInHours(now()),
                        ],
                    ],
                );

                if ($incidencias !== []) {
                    EventoOperacion::advertencia(
                        EventoOperacion::SYNC_INCIDENCIA,
                        "La venta {$uuid} se registró con stock insuficiente en el momento de sincronizar.",
                        [
                            'dispositivo' => $dispositivo,
                            'uuid_local'  => $uuid,
                            'usuario_id'  => $operador->id,
                            'pedido_id'   => $pedido->id,
                            'contexto'    => ['incidencias' => $incidencias],
                        ],
                    );
                }

                return [
                    'uuid_local'  => $uuid,
                    'estado'      => self::ESTADO_CREADA,
                    'pedido_id'   => $pedido->id,
                    'comprobante' => $this->identificador($comprobante),
                    'incidencias' => $incidencias,
                ];
            });
        } catch (QueryException $e) {
            /* Dos envíos del mismo lote cruzados en el tiempo: el segundo
               choca contra el índice único de `uuid_local`. No es un error de
               la caja ni hay nada que arreglar; la venta está registrada. */
            $yaRegistrado = Pedido::where('uuid_local', $uuid)->first();

            if ($yaRegistrado !== null) {
                return $this->respuestaDuplicada($yaRegistrado, $uuid, $operador, $dispositivo);
            }

            return $this->respuestaRechazada($uuid, $e, $operador, $dispositivo);
        } catch (Throwable $e) {
            return $this->respuestaRechazada($uuid, $e, $operador, $dispositivo);
        }
    }

    /** @return array<string, mixed> */
    private function respuestaDuplicada(Pedido $pedido, string $uuid, Usuario $operador, ?string $dispositivo): array
    {
        EventoOperacion::advertencia(
            EventoOperacion::SYNC_DUPLICADA,
            "La venta {$uuid} ya estaba registrada como pedido {$pedido->id}; se ignora el reenvío.",
            [
                'dispositivo' => $dispositivo,
                'uuid_local'  => $uuid,
                'usuario_id'  => $operador->id,
                'pedido_id'   => $pedido->id,
            ],
        );

        return [
            'uuid_local'  => $uuid,
            'estado'      => self::ESTADO_DUPLICADA,
            'pedido_id'   => $pedido->id,
            'comprobante' => $this->identificador($pedido->comprobanteElectronico),
            'incidencias' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function respuestaRechazada(string $uuid, Throwable $e, Usuario $operador, ?string $dispositivo): array
    {
        EventoOperacion::error(
            EventoOperacion::SYNC_RECHAZADA,
            "La venta {$uuid} no se pudo registrar: {$e->getMessage()}",
            [
                'dispositivo' => $dispositivo,
                'uuid_local'  => $uuid,
                'usuario_id'  => $operador->id,
                'contexto'    => ['excepcion' => $e::class],
            ],
        );

        return [
            'uuid_local' => $uuid,
            'estado'     => self::ESTADO_RECHAZADA,
            /* El mensaje va tal cual a la pantalla de la caja: quien lo lee es
               el vendedor, no un programador. Los servicios de venta ya los
               redactan pensando en eso. */
            'motivo'     => $e->getMessage(),
        ];
    }

    private function tipoComprobante(Pedido $pedido): string
    {
        $documento = preg_replace('/\D/', '', (string) $pedido->cliente_documento);

        return strlen((string) $documento) === 11 ? 'factura' : 'boleta';
    }

    private function identificador(?ComprobanteElectronico $comprobante): ?string
    {
        if ($comprobante === null) {
            return null;
        }

        return $comprobante->serie.'-'.str_pad((string) $comprobante->numero, 8, '0', STR_PAD_LEFT);
    }
}
