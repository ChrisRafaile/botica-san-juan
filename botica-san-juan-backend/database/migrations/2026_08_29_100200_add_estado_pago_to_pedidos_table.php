<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separa el estado logistico del pedido del estado del cobro.
 *
 * 'estado' responde a donde esta el pedido en la operacion (pendiente,
 * confirmado, entregado); 'estado_pago' responde a si el dinero llego. Son
 * dimensiones independientes: un pedido puede estar confirmado y su pago
 * pendiente, y esa combinacion tiene que poder representarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'estado_pago')) {
                $table->string('estado_pago', 20)->default('pendiente')->after('estado');
            }
            if (!Schema::hasColumn('pedidos', 'moneda')) {
                $table->string('moneda', 3)->default('PEN')->after('total');
            }
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->index('estado_pago', 'pedidos_estado_pago_index');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex('pedidos_estado_pago_index');
            $table->dropColumn(['estado_pago', 'moneda']);
        });
    }
};
