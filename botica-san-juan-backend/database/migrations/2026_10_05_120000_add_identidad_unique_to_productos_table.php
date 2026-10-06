<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Impide que el catálogo vuelva a tener dos filas para la misma mercancía.
 *
 * POR QUÉ UN ÍNDICE Y NO UNA VALIDACIÓN EN EL CÓDIGO
 * Los 1 071 grupos duplicados no los creó un formulario: los creó una
 * importación corrida varias veces, que escribe directo contra la tabla. Una
 * regla en el modelo no la habría frenado. La base es el único sitio donde la
 * restricción se cumple siempre, venga la fila de donde venga.
 *
 * POR QUÉ ESTA MIGRACIÓN PUEDE FALLAR, Y ESTÁ BIEN QUE FALLE
 * Si todavía quedan duplicados, el índice no se puede crear y la migración
 * aborta. Ese fallo es la señal de que falta correr antes
 * `php artisan catalogo:fusionar-duplicados --aplicar`. Preferimos avisar a
 * destiempo que dejar entrar duplicados en silencio.
 *
 * POR QUÉ `NULLS NOT DISTINCT`
 * En Postgres, por omisión, dos NULL se consideran distintos y un índice
 * UNIQUE normal dejaría pasar infinitas filas iguales con `adicional` o
 * `concentracion` vacíos, que es buena parte del catálogo. `NULLS NOT
 * DISTINCT` (Postgres 15+) trata los vacíos como un valor más, que es lo que
 * el negocio entiende: dos filas sin concentración y con todo lo demás igual
 * son el mismo producto.
 *
 * `codigo_digemid` NO entra en el índice, aunque sí en la agrupación del
 * comando de fusión: es un dato de referencia que se completa con el tiempo y
 * dos filas que solo difieren en ese código siguen siendo la misma mercancía.
 */
return new class extends Migration
{
    private const NOMBRE = 'productos_identidad_unique';

    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX '.self::NOMBRE.' ON productos '
            .'(nombre, concentracion, presentacion, laboratorio, tipo, adicional) '
            .'NULLS NOT DISTINCT'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::NOMBRE);
    }
};
