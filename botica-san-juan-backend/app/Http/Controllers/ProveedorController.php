<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $query = Proveedor::query()->orderBy('nombre');

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($search) {
                $inner->where('nombre', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%")
                    ->orWhere('contacto', 'like', "%{$search}%");
            });
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->boolean('with_counts')) {
            $query->withCount('compras');
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
            'nombre' => 'required|string|max:160',
            'ruc' => 'nullable|string|size:11|unique:proveedores,ruc',
            'contacto' => 'nullable|string|max:140',
            'telefono' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:160',
            'direccion' => 'nullable|string|max:255',
            'dias_credito' => 'nullable|integer|min:0|max:90',
            'activo' => 'nullable|boolean',
        ]);

        $proveedor = Proveedor::create([
            'nombre' => trim($validated['nombre']),
            'ruc' => $validated['ruc'] ?? null,
            'contacto' => $validated['contacto'] ?? null,
            'telefono' => $validated['telefono'] ?? null,
            'email' => $validated['email'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'dias_credito' => $validated['dias_credito'] ?? 0,
            'activo' => $validated['activo'] ?? true,
        ]);

        return response()->json($proveedor, 201);
    }

    public function show(string $id)
    {
        return Proveedor::withCount('compras')->findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $proveedor = Proveedor::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:160',
            'ruc' => 'nullable|string|size:11|unique:proveedores,ruc,' . $proveedor->id,
            'contacto' => 'nullable|string|max:140',
            'telefono' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:160',
            'direccion' => 'nullable|string|max:255',
            'dias_credito' => 'nullable|integer|min:0|max:90',
            'activo' => 'nullable|boolean',
        ]);

        if (array_key_exists('nombre', $validated)) {
            $proveedor->nombre = trim($validated['nombre']);
        }

        foreach (['ruc', 'contacto', 'telefono', 'email', 'direccion', 'dias_credito', 'activo'] as $field) {
            if (array_key_exists($field, $validated)) {
                $proveedor->{$field} = $validated[$field];
            }
        }

        $proveedor->save();

        return response()->json($proveedor);
    }

    public function destroy(string $id)
    {
        $proveedor = Proveedor::findOrFail($id);
        $proveedor->delete();

        return response()->json(['message' => 'Proveedor eliminado correctamente']);
    }
}
