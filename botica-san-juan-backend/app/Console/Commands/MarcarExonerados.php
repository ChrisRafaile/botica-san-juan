<?php

namespace App\Console\Commands;

use App\Models\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Marca qué productos están exonerados del IGV.
 *
 *   php artisan igv:exonerados                      → informe de cómo está hoy
 *   php artisan igv:exonerados --csv=lista.csv      → marca desde un archivo
 *   php artisan igv:exonerados --csv=lista.csv --simular
 *   php artisan igv:exonerados --limpiar            → todo a gravado
 *
 * FORMATO DEL CSV
 * Una fila por producto, con cabecera. Se acepta identificar el producto por
 * `id`, por `codigo_digemid` o por `nombre` (coincidencia exacta). Columnas:
 *
 *   id,codigo_digemid,nombre,base_legal
 *   ,,"AMOXICILINA 500 mg",ley_27450
 *   1234,,,apendice_i
 *
 * Bases legales admitidas: apendice_i, ley_27450, ley_28553, otra.
 *
 * POR QUÉ UN COMANDO Y NO ADIVINARLO
 * No hay forma de deducir del nombre si un medicamento está exonerado. La
 * lista de oncológicos, VIH/SIDA y diabetes la aprueba el MINSA por Decreto
 * Supremo y se actualiza cada año. Inventar el dato sería peor que no tenerlo:
 * un producto mal marcado factura mal, y eso se arrastra a la declaración.
 */
class MarcarExonerados extends Command
{
    protected $signature = 'igv:exonerados
        {--csv= : Ruta del archivo con los productos exonerados}
        {--simular : Muestra lo que haría sin escribir nada}
        {--limpiar : Devuelve TODOS los productos a gravado}';

    protected $description = 'Informa y actualiza qué productos están exonerados del IGV';

    public function handle(): int
    {
        if ($this->option('limpiar')) {
            return $this->limpiar();
        }

        if ($ruta = $this->option('csv')) {
            return $this->importar($ruta);
        }

        return $this->informar();
    }

    /* ==================================================================== */

    private function informar(): int
    {
        $total      = Producto::count();
        $gravados   = Producto::gravados()->count();
        $exonerados = Producto::exonerados()->count();
        $inafectos  = Producto::where('tipo_afectacion_igv', Producto::INAFECTO)->count();

        $this->newLine();
        $this->info('Afectación al IGV del catálogo');
        $this->line(str_repeat('=', 58));
        $this->line(sprintf('   %-28s %6d  (%5.1f %%)', 'Gravados', $gravados, $this->pct($gravados, $total)));
        $this->line(sprintf('   %-28s %6d  (%5.1f %%)', 'Exonerados', $exonerados, $this->pct($exonerados, $total)));
        $this->line(sprintf('   %-28s %6d  (%5.1f %%)', 'Inafectos', $inafectos, $this->pct($inafectos, $total)));

        if ($exonerados > 0) {
            $this->newLine();
            $this->line('Por base legal:');

            $porBase = Producto::exonerados()
                ->selectRaw('base_legal_exoneracion, count(*) as n')
                ->groupBy('base_legal_exoneracion')
                ->pluck('n', 'base_legal_exoneracion');

            foreach ($porBase as $base => $n) {
                $texto = Producto::BASES_LEGALES[$base] ?? '(sin base legal anotada)';
                $this->line(sprintf('   %-28s %6d   %s', $base ?: '(vacía)', $n, $texto));
            }
        }

        $this->newLine();

        if ($exonerados === 0) {
            $this->warn('Ningún producto está marcado como exonerado.');
            $this->line('  El archivo del contador de agosto registra venta exonerada, así que');
            $this->line('  faltan productos por marcar. Mientras tanto el sistema les calcula IGV.');
            $this->newLine();
            $this->comment('Para cargarlos:  php artisan igv:exonerados --csv=ruta/lista.csv');
        }

        $this->line('Bases legales admitidas:');
        foreach (Producto::BASES_LEGALES as $clave => $texto) {
            $this->line("   {$clave}  ·  {$texto}");
        }

        return self::SUCCESS;
    }

    /* ==================================================================== */

    private function importar(string $ruta): int
    {
        if (! is_readable($ruta)) {
            $this->error("No se puede leer el archivo: {$ruta}");

            return self::FAILURE;
        }

        $simular = (bool) $this->option('simular');

        $filas = $this->leerCsv($ruta);

        if ($filas === []) {
            $this->error('El archivo no tiene filas de datos.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(($simular ? 'SIMULACIÓN · ' : '')."Marcando exonerados desde {$ruta}");
        $this->line(str_repeat('-', 58));

        $encontrados = 0;
        $noEncontrados = [];
        $ambiguos = [];

        DB::beginTransaction();

        try {
            foreach ($filas as $numero => $fila) {
                $base = trim((string) ($fila['base_legal'] ?? ''));

                if ($base === '') {
                    $base = 'otra';
                }

                if (! array_key_exists($base, Producto::BASES_LEGALES)) {
                    throw new RuntimeException(
                        "Fila {$numero}: base legal desconocida '{$base}'. "
                        .'Admitidas: '.implode(', ', array_keys(Producto::BASES_LEGALES))
                    );
                }

                $coincidencias = $this->buscar($fila);

                if ($coincidencias->isEmpty()) {
                    $noEncontrados[] = $numero.': '.$this->describir($fila);
                    continue;
                }

                if ($coincidencias->count() > 1) {
                    $ambiguos[] = $numero.': '.$this->describir($fila)
                        .' ('.$coincidencias->count().' coincidencias)';
                    continue;
                }

                $producto = $coincidencias->first();

                $producto->tipo_afectacion_igv   = Producto::EXONERADO;
                $producto->base_legal_exoneracion = $base;
                $producto->save();

                $encontrados++;
            }

            if ($simular) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (RuntimeException $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("   Marcados como exonerados: {$encontrados}");

        if ($noEncontrados !== []) {
            $this->newLine();
            $this->warn('   No se encontraron en el catálogo ('.count($noEncontrados).'):');
            foreach (array_slice($noEncontrados, 0, 15) as $texto) {
                $this->line("     {$texto}");
            }
            if (count($noEncontrados) > 15) {
                $this->line('     … y '.(count($noEncontrados) - 15).' más');
            }
        }

        if ($ambiguos !== []) {
            $this->newLine();
            $this->warn('   Ambiguos, se omitieron ('.count($ambiguos).'):');
            foreach (array_slice($ambiguos, 0, 15) as $texto) {
                $this->line("     {$texto}");
            }
            $this->line('     Identifícalos por id o por codigo_digemid para resolverlo.');
        }

        $this->newLine();

        if ($simular) {
            $this->comment('Simulación: no se escribió nada. Quita --simular para aplicarlo.');
        } else {
            $this->info('Aplicado.');
        }

        return self::SUCCESS;
    }

    /**
     * Busca el producto de la fila.
     *
     * El orden importa: el id es inequívoco, el código DIGEMID casi siempre, y
     * el nombre es el último recurso porque en un catálogo de 3361 productos
     * hay nombres repetidos con concentraciones distintas. Por eso una
     * coincidencia múltiple se omite en vez de elegir una al azar: marcar mal
     * un producto factura mal, y eso llega a la declaración.
     */
    private function buscar(array $fila)
    {
        if (! empty($fila['id'])) {
            return Producto::where('id', (int) $fila['id'])->get();
        }

        if (! empty($fila['codigo_digemid'])) {
            return Producto::where('codigo_digemid', trim((string) $fila['codigo_digemid']))->get();
        }

        if (! empty($fila['nombre'])) {
            return Producto::whereRaw('LOWER(TRIM(nombre)) = ?', [
                mb_strtolower(trim((string) $fila['nombre'])),
            ])->get();
        }

        return Producto::whereRaw('1 = 0')->get();
    }

    private function describir(array $fila): string
    {
        return trim(implode(' ', array_filter([
            $fila['id'] ?? null,
            $fila['codigo_digemid'] ?? null,
            $fila['nombre'] ?? null,
        ]))) ?: '(fila vacía)';
    }

    /** @return array<int, array<string, string>> */
    private function leerCsv(string $ruta): array
    {
        $manejador = fopen($ruta, 'r');
        $cabecera = null;
        $filas = [];
        $numero = 1;

        while (($datos = fgetcsv($manejador, 0, ',')) !== false) {
            $numero++;

            if ($cabecera === null) {
                /* El BOM de Excel se cuela en el primer nombre de columna y
                   deja 'id' como "\u{FEFF}id", que luego no coincide. */
                $datos[0] = preg_replace('/^\x{FEFF}/u', '', (string) $datos[0]);
                $cabecera = array_map(fn ($c) => strtolower(trim((string) $c)), $datos);
                continue;
            }

            if (count(array_filter($datos, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $fila = [];
            foreach ($cabecera as $i => $columna) {
                $fila[$columna] = isset($datos[$i]) ? trim((string) $datos[$i]) : '';
            }

            $filas[$numero] = $fila;
        }

        fclose($manejador);

        return $filas;
    }

    /* ==================================================================== */

    private function limpiar(): int
    {
        $exonerados = Producto::exonerados()->count();

        if ($exonerados === 0) {
            $this->info('No hay productos exonerados que limpiar.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Se devolverán {$exonerados} productos a gravado. ¿Continuar?", false)) {
            $this->line('Cancelado.');

            return self::SUCCESS;
        }

        Producto::exonerados()->update([
            'tipo_afectacion_igv'    => Producto::GRAVADO,
            'base_legal_exoneracion' => null,
            'afecto_igv'             => true,
        ]);

        $this->info("{$exonerados} productos devueltos a gravado.");

        return self::SUCCESS;
    }

    private function pct(int $valor, int $base): float
    {
        return $base > 0 ? round($valor * 100 / $base, 1) : 0.0;
    }
}
