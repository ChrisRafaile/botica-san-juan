<?php

namespace App\Console\Commands;

use App\Models\PrincipioActivo;
use App\Models\Producto;
use App\Services\Clinico\NormalizadorPrincipioActivo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa el padrón oficial y completa el perfil clínico del catálogo.
 *
 *     php artisan clinico:importar-padron padron.csv
 *     php artisan clinico:importar-padron padron.csv --simular
 *
 * POR QUE ESTE COMANDO EXISTE ANTES QUE EL ARCHIVO
 *
 * Catalogar el padrón es responsabilidad del químico farmacéutico y del
 * administrador, no del sistema. Lo que sí es responsabilidad del sistema es
 * estar listo para recibirlo: cuando llegue el archivo, cargarlo tiene que
 * ser un comando, no un proyecto.
 *
 * FORMATO ESPERADO (CSV con cabecera)
 *
 *   codigo_digemid,nombre_producto,principio_activo,laboratorio,requiere_receta
 *
 * El emparejamiento con el catálogo propio se hace por `codigo_digemid` si el
 * producto lo tiene, y si no por nombre exacto normalizado. NO se empareja
 * por parecido: en un catálogo de medicamentos, unir dos fichas que no son la
 * misma es peor que dejar una sin emparejar.
 *
 * `--simular` recorre el archivo y dice qué haría sin escribir nada. Conviene
 * usarlo siempre la primera vez con un padrón nuevo.
 */
class ImportarPadronDigemid extends Command
{
    protected $signature = 'clinico:importar-padron
        {archivo : Ruta del CSV del padrón}
        {--simular : Informa de los cambios sin aplicarlos}';

    protected $description = 'Carga el padrón oficial y completa el principio activo de los productos.';

    public function handle(): int
    {
        $ruta = (string) $this->argument('archivo');

        if (! is_readable($ruta)) {
            $this->error("No se puede leer el archivo: {$ruta}");

            return self::FAILURE;
        }

        $simular = (bool) $this->option('simular');

        if ($simular) {
            $this->warn('Modo simulación: no se escribirá nada.');
        }

        $manejador = fopen($ruta, 'rb');
        $cabecera = fgetcsv($manejador);

        if ($cabecera === false) {
            $this->error('El archivo está vacío.');
            fclose($manejador);

            return self::FAILURE;
        }

        $columnas = array_flip(array_map(
            static fn ($c) => strtolower(trim((string) $c)),
            $cabecera,
        ));

        foreach (['nombre_producto', 'principio_activo'] as $obligatoria) {
            if (! isset($columnas[$obligatoria])) {
                $this->error("Falta la columna obligatoria «{$obligatoria}».");
                fclose($manejador);

                return self::FAILURE;
            }
        }

        $filas = 0;
        $principiosNuevos = 0;
        $emparejados = 0;
        $sinEmparejar = [];

        /* Todo o nada: si el archivo viene a medias, es mejor no dejar el
           catálogo con la mitad del padrón aplicada. */
        DB::beginTransaction();

        try {
            while (($fila = fgetcsv($manejador)) !== false) {
                $filas++;

                $nombre = trim((string) ($fila[$columnas['nombre_producto']] ?? ''));
                $principio = trim((string) ($fila[$columnas['principio_activo']] ?? ''));

                if ($nombre === '' || $principio === '') {
                    continue;
                }

                $canonico = NormalizadorPrincipioActivo::normalizar($principio);

                $registro = PrincipioActivo::firstOrNew(['nombre' => $canonico]);

                if (! $registro->exists) {
                    $principiosNuevos++;
                    $registro->sinonimos = [];
                    $registro->save();
                }

                $codigo = isset($columnas['codigo_digemid'])
                    ? trim((string) ($fila[$columnas['codigo_digemid']] ?? ''))
                    : '';

                $consulta = Producto::query();

                if ($codigo !== '') {
                    $consulta->where('codigo_digemid', $codigo);
                } else {
                    $consulta->whereRaw('upper(unaccent(nombre)) = ?', [$canonico]);
                }

                $producto = $consulta->first();

                if ($producto === null) {
                    $sinEmparejar[] = $nombre;
                    continue;
                }

                $emparejados++;

                if (! $simular) {
                    $producto->forceFill([
                        'principio_activo' => $principio,
                        'principio_activo_id' => $registro->id,
                    ])->save();
                }
            }

            $simular ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($manejador);
            $this->error('Se deshizo todo por un fallo: '.$e->getMessage());

            return self::FAILURE;
        }

        fclose($manejador);

        $this->newLine();
        $this->line("  Filas leídas          : {$filas}");
        $this->line("  Principios nuevos     : {$principiosNuevos}");
        $this->line("  Productos emparejados : {$emparejados}");
        $this->line('  Sin emparejar         : '.count($sinEmparejar));

        if ($sinEmparejar !== []) {
            $this->newLine();
            $this->warn('Primeros sin emparejar (el padrón los trae y el catálogo no):');
            foreach (array_slice($sinEmparejar, 0, 10) as $nombre) {
                $this->line("    · {$nombre}");
            }
        }

        return self::SUCCESS;
    }
}
