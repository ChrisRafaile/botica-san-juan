<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de lotes — el inventario real de la botica.
 *
 * Hasta ahora el stock vivía como un entero suelto en `productos.stock`. Eso
 * impide tres cosas que en una farmacia no son opcionales:
 *
 *   1. Saber qué vence y cuándo. Sin fecha de vencimiento por lote no hay FEFO
 *      ni alertas posibles; la documentación del proyecto las prometía, pero el
 *      esquema no las soportaba.
 *   2. Trazabilidad sanitaria. Si DIGEMID retira un lote, hay que poder decir
 *      qué unidades de ese lote se vendieron y cuándo.
 *   3. Costeo real. Cada compra entra a un costo distinto; con un solo número
 *      no se puede calcular margen.
 *
 * A partir de aquí, `lotes.cantidad_actual` es la fuente de verdad del stock.
 * `productos.stock` se conserva como copia denormalizada (ver la migración de
 * movimientos) para no romper el código existente, pero se recalcula siempre
 * desde aquí, dentro de la misma transacción.
 *
 * Todas las cantidades se expresan en UNIDAD BASE. Un blíster de 10 no son
 * "1", son "10". La conversión vive en el servicio de venta, no en la tabla:
 * así una caja que cambie de contenido no corrompe el histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->cascadeOnDelete();

            /* Código impreso en el envase. No es único a nivel global: dos
               laboratorios distintos pueden repetir codificación. Es único
               por producto. */
            $table->string('codigo_lote', 60);

            /* Nullable a propósito. Al migrar los 3361 productos existentes no
               conocemos el vencimiento real de lo que hay en el anaquel; se
               regulariza con el conteo físico y con cada compra nueva. Un lote
               sin fecha nunca se elige por FEFO antes que uno con fecha. */
            $table->date('fecha_vencimiento')->nullable();

            /* Cantidades en unidad base. */
            $table->unsignedInteger('cantidad_inicial')->default(0);
            $table->unsignedInteger('cantidad_actual')->default(0);

            /* Costo de adquisición por unidad base, para margen y valorización
               de inventario. Nullable porque el stock heredado no lo tiene. */
            $table->decimal('costo_unitario', 10, 4)->nullable();

            $table->foreignId('compra_id')
                ->nullable()
                ->constrained('compras')
                ->nullOnDelete();

            /*  activo    — disponible para la venta
                agotado   — cantidad_actual llegó a 0
                vencido   — pasó su fecha; NUNCA se vende
                retirado  — retirado por DIGEMID o por decisión interna  */
            $table->enum('estado', ['activo', 'agotado', 'vencido', 'retirado'])
                ->default('activo');

            $table->string('observacion', 255)->nullable();

            $table->timestamps();

            /* Índice principal de FEFO: por producto, ordenado por vencimiento.
               Es la consulta que corre en cada línea de cada venta. */
            $table->index(['producto_id', 'estado', 'fecha_vencimiento'], 'lotes_fefo_index');

            /* Para el panel de alertas de vencimiento (90/60/30 días). */
            $table->index(['fecha_vencimiento', 'estado'], 'lotes_vencimiento_index');

            $table->unique(['producto_id', 'codigo_lote'], 'lotes_producto_codigo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};
