<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo público · fachada de solo lectura para consumo externo.
 * ---------------------------------------------------------------------------
 * POR QUÉ UNA FACHADA Y NO EXPONER `/api/productos` SIN MÁS
 *
 * `/api/productos` ya es público, pero devuelve la fila entera: stock, stock
 * mínimo, stock de reposición, unidades por blíster, el identificador interno
 * de la categoría. Son datos que el panel necesita y que un tercero no, y
 * sobre todo son datos que cambian cuando cambia el modelo.
 *
 * Esa es la diferencia que importa: `/api/productos` es el detalle interno y
 * puede cambiar de forma cuando el sistema evolucione. `/api/catalogo` es un
 * CONTRATO: una forma corta y estable que otro proyecto puede consumir sin
 * que una migración nuestra le rompa el suyo. Las pruebas de
 * CatalogoPublicoTest fijan esa forma campo a campo, para que cambiarla
 * tenga que ser una decisión y no un descuido.
 *
 * QUÉ NO SALE DE AQUÍ, Y POR QUÉ
 *
 * No sale el STOCK. En una botica, cuánto queda de un medicamento es
 * información comercial —y, agregada en el tiempo, dice qué se está vendiendo
 * en el barrio—. Lo que sí sale es si hay existencias o no (`disponible`),
 * que es lo único que un catálogo necesita responder.
 *
 * Tampoco salen los precios de compra ni los márgenes, que no están en esta
 * tabla, ni ningún dato de persona: aquí no hay ninguno.
 *
 * SIN AUTENTICACIÓN, A PROPÓSITO
 *
 * Es el requisito del curso de SOA: que otro proyecto pueda consumirlo igual
 * que se consume PokeAPI. El límite por minuto lo pone `throttle:publico`, y
 * la ausencia de token no abre nada porque aquí no hay nada que abrir: son
 * los mismos datos que cualquiera ve entrando al portal.
 */
class CatalogoPublicoController extends Controller
{
    /** Tope de elementos por página. Sin él, `?per_page=100000` es una descarga de la tabla. */
    public const MAXIMO_POR_PAGINA = 100;

    private const POR_PAGINA = 20;

    /**
     * Listado paginado del catálogo público con búsqueda sin tildes.
     *
     * Fachada de solo lectura para consumo externo sin autenticación.
     *
     * Parámetros de consulta (query):
     * - `buscar` (string, máx 120): Término de búsqueda insensible a mayúsculas y sin tildes (utiliza PostgreSQL unaccent) sobre `nombre`, `principio_activo` y `laboratorio`. Ejemplo: `amoxicilina` o `panadol`.
     * - `categoria` (string, máx 120): Filtro por slug o identificador numérico de la categoría. Ejemplo: `medicamentos`.
     * - `requiere_receta` (boolean): Filtro según requiera o no receta médica obligatoria (`true` o `false`).
     * - `per_page` (integer, máx 100): Cantidad de elementos por página (por omisión 20, máximo 100).
     * - `page` (integer, mín 1): Número de página solicitada.
     */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            /**
             * Término de búsqueda sin tildes (unaccent) sobre nombre, principio activo y laboratorio.
             */
            'buscar' => ['nullable', 'string', 'max:120'],
            /**
             * Filtro por categoría mediante slug o identificador numérico.
             */
            'categoria' => ['nullable', 'string', 'max:120'],
            /**
             * Filtro por productos que requieren receta médica obligatoria.
             */
            'requiere_receta' => ['nullable', 'boolean'],
            /**
             * Cantidad de productos por página (mínimo 1, máximo 100).
             */
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAXIMO_POR_PAGINA],
            /**
             * Número de página para la paginación de resultados.
             */
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $consulta = Producto::query()->with(['categoria:id,nombre,slug']);

        if (! blank($datos['buscar'] ?? null)) {
            $consulta->buscar((string) $datos['buscar'], ['nombre', 'principio_activo', 'laboratorio']);
        }

        if (! blank($datos['categoria'] ?? null)) {
            $categoria = $datos['categoria'];

            /* Se admite el slug y también el id. Un consumidor externo
               descubre el slug leyendo la respuesta; obligarle a conocer
               nuestros identificadores numéricos sería pedirle que conozca
               nuestra base de datos. */
            $consulta->whereHas('categoria', function ($q) use ($categoria) {
                $q->where('slug', $categoria);

                if (ctype_digit($categoria)) {
                    $q->orWhere('id', (int) $categoria);
                }
            });
        }

        if (array_key_exists('requiere_receta', $datos) && $datos['requiere_receta'] !== null) {
            $consulta->where('requiere_receta', $datos['requiere_receta']);
        }

        $pagina = $consulta
            ->orderBy('nombre')
            ->paginate((int) ($datos['per_page'] ?? self::POR_PAGINA))
            ->withQueryString();

        return response()->json([
            'datos' => $pagina->getCollection()->map(fn (Producto $p) => $this->comoContrato($p))->all(),
            'paginacion' => [
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
                'paginas' => $pagina->lastPage(),
            ],
        ]);
    }

    /**
     * Un producto por su identificador.
     *
     * Devuelve 404 cuando no existe, nunca 500: la ruta restringe el
     * parámetro a dígitos, así que `/api/catalogo/destacados` no llega aquí
     * intentando convertir «destacados» en un entero.
     */
    public function show(Producto $producto): JsonResponse
    {
        $producto->load(['categoria:id,nombre,slug']);

        return response()->json(['datos' => $this->comoContrato($producto)]);
    }

    /**
     * Una muestra corta del catálogo.
     *
     * QUÉ SIGNIFICA «DESTACADO» AQUÍ
     *
     * No hay una columna `destacado` ni nadie marca productos a mano, así que
     * inventarse un criterio opaco sería peor que no tener el endpoint. El
     * criterio es explícito y comprobable: productos **con existencias** y
     * **sin receta**, ordenados por nombre.
     *
     * Sin receta porque es una vitrina pública: una lista de medicamentos
     * controlados en la portada de una API abierta no es una buena idea aunque
     * los datos sean públicos. Con existencias porque destacar lo que no se
     * puede comprar no ayuda a nadie.
     */
    public function destacados(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'limite' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $productos = Producto::query()
            ->with(['categoria:id,nombre,slug'])
            ->where('requiere_receta', false)
            ->where('stock', '>', 0)
            /* Mismo criterio que la primera página de /products: un bloque de
               «recomendados» hecho de ilustraciones genéricas no recomienda nada. */
            ->ordenDestacado()
            ->limit((int) ($datos['limite'] ?? 12))
            ->get();

        return response()->json([
            'datos' => $productos->map(fn (Producto $p) => $this->comoContrato($p))->all(),
            'criterio' => 'Productos con existencias y de venta sin receta; primero los disponibles con fotografía real.',
        ]);
    }

    /**
     * La forma del contrato público. Un solo sitio.
     *
     * Si esto estuviera escrito en cada método, las tres respuestas acabarían
     * divergiendo: alguien añade un campo en el listado y se olvida del
     * detalle, y el consumidor descubre la diferencia en producción.
     *
     * @return array<string, mixed>
     */
    private function comoContrato(Producto $producto): array
    {
        return [
            'id' => $producto->id,
            'nombre' => $producto->nombre,
            'concentracion' => $producto->concentracion,
            'presentacion' => $producto->presentacion,
            'laboratorio' => $producto->laboratorio,
            'principio_activo' => $producto->principio_activo,
            'categoria' => $producto->categoria?->nombre,
            'categoria_slug' => $producto->categoria?->slug,
            'precio' => $producto->precio === null ? null : (float) $producto->precio,
            'moneda' => 'PEN',
            'requiere_receta' => (bool) $producto->requiere_receta,
            /* Si hay existencias, no cuántas. Ver la cabecera de la clase. */
            'disponible' => (int) $producto->stock > 0,
            'imagen' => $producto->imagen,
        ];
    }
}
