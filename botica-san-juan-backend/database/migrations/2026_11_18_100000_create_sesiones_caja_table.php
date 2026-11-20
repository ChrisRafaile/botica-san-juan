<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('estado', 20)->default('abierta');
            $table->decimal('monto_inicial', 12, 2);
            $table->decimal('ventas_efectivo', 12, 2)->default(0);
            $table->decimal('total_ingresos', 12, 2)->default(0);
            $table->decimal('total_egresos', 12, 2)->default(0);
            $table->decimal('monto_esperado', 12, 2)->nullable();
            $table->decimal('monto_contado', 12, 2)->nullable();
            $table->decimal('diferencia', 12, 2)->nullable();
            $table->text('observacion_apertura')->nullable();
            $table->text('observacion_cierre')->nullable();
            $table->timestamp('abierta_en');
            $table->timestamp('cerrada_en')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'estado']);
        });

        // Garantiza en PostgreSQL que un mismo cajero no pueda mantener dos
        // sesiones abiertas de forma simultánea ante condiciones de carrera.
        DB::statement(
            "CREATE UNIQUE INDEX sesiones_caja_una_abierta_por_usuario ON sesiones_caja (usuario_id) WHERE estado = 'abierta'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sesiones_caja_una_abierta_por_usuario');
        Schema::dropIfExists('sesiones_caja');
    }
};
