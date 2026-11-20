<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_caja_id')->constrained('sesiones_caja')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->string('tipo', 20);
            $table->decimal('monto', 12, 2);
            $table->string('motivo', 255);
            $table->string('referencia', 100)->nullable();
            $table->timestamps();

            $table->index(['sesion_caja_id', 'tipo']);
        });

        // Evita duplicar el ingreso en caja si una venta del POS se reintenta por idempotencia.
        DB::statement(
            'CREATE UNIQUE INDEX movimientos_caja_sesion_pedido_unico ON movimientos_caja (sesion_caja_id, pedido_id) WHERE pedido_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS movimientos_caja_sesion_pedido_unico');
        Schema::dropIfExists('movimientos_caja');
    }
};
