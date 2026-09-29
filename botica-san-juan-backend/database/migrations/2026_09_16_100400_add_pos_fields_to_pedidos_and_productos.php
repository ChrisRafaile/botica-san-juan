<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adapta el esquema para la venta en mostrador.
 *
 * DOS HUECOS QUE ESTA MIGRACIÓN CIERRA
 *
 * 1) La venta de mostrador no tiene usuario registrado.
 *    `pedidos.usuario_id` era obligatorio porque el pedido nacía del carrito
 *    web. En mostrador atiende a un "cliente eventual" que no tiene cuenta —
 *    exactamente como hace hoy el sistema heredado. Se vuelve opcional y se
 *    añaden los datos mínimos del cliente para el comprobante.
 *
 * 2) No existía forma de saber qué productos están exonerados de IGV.
 *    El sistema heredado muestra "Exonerado" junto a Sub Total e I.G.V. en
 *    cada venta, y con razón: en Perú los medicamentos para tratamiento
 *    oncológico, VIH/SIDA y diabetes están exonerados del impuesto
 *    (Leyes 27450 y 28553). Sin este dato, la boleta
 *    calcularía impuesto donde no corresponde y el reporte al contador saldría
 *    mal — que es justo el trabajo que se quiere automatizar.
 *
 *    Por defecto `afecto_igv = true`, que es el caso de la mayoría del
 *    catálogo. Los exonerados se marcan a mano o por carga masiva; conviene
 *    revisarlo con el contador antes de emitir comprobantes reales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('afecto_igv')
                ->default(true)
                ->after('precio_caja')
                ->comment('Espejo de tipo_afectacion_igv; la fuente de verdad es esa columna');
        });

        Schema::table('pedidos', function (Blueprint $table) {
            /* Canal de la venta. Separa el mostrador del comercio web, que
               tienen reglas distintas: el mostrador descuenta stock al
               instante, el web puede reservar y confirmar después. */
            $table->enum('origen', ['web', 'pos'])
                ->default('web')
                ->after('usuario_id')
                ->index();

            /* Quién atendió. En mostrador importa para el cuadre de caja y
               para saber quién hizo cada ajuste. */
            $table->foreignId('vendedor_id')
                ->nullable()
                ->after('origen')
                ->constrained('usuarios')
                ->nullOnDelete();

            /* Datos del cliente cuando no tiene cuenta. Se quedan en blanco
               para la venta rápida de mostrador, igual que el "CLIENTE
               EVENTUAL" del sistema actual. */
            $table->string('cliente_nombre', 255)->nullable()->after('vendedor_id');
            $table->string('cliente_documento', 20)->nullable()->after('cliente_nombre');
            $table->enum('cliente_tipo_documento', ['dni', 'ruc', 'ce', 'sin_documento'])
                ->default('sin_documento')
                ->after('cliente_documento');

            /* Desglose fiscal. Sin esto no se puede emitir el comprobante ni
               armar el reporte para el contador. */
            $table->decimal('subtotal_gravado', 10, 2)->default(0)->after('total');
            $table->decimal('subtotal_exonerado', 10, 2)->default(0)->after('subtotal_gravado');
            $table->decimal('igv', 10, 2)->default(0)->after('subtotal_exonerado');

            /* Tasa vigente al momento de la venta, guardada en el propio
               documento. Si el IGV cambia el año que viene, los comprobantes
               antiguos deben seguir mostrando la tasa con la que se emitieron:
               recalcularlos con la tasa nueva falsearía el histórico. */
            $table->decimal('tasa_igv', 5, 4)->default(0.1800)->after('igv');

            $table->string('observacion', 500)->nullable()->after('estado_pago');
        });

        /* Se vuelve opcional en una sentencia aparte: cambiar una columna con
           clave foránea requiere tratarla por separado. */
        Schema::table('pedidos', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendedor_id');
            $table->dropColumn([
                'origen', 'cliente_nombre', 'cliente_documento', 'cliente_tipo_documento',
                'subtotal_gravado', 'subtotal_exonerado', 'igv', 'tasa_igv', 'observacion',
            ]);
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('afecto_igv');
        });
    }
};
