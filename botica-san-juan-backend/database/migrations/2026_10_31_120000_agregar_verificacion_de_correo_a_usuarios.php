<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verificacion de correo en la tabla de usuarios.
 * ---------------------------------------------------------------------------
 * POR QUE LAS CUENTAS EXISTENTES ENTRAN YA VERIFICADAS
 *
 * La columna nace nula, que es lo correcto para una cuenta nueva. Pero las
 * cuentas que YA existen no se registraron bajo esta regla: nadie les pidio
 * confirmar nada. Dejarlas en nulo seria cambiarles las condiciones a
 * posteriori y, el dia que la verificacion se vuelva obligatoria para comprar,
 * dejaria fuera a clientes que llevan meses comprando.
 *
 * Se les pone la fecha de su propio registro: es la fecha en la que la botica
 * acepto esa cuenta. No se inventa "ahora", que afirmaria que se verificaron
 * hoy, lo cual no ocurrio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        DB::table('usuarios')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
