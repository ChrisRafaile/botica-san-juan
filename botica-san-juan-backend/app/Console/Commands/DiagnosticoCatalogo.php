<?php

namespace App\Console\Commands;

use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Qué tan listo está el catálogo para operar de verdad.
 *
 *   php artisan catalogo:diagnostico
 *
 * El sistema puede estar impecable y aun así no servir en el mostrador si a los
 * productos les faltan los datos que el mostrador necesita. Este comando mide
 * exactamente eso, en lugar de suponerlo: cuántos productos pueden venderse por
 * blíster o caja, cuántos tienen fecha de vencimiento real, cuántos están
 * marcados como exonerados de IGV.
 *
 * Es de sólo lectura. No cambia nada.
 */
class DiagnosticoCatalogo extends Command
{
    protected $signature = 'catalogo:diagnostico';
    protected $description = 'Informa qué datos le faltan al catálogo para operar en mostrador';

    public function handle(): int
    {
        $total = Producto::count();

        if ($total === 0) {
            $this->error('El catálogo está vacío.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Diagnóstico del catálogo · {$total} productos");
        $this->line(str_repeat('=', 62));

        /* ---- Formas de venta ------------------------------------------- */
        $this->newLine();
        $this->line('FORMAS DE VENTA');

        $fraccionados = Producto::where('venta_fraccionada', true)->count();
        $conBlister   = Producto::whereNotNull('unidades_por_blister')->where('unidades_por_blister', '>', 1)->count();
        $conCaja      = Producto::whereNotNull('blisters_por_caja')->where('blisters_por_caja', '>', 1)->count();
        $precioBlist  = Producto::whereNotNull('precio_blister')->where('precio_blister', '>', 0)->count();
        $precioCaja   = Producto::whereNotNull('precio_caja')->where('precio_caja', '>', 0)->count();

        $this->fila('Marcados como venta fraccionada', $fraccionados, $total);
        $this->fila('Con unidades por blíster', $conBlister, $total);
        $this->fila('Con blísteres por caja', $conCaja, $total);
        $this->fila('Con precio propio de blíster', $precioBlist, $total);
        $this->fila('Con precio propio de caja', $precioCaja, $total);

        /* Vendible por blíster de verdad = tiene el factor Y el precio. */
        $listoBlister = Producto::where('unidades_por_blister', '>', 1)
            ->where('precio_blister', '>', 0)->count();
        $listoCaja = Producto::where('blisters_por_caja', '>', 1)
            ->where('precio_caja', '>', 0)->count();

        $this->newLine();
        $this->fila('LISTOS para vender por blíster', $listoBlister, $total, true);
        $this->fila('LISTOS para vender por caja', $listoCaja, $total, true);

        /* ---- Lotes y vencimientos -------------------------------------- */
        $this->newLine();
        $this->line('LOTES Y VENCIMIENTOS');

        $lotesTotal   = Lote::count();
        $lotesConFec  = Lote::whereNotNull('fecha_vencimiento')->count();
        $prodConFecha = Producto::whereHas('lotes', fn ($q) => $q->whereNotNull('fecha_vencimiento'))->count();
        $prodConStock = Producto::where('stock', '>', 0)->count();

        $this->fila('Lotes con fecha de vencimiento', $lotesConFec, $lotesTotal);
        $this->fila('Productos con algún lote fechado', $prodConFecha, $total);
        $this->line(sprintf('   %-38s %s', 'Productos con stock', $prodConStock));

        /* ---- IGV -------------------------------------------------------- */
        $this->newLine();
        $this->line('IGV');

        $exonerados = Producto::exonerados()->count();
        $inafectos  = Producto::where('tipo_afectacion_igv', Producto::INAFECTO)->count();
        $this->fila('Exonerados de IGV', $exonerados, $total);
        $this->fila('Inafectos', $inafectos, $total);

        /* ---- Precios ---------------------------------------------------- */
        $this->newLine();
        $this->line('PRECIOS');

        $sinPrecio = Producto::where(fn ($q) => $q->whereNull('precio')->orWhere('precio', '<=', 0))->count();
        $this->fila('SIN precio unitario', $sinPrecio, $total, true);

        /* ---- Conclusión -------------------------------------------------- */
        $this->newLine();
        $this->line(str_repeat('-', 62));

        $pendientes = [];

        if ($listoBlister === 0) {
            $pendientes[] = 'Ningún producto puede venderse por blíster: falta cargar unidades_por_blister y precio_blister.';
        }

        if ($listoCaja === 0) {
            $pendientes[] = 'Ningún producto puede venderse por caja: falta cargar blisters_por_caja y precio_caja.';
        }

        if ($lotesConFec === 0) {
            $pendientes[] = 'Ningún lote tiene fecha de vencimiento: el FEFO no tiene con qué ordenar. El conteo por ciclos las va capturando.';
        }

        if ($exonerados === 0) {
            $pendientes[] = 'Ningún producto está marcado como exonerado de IGV, pero en el archivo del '
                .'contador sí hay venta exonerada. Mientras tanto se les calcula impuesto. '
                .'Cárgalos con: php artisan igv:exonerados --csv=lista.csv';
        }

        if ($pendientes === []) {
            $this->info('El catálogo tiene todo lo que el mostrador necesita.');

            return self::SUCCESS;
        }

        $this->comment('Pendiente de cargar:');
        foreach ($pendientes as $i => $texto) {
            $this->line('  '.($i + 1).'. '.$texto);
        }

        return self::SUCCESS;
    }

    private function fila(string $etiqueta, int $valor, int $base, bool $destacar = false): void
    {
        $pct = $base > 0 ? round($valor * 100 / $base, 1) : 0.0;
        $texto = sprintf('   %-38s %6d  (%5.1f %%)', $etiqueta, $valor, $pct);

        if ($destacar && $valor === 0) {
            $this->error($texto);

            return;
        }

        $this->line($texto);
    }
}
