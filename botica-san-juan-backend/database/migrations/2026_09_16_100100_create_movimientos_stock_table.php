<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libro mayor del inventario: toda variación de stock deja rastro aquí.
 *
 * Por qué hace falta, más allá de la auditoría:
 *
 * El código actual ajusta el stock leyendo el producto y volviéndolo a
 * guardar (GET y luego PUT). Entre esas dos operaciones cabe otra venta, y el
 * resultado es que una de las dos se pierde sin que nadie lo note. Con dos
 * personas atendiendo en mostrador eso ocurre de verdad, y el inventario se
 * desvía en silencio.
 *
 * Registrar cada movimiento con el stock anterior y posterior convierte ese
 * fallo silencioso en algo detectable: si la cadena se rompe, se ve. Y permite
 * reconstruir el stock real sumando movimientos si la copia denormalizada
 * queda desfasada.
 *
 * Regla: nadie modifica `lotes.cantidad_actual` ni `productos.stock` sin
 * escribir su movimiento correspondiente en la MISMA transacción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->cascadeOnDelete();

            /* Nullable: un ajuste de inventario puede no atribuirse a un lote
               concreto mientras se regulariza el stock heredado. */
            $table->foreignId('lote_id')
                ->nullable()
                ->constrained('lotes')
                ->nullOnDelete();

            /*  venta       — salida por venta en mostrador o web
                compra      — entrada por ingreso de mercadería
                ajuste      — corrección manual tras conteo físico
                devolucion  — el cliente devuelve; entra de nuevo
                merma       — rotura, pérdida o robo
                vencimiento — baja por producto vencido
                anulacion   — reversa de una venta anulada  */
            $table->enum('tipo', [
                'venta', 'compra', 'ajuste', 'devolucion',
                'merma', 'vencimiento', 'anulacion',
            ]);

            /* Con signo, en unidad base: negativo sale, positivo entra.
               Un entero con signo evita tener que interpretar el tipo para
               saber la dirección del movimiento. */
            $table->integer('cantidad');

            /* Fotografía del antes y el después. Si alguna vez los números no
               cuadran, esto dice exactamente en qué movimiento se rompió. */
            $table->integer('stock_anterior');
            $table->integer('stock_posterior');

            /* Referencia polimórfica al documento que originó el movimiento:
               pedido, compra, ajuste… Sin constraint porque apunta a tablas
               distintas. */
            $table->string('referencia_tipo', 40)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();

            /* Quién lo hizo. Obligatorio para poder pedir cuentas: un ajuste
               manual sin responsable no sirve de nada. */
            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete();

            /* Obligatorio en la práctica para los ajustes manuales; lo valida
               el servicio, no la base, porque para una venta no aplica. */
            $table->string('motivo', 255)->nullable();

            $table->timestamps();

            $table->index(['producto_id', 'created_at'], 'movimientos_producto_fecha_index');
            $table->index(['referencia_tipo', 'referencia_id'], 'movimientos_referencia_index');
            $table->index(['tipo', 'created_at'], 'movimientos_tipo_fecha_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_stock');
    }
};
