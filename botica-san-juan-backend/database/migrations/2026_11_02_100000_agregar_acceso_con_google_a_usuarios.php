<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acceso con Google.
 * ---------------------------------------------------------------------------
 * POR QUÉ EL DNI PASA A SER ANULABLE, Y QUÉ CUESTA
 *
 * Este sistema identifica a las personas por DNI: el acceso se hace con DNI y
 * contraseña, y el documento acaba en el comprobante que se envía a la SUNAT.
 * Google, en cambio, acredita un correo. No sabe el DNI de nadie y no hay
 * manera de deducirlo.
 *
 * Quedaban tres salidas y dos son malas:
 *
 *   1. Inventar un DNI de relleno. Descartada: ese número viajaría a un
 *      comprobante tributario. Un documento falso en una boleta no es un
 *      detalle de interfaz.
 *   2. No dejar que Google cree cuentas, solo que entre a las que ya existen.
 *      Descartada: convierte el botón en algo que falla la primera vez, que
 *      es justo cuando se usa.
 *   3. Permitir la cuenta sin DNI y pedirlo cuando de verdad hace falta: al
 *      emitir el comprobante. Es la que se tomó.
 *
 * El coste es real y conviene tenerlo escrito: a partir de aquí
 * `usuarios.dni` puede ser nulo, de modo que cualquier código que lo dé por
 * presente tiene que decir qué hace cuando no lo está. Hoy son dos sitios y
 * los dos quedan resueltos en este mismo cambio:
 *
 *   - el checkout, que ya sabía pedir el documento al invitado y ahora lo
 *     pide también a quien entró con Google y todavía no lo tiene;
 *   - el perfil, que permite completarlo UNA vez y no cambiarlo después.
 *
 * El acceso con DNI y contraseña no se ve afectado: una cuenta sin DNI no
 * puede entrar por ahí, y es lo correcto, porque entra por Google.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            /* El identificador que da Google. Único: dos cuentas del sistema
               no pueden colgar de la misma cuenta de Google.

               Se guarda el `sub` de Google y NO el correo, porque el correo
               de una cuenta de Google puede cambiar y el `sub` no. Vincular
               por correo dejaría la cuenta colgando de un dato mutable. */
            $table->string('google_id', 64)->nullable()->unique()->after('email');

            /* Cuándo se vinculó. Sirve para responder «¿desde cuándo entra
               esta persona con Google?» sin tener que deducirlo. */
            $table->timestamp('google_vinculado_en')->nullable()->after('google_id');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('dni')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'google_vinculado_en']);
        });

        /* El DNI NO se devuelve a obligatorio en la bajada. Si existe alguna
           cuenta creada con Google, volver a NOT NULL fallaría a mitad y
           dejaría la tabla a medio migrar. Revertir eso exige decidir antes
           qué se hace con esas cuentas, y esa decisión no la puede tomar una
           migración. */
    }
};
