<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitacora de notificaciones de la pasarela y mecanismo de idempotencia.
 *
 * Una notificacion servidor a servidor puede llegar mas de una vez: el
 * proveedor reintenta ante cualquier duda sobre la entrega. Confiar en una
 * comprobacion "si ya esta pagado, no hagas nada" deja una ventana de carrera
 * entre dos entregas simultaneas.
 *
 * La restriccion UNIQUE sobre 'evento_id' traslada esa garantia a la base de
 * datos: la segunda insercion viola la restriccion, se captura la violacion y
 * se responde 200 sin volver a aplicar el efecto. La idempotencia deja de
 * depender del orden de ejecucion.
 *
 * 'payload' guarda el cuerpo recibido ya saneado; nunca datos de tarjeta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('evento_id', 160)->unique();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->string('tipo', 60)->nullable();
            $table->string('estado_reportado', 40)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('procesado_en')->nullable();
            $table->timestamps();

            $table->index('procesado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_eventos');
    }
};
