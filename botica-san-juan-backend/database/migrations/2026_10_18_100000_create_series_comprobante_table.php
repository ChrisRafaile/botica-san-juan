<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cierra la condicion de carrera del correlativo de comprobantes (C-03).
 *
 * QUE HACE
 *  1. Crea `series_comprobante`: una fila por serie con el ultimo numero
 *     emitido. Esa fila existe siempre, de modo que se puede bloquear con
 *     `SELECT ... FOR UPDATE` dentro de la misma transaccion que crea el
 *     comprobante (docs/planes/bloque3-correlativo-y-ubl.md, seccion 2).
 *  2. La siembra con el maximo `numero` ya emitido por serie en
 *     `comprobantes_electronicos`, para no repetir ni saltar ningun numero.
 *  3. Anade un UNIQUE parcial sobre `comprobantes_electronicos.pedido_id`, para
 *     que "un pedido, un comprobante" lo garantice el motor y no el orden de
 *     ejecucion de dos peticiones.
 *
 * POR QUE PUEDE FALLAR, Y ESTA BIEN QUE FALLE
 * Si ya existen dos comprobantes del mismo pedido (el hueco que dejaba la
 * comprobacion fuera de la transaccion), el indice no se puede crear. La
 * migracion aborta con los pedidos afectados y NO borra nada: decidir cual de
 * los dos es el valido es una decision fiscal, no tecnica.
 *
 * SOBRE EL PREDICADO DEL INDICE PARCIAL
 * `pedido_id` es NOT NULL y hoy no existe un estado "anulada" (el enum de
 * `estado_sunat` es pendiente|aceptada|rechazada; una rechazada conserva su
 * numero y sigue ocupando el pedido). Por eso el predicado es solo
 * `pedido_id IS NOT NULL`. Cuando se anada el estado de anulacion (paso 7 de la
 * Fase A del plan) hay que recrear este indice con
 * `AND estado_sunat <> 'anulada'`.
 *
 * `ambiente`: los comprobantes actuales se emitieron en modo simulado, asi que
 * la siembra los marca 'simulado'. Cuando haya envio real, las series de
 * 'beta' y 'produccion' son filas distintas y no contaminan esta numeracion.
 */
return new class extends Migration
{
    private const INDICE_PEDIDO = 'comprobantes_electronicos_pedido_id_unique_activo';

    public function up(): void
    {
        $this->abortarSiHayPedidosDuplicados();

        Schema::create('series_comprobante', function (Blueprint $table) {
            $table->id();
            $table->string('ambiente', 20)->default('simulado');
            $table->string('serie', 4);
            $table->string('tipo_comprobante', 20);
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();

            $table->unique(['ambiente', 'serie']);
        });

        /* Maximo ya emitido por cada serie existente. `MIN(tipo_comprobante)`
           solo desempata si una serie tuviera tipos mezclados, que no debe. */
        DB::statement(
            "INSERT INTO series_comprobante (ambiente, serie, tipo_comprobante, ultimo_numero, created_at, updated_at) "
            ."SELECT 'simulado', serie, MIN(tipo_comprobante), MAX(numero), NOW(), NOW() "
            .'FROM comprobantes_electronicos GROUP BY serie'
        );

        /* Las dos series en uso existen aunque aun no haya comprobantes. Si ya
           se sembraron arriba, ON CONFLICT no las toca. */
        DB::statement(
            "INSERT INTO series_comprobante (ambiente, serie, tipo_comprobante, ultimo_numero, created_at, updated_at) "
            ."VALUES ('simulado', 'B001', 'boleta', 0, NOW(), NOW()), "
            ."('simulado', 'F001', 'factura', 0, NOW(), NOW()) "
            .'ON CONFLICT (ambiente, serie) DO NOTHING'
        );

        DB::statement(
            'CREATE UNIQUE INDEX '.self::INDICE_PEDIDO.' ON comprobantes_electronicos (pedido_id) '
            .'WHERE pedido_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDICE_PEDIDO);
        Schema::dropIfExists('series_comprobante');
    }

    private function abortarSiHayPedidosDuplicados(): void
    {
        $duplicados = DB::table('comprobantes_electronicos')
            ->select('pedido_id', DB::raw('COUNT(*) AS cantidad'))
            ->whereNotNull('pedido_id')
            ->groupBy('pedido_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('pedido_id')
            ->limit(20)
            ->get();

        if ($duplicados->isEmpty()) {
            return;
        }

        $detalle = $duplicados
            ->map(fn ($f) => "pedido {$f->pedido_id} ({$f->cantidad} comprobantes)")
            ->implode(', ');

        throw new RuntimeException(
            'No se puede crear el indice unico de comprobantes por pedido: hay pedidos con mas de un '
            ."comprobante electronico: {$detalle}. La migracion no borra datos. Revise esos "
            .'comprobantes con el responsable fiscal, anule o corrija los sobrantes y vuelva a migrar.'
        );
    }
};
