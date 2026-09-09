<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Services\VentaService;
use Illuminate\Http\Request;

class PedidoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $request = request();
        $query = Pedido::with(['usuario', 'pedidoDetalles.producto'])->orderByDesc('fecha_pedido');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('estado', $request->input('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('fecha_pedido', $request->input('date'));
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($search) {
                $inner->where('id', 'like', "%{$search}%")
                    ->orWhereHas('usuario', function ($usuarioQuery) use ($search) {
                        $usuarioQuery->where('nombre', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('pedidoDetalles.producto', function ($productoQuery) use ($search) {
                        $productoQuery->where('nombre', 'like', "%{$search}%");
                    });
            });
        }

        $shouldPaginate = $request->has('page') || $request->has('per_page') || $request->boolean('paginate');
        if ($shouldPaginate) {
            $perPage = (int) $request->input('per_page', 10);
            return $query->paginate(max(1, min($perPage, 100)));
        }

        return $query->get();
    }

    /**
     * Get orders for a specific user.
     */
    public function getByUser($usuarioId)
    {
        return Pedido::with(['pedidoDetalles.producto'])->where('usuario_id', $usuarioId)->get();
    }

    /**
     * Confirma una venta: crea el pedido con su detalle y descuenta el stock
     * dentro de una unica transaccion.
     *
     * El consumidor declara unicamente que producto y que cantidad quiere. El
     * precio y el total los determina el servidor a partir del catalogo
     * vigente, de modo que un cliente manipulado no puede fijar el importe.
     */
    public function confirmar(Request $request, VentaService $ventas)
    {
        $validado = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ]);

        $pedido = $ventas->confirmar(
            (int) $request->user()->id,
            $validado['items'],
            $validado['direccion'] ?? null
        );

        return response()->json([
            'message' => 'Venta confirmada y stock actualizado.',
            'pedido' => $pedido,
        ], 201);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|exists:usuarios,id',
            'fecha_pedido' => 'required|date',
            'total' => 'required|numeric|min:0',
            'estado' => 'required|string|max:50',
        ]);

        $pedido = Pedido::create($request->all());

        return response()->json($pedido, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $pedido = Pedido::with(['usuario', 'pedidoDetalles.producto'])->findOrFail($id);
        return response()->json($pedido);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $pedido = Pedido::findOrFail($id);

        $request->validate([
            'usuario_id' => 'sometimes|required|exists:usuarios,id',
            'fecha_pedido' => 'sometimes|required|date',
            'total' => 'sometimes|required|numeric|min:0',
            'estado' => 'sometimes|required|string|max:50',
        ]);

        $pedido->update($request->all());

        return response()->json($pedido);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $pedido = Pedido::findOrFail($id);
        $pedido->delete();

        return response()->json(['message' => 'Pedido deleted successfully']);
    }
}
