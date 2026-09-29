<?php

namespace App\Http\Controllers;

use App\Models\Conteo;
use App\Models\ConteoDetalle;
use App\Services\ConteoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Conteo físico por ciclos.
 *
 * Los errores de regla de negocio salen como 422 con su mensaje tal cual: son
 * cosas que el usuario puede corregir ("ya hay un conteo abierto", "el
 * desglose no cuadra"), no fallos del sistema, y esconderlas tras un 500
 * genérico obligaría a mirar los logs para algo que se resuelve en pantalla.
 */
class ConteoController extends Controller
{
    public function __construct(private readonly ConteoService $conteos)
    {
    }

    /** GET /api/conteos — historial, el más reciente primero. */
    public function index(Request $peticion): JsonResponse
    {
        $conteos = Conteo::query()
            ->with('abiertoPor:id,nombre')
            ->withCount([
                'detalles',
                'detalles as contados' => fn ($q) => $q->whereNotNull('cantidad_contada'),
            ])
            ->when($peticion->query('estado'), fn ($q, $estado) => $q->where('estado', $estado))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json(['data' => $conteos->map(fn ($c) => $this->formatearCabecera($c))]);
    }

    /** GET /api/conteos/abierto — la sesión en curso, si la hay. */
    public function abierto(): JsonResponse
    {
        $conteo = Conteo::abiertos()->latest('id')->first();

        return response()->json([
            'data' => $conteo ? $this->formatearDetalle($conteo) : null,
        ]);
    }

    /** POST /api/conteos — abre una sesión y propone qué contar. */
    public function store(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate([
            'criterio'    => ['required', Rule::in(['rotacion', 'sin_lote', 'vencimiento', 'categoria', 'manual'])],
            'ambito'      => ['nullable', 'string', 'max:120'],
            'cantidad'    => ['nullable', 'integer', 'min:1', 'max:200'],
            'productos'   => ['nullable', 'array'],
            'productos.*' => ['integer', 'exists:productos,id'],
        ]);

        try {
            $conteo = $this->conteos->abrir(
                criterio: $datos['criterio'],
                ambito: $datos['ambito'] ?? null,
                cantidad: $datos['cantidad'] ?? 25,
                usuarioId: $peticion->user()?->id,
                productosManuales: $datos['productos'] ?? [],
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Conteo abierto.',
            'data'    => $this->formatearDetalle($conteo),
        ], 201);
    }

    /** GET /api/conteos/{conteo} */
    public function show(Conteo $conteo): JsonResponse
    {
        return response()->json(['data' => $this->formatearDetalle($conteo)]);
    }

    /** PUT /api/conteos/{conteo}/detalles/{detalle} — anota lo contado. */
    public function registrar(Request $peticion, Conteo $conteo, ConteoDetalle $detalle): JsonResponse
    {
        if ($detalle->conteo_id !== $conteo->id) {
            return response()->json(['message' => 'Ese producto no pertenece a este conteo.'], 404);
        }

        $datos = $peticion->validate([
            'cantidad_contada'         => ['required', 'integer', 'min:0'],
            'lotes'                    => ['nullable', 'array'],
            'lotes.*.codigo_lote'      => ['required', 'string', 'max:60'],
            'lotes.*.fecha_vencimiento'=> ['nullable', 'date'],
            'lotes.*.cantidad'         => ['required', 'integer', 'min:1'],
            'observacion'              => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $detalle = $this->conteos->registrarConteo(
                detalle: $detalle,
                cantidadContada: $datos['cantidad_contada'],
                lotes: $datos['lotes'] ?? [],
                observacion: $datos['observacion'] ?? null,
                usuarioId: $peticion->user()?->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Conteo registrado.',
            'data'    => $this->formatearLinea($detalle->load('producto')),
        ]);
    }

    /** POST /api/conteos/{conteo}/cerrar — aplica los ajustes. */
    public function cerrar(Request $peticion, Conteo $conteo): JsonResponse
    {
        try {
            $resumen = $this->conteos->cerrar($conteo, $peticion->user()?->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Conteo cerrado y aplicado al inventario.',
            'resumen' => $resumen,
            'data'    => $this->formatearDetalle($conteo->fresh()),
        ]);
    }

    /** POST /api/conteos/{conteo}/anular */
    public function anular(Request $peticion, Conteo $conteo): JsonResponse
    {
        try {
            $conteo = $this->conteos->anular($conteo, $peticion->user()?->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Conteo anulado. El inventario no se modificó.',
            'data'    => $this->formatearCabecera($conteo),
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function formatearCabecera(Conteo $conteo): array
    {
        return [
            'id'          => $conteo->id,
            'codigo'      => $conteo->codigo,
            'estado'      => $conteo->estado,
            'criterio'    => $conteo->criterio,
            'ambito'      => $conteo->ambito,
            'abierto_en'  => $conteo->abierto_en?->toIso8601String(),
            'cerrado_en'  => $conteo->cerrado_en?->toIso8601String(),
            'abierto_por' => $conteo->abiertoPor?->nombre,
            'total'       => $conteo->detalles_count ?? $conteo->detalles()->count(),
            'contados'    => $conteo->contados ?? $conteo->detalles()->whereNotNull('cantidad_contada')->count(),
        ];
    }

    private function formatearDetalle(Conteo $conteo): array
    {
        $conteo->loadMissing(['detalles.producto', 'abiertoPor:id,nombre']);

        return $this->formatearCabecera($conteo) + [
            'lineas' => $conteo->detalles
                ->sortBy(fn ($d) => $d->producto?->nombre ?? '')
                ->values()
                ->map(fn ($d) => $this->formatearLinea($d)),
        ];
    }

    private function formatearLinea(ConteoDetalle $detalle): array
    {
        $producto = $detalle->producto;

        return [
            'id'                  => $detalle->id,
            'producto_id'         => $detalle->producto_id,
            'nombre'              => $producto?->nombre,
            'concentracion'       => $producto?->concentracion,
            'presentacion'        => $producto?->presentacion,
            'laboratorio'         => $producto?->laboratorio,
            'codigo_barras'       => $producto?->codigo_barras,
            'stock_sistema'       => $detalle->stock_sistema,
            'cantidad_contada'    => $detalle->cantidad_contada,
            'diferencia'          => $detalle->diferencia(),
            'lotes_contados'      => $detalle->lotes_contados ?? [],
            'observacion'         => $detalle->observacion,
            'contado_en'          => $detalle->contado_en?->toIso8601String(),
            'diferencia_aplicada' => $detalle->diferencia_aplicada,
            /* Lo que el sistema cree tener hoy, por lote: es lo que el
               vendedor contrasta contra el anaquel sin salir de la pantalla. */
            'lotes_sistema'       => $producto?->lotes()
                ->where('cantidad_actual', '>', 0)
                ->ordenFefo()
                ->get(['id', 'codigo_lote', 'fecha_vencimiento', 'cantidad_actual'])
                ->map(fn ($l) => [
                    'codigo_lote'       => $l->codigo_lote,
                    'fecha_vencimiento' => $l->fecha_vencimiento?->toDateString(),
                    'cantidad'          => (int) $l->cantidad_actual,
                ]) ?? [],
        ];
    }
}
