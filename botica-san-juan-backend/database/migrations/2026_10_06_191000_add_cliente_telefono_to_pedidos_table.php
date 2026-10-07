<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teléfono de contacto del pedido.
 *
 * La tabla ya guardaba `cliente_nombre`, `cliente_documento` y
 * `cliente_tipo_documento` para la venta de mostrador, pero no un teléfono. En
 * mostrador no hacía falta: el cliente está delante.
 *
 * En un encargo del portal sí hace falta, y es el único dato que permite cerrar
 * el circuito. Cuando el pedido está listo, o cuando falta stock de una línea,
 * la botica necesita avisar; sin teléfono el pedido se queda esperando a que el
 * cliente aparezca por su cuenta.
 *
 * Es `nullable` porque las ventas de mostrador y los pedidos ya registrados no
 * lo tienen, y exigirlo retroactivamente habría impedido la migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('cliente_telefono', 30)
                ->nullable()
                ->after('cliente_documento');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('cliente_telefono');
        });
    }
};
