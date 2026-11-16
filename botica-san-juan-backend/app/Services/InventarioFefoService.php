<?php

namespace App\Services;

use App\Models\Lote;
use App\Models\MovimientoStock;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Servicio centralizado para el bloqueo ordenado, descuento FEFO y devolución
 * de stock en pedidos.
 *
 * ORDEN GLOBAL DE BLOQUEO CONTRA INTERBLOQUEOS
 * Para evitar interbloqueos (deadlocks) entre transacciones concurrentes
 * que solicitan los mismos productos en órdenes distintos, todo acceso a
 * inventario debe adquirir bloqueos pesimistas respetando estrictamente:
 *
 *   1. Fila de productos ordenados por ID ascendente.
 *   2. Fila de lotes ordenados por ID ascendente.
 */
class InventarioFefoService
{
    public function __construct(
        private readonly InventarioService $inventarioService,
    ) {
    }

    /**
     * Adquiere bloqueos pesimistas (SELECT FOR UPDATE) sobre los productos y
     * sus lotes siguiendo el orden canónico global (ID ascendente).
     *
     * @param  array<int, int>  $productoIds
     * @return array{productos: Collection, lotes: Collection}
     */
    public function bloquearParaVenta(array $productoIds): array
    {
        if (! DB::transactionLevel()) {
            throw new RuntimeException('bloquearParaVenta() debe ejecutarse dentro de una transacción activa.');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $productoIds))));
        sort($ids, SORT_NUMERIC);

        if (empty($ids)) {
            return [
                'productos' => collect(),
                'lotes' => collect(),
            ];
        }

        $productos = Producto::whereIn('id', $ids)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        $lotes = Lote::whereIn('producto_id', $ids)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        return [
            'productos' => $productos,
            'lotes' => $lotes,
        ];
    }

    /**
     * Descuenta stock siguiendo FEFO delegando en InventarioService.
     * Las filas ya deben haber sido bloqueadas por bloquearParaVenta en la misma transacción.
     *
     * @return array{asignaciones: array, atendido: int, faltante: int}
     */
    public function descontar(
        Producto $producto,
        int $unidades,
        string $referenciaTipo,
        ?int $referenciaId,
        ?int $usuarioId = null,
        string $tipoMovimiento = 'venta',
    ): array {
        return $this->inventarioService->descontar(
            $producto,
            $unidades,
            $referenciaTipo,
            $referenciaId,
            $usuarioId,
            $tipoMovimiento,
        );
    }

    /**
     * Devuelve a sus lotes de origen todas las unidades entregadas en un pedido.
     *
     * Reglas:
     * - Cada unidad vuelve a su lote original según `pedido_detalle_lotes`.
     * - Si el lote estaba en estado 'agotado', se reactiva a 'activo'.
     * - Si el lote estaba en 'vencido' o 'retirado', se restituye la cantidad pero se conserva su estado.
     * - Se registra un `MovimientoStock` de tipo 'anulacion' por cada lote.
     * - Se incrementa `productos.stock` atómicamente con SQL (stock + cantidad).
     *
     * @return array<int, array{lote_id: int, codigo_lote: string, producto_id: int, cantidad: int}>
     */
    public function devolverDePedido(Pedido $pedido, string $motivo, ?int $usuarioId): array
    {
        if (! DB::transactionLevel()) {
            throw new RuntimeException('devolverDePedido() debe ejecutarse dentro de una transacción activa.');
        }

        $pedido->loadMissing(['pedidoDetalles.lotesAsignados.lote']);

        // Bloquear productos y lotes involucrados en orden ascendente
        $productoIds = $pedido->pedidoDetalles
            ->pluck('producto_id')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->bloquearParaVenta($productoIds);

        $restituidos = [];

        foreach ($pedido->pedidoDetalles as $detalle) {
            foreach ($detalle->lotesAsignados as $detalleLote) {
                $cantidad = (int) $detalleLote->cantidad_unidades;
                if ($cantidad <= 0) {
                    continue;
                }

                $lote = Lote::whereKey($detalleLote->lote_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $producto = Producto::whereKey($lote->producto_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $stockAnterior = (int) $producto->stock;

                $lote->cantidad_actual += $cantidad;

                // Reactiva el lote si estaba agotado; si estaba vencido o retirado, conserva ese estado
                if ($lote->estado === 'agotado') {
                    $lote->estado = 'activo';
                }
                $lote->save();

                // Registrar movimiento de stock con tipo 'anulacion' (admitido por la migración)
                MovimientoStock::create([
                    'producto_id'     => $producto->id,
                    'lote_id'         => $lote->id,
                    'tipo'            => 'anulacion',
                    'cantidad'        => $cantidad,
                    'stock_anterior'  => $stockAnterior,
                    'stock_posterior' => $stockAnterior + $cantidad,
                    'referencia_tipo' => 'pedido',
                    'referencia_id'   => $pedido->id,
                    'usuario_id'      => $usuarioId,
                    'motivo'          => "Devolución por anulación de pedido #{$pedido->id} ({$motivo})",
                ]);

                // Ajuste atómico de productos.stock en SQL
                Producto::whereKey($producto->id)->update([
                    'stock' => DB::raw("stock + {$cantidad}"),
                ]);

                $producto->refresh();

                $restituidos[] = [
                    'lote_id'     => $lote->id,
                    'codigo_lote' => $lote->codigo_lote,
                    'producto_id' => $lote->producto_id,
                    'cantidad'    => $cantidad,
                ];
            }
        }

        return $restituidos;
    }
}
