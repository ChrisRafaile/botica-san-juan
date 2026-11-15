<?php

namespace App\Console\Commands;

use App\Models\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Puebla y actualiza de forma idempotente la columna `imagen` de `productos`
 * a partir del manifiesto versionado y los archivos WebP presentes en disco.
 */
class PoblarImagenesProductos extends Command
{
    public const IMAGEN_RESPALDO_DEFECTO = 'images/default_image.png';

    /** @var array<int, int> */
    private const PRODUCTOS_EXCLUIDOS_DEFECTO = [191, 205, 547];

    protected $signature = 'catalogo:poblar-imagenes
        {--archivo= : Ruta alternativa al archivo JSON de correspondencias}
        {--dry-run : Muestra los cambios sin persistir en la base de datos}';

    protected $description = 'Asigna de forma idempotente las imágenes reales verificadas del catálogo en storage/app/public/productos';

    public function handle(): int
    {
        $rutaJson = $this->option('archivo') ?: database_path('data/correspondencia_imagenes.json');

        if (! is_file($rutaJson)) {
            $this->error("No se encontró el archivo de correspondencias: {$rutaJson}");

            return self::FAILURE;
        }

        $contenido = file_get_contents($rutaJson);
        $datos = is_string($contenido) ? json_decode($contenido, true) : null;

        if (! is_array($datos) || ! isset($datos['correspondencias']) || ! is_array($datos['correspondencias'])) {
            $this->error("Formato inválido en {$rutaJson}: se esperaba la clave 'correspondencias'.");

            return self::FAILURE;
        }

        $discoPublico = Storage::disk('public');
        $excluidos = $this->productosExcluidos();
        $mapaValido = [];
        $archivosFaltantes = 0;

        foreach ($datos['correspondencias'] as $item) {
            if (! is_array($item) || ! isset($item['producto_id'], $item['imagen'])) {
                continue;
            }

            $productoId = (int) $item['producto_id'];
            if (in_array($productoId, $excluidos, true)) {
                continue;
            }

            $rutaImagen = trim((string) $item['imagen'], " \t\n\r\0\x0B/");
            if ($productoId <= 0 || $rutaImagen === '') {
                continue;
            }

            // En disco `public` la raíz es storage/app/public; en BD se guarda `storage/...`
            $rutaEnDisco = str_starts_with($rutaImagen, 'storage/')
                ? substr($rutaImagen, strlen('storage/'))
                : $rutaImagen;

            if (! $discoPublico->exists($rutaEnDisco)) {
                $archivosFaltantes++;
                continue;
            }

            $mapaValido[$productoId] = 'storage/' . $rutaEnDisco;
        }

        $dryRun = (bool) $this->option('dry-run');

        $productos = Producto::query()
            ->select(['id', 'imagen'])
            ->orderBy('id')
            ->get();

        $actualizados = 0;
        $sinCambios = 0;
        $restaurados = 0;
        $sinCoincidencia = 0;

        $ejecutar = function () use (
            $productos,
            $mapaValido,
            $dryRun,
            &$actualizados,
            &$sinCambios,
            &$restaurados,
            &$sinCoincidencia
        ): void {
            foreach ($productos as $producto) {
                $objetivo = $mapaValido[$producto->id] ?? null;

                if ($objetivo !== null) {
                    if ($producto->imagen === $objetivo) {
                        $sinCambios++;
                        continue;
                    }
                    if (! $dryRun) {
                        Producto::query()
                            ->whereKey($producto->id)
                            ->update(['imagen' => $objetivo]);
                    }
                    $actualizados++;
                    continue;
                }

                $sinCoincidencia++;
                if ($producto->imagen !== self::IMAGEN_RESPALDO_DEFECTO) {
                    if (! $dryRun) {
                        Producto::query()
                            ->whereKey($producto->id)
                            ->update(['imagen' => self::IMAGEN_RESPALDO_DEFECTO]);
                    }
                    $restaurados++;
                }
            }
        };

        if ($dryRun) {
            $ejecutar();
        } else {
            DB::transaction($ejecutar);
        }

        $prefijo = $dryRun ? '[Simulación] ' : '';
        $this->info(
            "{$prefijo}Catálogo analizado: {$productos->count()} productos | " .
            "Actualizados: {$actualizados} | Sin cambios: {$sinCambios} | " .
            "Sin coincidencia (respaldo): {$sinCoincidencia} | Archivos faltantes en disco: {$archivosFaltantes} | " .
            "Restaurados a respaldo: {$restaurados}"
        );

        return self::SUCCESS;
    }

    /**
     * Combina las exclusiones fijas de auditoría con la lista versionada en scripts/exclusiones_imagenes.json.
     *
     * @return array<int, int>
     */
    private function productosExcluidos(): array
    {
        $ids = self::PRODUCTOS_EXCLUIDOS_DEFECTO;
        $rutaExclusiones = dirname(base_path()) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'exclusiones_imagenes.json';

        if (! is_file($rutaExclusiones)) {
            return $ids;
        }

        $contenido = file_get_contents($rutaExclusiones);
        $datos = is_string($contenido) ? json_decode($contenido, true) : null;
        if (! is_array($datos) || ! isset($datos['exclusiones']) || ! is_array($datos['exclusiones'])) {
            return $ids;
        }

        foreach ($datos['exclusiones'] as $item) {
            if (is_array($item) && isset($item['producto_id'])) {
                $ids[] = (int) $item['producto_id'];
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }
}
