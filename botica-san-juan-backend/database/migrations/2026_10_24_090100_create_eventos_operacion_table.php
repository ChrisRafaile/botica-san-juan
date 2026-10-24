<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de eventos de operación.
 * ---------------------------------------------------------------------------
 * PARA QUE EXISTE
 *
 * La entrega de la Semana 18 pide monitoreo y gestión de eventos clasificados
 * en informativos, de advertencia y de error. Sin una tabla como esta, eso
 * sería una afirmación sin respaldo: los logs de Laravel se rotan, viven en
 * el contenedor de Render y no se pueden contar ni filtrar desde el panel.
 *
 * POR QUE HAY UN `codigo` ADEMAS DEL MENSAJE
 *
 * El mensaje está escrito para que lo lea una persona y por eso cambia: se
 * reformula, se traduce, se le añade un dato. El `codigo` no cambia nunca, y
 * es lo que permite responder «cuántas ventas duplicadas hubo en octubre»
 * contando filas en vez de buscando subcadenas. Agrupar por texto libre es
 * como medir con una regla de goma.
 *
 * QUE NO SE GUARDA AQUI
 *
 * Ni contraseñas, ni tokens, ni el documento del cliente, ni el contenido del
 * comprobante. El contexto guarda identificadores con los que se puede ir a
 * buscar el dato donde corresponde, no copias del dato. Una bitácora de
 * operación se consulta a menudo y por mucha gente; es el peor sitio para
 * acumular datos personales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_operacion', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->string('nivel', 10)
                ->comment('info | warning | error');

            $tabla->string('codigo', 60)
                ->comment('Identificador estable del tipo de evento. No cambia aunque cambie el mensaje.');

            $tabla->string('mensaje', 255)
                ->comment('Texto legible para quien revisa la bitacora.');

            $tabla->string('origen', 20)
                ->default('api')
                ->comment('caja | api');

            $tabla->string('dispositivo', 40)->nullable();

            $tabla->uuid('uuid_local')
                ->nullable()
                ->comment('Venta de caja relacionada, cuando el evento nace de una sincronizacion.');

            $tabla->foreignId('usuario_id')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete();

            $tabla->foreignId('pedido_id')
                ->nullable()
                ->constrained('pedidos')
                ->nullOnDelete();

            $tabla->jsonb('contexto')->nullable();

            $tabla->timestampTz('created_at')->useCurrent();

            /* Las dos consultas que realmente se hacen sobre esta tabla:
               «qué pasó en este periodo» y «cuántos de este tipo hubo». */
            $tabla->index(['created_at']);
            $tabla->index(['nivel', 'codigo']);
            $tabla->index(['uuid_local']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_operacion');
    }
};
