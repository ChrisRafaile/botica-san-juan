<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desglose por lote de cada línea de venta.
 *
 * Resuelve el caso decidido con el negocio: si el cliente pide 5 unidades y el
 * lote más próximo a vencer solo tiene 3, se toman 3 de ese lote y 2 del
 * siguiente. La boleta muestra una sola línea de 5 unidades — al cliente no le
 * interesa el detalle — pero el sistema conserva de qué lotes salieron.
 *
 * Eso es lo que permite, meses después, responder a un retiro de DIGEMID: qué
 * ventas incluyeron el lote afectado y en qué fecha.
 *
 * Una línea de venta tiene una o varias filas aquí, y la suma de
 * `cantidad_unidades` debe coincidir exactamente con la cantidad entregada de
 * esa línea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_detalle_lotes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pedido_detalle_id')
                ->constrained('pedido_detalles')
                ->cascadeOnDelete();

            /* restrictOnDelete: un lote con ventas asociadas no se borra nunca.
               Si hiciera falta retirarlo se marca como 'retirado', porque el
               histórico de ventas debe permanecer intacto. */
            $table->foreignId('lote_id')
                ->constrained('lotes')
                ->restrictOnDelete();

            /* Unidades base tomadas de este lote concreto. */
            $table->unsignedInteger('cantidad_unidades');

            /* Se copian del lote en el momento de la venta. Redundante a
               propósito: si el lote se edita o corrige después, el comprobante
               debe seguir reflejando lo que realmente se entregó. */
            $table->string('codigo_lote', 60);
            $table->date('fecha_vencimiento')->nullable();

            $table->timestamps();

            $table->index('lote_id', 'detalle_lotes_lote_index');
            $table->index('pedido_detalle_id', 'detalle_lotes_detalle_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_detalle_lotes');
    }
};
