<?php

namespace App\Http\Controllers;

use App\Exceptions\VentaSinStockException;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\PedidoPago;
use App\Models\Producto;
use App\Services\InventarioService;
use App\Services\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Punto de venta de mostrador.
 *
 * La prioridad aquí es la velocidad de atención: quien está detrás del
 * mostrador tiene un cliente esperando. Por eso la búsqueda devuelve poco y
 * rápido, y la verificación de stock se resuelve en una sola llamada para
 * todo el carrito en lugar de una por producto.
 */
class PosController extends Controller
{
    public function __construct(
        private readonly InventarioService $inventario,
        private readonly VentaService $ventas,
    ) {
    }

    /**
     * Búsqueda de productos para el mostrador.
     *
     * GET /api/pos/productos?q=paracetamol
     *
     * Devuelve solo lo que la pantalla de venta necesita pintar, no el
     * producto completo: en una búsqueda mientras el cliente espera, cada
     * campo de más es latencia.
     *
     * Busca por nombre, principio activo y código de barras. Lo del código de
     * barras es deliberado aunque hoy no usen escáner: el día que conecten
     * uno, el lector escribe el código en el mismo cuadro de búsqueda y
     * funciona sin cambiar nada.
     */
    public function buscarProductos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'q'     => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $termino = trim($datos['q'] ?? '');
        $limite  = (int) ($datos['limit'] ?? 20);

        if (mb_strlen($termino) < 2) {
            return response()->json(['data' => []]);
        }

        $patron = '%' . str_replace('%', '\%', $termino) . '%';

        $productos = Producto::query()
            ->select([
                'id', 'nombre', 'concentracion', 'presentacion', 'laboratorio',
                'precio', 'precio_blister', 'precio_caja',
                'unidades_por_blister', 'blisters_por_caja', 'venta_fraccionada',
                'codigo_barras', 'principio_activo', 'requiere_receta',
                'afecto_igv', 'tipo_afectacion_igv', 'base_legal_exoneracion', 'stock',
            ])
            ->where(function ($q) use ($patron, $termino) {
                $q->where('nombre', 'ILIKE', $patron)
                  ->orWhere('principio_activo', 'ILIKE', $patron)
                  ->orWhere('codigo_barras', '=', $termino);
            })
            ->orderBy('nombre')
            ->limit($limite)
            ->get();

        /* El stock que importa en mostrador es el vendible, no el contable:
           un producto puede tener 40 unidades y ninguna apta si está todo
           vencido. Se resuelve en una sola consulta agrupada para no lanzar
           una por producto. */
        $idsProductos = $productos->pluck('id');

        $disponibles = Lote::query()
            ->selectRaw('producto_id, SUM(cantidad_actual) AS total')
            ->whereIn('producto_id', $idsProductos)
            ->disponible()
            ->groupBy('producto_id')
            ->pluck('total', 'producto_id');

        $respuesta = $productos->map(function (Producto $p) use ($disponibles) {
            $disponible = (int) ($disponibles[$p->id] ?? 0);

            return [
                'id'                  => $p->id,
                'nombre'              => $p->nombre,
                'concentracion'       => $p->concentracion,
                'presentacion'        => $p->presentacion,
                'laboratorio'         => $p->laboratorio,
                'principio_activo'    => $p->principio_activo,
                'codigo_barras'       => $p->codigo_barras,
                'requiere_receta'     => (bool) $p->requiere_receta,
                'afecto_igv'          => (bool) $p->afecto_igv,
                /* El tratamiento es del producto y vale igual para las tres
                   presentaciones: la pantalla no debe derivarlo de la forma
                   de venta elegida. */
                'tipo_afectacion_igv' => $p->tipo_afectacion_igv,
                'afectacion'          => $p->etiquetaAfectacion(),
                'stock_contable'      => (int) $p->stock,
                'stock_disponible'    => $disponible,
                'venta_fraccionada'   => (bool) $p->venta_fraccionada,
                /* Formas de venta con su precio y su equivalencia. El POS las
                   pinta tal cual: no tiene que calcular nada. */
                'formas_venta'        => $this->formasVenta($p, $disponible),
            ];
        });

        return response()->json(['data' => $respuesta]);
    }

    /**
     * Formas en que se puede vender un producto, con precio y disponibilidad.
     *
     * Una forma se marca como no disponible cuando no alcanza ni para una
     * unidad de ella: si quedan 7 sueltas, la caja de 50 no se ofrece.
     */
    private function formasVenta(Producto $producto, int $disponible): array
    {
        $formas = [];

        foreach (['unidad', 'blister', 'caja'] as $forma) {
            if ($forma === 'blister' && empty($producto->unidades_por_blister)) {
                continue;
            }
            if ($forma === 'caja' && empty($producto->blisters_por_caja)) {
                continue;
            }

            $factor = $this->inventario->factorUnidades($producto, $forma);

            $formas[] = [
                'unidad_venta'     => $forma,
                'factor_unidades'  => $factor,
                'precio'           => round($this->inventario->precioUnidadVenta($producto, $forma), 2),
                'maximo_vendible'  => intdiv($disponible, $factor),
                'disponible'       => $disponible >= $factor,
            ];
        }

        return $formas;
    }

    /**
     * Comprueba el carrito completo antes de cobrar.
     *
     * POST /api/pos/verificar
     *
     * Es lo que permite avisar "pediste 10, solo hay 7" ANTES de que el
     * cliente pague, que es la regla acordada con el negocio. No modifica
     * nada: se puede llamar tantas veces como haga falta.
     */
    public function verificar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.producto_id'   => ['required', 'integer', 'exists:productos,id'],
            'items.*.unidad_venta'  => ['required', Rule::in(['unidad', 'blister', 'caja'])],
            'items.*.cantidad'      => ['required', 'integer', 'min:1'],
        ]);

        $verificacion = $this->ventas->verificarDisponibilidad($datos['items']);

        return response()->json([
            'data' => $verificacion,
            'hay_faltantes' => collect($verificacion)->contains(fn ($v) => ($v['faltante'] ?? 0) > 0),
        ]);
    }

    /**
     * Registra la venta.
     *
     * POST /api/pos/ventas
     *
     * `confirmar_parcial` es la salvaguarda de la regla de negocio: si falta
     * stock y el vendedor no ha confirmado explícitamente, la petición se
     * rechaza con el detalle de lo que falta. Así nunca se cobra una entrega
     * parcial sin que alguien lo haya aceptado a conciencia.
     */
    public function registrarVenta(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.producto_id'      => ['required', 'integer', 'exists:productos,id'],
            'items.*.unidad_venta'     => ['required', Rule::in(['unidad', 'blister', 'caja'])],
            'items.*.cantidad'         => ['required', 'integer', 'min:1'],
            'cliente_nombre'           => ['nullable', 'string', 'max:255'],
            'cliente_documento'        => ['nullable', 'string', 'max:20'],
            'cliente_tipo_documento'   => ['nullable', Rule::in(['dni', 'ruc', 'ce', 'sin_documento'])],
            'observacion'              => ['nullable', 'string', 'max:500'],
            'confirmar_parcial'        => ['nullable', 'boolean'],

            /* El cobro es opcional: sin datos se asume efectivo exacto, que es
               la venta típica de mostrador. */
            'pagos'                    => ['nullable', 'array', 'max:4'],
            'pagos.*.medio'            => ['required', Rule::in(PedidoPago::MEDIOS)],
            'pagos.*.monto'            => ['nullable', 'numeric', 'min:0.01'],
            'pagos.*.monto_recibido'   => ['nullable', 'numeric', 'min:0'],
            'pagos.*.referencia'       => ['nullable', 'string', 'max:60'],
        ]);

        $verificacion = $this->ventas->verificarDisponibilidad($datos['items']);
        $faltantes    = collect($verificacion)->filter(fn ($v) => ($v['faltante'] ?? 0) > 0);

        if ($faltantes->isNotEmpty() && ! ($datos['confirmar_parcial'] ?? false)) {
            return response()->json([
                'message'    => 'Hay productos sin stock suficiente. Confirma la entrega parcial para continuar.',
                'requiere_confirmacion' => true,
                'faltantes'  => $faltantes->values(),
            ], 409);
        }

        try {
            $venta = $this->ventas->registrar(
                items: $datos['items'],
                datosCliente: [
                    'cliente_nombre'         => $datos['cliente_nombre'] ?? null,
                    'cliente_documento'      => $datos['cliente_documento'] ?? null,
                    'cliente_tipo_documento' => $datos['cliente_tipo_documento'] ?? 'sin_documento',
                    'observacion'            => $datos['observacion'] ?? null,
                ],
                vendedorId: $request->user()?->id,
                pagos: $datos['pagos'] ?? [],
            );
        } catch (VentaSinStockException $e) {
            /* Confirmar la entrega parcial no puede convertirse en aceptar una
               venta vacía: si no hay ni una unidad que entregar, no hay venta. */
            return response()->json([
                'message'   => $e->getMessage(),
                'sin_stock' => true,
                'faltantes' => $faltantes->values(),
            ], 422);
        } catch (RuntimeException $e) {
            /* Cobro que no cuadra, medio no válido, efectivo insuficiente: son
               cosas que el vendedor corrige en pantalla, no fallos del sistema.
               La transacción ya revirtió, así que no quedó venta a medias. */
            return response()->json([
                'message'    => $e->getMessage(),
                'error_pago' => true,
            ], 422);
        }

        return response()->json([
            'message' => 'Venta registrada.',
            'data'    => $this->formatearVenta($venta),
        ], 201);
    }

    /**
     * Detalle de una venta, con su desglose por lotes.
     *
     * GET /api/pos/ventas/{pedido}
     */
    public function verVenta(Pedido $pedido): JsonResponse
    {
        $pedido->load(['detalles.producto:id,nombre,concentracion,presentacion', 'detalles.lotesAsignados', 'incidencias', 'vendedor:id,nombre']);

        return response()->json(['data' => $this->formatearVenta($pedido)]);
    }

    private function formatearVenta(Pedido $pedido): array
    {
        $pedido->loadMissing(['detalles.lotesAsignados', 'detalles.producto:id,nombre,concentracion,presentacion', 'incidencias', 'pagos']);

        return [
            'id'                 => $pedido->id,
            'medio_pago'         => $pedido->medio_pago,
            'pagos'              => $pedido->pagos->map(fn ($p) => [
                'medio'          => $p->medio,
                'etiqueta'       => $p->etiqueta(),
                'monto'          => (float) $p->monto,
                'monto_recibido' => $p->monto_recibido !== null ? (float) $p->monto_recibido : null,
                'vuelto'         => $p->vuelto !== null ? (float) $p->vuelto : null,
                'referencia'     => $p->referencia,
            ]),
            'fecha'              => $pedido->fecha?->toIso8601String(),
            'origen'             => $pedido->origen,
            'cliente_nombre'     => $pedido->cliente_nombre,
            'cliente_documento'  => $pedido->cliente_documento,
            'subtotal_gravado'   => (float) $pedido->subtotal_gravado,
            'subtotal_exonerado' => (float) $pedido->subtotal_exonerado,
            'igv'                => (float) $pedido->igv,
            'tasa_igv'           => (float) $pedido->tasa_igv,
            'total'              => (float) $pedido->total,
            'observacion'        => $pedido->observacion,
            'items' => $pedido->detalles->map(fn ($d) => [
                'producto_id'       => $d->producto_id,
                'nombre'            => $d->producto?->nombre,
                'presentacion'      => $d->producto?->presentacion,
                'unidad_venta'      => $d->unidad_venta,
                'cantidad'          => $d->cantidad,
                'cantidad_unidades' => $d->cantidad_unidades,
                'precio'            => (float) $d->precio,
                'subtotal'          => (float) $d->subtotal,
                /* El desglose por lote no se imprime en la boleta, pero viaja
                   en la respuesta para que el panel pueda mostrarlo. */
                'lotes' => $d->lotesAsignados->map(fn ($l) => [
                    'codigo_lote'       => $l->codigo_lote,
                    'fecha_vencimiento' => $l->fecha_vencimiento?->toDateString(),
                    'cantidad_unidades' => $l->cantidad_unidades,
                ]),
            ]),
            'incidencias' => $pedido->incidencias->map(fn ($i) => [
                'tipo'                => $i->tipo,
                'producto_id'         => $i->producto_id,
                'cantidad_solicitada' => $i->cantidad_solicitada,
                'cantidad_atendida'   => $i->cantidad_atendida,
                'faltante'            => $i->faltante(),
                'observacion'         => $i->observacion,
            ]),
        ];
    }

    /**
     * Lotes de un producto, para consulta desde el mostrador.
     *
     * GET /api/pos/productos/{producto}/lotes
     */
    public function lotesDeProducto(Producto $producto): JsonResponse
    {
        $lotes = Lote::query()
            ->delProducto($producto->id)
            ->where('cantidad_actual', '>', 0)
            ->ordenFefo()
            ->get()
            ->map(fn (Lote $l) => [
                'id'                => $l->id,
                'codigo_lote'       => $l->codigo_lote,
                'fecha_vencimiento' => $l->fecha_vencimiento?->toDateString(),
                'dias_para_vencer'  => $l->diasParaVencer(),
                'nivel_vencimiento' => $l->nivelVencimiento(),
                'cantidad_actual'   => $l->cantidad_actual,
                'estado'            => $l->estado,
                'vendible'          => $l->estado === 'activo' && ! $l->estaVencido(),
            ]);

        return response()->json(['data' => $lotes]);
    }

    /**
     * Corrección de stock desde el mostrador.
     *
     * POST /api/pos/lotes/{lote}/ajustar
     *
     * Existe porque el conteo físico y el del sistema se desfasan siempre. Si
     * el vendedor ve diez cajas y el sistema le obliga a vender siete, acaba
     * ignorando el sistema — y ahí es donde estos proyectos mueren. Mejor
     * permitir la corrección dejando registro de quién y por qué.
     */
    public function ajustarLote(Request $request, Lote $lote): JsonResponse
    {
        if (! config('inventario.permitir_ajuste_en_venta', true)) {
            return response()->json(['message' => 'El ajuste desde el punto de venta esta desactivado.'], 403);
        }

        $datos = $request->validate([
            'cantidad' => ['required', 'integer', 'min:0'],
            'motivo'   => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $movimiento = DB::transaction(function () use ($lote, $datos, $request) {
            $producto = Producto::whereKey($lote->producto_id)->lockForUpdate()->first();

            return $this->inventario->ajustar(
                producto: $producto,
                lote: $lote,
                nuevaCantidad: (int) $datos['cantidad'],
                motivo: $datos['motivo'],
                usuarioId: $request->user()?->id,
            );
        });

        return response()->json([
            'message' => 'Stock ajustado y registrado.',
            'data'    => [
                'lote_id'         => $lote->id,
                'cantidad_actual' => $lote->fresh()->cantidad_actual,
                'diferencia'      => $movimiento->cantidad,
            ],
        ]);
    }
}
