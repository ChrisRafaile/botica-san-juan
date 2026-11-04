<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca si la contraseña de una cuenta la eligió una persona.
 * ---------------------------------------------------------------------------
 * EL PROBLEMA QUE RESUELVE
 *
 * La columna `password` NUNCA es nula, ni siquiera en las cuentas creadas con
 * Google: `GoogleController` les pone un hash aleatorio de 64 caracteres
 * porque la columna es obligatoria y el acceso de esas cuentas es Google.
 *
 * Eso significa que `password !== null` no responde la pregunta que hace falta
 * para desvincular Google sin dejar a nadie fuera de su cuenta:
 *
 *     ¿Hay alguna contraseña que esta persona CONOZCA?
 *
 * Sin una marca aparte, desvincular Google de una cuenta nacida con Google la
 * deja sin ninguna vía de acceso: el botón de Google ya no la reconoce y la
 * contraseña es un valor aleatorio que nadie ha visto nunca.
 *
 * POR QUÉ UNA FECHA Y NO UN BOOLEANO
 *
 * Mismo criterio que `google_vinculado_en`: una fecha responde «sí» y además
 * «cuándo», que es lo que se quiere saber al revisar un incidente de acceso.
 * Un booleano habría costado lo mismo y dicho menos.
 *
 * SOBRE EL RELLENO DE LAS FILAS QUE YA EXISTEN
 *
 * El criterio es deliberadamente CONSERVADOR, y conviene decir qué deja fuera:
 *
 *   - `google_id IS NULL` → la cuenta se registró por el formulario, así que
 *     su dueño eligió la contraseña. Se rellena con `created_at`, que es
 *     cuando ocurrió, y no con «ahora», que afirmaría algo falso.
 *
 *   - `google_id IS NOT NULL` → queda en nulo, aunque algunas de esas cuentas
 *     SÍ tengan contraseña propia (las que existían antes y luego vincularon
 *     Google). No hay forma fiable de distinguirlas de las nacidas con Google:
 *     en las dos, `google_vinculado_en` y `created_at` pueden estar a segundos
 *     de distancia.
 *
 * El coste de equivocarse por este lado es que a alguien se le pida recuperar
 * su contraseña por correo antes de desvincular Google. El coste de
 * equivocarse por el otro lado es dejar una cuenta sin acceso. La asimetría
 * decide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $tabla) {
            $tabla->timestamp('contrasena_establecida_en')
                ->nullable()
                ->after('password');
        });

        DB::table('usuarios')
            ->whereNull('google_id')
            ->update(['contrasena_establecida_en' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $tabla) {
            $tabla->dropColumn('contrasena_establecida_en');
        });
    }
};
