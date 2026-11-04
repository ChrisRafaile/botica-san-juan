<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recetas médicas adjuntas a un pedido.
 * ---------------------------------------------------------------------------
 * UNA RECETA POR PEDIDO, Y POR QUÉ
 *
 * `pedido_id` es único. La receta acredita ESE pedido: si el cliente adjunta
 * otra, sustituye a la anterior y el contador `version` sube. Permitir varias
 * filas vivas obligaría a decidir cuál manda, y esa decisión acabaría
 * tomándola la consulta que llegue primero.
 *
 * LA RUTA NO APUNTA AL DISCO PÚBLICO
 *
 * La foto de perfil vive en storage/app/public porque no pasa nada si alguien
 * adivina su URL. Una receta es otra cosa: lleva el nombre del paciente, el
 * del médico y, por el medicamento, su diagnóstico. Se guarda en el disco
 * privado y solo sale por un endpoint que comprueba quién pide.
 *
 * Por eso aquí no hay ninguna columna con una URL: solo la ruta relativa
 * dentro del disco privado, que no se publica nunca tal cual.
 *
 * EL ESTADO LO PONE UNA PERSONA
 *
 * `estado` nace 'pendiente' y solo lo mueve un administrador. No hay
 * validación automática, ni OCR, ni nada que decida por un profesional si una
 * receta es válida. El sistema guarda, muestra y registra quién decidió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recetas', function (Blueprint $table) {
            $table->id();

            /* Si se borra el pedido se va la receta con él: fuera de su
               pedido, el archivo es un documento clínico huérfano. */
            $table->foreignId('pedido_id')
                ->unique()
                ->constrained('pedidos')
                ->cascadeOnDelete();

            /* Quién la subió. Se guarda aparte de pedidos.usuario_id porque un
               pedido de mostrador puede no tener cuenta asociada. */
            $table->foreignId('subida_por_id')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete();

            /* Ruta RELATIVA dentro del disco privado. Nunca una URL. */
            $table->string('archivo', 255);
            $table->string('nombre_original', 255);
            $table->string('tipo_mime', 100);
            $table->unsignedInteger('tamano_bytes');

            $table->string('estado', 20)->default('pendiente');

            /* Un rechazo sin motivo obliga al cliente a adivinar qué repetir. */
            $table->string('motivo_rechazo', 300)->nullable();

            $table->timestamp('revisada_en')->nullable();
            $table->foreignId('revisada_por_id')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete();

            /* Cuántas veces se ha adjuntado para este pedido. Un 3 le dice al
               boticario que el cliente ya lo intentó dos veces, que es
               justamente cuando conviene llamarle en vez de rechazar otra. */
            $table->unsignedSmallInteger('version')->default(1);

            $table->timestamps();

            /* La cola de trabajo pregunta siempre lo mismo: qué hay pendiente
               de revisar, lo más antiguo primero. */
            $table->index(['estado', 'created_at'], 'recetas_estado_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recetas');
    }
};
