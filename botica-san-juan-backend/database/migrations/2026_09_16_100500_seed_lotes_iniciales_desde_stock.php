<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Traslada el stock existente a la nueva estructura de lotes.
 *
 * HONESTIDAD SOBRE LO QUE ESTA MIGRACIÓN PUEDE Y NO PUEDE HACER
 *
 * Hoy hay 3361 productos con una cantidad y ninguna información de lote ni de
 * vencimiento, porque el esquema anterior no la guardaba. Esa información no
 * existe en ningún sitio: no se puede deducir, inventar ni estimar.
 *
 * Así que cada producto con stock recibe UN lote inicial, marcado como tal,
 * con la cantidad que ya tenía y SIN fecha de vencimiento. Es un punto de
 * partida sincero, no un dato real.
 *
 * Consecuencias prácticas, que conviene tener presentes:
 *
 *   · FEFO no podrá priorizar dentro de ese stock heredado: sin fecha no hay
 *     orden posible. Sí funcionará en cuanto entren compras nuevas con su
 *     lote y vencimiento, porque un lote con fecha siempre se prioriza sobre
 *     uno sin ella.
 *   · Las alertas de vencimiento empezarán vacías y se irán poblando con cada
 *     ingreso de mercadería.
 *   · La regularización real se hace con un conteo físico en la botica,
 *     anotando lote y vencimiento de lo que hay en el anaquel. Es trabajo
 *     manual y no hay atajo; el sistema debe facilitarlo, no fingir que ya
 *     está hecho.
 *
 * La migración es reversible y no destruye nada: `productos.stock` se queda
 * donde está.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        /* Por bloques, para no cargar 3361 productos en memoria de golpe. */
        DB::table('productos')
            ->select('id', 'stock')
            ->where('stock', '>', 0)
            ->orderBy('id')
            ->chunk(500, function ($productos) use ($ahora) {
                $filas = [];

                foreach ($productos as $producto) {
                    $filas[] = [
                        'producto_id'       => $producto->id,
                        'codigo_lote'       => 'INICIAL',
                        'fecha_vencimiento' => null,
                        'cantidad_inicial'  => $producto->stock,
                        'cantidad_actual'   => $producto->stock,
                        'costo_unitario'    => null,
                        'compra_id'         => null,
                        'estado'            => 'activo',
                        'observacion'       => 'Stock heredado del sistema anterior. '
                                             . 'Lote y vencimiento pendientes de conteo fisico.',
                        'created_at'        => $ahora,
                        'updated_at'        => $ahora,
                    ];
                }

                if ($filas !== []) {
                    DB::table('lotes')->insert($filas);
                }
            });
    }

    public function down(): void
    {
        /* Sólo se eliminan los lotes que creó esta migración. Los lotes reales
           que se hayan registrado después no se tocan. */
        DB::table('lotes')
            ->where('codigo_lote', 'INICIAL')
            ->whereNull('fecha_vencimiento')
            ->delete();
    }
};
