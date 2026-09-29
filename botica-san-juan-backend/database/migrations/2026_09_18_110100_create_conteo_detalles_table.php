<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una línea por producto contado dentro de una sesión.
 *
 * `stock_sistema` es una FOTO del momento en que se propuso el producto, no un
 * cálculo al cerrar. Guardarla es lo que permite explicar después de dónde
 * salió cada diferencia: si al cerrar recalculáramos, una venta ocurrida
 * mientras se contaba aparecería como un descuadre del anaquel.
 *
 * `lotes_contados` guarda el desglose que dicta el vendedor al contar
 * (código de lote + fecha de vencimiento + cantidad). Es el dato que hoy no
 * existe: todo el stock migró a un único lote "INICIAL" sin fecha, así que el
 * FEFO no tiene con qué priorizar. Cada conteo lo va poblando con datos reales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conteo_detalles', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('conteo_id')->constrained('conteos')->cascadeOnDelete();
            $tabla->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();

            $tabla->integer('stock_sistema')
                ->comment('Unidades que el sistema creía tener cuando se propuso el producto');

            $tabla->integer('cantidad_contada')->nullable()
                ->comment('NULL mientras no se haya contado; 0 es un conteo válido que significa "no hay"');

            /* Desglose dictado por quien cuenta:
               [{codigo_lote, fecha_vencimiento, cantidad}, ...] */
            $tabla->json('lotes_contados')->nullable();

            $tabla->string('observacion', 300)->nullable();

            $tabla->foreignId('contado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamp('contado_en')->nullable();

            /* Se llena al cerrar: deja el rastro de qué se aplicó. */
            $tabla->integer('diferencia_aplicada')->nullable();

            $tabla->timestamps();

            /* Un producto no se cuenta dos veces en la misma sesión. */
            $tabla->unique(['conteo_id', 'producto_id']);
            $tabla->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conteo_detalles');
    }
};
