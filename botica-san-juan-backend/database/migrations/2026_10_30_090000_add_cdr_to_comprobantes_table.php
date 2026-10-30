<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Constancia de recepción como artefacto propio.
 * ---------------------------------------------------------------------------
 * POR QUE NO BASTABA CON `sunat_response`
 *
 * Esa columna guarda un JSON con lo que devolvió la integración, y sirve para
 * consultar sin abrir archivos. Pero el CDR no es un dato: es un documento
 * que hay que conservar y poder enseñar. Una columna se actualiza en el
 * siguiente reintento y el original se pierde; un archivo con nombre propio,
 * no.
 *
 * Así que el documento íntegro vive en disco y aquí queda su rastro: dónde
 * está, cuándo llegó, qué código trajo y el hash de lo que se guardó. El hash
 * es lo que permite demostrar, meses después, que el archivo del disco es el
 * mismo que llegó y no una copia manipulada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $tabla) {
            $tabla->string('cdr_path', 255)
                ->nullable()
                ->comment('Ruta del ZIP de la constancia de recepcion, nombrada R-RUC-TIPO-SERIE-CORRELATIVO.zip');

            $tabla->timestampTz('cdr_recibido_en')
                ->nullable()
                ->comment('Momento en que se recibio y guardo la constancia.');

            $tabla->string('cdr_hash', 64)
                ->nullable()
                ->comment('SHA-256 del ZIP guardado, para demostrar que el archivo no cambio.');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $tabla) {
            $tabla->dropColumn(['cdr_path', 'cdr_recibido_en', 'cdr_hash']);
        });
    }
};
