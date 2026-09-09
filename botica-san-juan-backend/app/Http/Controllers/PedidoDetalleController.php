<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\PedidoDetalle;
use Illuminate\Http\Request;

class PedidoDetalleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return PedidoDetalle::with(['pedido', 'producto'])->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pedido_id' => 'required|exists:pedidos,id',
            'producto_id' => 'required|exists:productos,id',
            'unidad_venta' => 'nullable|in:unidad,blister,caja',
            'cantidad' => 'required|integer|min:1',
            'precio_unitario' => 'nullable|numeric|min:0',
            'subtotal' => 'nullable|numeric|min:0',
        ]);

        $producto = Producto::findOrFail((int) $validated['producto_id']);
        [$factorUnidades, $precioUnitario] = $this->resolveUnitAndPrice($producto, (string) ($validated['unidad_venta'] ?? 'unidad'), $validated['precio_unitario'] ?? null);

        $cantidad = (int) $validated['cantidad'];
        $cantidadUnidades = $cantidad * $factorUnidades;
        $subtotal = $validated['subtotal'] !== null
            ? (float) $validated['subtotal']
            : round($cantidad * $precioUnitario, 2);

        $pedidoDetalle = PedidoDetalle::create([
            'pedido_id' => $validated['pedido_id'],
            'producto_id' => $validated['producto_id'],
            'unidad_venta' => $validated['unidad_venta'] ?? 'unidad',
            'factor_unidades' => $factorUnidades,
            'cantidad' => $cantidad,
            'cantidad_unidades' => $cantidadUnidades,
            'precio_unitario' => $precioUnitario,
            'precio' => $precioUnitario,
            'subtotal' => $subtotal,
        ]);

        return response()->json($pedidoDetalle, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $pedidoDetalle = PedidoDetalle::with(['pedido', 'producto'])->findOrFail($id);
        return response()->json($pedidoDetalle);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $pedidoDetalle = PedidoDetalle::findOrFail($id);

        $validated = $request->validate([
            'pedido_id' => 'sometimes|required|exists:pedidos,id',
            'producto_id' => 'sometimes|required|exists:productos,id',
            'unidad_venta' => 'nullable|in:unidad,blister,caja',
            'cantidad' => 'sometimes|required|integer|min:1',
            'precio_unitario' => 'nullable|numeric|min:0',
            'subtotal' => 'nullable|numeric|min:0',
        ]);

        $producto = isset($validated['producto_id'])
            ? Producto::findOrFail((int) $validated['producto_id'])
            : $pedidoDetalle->producto;

        $unidadVenta = (string) ($validated['unidad_venta'] ?? $pedidoDetalle->unidad_venta ?? 'unidad');
        [$factorUnidades, $precioUnitario] = $this->resolveUnitAndPrice($producto, $unidadVenta, $validated['precio_unitario'] ?? $pedidoDetalle->precio_unitario ?? null);

        $cantidad = (int) ($validated['cantidad'] ?? $pedidoDetalle->cantidad);
        $subtotal = array_key_exists('subtotal', $validated)
            ? (float) $validated['subtotal']
            : round($cantidad * $precioUnitario, 2);

        $pedidoDetalle->fill($validated);
        $pedidoDetalle->unidad_venta = $unidadVenta;
        $pedidoDetalle->factor_unidades = $factorUnidades;
        $pedidoDetalle->cantidad = $cantidad;
        $pedidoDetalle->cantidad_unidades = $cantidad * $factorUnidades;
        $pedidoDetalle->precio_unitario = $precioUnitario;
        $pedidoDetalle->precio = $precioUnitario;
        $pedidoDetalle->subtotal = $subtotal;
        $pedidoDetalle->save();

        return response()->json($pedidoDetalle);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $pedidoDetalle = PedidoDetalle::findOrFail($id);
        $pedidoDetalle->delete();

        return response()->json(['message' => 'PedidoDetalle deleted successfully']);
    }

    private function resolveUnitAndPrice(Producto $producto, string $unidadVenta, mixed $precioOverride): array
    {
        $unidad = in_array($unidadVenta, ['unidad', 'blister', 'caja'], true) ? $unidadVenta : 'unidad';

        $unidadesPorBlister = max(1, (int) ($producto->unidades_por_blister ?? 1));
        $blistersPorCaja = max(1, (int) ($producto->blisters_por_caja ?? 1));

        $factorUnidades = match ($unidad) {
            'blister' => $unidadesPorBlister,
            'caja' => $unidadesPorBlister * $blistersPorCaja,
            default => 1,
        };

        if ($precioOverride !== null && $precioOverride !== '') {
            return [$factorUnidades, (float) $precioOverride];
        }

        $precioUnitario = match ($unidad) {
            'blister' => (float) ($producto->precio_blister ?? $producto->precio),
            'caja' => (float) ($producto->precio_caja ?? $producto->precio),
            default => (float) $producto->precio,
        };

        return [$factorUnidades, $precioUnitario];
    }
}
