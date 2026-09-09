<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoriaStoreRequest;
use App\Http\Requests\CategoriaUpdateRequest;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoriaController extends Controller
{
    public function index(Request $request)
    {
        $query = Categoria::query()->orderBy('nombre');

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

        if ($request->boolean('with_subcategorias')) {
            $query->with(['subcategorias' => function ($q) {
                $q->orderBy('nombre');
            }]);
        }

        $shouldPaginate = $request->has('page') || $request->has('per_page') || $request->boolean('paginate');
        if ($shouldPaginate) {
            $perPage = (int) $request->input('per_page', 10);
            return $query->paginate(max(1, min($perPage, 100)));
        }

        return $query->get();
    }

    public function store(CategoriaStoreRequest $request)
    {
        $validated = $request->validated();

        $categoria = Categoria::create([
            'nombre' => trim($validated['nombre']),
            'slug' => Str::slug($validated['nombre']),
            'descripcion' => $validated['descripcion'] ?? null,
            'color' => $validated['color'] ?? 'blue',
            'activa' => $validated['activa'] ?? true,
        ]);

        return response()->json($categoria, 201);
    }

    public function show(string $id)
    {
        return Categoria::with(['subcategorias' => function ($q) {
            $q->orderBy('nombre');
        }])->withCount('productos')->findOrFail($id);
    }

    public function update(CategoriaUpdateRequest $request, string $id)
    {
        $categoria = Categoria::findOrFail($id);

        $validated = $request->validated();

        if (array_key_exists('nombre', $validated)) {
            $categoria->nombre = trim($validated['nombre']);
            $categoria->slug = Str::slug($validated['nombre']);
        }

        if (array_key_exists('descripcion', $validated)) {
            $categoria->descripcion = $validated['descripcion'];
        }

        if (array_key_exists('color', $validated)) {
            $categoria->color = $validated['color'];
        }

        if (array_key_exists('activa', $validated)) {
            $categoria->activa = (bool) $validated['activa'];
        }

        $categoria->save();

        return response()->json($categoria);
    }

    public function destroy(string $id)
    {
        $categoria = Categoria::findOrFail($id);
        $categoria->delete();

        return response()->json(['message' => 'Categoria eliminada correctamente']);
    }
}
