<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIGRACION PREPARADA, NO EJECUTADA.
 *
 * Elimina de 'pedidos' las tres columnas heredadas del checkout monolitico:
 * numero de tarjeta, fecha de expiracion y codigo de verificacion.
 *
 * Es un cambio DESTRUCTIVO: se pierden los valores almacenados. Por eso el
 * archivo vive fuera de database/migrations y con extension .pendiente, de
 * modo que 'php artisan migrate' no lo tome por accidente.
 *
 * Antes de activarla:
 *
 *   1. Respaldar:  infra/backup/backup_postgres.ps1
 *   2. Mover el archivo a database/migrations/ y quitarle la extension
 *      .pendiente.
 *   3. php artisan migrate --force
 *
 * Justificacion del caracter irreversible: PCI-DSS prohibe conservar el codigo
 * de verificacion despues de autorizar la transaccion, cifrado o no. Un
 * down() que restituyera los valores seria precisamente lo que la norma
 * impide, de modo que solo restituye la estructura, vacia.
 *
 * Verificado antes de proponerla: ningun controlador, ninguna ruta, ningun
 * componente Vue y ninguna prueba del sistema activo leen o escriben estas
 * columnas. Solo las referencian la migracion original, el seeder de pedidos y
 * el checkout de legacy-php, que ya fue reemplazado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            foreach (['card_number', 'expiry_date', 'cvv'] as $columna) {
                if (Schema::hasColumn('pedidos', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }

    public function down(): void
    {
        // Restituye la estructura, nunca los valores.
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('card_number')->nullable();
            $table->string('expiry_date')->nullable();
            $table->string('cvv')->nullable();
        });
    }
};
