<?php

use App\Models\Producto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Fusiona los duplicados del catálogo ANTES de que se cree el índice UNIQUE.
 *
 * EL BLOQUEO CIRCULAR QUE RESUELVE
 *
 * La migración `2026_10_05_120000_add_identidad_unique_to_productos_table` no
 * puede aplicarse sobre una tabla que ya contiene duplicados: PostgreSQL rechaza
 * crear un índice único si los datos lo violan. En el entorno local eso no se
 * notó porque la fusión se había ejecutado a mano primero, con el comando
 * `catalogo:fusionar-duplicados --aplicar`.
 *
 * En el entorno desplegado no hay nadie que ejecute ese comando a mano. El
 * contenedor arranca, corre `php artisan migrate --force`, la creación del
 * índice falla, y como el entrypoint lleva `set -e` el contenedor muere. Render
 * mantiene vivo el anterior, así que el servicio sigue respondiendo y **el fallo
 * no se ve desde fuera**: lo único que ocurre es que el código nuevo nunca llega.
 *
 * Y no se podía arreglar desde la consola del servidor, porque el comando de
 * fusión viaja en el mismo commit que la migración que falla: el contenedor en
 * ejecución es anterior y no lo tiene.
 *
 * POR QUÉ ESTA MIGRACIÓN LLEVA UNA MARCA DE TIEMPO ANTERIOR
 *
 * `110000` contra `120000`. Laravel ejecuta las migraciones pendientes en orden
 * de nombre de archivo, de modo que esta corre primero aunque se haya escrito
 * después. No es un truco: es la forma de declarar que la limpieza de datos es
 * un requisito previo del cambio de esquema, y que ese orden no depende de que
 * nadie lo recuerde.
 *
 * POR QUÉ LLAMA AL COMANDO EN LUGAR DE REPETIR SU LÓGICA
 *
 * La regla de fusión no es trivial —el superviviente se elige por actividad y
 * no por identificador, y los stocks NO se suman— y está documentada, probada y
 * revisada en `FusionarDuplicadosCatalogo`. Copiarla aquí crearía dos versiones
 * que se separarían con el tiempo; es el mismo error que el cálculo del IGV
 * duplicado entre el portal y el mostrador, que ya costó extraer a un servicio.
 *
 * ES DESTRUCTIVA, Y NO SE DISIMULA
 *
 * Elimina filas de productos. En el entorno local pasó de 3 361 a 884,
 * descartando 12 562 unidades de stock fantasma —la misma mercancía
 * reimportada cuatro veces, no cuatro entregas distintas—. Antes de aplicarla en
 * producción hay que tener un punto de restauración: en Neon, una rama desde el
 * instante anterior. El procedimiento completo está en
 * `docs/RUNBOOK-fusion-duplicados.md`.
 *
 * Si no hay duplicados no hace nada, así que volver a ejecutarla es inofensivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $grupos = $this->gruposDuplicados();

        if ($grupos === 0) {
            /* Caso del entorno local, donde la fusión ya se hizo a mano, y de
               cualquier entorno nuevo creado desde cero. */
            echo "  Sin duplicados en el catalogo: no hay nada que fusionar.\n";

            return;
        }

        $antes = Producto::count();

        echo "  Se detectaron {$grupos} grupos de productos duplicados sobre {$antes} filas.\n";
        echo "  Fusionando antes de crear el indice unico de identidad...\n";

        Artisan::call('catalogo:fusionar-duplicados', ['--aplicar' => true]);

        $despues  = Producto::count();
        $restantes = $this->gruposDuplicados();

        echo "  Catalogo: {$antes} -> {$despues} productos.\n";

        /* Si quedan grupos, el comando los dejó fuera por ambiguos —más de una
           fila con actividad— y el índice volvería a fallar. Más vale detenerse
           aquí, con el motivo escrito, que dejar que reviente dos líneas después
           con un error de unicidad que no explica nada. */
        if ($restantes > 0) {
            throw new RuntimeException(
                "Quedan {$restantes} grupos duplicados que la fusion no pudo resolver sola "
                ."(mas de una fila con historial). Hay que decidirlos a mano antes de crear "
                ."el indice unico. Ver docs/RUNBOOK-fusion-duplicados.md."
            );
        }
    }

    /**
     * Esta migración no se revierte.
     *
     * `down()` tendría que resucitar filas eliminadas, y esa información ya no
     * existe en la base. La vuelta atrás de una fusión no es una migración: es
     * una restauración del respaldo, o una rama de Neon desde el punto anterior.
     * Decirlo aquí es más honesto que dejar un método vacío que sugiera que
     * `migrate:rollback` deshace esto.
     */
    public function down(): void
    {
        echo "  Esta migracion no se revierte: restaura el respaldo previo a la fusion.\n";
    }

    /** Cuántos grupos de productos comparten la misma identidad. */
    private function gruposDuplicados(): int
    {
        return DB::table(DB::raw(
            '(SELECT 1 FROM productos
              GROUP BY nombre, concentracion, presentacion, laboratorio, tipo, adicional, codigo_digemid
              HAVING COUNT(*) > 1) AS duplicados'
        ))->count();
    }
};
