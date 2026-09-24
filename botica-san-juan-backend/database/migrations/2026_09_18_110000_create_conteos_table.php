<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesiones de conteo físico por ciclos.
 *
 * Por qué una SESIÓN y no un ajuste suelto: contar el anaquel y corregir el
 * sistema son dos actos distintos, y entre uno y otro sigue habiendo ventas.
 * La sesión guarda lo que se contó y cuándo, y sólo al cerrarla se aplican los
 * ajustes — así queda claro qué diferencia venía del conteo y cuál de una venta
 * posterior. Sin esa separación, un conteo de la mañana aplicado por la tarde
 * "corrige" ventas legítimas y descuadra el inventario en vez de arreglarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conteos', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->string('codigo', 20)->unique()
                ->comment('Identificador legible para el personal: CONT-2026-0001');

            /* `abierto` mientras se cuenta; `cerrado` una vez aplicados los
               ajustes; `anulado` si se descarta sin aplicar nada. */
            $tabla->string('estado', 12)->default('abierto');

            $tabla->string('criterio', 20)->default('rotacion')
                ->comment('Cómo se eligieron los productos: rotacion, categoria, vencimiento, manual');

            $tabla->string('ambito', 120)->nullable()
                ->comment('Detalle del criterio: nombre de la categoría, rango de días, etc.');

            $tabla->text('observacion')->nullable();

            $tabla->foreignId('abierto_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->foreignId('cerrado_por')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->timestamp('abierto_en')->useCurrent();
            $tabla->timestamp('cerrado_en')->nullable();

            $tabla->timestamps();

            $tabla->index(['estado', 'abierto_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conteos');
    }
};
