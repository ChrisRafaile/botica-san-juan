<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comprobantes_electronicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->enum('tipo_comprobante', ['boleta', 'factura']);
            $table->string('serie', 4);
            $table->unsignedBigInteger('numero');
            $table->string('cliente_nombre', 255);
            $table->string('cliente_documento', 20)->nullable();
            $table->decimal('total', 10, 2);
            $table->enum('estado_sunat', ['pendiente', 'aceptada', 'rechazada'])->default('pendiente');
            $table->string('codigo_respuesta_sunat', 10)->nullable();
            $table->string('mensaje_sunat', 255)->nullable();
            $table->timestamp('fecha_emision');
            $table->timestamp('fecha_envio_sunat')->nullable();
            $table->string('hash_documento', 128)->nullable();
            $table->string('xml_path', 255)->nullable();
            $table->timestamps();

            $table->unique(['serie', 'numero']);
            $table->index(['estado_sunat', 'tipo_comprobante']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comprobantes_electronicos');
    }
};
