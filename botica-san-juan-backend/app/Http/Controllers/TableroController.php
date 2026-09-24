<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tablero del panel.
 * ---------------------------------------------------------------------------
 * QUÉ RESPONDE Y POR QUÉ ESTAS PREGUNTAS
 * Un tablero que cuenta productos, pedidos y clientes sirve para cualquier
 * tienda y no ayuda a ninguna. Quien abre esta pantalla por la mañana es el
 * dueño de una botica, y lo que necesita saber es concreto:
 *
 *   ¿cómo va el día comparado con ayer?
 *   ¿qué se me está por vencer?
 *   ¿qué tengo que reponer?
 *
 * La segunda es la que el sistema anterior nunca pudo responder, y es la que
 * evita tirar medicamento caducado.
 *
 * POR QUÉ SE CALCULA AQUÍ Y NO EN EL NAVEGADOR
 * La pantalla anterior pedía TODOS los pedidos y TODOS los usuarios para
 * contarlos en el navegador, y los productos los contaba sobre una sola página
 * de resultados: por eso mostraba 100 productos cuando el catálogo tiene 3361.
 * Un conteo se hace donde están los datos.
 */
class TableroController extends Controller
{
    public function index(): JsonResponse
    {
        $hoy   = Carbon::today();
        $ayer  = $hoy->copy()->subDay();

        return response()->json([
            'ventas'        => $this->ventas($hoy, $ayer),
            'vencimientos'  => $this->vencimientos(),
            'reposicion'    => $this->reposicion(),
            'catalogo'      => $this->catalogo(),
            'ultimas'       => $this->ultimasVentas(),
            'mas_vendidos'  => $this->masVendidos(),
        ]);
    }

    /**
     * Cómo va el día.
     *
     * La comparación con ayer se da en cifras, no en porcentaje: con los
     * importes de una botica de barrio, un día flojo tras uno bueno produce
     * porcentajes alarmantes que no significan nada.
     */
    private function ventas(Carbon $hoy, Carbon $ayer): array
    {
        $resumen = fn (Carbon $dia) => Pedido::query()
            ->whereDate('fecha', $dia)
            ->where('estado', '!=', 'anulado')
            ->selectRaw('COALESCE(SUM(total), 0) AS importe, COUNT(*) AS comprobantes')
            ->first();

        $deHoy  = $resumen($hoy);
        $deAyer = $resumen($ayer);

        return [
            'hoy' => [
                'importe'      => round((float) $deHoy->importe, 2),
                'comprobantes' => (int) $deHoy->comprobantes,
            ],
            'ayer' => [
                'importe'      => round((float) $deAyer->importe, 2),
                'comprobantes' => (int) $deAyer->comprobantes,
            ],
            'diferencia' => round((float) $deHoy->importe - (float) $deAyer->importe, 2),
        ];
    }

    /**
     * Qué se está por vencer, por tramos.
     *
     * Los tramos no son arbitrarios: a 90 días todavía se puede negociar la
     * devolución con el proveedor, a 30 ya sólo queda intentar venderlo, y lo
     * vencido hay que separarlo del anaquel para no venderlo por error.
     */
    private function vencimientos(): array
    {
        $hoy = Carbon::today();

        $enTramo = fn (?int $desdeDias, ?int $hastaDias) => Lote::query()
            ->where('cantidad_actual', '>', 0)
            ->whereNotNull('fecha_vencimiento')
            ->when($desdeDias !== null, fn ($q) => $q->where('fecha_vencimiento', '>=', $hoy->copy()->addDays($desdeDias)))
            ->when($hastaDias !== null, fn ($q) => $q->where('fecha_vencimiento', '<', $hoy->copy()->addDays($hastaDias)))
            ->selectRaw('COUNT(*) AS lotes, COALESCE(SUM(cantidad_actual), 0) AS unidades')
            ->first();

        $vencidos = $enTramo(null, 0);
        $treinta  = $enTramo(0, 30);
        $noventa  = $enTramo(30, 90);

        /* Cuántos lotes tienen fecha, para saber si estas cifras significan
           algo. Con el catálogo recién migrado, ningún lote la tiene todavía. */
        $conFecha = Lote::whereNotNull('fecha_vencimiento')->where('cantidad_actual', '>', 0)->count();
        $total    = Lote::where('cantidad_actual', '>', 0)->count();

        return [
            'vencidos'        => ['lotes' => (int) $vencidos->lotes, 'unidades' => (int) $vencidos->unidades],
            'en_30_dias'      => ['lotes' => (int) $treinta->lotes, 'unidades' => (int) $treinta->unidades],
            'en_90_dias'      => ['lotes' => (int) $noventa->lotes, 'unidades' => (int) $noventa->unidades],
            'lotes_con_fecha' => $conFecha,
            'lotes_totales'   => $total,
        ];
    }

    /** Qué hay que reponer, según el mínimo configurado de cada producto. */
    private function reposicion(): array
    {
        /* El stock vendible sale de los lotes, no de productos.stock, que
           incluye lo vencido: reponer según una cifra que cuenta unidades que
           no se pueden vender llevaría a comprar de menos. */
        $vendible = DB::table('lotes')
            ->selectRaw('producto_id, COALESCE(SUM(cantidad_actual), 0) AS disponible')
            ->where('cantidad_actual', '>', 0)
            ->where('estado', 'activo')
            ->where(function ($q) {
                $q->whereNull('fecha_vencimiento')->orWhere('fecha_vencimiento', '>', Carbon::today());
            })
            ->groupBy('producto_id');

        $fila = DB::query()
            ->fromSub(
                Producto::query()
                    ->leftJoinSub($vendible, 'v', 'v.producto_id', '=', 'productos.id')
                    ->selectRaw('COALESCE(v.disponible, 0) AS disponible, productos.stock_minimo, productos.stock_reposicion'),
                'p'
            )
            ->selectRaw('
                COUNT(*) FILTER (WHERE disponible <= 0) AS agotados,
                COUNT(*) FILTER (WHERE disponible > 0 AND disponible <= COALESCE(stock_minimo, 5)) AS criticos,
                COUNT(*) FILTER (WHERE disponible > COALESCE(stock_minimo, 5) AND disponible <= COALESCE(stock_reposicion, 10)) AS bajos
            ')
            ->first();

        return [
            'agotados' => (int) $fila->agotados,
            'criticos' => (int) $fila->criticos,
            'bajos'    => (int) $fila->bajos,
        ];
    }

    private function catalogo(): array
    {
        return [
            'productos' => Producto::count(),
            'lotes'     => Lote::where('cantidad_actual', '>', 0)->count(),
        ];
    }

    /**
     * Últimas ventas, con su importe.
     *
     * El importe es lo primero que se quiere ver de una venta; la pantalla
     * anterior mostraba el número de pedido y el cliente, que en una botica es
     * casi siempre "cliente eventual" y no dice nada.
     */
    private function ultimasVentas(): array
    {
        return Pedido::query()
            ->where('estado', '!=', 'anulado')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'fecha', 'total', 'medio_pago', 'origen', 'cliente_nombre'])
            ->map(fn (Pedido $p) => [
                'id'         => $p->id,
                'fecha'      => $p->fecha?->toIso8601String(),
                'total'      => round((float) $p->total, 2),
                'medio_pago' => $p->medio_pago ?? 'efectivo',
                'origen'     => $p->origen,
                'cliente'    => $p->cliente_nombre,
            ])
            ->all();
    }

    /**
     * Lo más vendido de los últimos 30 días, en unidades.
     *
     * Se cuenta en unidades y no en número de líneas: vender una caja de 100 y
     * una unidad suelta son dos líneas iguales en la boleta, pero no en el
     * anaquel ni a la hora de reponer.
     */
    private function masVendidos(): array
    {
        return DB::table('pedido_detalles as d')
            ->join('pedidos as p', 'p.id', '=', 'd.pedido_id')
            ->join('productos as pr', 'pr.id', '=', 'd.producto_id')
            ->where('p.fecha', '>=', Carbon::today()->subDays(30))
            ->where('p.estado', '!=', 'anulado')
            ->groupBy('pr.id', 'pr.nombre', 'pr.concentracion')
            ->selectRaw('pr.id, pr.nombre, pr.concentracion, SUM(d.cantidad_unidades) AS unidades, SUM(d.subtotal) AS importe')
            ->orderByDesc('unidades')
            ->limit(6)
            ->get()
            ->map(fn ($f) => [
                'id'            => (int) $f->id,
                'nombre'        => $f->nombre,
                'concentracion' => $f->concentracion,
                'unidades'      => (int) $f->unidades,
                'importe'       => round((float) $f->importe, 2),
            ])
            ->all();
    }
}
