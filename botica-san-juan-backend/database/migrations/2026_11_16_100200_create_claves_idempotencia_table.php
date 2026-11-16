<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de claves de idempotencia para peticiones transaccionales.
     *
     * Permite reintentos seguros en operaciones críticas (confirmar pedido, iniciar pago,
     * simular pago y cancelar pedido) sin riesgo de duplicidad de cobros ni inventario.
     */
    public function up(): void
    {
        Schema::create('claves_idempotencia', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 120);
            $table->string('alcance', 80);
            $table->string('actor', 80);
            $table->char('huella_peticion', 64);
            $table->string('estado', 20)->default('en_curso');
            $table->integer('codigo_respuesta')->nullable();
            $table->text('cuerpo_respuesta')->nullable();
            $table->timestamp('expira_en');
            $table->timestamps();

            $table->unique(['clave', 'alcance', 'actor'], 'claves_idempotencia_clave_alcance_actor_unique');
            $table->index('expira_en', 'claves_idempotencia_expira_en_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claves_idempotencia');
    }
};
