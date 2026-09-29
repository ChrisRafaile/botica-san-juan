<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Afectación al IGV: de un booleano a un tipo con base legal.
 * ---------------------------------------------------------------------------
 *
 * POR QUÉ NO BASTA `afecto_igv`
 * Un sí/no responde "¿se le cobra IGV?" pero no "¿por qué no?". Y el porqué es
 * lo que hay que revisar cada año: la lista de medicamentos exonerados la
 * actualiza el MINSA por Decreto Supremo, así que sin la base legal anotada
 * nadie sabe qué productos volver a mirar cuando salga la lista nueva.
 *
 * Además el comprobante electrónico no acepta un booleano: la SUNAT pide el
 * código del catálogo 07 por cada línea (10 gravado, 20 exonerado, 30
 * inafecto). Con un sí/no habría que adivinar entre exonerado e inafecto, que
 * no son lo mismo: el exonerado está gravado pero dispensado por ley, el
 * inafecto queda fuera del ámbito del impuesto.
 *
 * DE DÓNDE SALE LA EXONERACIÓN EN UNA BOTICA
 * - Apéndice I del TUO de la Ley del IGV: bienes exonerados en general
 *   (sobre todo productos agrícolas en estado natural). Prorrogado hasta el
 *   31/12/2028 por la Ley 32542.
 * - Ley 27450: medicamentos e insumos para tratamiento oncológico y VIH/SIDA.
 * - Ley 28553: amplía lo anterior a los medicamentos para diabetes.
 * Para una botica pesan mucho más las dos leyes que el Apéndice I, porque casi
 * todo lo que vende son medicamentos, no fruta fresca.
 *
 * POR QUÉ SE COPIA EL TIPO A CADA LÍNEA DE VENTA
 * `pedido_detalles.tipo_afectacion_igv` es una foto del momento de la venta.
 * Si mañana un medicamento sale de la lista de exonerados, las boletas ya
 * emitidas tienen que seguir diciendo lo que dijeron: recalcular el pasado con
 * las reglas de hoy falsearía los comprobantes ya entregados y declarados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $tabla) {
            /* Catálogo 07 de la SUNAT, reducido a lo que una botica usa. */
            $tabla->string('tipo_afectacion_igv', 2)
                ->default('10')
                ->after('afecto_igv')
                ->comment('Catalogo 07 SUNAT: 10 gravado, 20 exonerado, 30 inafecto');

            $tabla->string('base_legal_exoneracion', 40)
                ->nullable()
                ->after('tipo_afectacion_igv')
                ->comment('Por que no paga IGV: apendice_i, ley_27450, ley_28553, otra');

            $tabla->index('tipo_afectacion_igv');
        });

        /* Se corrige de paso la referencia equivocada del comentario anterior:
           el Apéndice I es el de bienes; el II es el de servicios. */
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "COMMENT ON COLUMN productos.afecto_igv IS "
                ."'Espejo derivado de tipo_afectacion_igv. Fuente de verdad: tipo_afectacion_igv'"
            );
        }

        /* Coherencia con lo que ya había: lo marcado como no afecto pasa a
           exonerado, que es el caso real en una botica. Inafecto se marca a
           mano, porque es excepcional y conviene que alguien lo decida. */
        DB::table('productos')->where('afecto_igv', false)->update([
            'tipo_afectacion_igv' => '20',
        ]);

        Schema::table('pedidos', function (Blueprint $tabla) {
            $tabla->decimal('subtotal_inafecto', 10, 2)
                ->default(0)
                ->after('subtotal_exonerado');
        });

        Schema::table('pedido_detalles', function (Blueprint $tabla) {
            $tabla->string('tipo_afectacion_igv', 2)
                ->default('10')
                ->after('subtotal')
                ->comment('Foto del tratamiento al momento de vender; no se recalcula despues');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_detalles', function (Blueprint $tabla) {
            $tabla->dropColumn('tipo_afectacion_igv');
        });

        Schema::table('pedidos', function (Blueprint $tabla) {
            $tabla->dropColumn('subtotal_inafecto');
        });

        Schema::table('productos', function (Blueprint $tabla) {
            $tabla->dropIndex(['tipo_afectacion_igv']);
            $tabla->dropColumn(['tipo_afectacion_igv', 'base_legal_exoneracion']);
        });
    }
};
