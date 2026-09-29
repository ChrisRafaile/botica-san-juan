<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobro en mostrador: una fila por medio de pago usado en la venta.
 *
 * POR QUÉ UNA TABLA Y NO UNA COLUMNA
 * Una sola columna `medio_pago` alcanza mientras nadie divida el pago, y en el
 * momento en que alguien paga S/ 30 en efectivo y S/ 20 por Yape obliga a
 * migrar el esquema con ventas ya registradas. Además, cada medio necesita
 * datos distintos: el efectivo lleva cuánto entregó el cliente y cuánto se le
 * devolvió; la tarjeta y el Yape llevan el código de operación con el que se
 * concilia después. Meter todo eso en una fila de `pedidos` deja media docena
 * de columnas vacías en el 95% de las ventas.
 *
 * POR QUÉ ADEMÁS UNA COLUMNA RESUMEN EN `pedidos`
 * El reporte del contador es una fila por comprobante con un solo valor de
 * medio de pago — así lo genera hoy el sistema heredado en el campo `tipven`.
 * `pedidos.medio_pago` guarda ese valor ya resuelto ('efectivo', 'yape', ... o
 * 'mixto') para no tener que agregar la tabla de pagos en cada exportación.
 *
 * POR QUÉ IMPORTA SEPARAR LOS MEDIOS
 * Para el arqueo de caja no son equivalentes: al cerrar el día, en la gaveta
 * sólo debe estar el efectivo. El Yape y el Plin entran al celular, y la
 * tarjeta se liquida días después y con comisión. Un sistema que los sume
 * todos como "ventas del día" hace imposible cuadrar la caja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_pagos', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();

            $tabla->string('medio', 20)
                ->comment('efectivo, tarjeta, yape, plin, transferencia');

            $tabla->decimal('monto', 10, 2)
                ->comment('Cuánto de la venta cubre este medio');

            /* Sólo efectivo: lo que el cliente puso sobre el mostrador. */
            $tabla->decimal('monto_recibido', 10, 2)->nullable();
            $tabla->decimal('vuelto', 10, 2)->nullable();

            $tabla->string('referencia', 60)->nullable()
                ->comment('Últimos 4 de la tarjeta, código de operación de Yape/Plin, etc.');

            $tabla->timestamps();

            $tabla->index(['pedido_id']);
            $tabla->index(['medio', 'created_at']);
        });

        Schema::table('pedidos', function (Blueprint $tabla) {
            $tabla->string('medio_pago', 20)->nullable()->after('estado_pago')
                ->comment("Resumen para el reporte contable: el medio único, o 'mixto'");

            $tabla->index('medio_pago');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $tabla) {
            $tabla->dropIndex(['medio_pago']);
            $tabla->dropColumn('medio_pago');
        });

        Schema::dropIfExists('pedido_pagos');
    }
};
