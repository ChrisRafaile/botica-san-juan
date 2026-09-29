<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Incidencias de venta: lo que se pidió y no se pudo entregar.
 *
 * Regla de negocio acordada: el sistema no bloquea la venta por falta de
 * stock. Si hay 7 y piden 10, avisa, permite confirmar la entrega de 7 y deja
 * constancia de que faltaron 3. Nunca se genera stock negativo.
 *
 * Y hay un segundo uso, más valioso que la propia trazabilidad: estas filas
 * son DEMANDA INSATISFECHA. Un producto que acumula incidencias está diciendo
 * que su nivel de reposición es demasiado bajo. El sistema heredado no da esa
 * información porque la venta que no ocurre no deja rastro en ningún sitio:
 * el cliente simplemente se va a otra botica y nadie se entera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencias_venta', function (Blueprint $table) {
            $table->id();

            /* Nullable: una incidencia puede registrarse aunque la venta
               termine anulándose o no llegue a cerrarse. */
            $table->foreignId('pedido_id')
                ->nullable()
                ->constrained('pedidos')
                ->nullOnDelete();

            $table->foreignId('pedido_detalle_id')
                ->nullable()
                ->constrained('pedido_detalles')
                ->nullOnDelete();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->cascadeOnDelete();

            /*  stock_insuficiente    — se pidió más de lo disponible
                lote_vencido_omitido  — había stock, pero estaba vencido
                ajuste_en_venta       — el vendedor corrigió el stock en el acto
                                        porque el anaquel no coincidía  */
            $table->enum('tipo', [
                'stock_insuficiente',
                'lote_vencido_omitido',
                'ajuste_en_venta',
            ])->default('stock_insuficiente');

            /* En unidad base, para poder comparar y sumar entre formas de
               venta distintas. */
            $table->unsignedInteger('cantidad_solicitada');
            $table->unsignedInteger('cantidad_atendida');

            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete();

            $table->string('observacion', 255)->nullable();

            $table->timestamps();

            /* Consulta objetivo: "qué productos acumulan más faltantes este
               mes", que es la que convierte esto en decisión de compra. */
            $table->index(['producto_id', 'created_at'], 'incidencias_producto_fecha_index');
            $table->index(['tipo', 'created_at'], 'incidencias_tipo_fecha_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias_venta');
    }
};
