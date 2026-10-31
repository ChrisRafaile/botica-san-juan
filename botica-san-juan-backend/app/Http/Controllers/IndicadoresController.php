<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores gerenciales del panel.
 * ---------------------------------------------------------------------------
 * QUÉ RESPONDE
 *
 *   1. ¿Qué se me vence y cuándo?        semáforo 30 / 60 / 90 días
 *   2. ¿Cómo me están pagando?           arqueo por medio de pago
 *   3. ¿Qué se vende de verdad?          top de medicamentos
 *
 * POR QUÉ VA SEPARADO DEL TABLERO
 *
 * El tablero responde «cómo va el día» y tiene que abrir al instante. Estos
 * tres recorren el histórico y son más caros. Separarlos deja que la pantalla
 * pinte lo urgente primero y los gráficos lleguen después, en vez de retrasar
 * ambas cosas hasta que la más lenta termine.
 *
 * TODO SE AGREGA EN SQL
 *
 * Por el mismo motivo que el tablero: la pantalla anterior se traía las filas
 * y las contaba en el navegador. Con 886 lotes y un histórico que solo crece,
 * eso es mandar al mostrador trabajo que la base hace en milisegundos.
 *
 * SOBRE LOS DATOS QUE FALTAN
 *
 * Los lotes sin fecha de vencimiento NO se reparten entre los tramos ni se
 * ignoran: se cuentan aparte. Repartirlos inventaría un semáforo; ignorarlos
 * daría a entender que todo el inventario está controlado cuando la mayor
 * parte del catálogo todavía no tiene fecha capturada. El hueco es el dato
 * más accionable de los tres.
 */
class IndicadoresController extends Controller
{
    /** Tramos del semáforo, en días. */
    private const TRAMOS = [30, 60, 90];

    public function index(Request $request): JsonResponse
    {
        $dias = (int) $request->integer('dias', 30);
        $dias = max(1, min($dias, 365));

        return response()->json([
            'vencimientos' => $this->semaforoDeVencimiento(),
            'arqueo' => $this->arqueoPorMedio($dias),
            'mas_vendidos' => $this->masVendidos($dias),
            'ventana_dias' => $dias,
            'generado_en' => now()->toIso8601String(),
        ]);
    }

    /**
     * Reparte las existencias por cercanía al vencimiento.
     *
     * Se cuenta sobre LOTES CON EXISTENCIA (`cantidad_actual > 0`): un lote
     * agotado que venció el mes pasado no es un problema de inventario, y
     * contarlo infla la alarma hasta volverla ruido que nadie mira.
     */
    private function semaforoDeVencimiento(): array
    {
        $hoy = now()->startOfDay();

        $fila = DB::table('lotes')
            ->where('cantidad_actual', '>', 0)
            ->selectRaw('
                count(*) filter (where fecha_vencimiento is null)                                   as sin_fecha,
                coalesce(sum(cantidad_actual) filter (where fecha_vencimiento is null), 0)          as sin_fecha_unidades,
                count(*) filter (where fecha_vencimiento < ?)                                       as vencido,
                coalesce(sum(cantidad_actual) filter (where fecha_vencimiento < ?), 0)              as vencido_unidades,
                count(*) filter (where fecha_vencimiento between ? and ?)                           as t30,
                coalesce(sum(cantidad_actual) filter (where fecha_vencimiento between ? and ?), 0)  as t30_unidades,
                count(*) filter (where fecha_vencimiento between ? and ?)                           as t60,
                coalesce(sum(cantidad_actual) filter (where fecha_vencimiento between ? and ?), 0)  as t60_unidades,
                count(*) filter (where fecha_vencimiento between ? and ?)                           as t90,
                coalesce(sum(cantidad_actual) filter (where fecha_vencimiento between ? and ?), 0)  as t90_unidades,
                count(*) filter (where fecha_vencimiento > ?)                                       as holgado,
                coalesce(sum(cantidad_actual) filter (where fecha_vencimiento > ?), 0)              as holgado_unidades
            ', [
                $hoy, $hoy,
                $hoy, $hoy->copy()->addDays(self::TRAMOS[0]),
                $hoy, $hoy->copy()->addDays(self::TRAMOS[0]),
                $hoy->copy()->addDays(self::TRAMOS[0] + 1), $hoy->copy()->addDays(self::TRAMOS[1]),
                $hoy->copy()->addDays(self::TRAMOS[0] + 1), $hoy->copy()->addDays(self::TRAMOS[1]),
                $hoy->copy()->addDays(self::TRAMOS[1] + 1), $hoy->copy()->addDays(self::TRAMOS[2]),
                $hoy->copy()->addDays(self::TRAMOS[1] + 1), $hoy->copy()->addDays(self::TRAMOS[2]),
                $hoy->copy()->addDays(self::TRAMOS[2]), $hoy->copy()->addDays(self::TRAMOS[2]),
            ])
            ->first();

        $tramos = [
            ['clave' => 'vencido',  'etiqueta' => 'Ya vencido',      'color' => 'peligro', 'lotes' => (int) $fila->vencido,  'unidades' => (int) $fila->vencido_unidades],
            ['clave' => 't30',      'etiqueta' => 'Vence en 30 días', 'color' => 'peligro', 'lotes' => (int) $fila->t30,      'unidades' => (int) $fila->t30_unidades],
            ['clave' => 't60',      'etiqueta' => 'Vence en 60 días', 'color' => 'alerta',  'lotes' => (int) $fila->t60,      'unidades' => (int) $fila->t60_unidades],
            ['clave' => 't90',      'etiqueta' => 'Vence en 90 días', 'color' => 'ambar',   'lotes' => (int) $fila->t90,      'unidades' => (int) $fila->t90_unidades],
            ['clave' => 'holgado',  'etiqueta' => 'Más de 90 días',   'color' => 'exito',   'lotes' => (int) $fila->holgado,  'unidades' => (int) $fila->holgado_unidades],
        ];

        $conFecha = array_sum(array_column($tramos, 'lotes'));
        $sinFecha = (int) $fila->sin_fecha;
        $total = $conFecha + $sinFecha;

        return [
            'tramos' => $tramos,
            'sin_fecha' => [
                'lotes' => $sinFecha,
                'unidades' => (int) $fila->sin_fecha_unidades,
            ],
            'lotes_con_existencia' => $total,
            /* La cobertura es la cifra que de verdad manda: un semáforo
               calculado sobre el 2 % del inventario no describe el almacén,
               y la pantalla tiene que poder decirlo. */
            'cobertura' => $total > 0 ? round($conFecha / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Arqueo por medio de pago.
     *
     * Solo pedidos pagados: un pedido anulado o pendiente no es caja.
     */
    private function arqueoPorMedio(int $dias): array
    {
        $desde = now()->subDays($dias)->startOfDay();

        $filas = DB::table('pedido_pagos')
            ->join('pedidos', 'pedidos.id', '=', 'pedido_pagos.pedido_id')
            ->where('pedidos.fecha', '>=', $desde)
            ->whereNotIn('pedidos.estado', ['anulado', 'cancelado'])
            ->groupBy('pedido_pagos.medio')
            ->orderByDesc(DB::raw('sum(pedido_pagos.monto)'))
            ->get([
                'pedido_pagos.medio',
                DB::raw('count(*) as operaciones'),
                DB::raw('sum(pedido_pagos.monto) as total'),
            ]);

        $total = (float) $filas->sum('total');

        return [
            'medios' => $filas->map(fn ($f) => [
                'medio' => $f->medio,
                'etiqueta' => ucfirst((string) $f->medio),
                'operaciones' => (int) $f->operaciones,
                'total' => round((float) $f->total, 2),
                'porcentaje' => $total > 0 ? round((float) $f->total / $total * 100, 1) : 0.0,
            ])->all(),
            'total' => round($total, 2),
        ];
    }

    /**
     * Medicamentos más entregados en la ventana.
     *
     * Se ordena por UNIDADES y no por importe: lo que interesa para reponer
     * es cuántas cajas salieron del anaquel, no cuánto facturaron. Un producto
     * caro vendido dos veces no se repone antes que uno barato vendido ciento.
     */
    private function masVendidos(int $dias, int $tope = 10): array
    {
        $desde = now()->subDays($dias)->startOfDay();

        $filas = DB::table('pedido_detalles')
            ->join('pedidos', 'pedidos.id', '=', 'pedido_detalles.pedido_id')
            ->join('productos', 'productos.id', '=', 'pedido_detalles.producto_id')
            ->where('pedidos.fecha', '>=', $desde)
            ->whereNotIn('pedidos.estado', ['anulado', 'cancelado'])
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc(DB::raw('sum(pedido_detalles.cantidad_unidades)'))
            ->limit($tope)
            ->get([
                'productos.id',
                'productos.nombre',
                DB::raw('sum(pedido_detalles.cantidad_unidades) as unidades'),
                DB::raw('sum(pedido_detalles.subtotal) as importe'),
            ]);

        return $filas->map(fn ($f) => [
            'producto_id' => (int) $f->id,
            'nombre' => $f->nombre,
            'unidades' => (int) $f->unidades,
            'importe' => round((float) $f->importe, 2),
        ])->all();
    }
}
