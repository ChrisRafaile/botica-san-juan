<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubcategoriaStoreRequest;
use App\Http\Requests\SubcategoriaUpdateRequest;
use App\Models\Subcategoria;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubcategoriaController extends Controller
{
    public function index(Request $request)
    {
        $query = Subcategoria::query()->with('categoria')->orderBy('nombre');

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', (int) $request->input('categoria_id'));
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($search) {
                $inner->where('nombre', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        if ($request->filled('activa')) {
            $query->where('activa', $request->boolean('activa'));
        }

        if ($request->boolean('with_counts')) {
            $query->withCount('productos');
        }

        $shouldPaginate = $request->has('page') || $request->has('per_page') || $request->boolean('paginate');
        if ($shouldPaginate) {
            $perPage = (int) $request->input('per_page', 10);
            return $query->paginate(max(1, min($perPage, 100)));
        }

        return $query->get();
    }

    public function store(SubcategoriaStoreRequest $request)
    {
        $validated = $request->validated();

        $subcategoria = Subcategoria::create([
            'categoria_id' => $validated['categoria_id'],
            'nombre' => trim($validated['nombre']),
            'slug' => Str::slug($validated['nombre']),
            'descripcion' => $validated['descripcion'] ?? null,
            'activa' => $validated['activa'] ?? true,
        ]);

        return response()->json($subcategoria->load('categoria'), 201);
    }

    public function show(string $id)
    {
        return Subcategoria::with('categoria')->withCount('productos')->findOrFail($id);
    }

    public function update(SubcategoriaUpdateRequest $request, string $id)
    {
        $subcategoria = Subcategoria::findOrFail($id);

        $validated = $request->validated();

        if (array_key_exists('categoria_id', $validated)) {
            $subcategoria->categoria_id = (int) $validated['categoria_id'];
        }

        if (array_key_exists('nombre', $validated)) {
            $subcategoria->nombre = trim($validated['nombre']);
            $subcategoria->slug = Str::slug($validated['nombre']);
        }

        if (array_key_exists('descripcion', $validated)) {
            $subcategoria->descripcion = $validated['descripcion'];
        }

        if (array_key_exists('activa', $validated)) {
            $subcategoria->activa = (bool) $validated['activa'];
        }

        $subcategoria->save();

        return response()->json($subcategoria->load('categoria'));
    }

    public function destroy(string $id)
    {
        $subcategoria = Subcategoria::findOrFail($id);
        $subcategoria->delete();

        return response()->json(['message' => 'Subcategoria eliminada correctamente']);
    }
}
