<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cuántos productos hay en cada estado de stock.
 * ---------------------------------------------------------------------------
 * POR QUÉ ESTO VIVE EN UN SERVICIO
 * La misma pregunta la hacen el tablero y la pantalla de productos. Tenerla
 * escrita dos veces garantiza que un día una diga 1706 críticos y la otra 8, y
 * que nadie sepa cuál creer.
 *
 * TRES DECISIONES QUE PARECEN DETALLE Y NO LO SON
 *
 * 1. El stock vendible sale de los LOTES, no de `productos.stock`. Esa columna
 *    incluye unidades vencidas: contar como disponible lo que no se puede
 *    vender lleva a no reponer algo que en realidad falta.
 *
 * 2. El umbral es el de cada producto (`stock_minimo`), no una cifra fija. Diez
 *    unidades son muchas para un antibiótico caro y pocas para el paracetamol.
 *
 * 3. Se cuenta sobre TODO el catálogo, en SQL. Las pantallas lo calculaban
 *    sobre la página que tenían cargada, de modo que el mismo catálogo mostraba
 *    "2 en stock" en una vista y otra cifra en la siguiente página.
 */
class ResumenInventarioService
{
    /**
     * @return array{agotados:int, criticos:int, bajos:int, normales:int, total:int}
     */
    public function porEstadoDeStock(): array
    {
        $vendible = DB::table('lotes')
            ->selectRaw('producto_id, COALESCE(SUM(cantidad_actual), 0) AS disponible')
            ->where('cantidad_actual', '>', 0)
            ->where('estado', 'activo')
            ->where(function ($q) {
                $q->whereNull('fecha_vencimiento')
                  ->orWhere('fecha_vencimiento', '>', Carbon::today());
            })
            ->groupBy('producto_id');

        $fila = DB::query()
            ->fromSub(
                Producto::query()
                    ->leftJoinSub($vendible, 'v', 'v.producto_id', '=', 'productos.id')
                    ->selectRaw('
                        COALESCE(v.disponible, 0) AS disponible,
                        COALESCE(productos.stock_minimo, 5) AS minimo,
                        COALESCE(productos.stock_reposicion, 10) AS reposicion
                    '),
                'p'
            )
            ->selectRaw('
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE disponible <= 0) AS agotados,
                COUNT(*) FILTER (WHERE disponible > 0 AND disponible <= minimo) AS criticos,
                COUNT(*) FILTER (WHERE disponible > minimo AND disponible <= reposicion) AS bajos,
                COUNT(*) FILTER (WHERE disponible > reposicion) AS normales
            ')
            ->first();

        return [
            'total'     => (int) $fila->total,
            'agotados'  => (int) $fila->agotados,
            'criticos'  => (int) $fila->criticos,
            'bajos'     => (int) $fila->bajos,
            'normales'  => (int) $fila->normales,
        ];
    }

    /**
     * Si casi todo el catálogo cae en alerta, el umbral está mal puesto.
     *
     * Vale la pena decirlo donde se muestren estas cifras: una alerta que salta
     * siempre deja de ser una alerta, y alguien podría comprar de más creyendo
     * que le falta medio inventario.
     */
    public function umbralesSonSospechosos(array $resumen): bool
    {
        if ($resumen['total'] === 0) {
            return false;
        }

        return ($resumen['criticos'] + $resumen['bajos']) / $resumen['total'] > 0.6;
    }
}
