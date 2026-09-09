<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desacopla el cobro del pedido.
 *
 * Hasta ahora el instrumento de pago vivia dentro de 'pedidos' como numero de
 * tarjeta, fecha de expiracion y codigo de verificacion en texto claro. Este
 * esquema invierte la responsabilidad: el sistema deja de custodiar el medio
 * de pago y pasa a custodiar unicamente la referencia opaca que emite la
 * pasarela, mas los datos no sensibles que el negocio necesita para conciliar,
 * emitir el comprobante y atender un reclamo.
 *
 * Lo que esta tabla NO contiene, deliberadamente: numero completo de tarjeta,
 * codigo de verificacion y fecha de vencimiento completa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();

            $table->string('proveedor', 30)->default('izipay');
            $table->string('proveedor_pago_id', 120)->nullable();
            $table->string('referencia_pedido', 80)->nullable();

            $table->string('estado', 20)->default('pendiente');
            $table->string('metodo_pago', 30)->nullable();

            // Datos no sensibles: lo justo para que el cliente reconozca su pago.
            $table->string('marca_tarjeta', 30)->nullable();
            $table->string('ultimos4', 4)->nullable();

            $table->decimal('monto', 10, 2);
            $table->string('moneda', 3)->default('PEN');
            $table->timestamp('pagado_en')->nullable();

            $table->string('codigo_error', 40)->nullable();
            $table->string('mensaje_error', 255)->nullable();

            $table->timestamps();

            // Un mismo cobro del proveedor no puede registrarse dos veces.
            $table->unique(['proveedor', 'proveedor_pago_id'], 'pagos_proveedor_pago_unico');
            $table->unique('referencia_pedido', 'pagos_referencia_unica');
            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
