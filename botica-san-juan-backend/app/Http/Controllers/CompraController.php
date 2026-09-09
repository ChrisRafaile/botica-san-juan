<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use Illuminate\Http\Request;

class CompraController extends Controller
{
    public function index(Request $request)
    {
        $query = Compra::query()
            ->with('proveedor:id,nombre,ruc')
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id');

        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', (int) $request->input('proveedor_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', (string) $request->input('estado'));
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($search) {
                $inner->where('numero_compra', 'like', "%{$search}%")
                    ->orWhere('observaciones', 'like', "%{$search}%")
                    ->orWhereHas('proveedor', function ($p) use ($search) {
                        $p->where('nombre', 'like', "%{$search}%")
                            ->orWhere('ruc', 'like', "%{$search}%");
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'proveedor_id' => 'required|integer|exists:proveedores,id',
            'numero_compra' => 'required|string|max:32|unique:compras,numero_compra',
            'fecha_compra' => 'required|date',
            'estado' => 'nullable|in:borrador,emitida,recibida,anulada',
            'total' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        $compra = Compra::create([
            'proveedor_id' => $validated['proveedor_id'],
            'numero_compra' => trim($validated['numero_compra']),
            'fecha_compra' => $validated['fecha_compra'],
            'estado' => $validated['estado'] ?? 'borrador',
            'total' => $validated['total'],
            'observaciones' => $validated['observaciones'] ?? null,
        ]);

        return response()->json($compra->load('proveedor:id,nombre,ruc'), 201);
    }

    public function show(string $id)
    {
        return Compra::with('proveedor:id,nombre,ruc')->findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $compra = Compra::findOrFail($id);

        $validated = $request->validate([
            'proveedor_id' => 'sometimes|required|integer|exists:proveedores,id',
            'numero_compra' => 'sometimes|required|string|max:32|unique:compras,numero_compra,' . $compra->id,
            'fecha_compra' => 'sometimes|required|date',
            'estado' => 'nullable|in:borrador,emitida,recibida,anulada',
            'total' => 'sometimes|required|numeric|min:0',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        foreach (['proveedor_id', 'numero_compra', 'fecha_compra', 'estado', 'total', 'observaciones'] as $field) {
            if (array_key_exists($field, $validated)) {
                $compra->{$field} = $validated[$field];
            }
        }

        $compra->save();

        return response()->json($compra->load('proveedor:id,nombre,ruc'));
    }

    public function destroy(string $id)
    {
        $compra = Compra::findOrFail($id);
        $compra->delete();

        return response()->json(['message' => 'Compra eliminada correctamente']);
    }
}
