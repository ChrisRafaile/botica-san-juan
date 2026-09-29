<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comprueba la coherencia del inventario entre productos, lotes y movimientos.
 *
 * Pensado para ejecutarse después de migrar y, sobre todo, de forma periódica
 * en producción: `productos.stock` es una copia denormalizada de la suma de
 * lotes, y una copia puede desincronizarse. Este comando detecta esa deriva
 * antes de que alguien la descubra vendiendo lo que no hay.
 *
 *   php artisan inventario:verificar
 *   php artisan inventario:verificar --corregir
 */
class VerificarInventario extends Command
{
    protected $signature = 'inventario:verificar
                            {--corregir : Recalcula productos.stock desde los lotes}';

    protected $description = 'Verifica la coherencia entre productos, lotes y movimientos de stock';

    public function handle(): int
    {
        $this->newLine();
        $this->info('=== ESTRUCTURA ===');

        $tablas = ['lotes', 'movimientos_stock', 'pedido_detalle_lotes', 'incidencias_venta'];
        $faltantes = [];
        foreach ($tablas as $t) {
            $existe = Schema::hasTable($t);
            $this->line(sprintf('  %-24s %s', $t, $existe ? 'OK' : 'FALTA'));
            if (! $existe) {
                $faltantes[] = $t;
            }
        }

        $columnas = [
            'productos' => ['afecto_igv'],
            'pedidos'   => ['origen', 'vendedor_id', 'cliente_nombre', 'subtotal_gravado',
                            'subtotal_exonerado', 'igv', 'tasa_igv'],
        ];
        foreach ($columnas as $tabla => $lista) {
            foreach ($lista as $col) {
                $existe = Schema::hasColumn($tabla, $col);
                $this->line(sprintf('  %-24s %s', "$tabla.$col", $existe ? 'OK' : 'FALTA'));
                if (! $existe) {
                    $faltantes[] = "$tabla.$col";
                }
            }
        }

        if ($faltantes !== []) {
            $this->newLine();
            $this->error('Faltan elementos del esquema. ¿Se ejecutaron las migraciones?');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== CUADRE DE INVENTARIO ===');

        $productosConStock = DB::table('productos')->where('stock', '>', 0)->count();
        $lotesActivos      = DB::table('lotes')->count();
        $sumaStock         = (int) DB::table('productos')->sum('stock');
        $sumaLotes         = (int) DB::table('lotes')->sum('cantidad_actual');

        $this->line('  Productos con stock  : ' . number_format($productosConStock));
        $this->line('  Lotes registrados    : ' . number_format($lotesActivos));
        $this->line('  Suma productos.stock : ' . number_format($sumaStock));
        $this->line('  Suma lotes.cantidad  : ' . number_format($sumaLotes));

        /* Productos donde la copia denormalizada no coincide con sus lotes.
           Es la consulta que de verdad importa: la diferencia por producto,
           no el total, porque dos errores de signo contrario se compensarían
           en la suma global y pasarían desapercibidos. */
        $discrepancias = DB::select("
            SELECT p.id, p.nombre, p.stock AS stock_producto,
                   COALESCE(SUM(l.cantidad_actual), 0) AS stock_lotes
            FROM productos p
            LEFT JOIN lotes l ON l.producto_id = p.id AND l.estado <> 'retirado'
            GROUP BY p.id, p.nombre, p.stock
            HAVING p.stock <> COALESCE(SUM(l.cantidad_actual), 0)
            ORDER BY ABS(p.stock - COALESCE(SUM(l.cantidad_actual), 0)) DESC
            LIMIT 20
        ");

        $this->newLine();
        if ($discrepancias === []) {
            $this->info('  Sin discrepancias: cada producto cuadra con sus lotes.');

            return self::SUCCESS;
        }

        $this->warn('  ' . count($discrepancias) . ' producto(s) con diferencias (máximo 20 mostrados):');
        $this->table(
            ['ID', 'Producto', 'stock', 'lotes', 'dif.'],
            array_map(fn ($d) => [
                $d->id,
                mb_strimwidth($d->nombre, 0, 38, '…'),
                $d->stock_producto,
                $d->stock_lotes,
                $d->stock_producto - $d->stock_lotes,
            ], $discrepancias)
        );

        if (! $this->option('corregir')) {
            $this->newLine();
            $this->line('  Ejecuta con --corregir para recalcular productos.stock desde los lotes.');

            return self::FAILURE;
        }

        /* Los lotes mandan: son la fuente de verdad. */
        $this->newLine();
        $actualizados = DB::update("
            UPDATE productos p
            SET stock = COALESCE(sub.total, 0)
            FROM (
                SELECT pr.id AS producto_id, SUM(l.cantidad_actual) AS total
                FROM productos pr
                LEFT JOIN lotes l ON l.producto_id = pr.id AND l.estado <> 'retirado'
                GROUP BY pr.id
            ) sub
            WHERE p.id = sub.producto_id
              AND p.stock <> COALESCE(sub.total, 0)
        ");

        $this->info("  Corregidos {$actualizados} producto(s) desde sus lotes.");

        return self::SUCCESS;
    }
}
