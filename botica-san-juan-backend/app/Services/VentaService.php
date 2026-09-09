<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Caso de uso de confirmacion de venta.
 *
 * Resuelve el requerimiento RF-07 (descuento automatico de stock) y el RNF-05
 * (integridad transaccional), que hasta ahora no estaban implementados en
 * ningun controlador: confirmar un pedido no descontaba existencias.
 *
 * Tres garantias que este servicio debe sostener:
 *
 * 1. El importe lo determina el servidor a partir del precio vigente en la
 *    tabla de productos. El consumidor solo declara que producto y que
 *    cantidad quiere; nunca el precio ni el total.
 * 2. La lectura del stock y su descuento ocurren dentro de una unica
 *    transaccion, con bloqueo pesimista sobre las filas de producto, de modo
 *    que dos ventas simultaneas del ultimo envase no puedan ambas confirmarse.
 * 3. Si cualquier linea falla, no se persiste ninguna: la venta es atomica.
 */
class VentaService
{
    /**
     * @param  array<int, array{producto_id:int, cantidad:int}>  $items
     */
    public function confirmar(int $usuarioId, array $items, ?string $direccion = null): Pedido
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'La venta debe contener al menos una linea.',
            ]);
        }

        // Se consolidan las lineas repetidas del mismo producto para que el
        // bloqueo y la validacion de stock se hagan una sola vez por producto.
        $cantidades = [];
        foreach ($items as $item) {
            $id = (int) $item['producto_id'];
            $cantidades[$id] = ($cantidades[$id] ?? 0) + (int) $item['cantidad'];
        }

        return DB::transaction(function () use ($usuarioId, $cantidades, $direccion) {
            // Orden estable de bloqueo: evita el interbloqueo entre dos ventas
            // que compartan productos pero los declaren en distinto orden.
            $ids = array_keys($cantidades);
            sort($ids);

            $productos = Producto::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $faltantes = array_diff($ids, $productos->keys()->all());
            if ($faltantes !== []) {
                throw ValidationException::withMessages([
                    'items' => 'No existen los productos: ' . implode(', ', $faltantes),
                ]);
            }

            $insuficientes = [];
            $total = 0.0;

            foreach ($ids as $id) {
                $producto = $productos[$id];
                $cantidad = $cantidades[$id];

                if ((int) $producto->stock < $cantidad) {
                    $insuficientes[] = sprintf(
                        '%s (disponible %d, solicitado %d)',
                        $producto->nombre,
                        (int) $producto->stock,
                        $cantidad
                    );
                    continue;
                }

                $total += round((float) $producto->precio * $cantidad, 2);
            }

            if ($insuficientes !== []) {
                throw ValidationException::withMessages([
                    'items' => 'Stock insuficiente: ' . implode('; ', $insuficientes),
                ]);
            }

            $pedido = Pedido::create([
                'usuario_id' => $usuarioId,
                'fecha_pedido' => now(),
                'total' => round($total, 2),
                'estado' => 'confirmado',
            ]);

            if ($direccion !== null && $direccion !== '') {
                // 'address' no es asignable en masa; se asigna explicitamente.
                $pedido->address = $direccion;
                $pedido->save();
            }

            foreach ($ids as $id) {
                $producto = $productos[$id];
                $cantidad = $cantidades[$id];
                $precioUnitario = round((float) $producto->precio, 2);

                PedidoDetalle::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio' => $precioUnitario,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => round($precioUnitario * $cantidad, 2),
                ]);

                // decrement() emite un UPDATE relativo sobre la fila ya
                // bloqueada; no reutiliza un valor leido previamente.
                $producto->decrement('stock', $cantidad);
            }

            return $pedido->fresh(['pedidoDetalles.producto', 'usuario']);
        });
    }
}
