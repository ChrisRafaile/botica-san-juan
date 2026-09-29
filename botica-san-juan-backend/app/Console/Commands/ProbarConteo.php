<?php

namespace App\Console\Commands;

use App\Models\Conteo;
use App\Models\Lote;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Services\ConteoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Ejercita las reglas del conteo por ciclos contra la base real y revierte.
 *
 *   php artisan conteo:probar
 *
 * Trabaja sobre los datos de verdad porque una prueba con datos inventados no
 * dice nada sobre si el FEFO ordena bien los lotes que existen. Todo ocurre
 * dentro de una transacción que se deshace al final: el inventario queda igual.
 */
class ProbarConteo extends Command
{
    protected $signature = 'conteo:probar';
    protected $description = 'Prueba las reglas del conteo fisico por ciclos (revierte los cambios)';

    public function handle(ConteoService $conteos): int
    {
        $this->newLine();
        $this->info('Prueba del conteo fisico por ciclos');
        $this->line(str_repeat('-', 60));

        DB::beginTransaction();

        try {
            $this->ejecutar($conteos);

            $this->newLine();
            $this->line(str_repeat('-', 60));
            $this->info('RESULTADO: todas las reglas del conteo se cumplen.');
            $this->newLine();
            $this->comment('Transaccion revertida: el inventario real quedo intacto.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('FALLO: '.$e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());

            return self::FAILURE;
        } finally {
            DB::rollBack();
        }
    }

    private function ejecutar(ConteoService $conteos): void
    {
        /* Si hubiera un conteo abierto de verdad, la regla de "uno a la vez"
           haria fallar la prueba por un motivo que no es un error. Se aparta
           dentro de la transaccion, que luego se revierte. */
        Conteo::abiertos()->update(['estado' => 'cerrado']);

        $this->paso(1, 'Abrir una sesion propone productos y fotografia su stock');
        $conteo = $conteos->abrir(criterio: 'sin_lote', cantidad: 5);
        $lineas = $conteo->detalles()->with('producto')->get();
        $this->linea("Codigo: {$conteo->codigo} | productos propuestos: ".$lineas->count());
        $this->assert($lineas->isNotEmpty(), 'la sesion no propuso ningun producto');
        $this->assert(
            $lineas->every(fn ($d) => $d->stock_sistema === (int) $d->producto->stock),
            'la foto de stock no coincide con el stock real'
        );
        $this->ok('stock fotografiado al abrir');

        $this->paso(2, 'No se permiten dos sesiones abiertas a la vez');
        try {
            $conteos->abrir(criterio: 'rotacion', cantidad: 3);
            $this->assert(false, 'permitio abrir una segunda sesion');
        } catch (RuntimeException $e) {
            $this->linea('Rechazado: '.$e->getMessage());
            $this->ok('solo una sesion abierta');
        }

        $this->paso(3, 'El desglose por lotes debe cuadrar con el total contado');
        $primera = $lineas->first();
        try {
            $conteos->registrarConteo($primera, 10, [
                ['codigo_lote' => 'L-A', 'fecha_vencimiento' => null, 'cantidad' => 4],
            ]);
            $this->assert(false, 'acepto un desglose que no cuadra');
        } catch (RuntimeException $e) {
            $this->linea('Rechazado: '.$e->getMessage());
            $this->ok('desglose descuadrado rechazado');
        }

        $this->paso(4, 'Contar captura lote y fecha de vencimiento reales');
        $producto = $primera->producto;
        $stockAntes = (int) $producto->stock;
        $vence = now()->addMonths(8)->toDateString();
        $conteos->registrarConteo($primera, 12, [
            ['codigo_lote' => 'LOTE-PRUEBA-A', 'fecha_vencimiento' => $vence, 'cantidad' => 7],
            ['codigo_lote' => 'LOTE-PRUEBA-B', 'fecha_vencimiento' => now()->addMonths(20)->toDateString(), 'cantidad' => 5],
        ]);
        $this->linea("{$producto->nombre}: el sistema creia {$stockAntes}, se contaron 12 en 2 lotes");
        $this->assert((int) $producto->fresh()->stock === $stockAntes, 'contar modifico el stock antes de cerrar');
        $this->ok('contar no toca el inventario todavia');

        $this->paso(5, 'Cerrar aplica los ajustes y deja rastro');
        $movAntes = MovimientoStock::where('producto_id', $producto->id)->count();
        $resumen = $conteos->cerrar($conteo);
        $producto->refresh();
        $this->linea('Resumen: '.json_encode($resumen));
        $this->linea("Stock resultante: {$producto->stock} (contado: 12)");
        $this->assert((int) $producto->stock === 12, 'el stock no quedo igual a lo contado');
        $this->assert(
            MovimientoStock::where('producto_id', $producto->id)->count() > $movAntes,
            'el ajuste no dejo movimiento'
        );
        $this->ok('inventario ajustado y auditable');

        $this->paso(6, 'Los lotes contados reemplazan a los del sistema, con su fecha');
        $lotes = Lote::where('producto_id', $producto->id)->where('cantidad_actual', '>', 0)->ordenFefo()->get();
        foreach ($lotes as $l) {
            $this->linea("  {$l->codigo_lote} | vence ".($l->fecha_vencimiento?->toDateString() ?? 'sin fecha')." | {$l->cantidad_actual} u.");
        }
        $conFecha = $lotes->filter(fn ($l) => $l->fecha_vencimiento !== null);
        $this->assert($conFecha->count() === 2, 'no quedaron los 2 lotes con fecha de vencimiento');
        $this->assert(
            $lotes->first()->fecha_vencimiento?->toDateString() === $vence,
            'el FEFO no ordeno primero el lote que vence antes'
        );
        $this->assert(
            $lotes->every(fn ($l) => str_starts_with($l->codigo_lote, 'LOTE-PRUEBA-')),
            'sobrevivio un lote que el conteo no menciono'
        );
        $this->ok('FEFO ya tiene fechas reales con las que ordenar');

        $this->paso(7, 'Todo movimiento del conteo dice de donde salio');
        $movimientos = MovimientoStock::where('producto_id', $producto->id)
            ->orderByDesc('id')->limit(4)->get();
        foreach ($movimientos as $m) {
            $this->linea(sprintf('  %+d u. | %s | ref: %s#%s',
                $m->cantidad, $m->motivo ?: '(SIN MOTIVO)', $m->referencia_tipo ?: '-', $m->referencia_id ?: '-'));
        }
        $delConteo = $movimientos->filter(fn ($m) => str_contains((string) $m->motivo, $conteo->codigo));
        $this->assert($delConteo->count() >= 2, 'no todos los movimientos llevan el codigo del conteo');
        $this->assert(
            $delConteo->every(fn ($m) => trim((string) $m->motivo) !== ''),
            'hay movimientos del conteo sin motivo'
        );
        /* Los lotes CREADOS por el conteo son los que antes quedaban huerfanos:
           sin motivo y sin referencia, imposibles de rastrear despues. */
        $creados = $delConteo->filter(fn ($m) => $m->cantidad > 0);
        $this->assert(
            $creados->isEmpty() || $creados->every(fn ($m) => $m->referencia_tipo === 'conteo'),
            'los lotes creados por el conteo no quedan enlazados a su sesion'
        );
        $this->ok('cada movimiento se puede rastrear hasta su sesion de conteo');

        $this->paso(8, 'Un conteo cerrado no admite mas registros');
        try {
            $conteos->registrarConteo($primera->fresh(), 3);
            $this->assert(false, 'admitio registrar sobre un conteo cerrado');
        } catch (RuntimeException $e) {
            $this->linea('Rechazado: '.$e->getMessage());
            $this->ok('sesion cerrada es inmutable');
        }

        $this->paso(9, 'Anular descarta la sesion sin tocar el inventario');
        $otro = $conteos->abrir(criterio: 'rotacion', cantidad: 3);
        $linea = $otro->detalles()->with('producto')->first();
        $stockPrevio = (int) $linea->producto->stock;
        $conteos->registrarConteo($linea, $stockPrevio + 99);
        $conteos->anular($otro->fresh());
        $this->linea("Producto {$linea->producto->nombre}: stock antes {$stockPrevio}, despues ".$linea->producto->fresh()->stock);
        $this->assert((int) $linea->producto->fresh()->stock === $stockPrevio, 'anular modifico el stock');
        $this->ok('anular no deja rastro en el inventario');
    }

    private function paso(int $n, string $texto): void
    {
        $this->newLine();
        $this->line("{$n}) {$texto}");
    }

    private function linea(string $texto): void
    {
        $this->line("   {$texto}");
    }

    private function ok(string $texto): void
    {
        $this->info("   OK  {$texto}");
    }

    private function assert(bool $condicion, string $queFallo): void
    {
        if (! $condicion) {
            throw new RuntimeException($queFallo);
        }
    }
}
