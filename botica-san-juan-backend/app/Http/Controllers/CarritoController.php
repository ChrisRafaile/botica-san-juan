<?php

namespace App\Http\Controllers;

use App\Models\Carrito;
use Illuminate\Http\Request;

class CarritoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Carrito::with(['usuario', 'producto'])->get();
    }

    /**
     * Get cart items for a specific user.
     */
    public function getByUser($usuarioId)
    {
        return Carrito::with('producto')->where('usuario_id', $usuarioId)->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|exists:usuarios,id',
            'producto_id' => 'required|exists:productos,id',
            'cantidad' => 'required|integer|min:1',
        ]);

        // Check if item already exists in cart
        $existing = Carrito::where('usuario_id', $request->usuario_id)
                          ->where('producto_id', $request->producto_id)
                          ->first();

        if ($existing) {
            $existing->update(['cantidad' => $existing->cantidad + $request->cantidad]);
            return response()->json($existing);
        }

        $carrito = Carrito::create($request->all());

        return response()->json($carrito, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $carrito = Carrito::with(['usuario', 'producto'])->findOrFail($id);
        return response()->json($carrito);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $carrito = Carrito::findOrFail($id);

        $request->validate([
            'usuario_id' => 'sometimes|required|exists:usuarios,id',
            'producto_id' => 'sometimes|required|exists:productos,id',
            'cantidad' => 'sometimes|required|integer|min:1',
        ]);

        $carrito->update($request->all());

        return response()->json($carrito);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $carrito = Carrito::findOrFail($id);
        $carrito->delete();

        return response()->json(['message' => 'Carrito item deleted successfully']);
    }
}
