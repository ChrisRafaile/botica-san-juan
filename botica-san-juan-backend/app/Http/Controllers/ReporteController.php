<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    public function ventas(Request $request)
    {
        $period = (string) $request->input('period', 'month');
        $from = $this->resolveFromDate($request->input('from'), $period);
        $to = $this->resolveToDate($request->input('to'), $period);

        $query = Pedido::query()
            ->with(['pedidoDetalles.producto'])
            ->whereBetween('fecha_pedido', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        if ($request->filled('estado') && $request->input('estado') !== 'all') {
            $query->where('estado', $request->input('estado'));
        }

        $orders = $query->get();

        $dailyRows = [];
        $topProducts = [];
        $statusBreakdown = [];
        $uniqueCustomers = [];

        foreach ($orders as $order) {
            $orderDate = Carbon::parse($order->fecha_pedido)->toDateString();
            $dailyRows[$orderDate] ??= [
                'fecha' => $orderDate,
                'pedidos' => 0,
                'productos' => 0,
                'ingresos' => 0.0,
            ];

            $dailyRows[$orderDate]['pedidos'] += 1;
            $dailyRows[$orderDate]['ingresos'] += (float) $order->total;

            if ($order->usuario_id) {
                $uniqueCustomers[(string) $order->usuario_id] = true;
            }

            $status = (string) $order->estado;
            $statusBreakdown[$status] ??= [
                'estado' => $status,
                'pedidos' => 0,
                'total' => 0.0,
            ];
            $statusBreakdown[$status]['pedidos'] += 1;
            $statusBreakdown[$status]['total'] += (float) $order->total;

            foreach ($order->pedidoDetalles as $detail) {
                $dailyRows[$orderDate]['productos'] += (int) $detail->cantidad;

                $productName = $detail->producto?->nombre ?: 'Producto';
                $productKey = $detail->producto_id ? (string) $detail->producto_id : $productName;
                $topProducts[$productKey] ??= [
                    'nombre' => $productName,
                    'cantidad' => 0,
                    'total' => 0.0,
                ];
                $topProducts[$productKey]['cantidad'] += (int) $detail->cantidad;
                $topProducts[$productKey]['total'] += (float) $detail->subtotal;
            }
        }

        $dailyRows = array_values($dailyRows);
        usort($dailyRows, static fn (array $left, array $right): int => $left['fecha'] <=> $right['fecha']);

        $topProducts = array_values($topProducts);
        usort($topProducts, static fn (array $left, array $right): int => $right['cantidad'] <=> $left['cantidad']);

        $statusBreakdown = array_values($statusBreakdown);
        usort($statusBreakdown, static fn (array $left, array $right): int => $right['pedidos'] <=> $left['pedidos']);

        $totalSales = array_reduce($dailyRows, static fn (float $sum, array $row): float => $sum + (float) $row['ingresos'], 0.0);
        $totalOrders = $orders->count();
        $totalUnits = array_reduce($dailyRows, static fn (int $sum, array $row): int => $sum + (int) $row['productos'], 0);

        return response()->json([
            'filters' => [
                'period' => $period,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => [
                'sales' => round($totalSales, 2),
                'orders' => $totalOrders,
                'units' => $totalUnits,
                'average_ticket' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
                'customers' => count($uniqueCustomers),
            ],
            'daily_rows' => array_map(static function (array $row): array {
                $row['ingresos'] = round($row['ingresos'], 2);
                return $row;
            }, $dailyRows),
            'top_products' => array_slice(array_map(static function (array $item): array {
                $item['total'] = round($item['total'], 2);
                return $item;
            }, $topProducts), 0, 5),
            'status_breakdown' => array_map(static function (array $row): array {
                $row['total'] = round($row['total'], 2);
                return $row;
            }, $statusBreakdown),
        ]);
    }

    public function gerencial(Request $request)
    {
        // El periodo declarado por el consumidor se respeta igual que en el
        // reporte de ventas. Antes quedaba fijado en 'month', de modo que una
        // peticion con period=year devolvia el rango del mes en curso y el
        // consumidor recibia indicadores que no correspondian a lo solicitado.
        $period = (string) $request->input('period', 'month');
        $from = $this->resolveFromDate($request->input('from'), $period);
        $to = $this->resolveToDate($request->input('to'), $period);

        $ventas = Pedido::query()
            ->whereBetween('fecha_pedido', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('estado', '!=', 'cancelado')
            ->selectRaw('COALESCE(SUM(total),0) as total')
            ->value('total');

        $compras = Compra::query()
            ->whereBetween('fecha_compra', [$from->toDateString(), $to->toDateString()])
            ->whereIn('estado', ['emitida', 'recibida'])
            ->selectRaw('COALESCE(SUM(total),0) as total')
            ->value('total');

        $topProductos = PedidoDetalle::query()
            ->selectRaw('producto_id, SUM(cantidad) as unidades, SUM(subtotal) as total')
            ->whereHas('pedido', function ($query) use ($from, $to) {
                $query->whereBetween('fecha_pedido', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
            })
            ->with('producto:id,nombre,stock,stock_minimo')
            ->groupBy('producto_id')
            ->orderByDesc('unidades')
            ->limit(10)
            ->get()
            ->map(static function (PedidoDetalle $detalle): array {
                return [
                    'producto_id' => $detalle->producto_id,
                    'nombre' => $detalle->producto?->nombre ?? 'Producto',
                    'unidades' => (int) $detalle->unidades,
                    'total' => round((float) $detalle->total, 2),
                    'stock' => (int) ($detalle->producto?->stock ?? 0),
                    'stock_minimo' => (int) ($detalle->producto?->stock_minimo ?? 0),
                ];
            })
            ->values();

        $stockRiesgo = Producto::query()
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->orderBy('stock')
            ->limit(50)
            ->get(['id', 'nombre', 'stock', 'stock_minimo', 'precio'])
            ->map(static function (Producto $producto): array {
                return [
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'stock' => (int) ($producto->stock ?? 0),
                    'stock_minimo' => (int) ($producto->stock_minimo ?? 0),
                    'precio' => round((float) ($producto->precio ?? 0), 2),
                ];
            })
            ->values();

        $ventasTotal = (float) $ventas;
        $comprasTotal = (float) $compras;
        $margen = $ventasTotal - $comprasTotal;
        $margenPct = $ventasTotal > 0 ? ($margen / $ventasTotal) * 100 : 0;

        return response()->json([
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'kpis' => [
                'ventas' => round($ventasTotal, 2),
                'compras' => round($comprasTotal, 2),
                'margen' => round($margen, 2),
                'margen_pct' => round($margenPct, 2),
                'quiebres_riesgo' => $stockRiesgo->count(),
            ],
            'top_productos' => $topProductos,
            'stock_riesgo' => $stockRiesgo,
        ]);
    }

    public function exportGerencialCsv(Request $request): StreamedResponse
    {
        $report = $this->gerencial($request)->getData(true);
        $filename = 'reporte-gerencial-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($report): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, ['SECCION', 'CLAVE', 'VALOR_1', 'VALOR_2', 'VALOR_3']);

            foreach (($report['kpis'] ?? []) as $key => $value) {
                fputcsv($output, ['kpi', $key, (string) $value, '', '']);
            }

            foreach (($report['top_productos'] ?? []) as $row) {
                fputcsv($output, [
                    'top_producto',
                    (string) ($row['nombre'] ?? 'Producto'),
                    (string) ($row['unidades'] ?? 0),
                    (string) ($row['total'] ?? 0),
                    (string) ($row['stock'] ?? 0),
                ]);
            }

            foreach (($report['stock_riesgo'] ?? []) as $row) {
                fputcsv($output, [
                    'stock_riesgo',
                    (string) ($row['nombre'] ?? 'Producto'),
                    (string) ($row['stock'] ?? 0),
                    (string) ($row['stock_minimo'] ?? 0),
                    (string) ($row['precio'] ?? 0),
                ]);
            }

            fclose($output);
        }, 200, $headers);
    }

    private function resolveFromDate(mixed $from, string $period): Carbon
    {
        if (is_string($from) && $from !== '') {
            return Carbon::parse($from);
        }

        return match ($period) {
            'quarter' => Carbon::now()->startOfQuarter(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };
    }

    private function resolveToDate(mixed $to, string $period): Carbon
    {
        if (is_string($to) && $to !== '') {
            return Carbon::parse($to);
        }

        return match ($period) {
            'quarter' => Carbon::now()->endOfQuarter(),
            'year' => Carbon::now()->endOfYear(),
            default => Carbon::now()->endOfMonth(),
        };
    }
}
