<?php

namespace App\Http\Controllers;

use App\Models\DigemidCatalogo;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $query = Producto::query()
            ->with(['categoria:id,nombre', 'subcategoria:id,nombre', 'digemidCatalogo:id,codigo_digemid,nombre_producto,precio_maximo_regulado'])
            ->orderByDesc('updated_at');

        $search = trim((string) $request->input('q', $request->input('search', '')));
        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo_barras', 'like', "%{$search}%")
                    ->orWhere('codigo_digemid', 'like', "%{$search}%")
                    ->orWhere('principio_activo', 'like', "%{$search}%")
                    ->orWhere('tipo', 'like', "%{$search}%")
                    ->orWhere('laboratorio', 'like', "%{$search}%")
                    ->orWhere('laboratorio_fabricante', 'like', "%{$search}%")
                    ->orWhere('presentacion', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tipo') && $request->input('tipo') !== 'all') {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', (int) $request->input('categoria_id'));
        }

        if ($request->filled('subcategoria_id')) {
            $query->where('subcategoria_id', (int) $request->input('subcategoria_id'));
        }

        if ($request->filled('requiere_receta')) {
            $query->where('requiere_receta', $request->boolean('requiere_receta'));
        }

        if ($request->filled('digemid_status')) {
            $digemidStatus = (string) $request->input('digemid_status');
            if ($digemidStatus === 'with_code') {
                $query->whereNotNull('codigo_digemid')->where('codigo_digemid', '!=', '');
            }
            if ($digemidStatus === 'without_code') {
                $query->where(function ($inner) {
                    $inner->whereNull('codigo_digemid')->orWhere('codigo_digemid', '');
                });
            }
        }

        if ($request->filled('stock_status') && $request->input('stock_status') !== 'all') {
            $stockStatus = (string) $request->input('stock_status');

            if ($stockStatus === 'critical') {
                $query->whereColumn('stock', '<=', 'stock_minimo');
            } elseif ($stockStatus === 'low' || $stockStatus === 'low_stock') {
                $query->whereColumn('stock', '>', 'stock_minimo')
                    ->whereColumn('stock', '<=', 'stock_reposicion');
            } elseif ($stockStatus === 'in_stock') {
                $query->where('stock', '>', 0);
            } elseif ($stockStatus === 'out_of_stock') {
                $query->where('stock', '=', 0);
            } elseif ($stockStatus === 'normal') {
                $query->whereColumn('stock', '>', 'stock_reposicion');
            }
        }

        return $query->paginate(max(1, min($perPage, 100)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'concentracion' => 'required|string|max:255',
            'adicional' => 'nullable|string|max:255',
            'laboratorio' => 'required|string|max:255',
            'presentacion' => 'required|string|max:255',
            'unidad_base' => 'nullable|in:unidad,blister,caja',
            'venta_fraccionada' => 'nullable|boolean',
            'unidades_por_blister' => 'nullable|integer|min:1',
            'blisters_por_caja' => 'nullable|integer|min:1',
            'tipo' => 'required|string|max:255',
            'categoria_id' => 'nullable|integer|exists:categorias,id',
            'subcategoria_id' => 'nullable|integer|exists:subcategorias,id',
            'stock' => 'required|integer|min:0',
            'stock_minimo' => 'nullable|integer|min:0',
            'stock_reposicion' => 'nullable|integer|min:0',
            'precio' => 'required|numeric|min:0',
            'precio_blister' => 'nullable|numeric|min:0',
            'precio_caja' => 'nullable|numeric|min:0',
            'codigo_barras' => 'nullable|string|max:64|unique:productos,codigo_barras',
            'codigo_digemid' => 'nullable|string|max:64',
            'principio_activo' => 'nullable|string|max:255',
            'requiere_receta' => 'nullable|boolean',
            'laboratorio_fabricante' => 'nullable|string|max:255',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->all();
        $data['unidad_base'] = $data['unidad_base'] ?? 'unidad';
        $data['venta_fraccionada'] = (bool) ($data['venta_fraccionada'] ?? false);

        if (!$data['venta_fraccionada']) {
            $data['unidades_por_blister'] = null;
            $data['blisters_por_caja'] = null;
            $data['precio_blister'] = null;
            $data['precio_caja'] = null;
        }

        $this->validateDigemidCompliance($data);

        // Handle image upload
        if ($request->hasFile('imagen')) {
            $image = $request->file('imagen');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('images/productos'), $imageName);
            $data['imagen'] = url('/images/productos/' . $imageName);
        }

        $producto = Producto::create($data);

        return response()->json($producto->load(['categoria:id,nombre', 'subcategoria:id,nombre', 'digemidCatalogo:id,codigo_digemid,nombre_producto,precio_maximo_regulado']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $producto = Producto::with(['categoria:id,nombre', 'subcategoria:id,nombre', 'digemidCatalogo:id,codigo_digemid,nombre_producto,precio_maximo_regulado'])->findOrFail($id);
        return response()->json($producto);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $producto = Producto::findOrFail($id);

        $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'concentracion' => 'sometimes|required|string|max:255',
            'adicional' => 'nullable|string|max:255',
            'laboratorio' => 'sometimes|required|string|max:255',
            'presentacion' => 'sometimes|required|string|max:255',
            'unidad_base' => 'nullable|in:unidad,blister,caja',
            'venta_fraccionada' => 'nullable|boolean',
            'unidades_por_blister' => 'nullable|integer|min:1',
            'blisters_por_caja' => 'nullable|integer|min:1',
            'tipo' => 'sometimes|required|string|max:255',
            'categoria_id' => 'nullable|integer|exists:categorias,id',
            'subcategoria_id' => 'nullable|integer|exists:subcategorias,id',
            'stock' => 'sometimes|required|integer|min:0',
            'stock_minimo' => 'nullable|integer|min:0',
            'stock_reposicion' => 'nullable|integer|min:0',
            'precio' => 'sometimes|required|numeric|min:0',
            'precio_blister' => 'nullable|numeric|min:0',
            'precio_caja' => 'nullable|numeric|min:0',
            'codigo_barras' => 'nullable|string|max:64|unique:productos,codigo_barras,' . $id,
            'codigo_digemid' => 'nullable|string|max:64',
            'principio_activo' => 'nullable|string|max:255',
            'requiere_receta' => 'nullable|boolean',
            'laboratorio_fabricante' => 'nullable|string|max:255',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->all();
        if (array_key_exists('venta_fraccionada', $data) && !$data['venta_fraccionada']) {
            $data['unidades_por_blister'] = null;
            $data['blisters_por_caja'] = null;
            $data['precio_blister'] = null;
            $data['precio_caja'] = null;
        }

        $this->validateDigemidCompliance($data, $producto);

        // Handle image upload
        if ($request->hasFile('imagen')) {
            // Delete old image if exists
            if ($producto->imagen && file_exists(public_path($producto->imagen))) {
                unlink(public_path($producto->imagen));
            }

            $image = $request->file('imagen');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('images/productos'), $imageName);
            $data['imagen'] = url('/images/productos/' . $imageName);
        }

        $producto->update($data);

        return response()->json($producto->load(['categoria:id,nombre', 'subcategoria:id,nombre', 'digemidCatalogo:id,codigo_digemid,nombre_producto,precio_maximo_regulado']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $producto = Producto::findOrFail($id);
        $producto->delete();

        return response()->json(['message' => 'Producto deleted successfully']);
    }

    /**
     * Store multiple products in bulk.
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'productos' => 'required|array',
            'productos.*.nombre' => 'required|string|max:255',
            'productos.*.concentracion' => 'required|string|max:255',
            'productos.*.adicional' => 'nullable|string|max:255',
            'productos.*.laboratorio' => 'required|string|max:255',
            'productos.*.presentacion' => 'required|string|max:255',
            'productos.*.tipo' => 'required|string|max:255',
            'productos.*.codigo_digemid' => 'nullable|string|max:64',
            'productos.*.principio_activo' => 'nullable|string|max:255',
            'productos.*.requiere_receta' => 'nullable|boolean',
            'productos.*.laboratorio_fabricante' => 'nullable|string|max:255',
            'productos.*.stock' => 'required|integer|min:0',
            'productos.*.precio' => 'required|numeric|min:0',
            'productos.*.imagen' => 'nullable|string|max:255',
        ]);

        $productos = [];
        $errors = [];

        foreach ($request->productos as $index => $productoData) {
            try {
                $this->validateDigemidCompliance($productoData);
                $producto = Producto::create($productoData);
                $productos[] = $producto;
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'data' => $productoData,
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'message' => 'Bulk upload completed',
            'created_count' => count($productos),
            'error_count' => count($errors),
            'productos' => $productos,
            'errors' => $errors
        ], count($errors) > 0 ? 207 : 201); // 207 Multi-Status for partial success
    }

    private function validateDigemidCompliance(array &$data, ?Producto $existingProduct = null): void
    {
        $codigoDigemid = trim((string) ($data['codigo_digemid'] ?? $existingProduct?->codigo_digemid ?? ''));
        if ($codigoDigemid === '') {
            return;
        }

        $catalogo = DigemidCatalogo::where('codigo_digemid', $codigoDigemid)->where('activo', true)->first();
        if (!$catalogo) {
            throw ValidationException::withMessages([
                'codigo_digemid' => ['El codigo DIGEMID no existe o no esta activo en el catalogo local.'],
            ]);
        }

        $precio = array_key_exists('precio', $data) ? (float) $data['precio'] : (float) ($existingProduct?->precio ?? 0);
        $precioMaximo = $catalogo->precio_maximo_regulado !== null ? (float) $catalogo->precio_maximo_regulado : null;
        if ($precioMaximo !== null && $precio > $precioMaximo) {
            throw ValidationException::withMessages([
                'precio' => ["El precio excede el maximo regulado DIGEMID (S/ {$catalogo->precio_maximo_regulado})."],
            ]);
        }

        $data['codigo_digemid'] = $codigoDigemid;
        if (empty($data['principio_activo']) && !empty($catalogo->principio_activo)) {
            $data['principio_activo'] = $catalogo->principio_activo;
        }
        if (!array_key_exists('requiere_receta', $data)) {
            $data['requiere_receta'] = $catalogo->requiere_receta;
        }
        if (empty($data['laboratorio_fabricante']) && !empty($catalogo->laboratorio_fabricante)) {
            $data['laboratorio_fabricante'] = $catalogo->laboratorio_fabricante;
        }
    }
}
