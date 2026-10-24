<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad de las ventas cobradas sin conexión.
 * ---------------------------------------------------------------------------
 * POR QUE `uuid_local` LLEVA UN INDICE UNICO Y NO BASTA CON COMPROBARLO EN PHP
 *
 * La caja reenvía su cola cada vez que recupera la red. Una reconexión
 * intermitente puede disparar dos envíos del mismo lote con milisegundos de
 * diferencia. Si la garantía de «esta venta ya entró» viviera en un
 * `where(...)->exists()` seguido de un `create(...)`, entre esas dos
 * operaciones cabría el segundo envío y la botica acabaría con dos pedidos y
 * dos boletas para una sola venta real: una contingencia tributaria, no un
 * detalle cosmético.
 *
 * Con el índice único la carrera la resuelve el motor. El segundo intento
 * falla en la base y el servicio lo traduce a «duplicada», que es la
 * respuesta correcta y además deja la cola de la caja limpia.
 *
 * LAS DOS FECHAS SON DOS HECHOS DISTINTOS
 *
 * `fecha` es cuándo ocurrió la venta en el mostrador. `registrado_offline_en`
 * marca que esa venta llegó por sincronización y cuándo se cobró realmente en
 * caja. Nunca se reescribe la primera con la del envío: la fecha de la
 * operación es la que vale para el registro de ventas y para la SUNAT, y
 * perderla por comodidad sería falsear el libro.
 *
 * `nota_contingencia` guarda el ticket interno (NV-xxxxxx) que el cliente se
 * llevó cuando no había conexión, para poder cruzar ese papel con la boleta
 * que se emitió después.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $tabla) {
            $tabla->uuid('uuid_local')
                ->nullable()
                ->unique()
                ->comment('Identificador generado en la caja. Clave de idempotencia de la sincronizacion.');

            $tabla->string('nota_contingencia', 20)
                ->nullable()
                ->comment('Ticket interno entregado al cliente sin conexion (NV-xxxxxx). No es comprobante de pago.');

            $tabla->timestampTz('registrado_offline_en')
                ->nullable()
                ->comment('Momento del cobro en caja cuando la venta se tomo sin conexion.');

            $tabla->string('dispositivo_origen', 40)
                ->nullable()
                ->comment('Caja desde la que se sincronizo, para poder aislar incidencias por equipo.');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $tabla) {
            $tabla->dropColumn([
                'uuid_local',
                'nota_contingencia',
                'registrado_offline_en',
                'dispositivo_origen',
            ]);
        });
    }
};
