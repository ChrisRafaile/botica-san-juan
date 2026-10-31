<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil clínico del catálogo.
 * ---------------------------------------------------------------------------
 * POR QUE SE SEPARA DE LA IDENTIDAD COMERCIAL
 *
 * Un producto tiene dos caras que no coinciden. La COMERCIAL es con la que
 * llega el cliente —«AB-MOKS FORTE», del laboratorio tal— y la CLINICA es la
 * que determina si dos cosas pueden tomarse juntas: el principio activo.
 *
 * Mientras el principio activo vive como texto suelto dentro de la ficha del
 * producto, no hay forma de preguntar «qué más tengo con amoxicilina»: cada
 * fila lo escribe a su manera. Con una tabla propia y un vinculo, esa
 * pregunta es un join.
 *
 * LAS REGLAS DE INTERACCION SON DATO, NO CODIGO
 *
 * Van en tabla, con su fuente, y NO se muestran a nadie hasta que un químico
 * farmacéutico las aprueba. Un aviso farmacológico que nadie con título ha
 * revisado no es una funcionalidad: es un riesgo con interfaz bonita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('principios_activos', function (Blueprint $table) {
            $table->id();

            /* Nombre canónico, en mayúsculas y sin tildes: es la forma con la
               que se compara. La presentación bonita se hace al mostrar. */
            $table->string('nombre', 160)->unique();

            /* Otras formas de escribirlo que aparecen en los nombres de
               producto. Un arreglo y no filas aparte porque siempre se
               consultan junto al canónico, nunca por su cuenta. */
            $table->jsonb('sinonimos')->default('[]');

            $table->timestamps();
        });

        Schema::create('interacciones_farmacologicas', function (Blueprint $table) {
            $table->id();

            /* El par se guarda SIEMPRE con el id menor primero. Sin esa
               convención la misma interacción entraría dos veces, una por
               cada orden, y el índice único no serviría de nada. */
            $table->foreignId('principio_menor_id')->constrained('principios_activos')->cascadeOnDelete();
            $table->foreignId('principio_mayor_id')->constrained('principios_activos')->cascadeOnDelete();

            $table->string('severidad', 20); // leve | moderada | grave

            /* Qué puede pasar y qué hacer. Se guarda redactado porque lo
               escribe quien sabe, no el sistema. */
            $table->text('efecto');
            $table->text('recomendacion');

            /* De dónde sale la regla. Sin fuente, una regla no es revisable
               y no debería publicarse. */
            $table->string('fuente', 255);

            /* Aprobación. Mientras `aprobada_en` sea nula, la regla existe
               pero NO se muestra a ningún cliente. */
            $table->timestamp('aprobada_en')->nullable();
            $table->foreignId('aprobada_por_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $table->timestamps();

            $table->unique(['principio_menor_id', 'principio_mayor_id'], 'interaccion_par_unico');
            $table->index('aprobada_en');
        });

        Schema::table('productos', function (Blueprint $table) {
            /* El vínculo al perfil clínico. Nullable a propósito: la mayor
               parte del catálogo todavía no lo tiene, y el consultor tiene
               que poder decir cuántos productos pudo revisar y cuántos no.
               Rellenarlo a la fuerza con una suposición sería peor que
               dejarlo vacío. */
            $table->foreignId('principio_activo_id')
                ->nullable()
                ->after('principio_activo')
                ->constrained('principios_activos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('principio_activo_id');
        });

        Schema::dropIfExists('interacciones_farmacologicas');
        Schema::dropIfExists('principios_activos');
    }
};
