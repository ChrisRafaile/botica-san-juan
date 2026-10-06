<?php

namespace App\Http\Controllers;

use App\Models\DigemidCatalogo;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    /**
     * Cuántos productos hay en cada estado de stock, sobre TODO el catálogo.
     *
     * GET /api/productos/resumen
     *
     * Existe porque la pantalla lo calculaba sobre la página que tenía cargada:
     * con 3361 productos y diez por página, mostraba "2 en stock". Un resumen
     * del catálogo se cuenta en la base, no sobre lo que cabe en pantalla.
     */
    /**
     * Valores disponibles para los filtros del catálogo, con su recuento.
     *
     * GET /api/productos/facetas   (público)
     *
     * POR QUÉ UN ENDPOINT Y NO DEDUCIRLOS DE LA PÁGINA
     *
     * La pantalla pública construía sus desplegables con los tipos y
     * laboratorios que venían en la página cargada. Con 48 productos de 3 361
     * eso significa que el desplegable ofrecía un puñado de valores y el resto
     * del catálogo era inalcanzable: filtrar por un laboratorio que no hubiera
     * salido en esa página era imposible, y nada en la interfaz lo decía.
     *
     * Es el mismo error que ya apareció en el tablero y en inventario: contar
     * o listar sobre lo cargado en vez de preguntárselo a quien tiene todos los
     * datos. Aquí son tres agregaciones en SQL sobre la tabla entera.
     *
     * El recuento no es decorativo: es lo que permite no ofrecer un filtro que
     * sólo puede devolver una lista vacía.
     */
    public function facetas()
    {
        $tipos = Producto::query()
            ->selectRaw('tipo AS valor, COUNT(*) AS total')
            ->whereNotNull('tipo')->where('tipo', '!=', '')
            ->groupBy('tipo')->orderByDesc('total')
            ->get();

        $laboratorios = Producto::query()
            ->selectRaw('laboratorio AS valor, COUNT(*) AS total')
            ->whereNotNull('laboratorio')->where('laboratorio', '!=', '')
            ->groupBy('laboratorio')->orderBy('laboratorio')
            ->get();

        $categorias = Producto::query()
            ->selectRaw('productos.categoria_id AS valor, categorias.nombre AS etiqueta, COUNT(*) AS total')
            ->join('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->groupBy('productos.categoria_id', 'categorias.nombre')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'tipos'         => $tipos,
            'laboratorios'  => $laboratorios,
            'categorias'    => $categorias,
            'total'         => Producto::count(),
            'sin_stock'     => Producto::where('stock', '<=', 0)->count(),
        ]);
    }

    public function resumen(\App\Services\ResumenInventarioService $inventario)
    {
        $resumen = $inventario->porEstadoDeStock();

        return response()->json([
            'data'                 => $resumen,
            'umbrales_sospechosos' => $inventario->umbralesSonSospechosos($resumen),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $query = Producto::query()
            ->with(['categoria:id,nombre', 'subcategoria:id,nombre', 'digemidCatalogo:id,codigo_digemid,nombre_producto,precio_maximo_regulado']);

        /**
         * Orden.
         *
         * Por omision sigue siendo el mas reciente primero, que es lo que
         * quiere el administrador al volver a una pantalla que acaba de
         * editar. Para el catalogo publico eso no significa nada —nadie busca
         * "lo ultimo que toco el almacenero"— y por eso la pantalla publica
         * pide `nombre`.
         *
         * El valor llega del navegador, asi que se compara contra una lista
         * cerrada en vez de interpolarlo: un `orderByRaw` con texto de fuera
         * es una inyeccion esperando a ocurrir.
         */
        match ((string) $request->input('orden', 'recientes')) {
            'nombre' => $query->orderBy('nombre'),
            'precio_asc' => $query->orderBy('precio'),
            'precio_desc' => $query->orderByDesc('precio'),
            default => $query->orderByDesc('updated_at'),
        };

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

        /**
         * Laboratorio.
         *
         * Faltaba, y su ausencia no se notaba desde fuera: la pantalla publica
         * ofrecia un desplegable de laboratorios, el navegador enviaba el
         * parametro y el servidor lo IGNORABA en silencio. El resultado era un
         * filtro que parecia funcionar —la lista cambiaba, porque cambiaba la
         * pagina— pero que no filtraba nada. Hay 83 laboratorios distintos en
         * el catalogo, asi que el criterio es util de verdad.
         */
        if ($request->filled('laboratorio') && $request->input('laboratorio') !== 'all') {
            $query->where('laboratorio', $request->input('laboratorio'));
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

        $pagina = $query->paginate(max(1, min($perPage, 100)));

        /* Stock realmente vendible, separado del contable.
           `productos.stock` incluye lo vencido, porque fisicamente esta en el
           anaquel; pero mostrarlo como disponible lleva a prometer lo que no
           se puede entregar. Se calcula en una sola consulta agrupada para
           los productos de esta pagina, no una por producto. */
        $idsPagina = collect($pagina->items())->pluck('id');

        $disponibles = \App\Models\Lote::query()
            ->selectRaw('producto_id, SUM(cantidad_actual) AS total')
            ->whereIn('producto_id', $idsPagina)
            ->disponible()
            ->groupBy('producto_id')
            ->pluck('total', 'producto_id');

        $pagina->getCollection()->transform(function ($producto) use ($disponibles) {
            $producto->stock_disponible = (int) ($disponibles[$producto->id] ?? 0);
            $producto->stock_no_vendible = max(0, (int) $producto->stock - $producto->stock_disponible);

            return $producto;
        });

        return $pagina;
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
