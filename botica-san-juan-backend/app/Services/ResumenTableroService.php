<?php

namespace App\Services;

use App\Models\Pedido;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Métricas comparativas y de distribución para el tablero administrativo.
 */
class ResumenTableroService
{
    /**
     * Compara las ventas diarias de los últimos 7 días contra los 7 días previos.
     *
     * @return array{
     *   dias: array<int, array{etiqueta:string, fecha:string, actual:float, anterior:float}>,
     *   total_actual: float,
     *   total_anterior: float,
     *   variacion_absoluta: float
     * }
     */
    public function comparativaSemanal(?Carbon $referencia = null): array
    {
        $hoy = ($referencia ?? Carbon::today())->copy()->startOfDay();
        $inicioAnterior = $hoy->copy()->subDays(13);

        $porDia = Pedido::query()
            ->whereDate('fecha', '>=', $inicioAnterior)
            ->whereDate('fecha', '<=', $hoy)
            ->where('estado', '!=', 'anulado')
            ->selectRaw('DATE(fecha) AS dia, COALESCE(SUM(total), 0) AS importe')
            ->groupByRaw('DATE(fecha)')
            ->pluck('importe', 'dia');

        $etiquetas = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $dias = [];
        $totalActual = 0.0;
        $totalAnterior = 0.0;

        for ($offset = 6; $offset >= 0; $offset--) {
            $fechaActual = $hoy->copy()->subDays($offset);
            $fechaAnterior = $fechaActual->copy()->subDays(7);

            $impActual = round((float) ($porDia[$fechaActual->toDateString()] ?? 0), 2);
            $impAnterior = round((float) ($porDia[$fechaAnterior->toDateString()] ?? 0), 2);

            $totalActual += $impActual;
            $totalAnterior += $impAnterior;

            $dias[] = [
                'etiqueta' => $etiquetas[$fechaActual->dayOfWeek],
                'fecha'    => $fechaActual->toDateString(),
                'actual'   => $impActual,
                'anterior' => $impAnterior,
            ];
        }

        return [
            'dias'               => $dias,
            'total_actual'       => round($totalActual, 2),
            'total_anterior'     => round($totalAnterior, 2),
            'variacion_absoluta' => round($totalActual - $totalAnterior, 2),
        ];
    }

    /**
     * Distribución de ventas por método de pago en los últimos N días.
     *
     * @return array<int, array{medio:string, comprobantes:int, importe:float, porcentaje:float}>
     */
    public function distribucionMetodosPago(int $dias = 30): array
    {
        $desde = Carbon::today()->subDays($dias);

        $filas = Pedido::query()
            ->where('fecha', '>=', $desde)
            ->where('estado', '!=', 'anulado')
            ->selectRaw("COALESCE(NULLIF(medio_pago, ''), 'efectivo') AS medio, COUNT(*) AS comprobantes, COALESCE(SUM(total), 0) AS importe")
            ->groupByRaw("COALESCE(NULLIF(medio_pago, ''), 'efectivo')")
            ->orderByDesc('importe')
            ->get();

        $totalImporte = (float) $filas->sum('importe');

        return $filas->map(function ($fila) use ($totalImporte) {
            $importe = round((float) $fila->importe, 2);
            $porcentaje = $totalImporte > 0 ? round(($importe / $totalImporte) * 100, 1) : 0.0;

            return [
                'medio'        => (string) $fila->medio,
                'comprobantes' => (int) $fila->comprobantes,
                'importe'      => $importe,
                'porcentaje'   => $porcentaje,
            ];
        })->values()->all();
    }

    /**
     * Top N productos más vendidos por unidades en los últimos días.
     *
     * @return array<int, array{id:int, nombre:string, concentracion:?string, unidades:int, importe:float}>
     */
    public function topProductos(int $dias = 30, int $limite = 5): array
    {
        return DB::table('pedido_detalles as d')
            ->join('pedidos as p', 'p.id', '=', 'd.pedido_id')
            ->join('productos as pr', 'pr.id', '=', 'd.producto_id')
            ->where('p.fecha', '>=', Carbon::today()->subDays($dias))
            ->where('p.estado', '!=', 'anulado')
            ->groupBy('pr.id', 'pr.nombre', 'pr.concentracion')
            ->selectRaw('pr.id, pr.nombre, pr.concentracion, SUM(d.cantidad_unidades) AS unidades, SUM(d.subtotal) AS importe')
            ->orderByDesc('unidades')
            ->limit($limite)
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
