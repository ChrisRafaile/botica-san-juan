<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comisiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_electronico_id')->constrained('comprobantes_electronicos')->cascadeOnDelete();
            $table->enum('tipo_agente', ['medico', 'vendedor', 'referido']);
            $table->string('agente_nombre', 255);
            $table->string('agente_documento', 20)->nullable();
            $table->decimal('porcentaje', 5, 2);
            $table->decimal('monto', 10, 2);
            $table->enum('estado', ['pendiente', 'liquidada'])->default('pendiente');
            $table->timestamp('fecha_liquidacion')->nullable();
            $table->timestamps();

            $table->index(['estado', 'tipo_agente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comisiones');
    }
};
