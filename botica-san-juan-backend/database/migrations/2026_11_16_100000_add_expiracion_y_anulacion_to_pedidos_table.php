<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Añade expiración y motivo de anulación a pedidos.
     *
     * `expira_en`: timestamp de reserva temporal para pagos con tarjeta.
     * `anulado_en`: momento exacto de la cancelación del pedido.
     * `motivo_anulacion`: actor o causa de la anulación ('cliente', 'administrador', 'expirado').
     */
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->timestamp('expira_en')->nullable()->index();
            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['expira_en']);
            $table->dropColumn(['expira_en', 'anulado_en', 'motivo_anulacion']);
        });
    }
};
