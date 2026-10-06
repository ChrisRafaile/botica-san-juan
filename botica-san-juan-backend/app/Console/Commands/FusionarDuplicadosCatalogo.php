<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fusiona las filas del catálogo que son la misma mercancía.
 *
 *   php artisan catalogo:fusionar-duplicados                      → SIMULA
 *   php artisan catalogo:fusionar-duplicados --aplicar             → escribe
 *   php artisan catalogo:fusionar-duplicados --sumar-stock         → suma el stock
 *
 * POR QUÉ EXISTE
 * El catálogo se importó varias veces y quedaron 869 grupos de filas idénticas
 * en todos los campos de negocio. El mostrador y el portal muestran como
 * productos distintos lo que es la misma caja.
 *
 * POR QUÉ NO SE AGRUPA POR PRECIO
 * Dos filas iguales con precio distinto son esa misma mercancía reimportada a
 * otro precio, no dos productos. Agrupar por precio dejaría fuera justo los
 * casos que hay que fusionar. El superviviente se queda con el precio del id
 * MAYOR porque la importación más reciente es la que trae el precio vigente.
 *
 * POR QUÉ EL STOCK **NO** SE SUMA
 * De los 869 grupos, 858 tienen el stock IDÉNTICO en todas sus copias
 * (8,8,8,8 · 10,10,10,10 · 12,12,12,12). Cuatro copias con exactamente la misma
 * cantidad no son cuatro entregas distintas: son la MISMA mercancía física,
 * reimportada cuatro veces desde el mismo archivo. Sumarla llevaba el inventario
 * de esos grupos de 4 434 a 16 975 unidades, es decir, hacía que el punto de
 * venta prometiera existencias que no están en el anaquel. Y una promesa de
 * stock que el anaquel no puede cumplir cuesta más que un catálogo duplicado.
 *
 * Por omisión, entonces: el superviviente conserva SOLO sus propios lotes y los
 * de las copias se eliminan, porque representan las mismas unidades físicas ya
 * contadas. El stock se recalcula desde los lotes que le quedan. La bandera
 * `--sumar-stock` restaura el comportamiento anterior para el caso en que las
 * copias sí sean entregas físicas distintas.
 *
 * POR QUÉ EL SUPERVIVIENTE SE ELIGE POR ACTIVIDAD Y NO POR ID MENOR
 * (esto es lo que cambió)
 * La versión anterior se quedaba siempre con el id menor y omitía como
 * "ambiguos" los 11 grupos cuyo stock difiere entre copias. Medidos esos 11
 * grupos contra la base, resulta que no son ambiguos: en todos, exactamente UNA
 * fila tiene actividad (filas en `movimientos_stock`, `pedido_detalles` o
 * `incidencias_venta`) y las demás copias tienen CERO actividad y conservan
 * clavado el valor de la importación. Ejemplos reales:
 *
 *   A FOLIC                 id 1   → stock 3, 2 ventas  | 755/1625/2495 → 5, sin actividad
 *   AB MOKS                 id 6   → stock 0, movimiento+venta+2 incidencias | 760/1630/2500 → 6
 *   AMOXICILINA PHARMAGEN   id 835 → stock 2, 1 movimiento + 1 venta | 72/1705/2575 → 3
 *
 * La fila con actividad es la que se ha estado vendiendo: su stock es el que el
 * anaquel refleja y las copias son el valor de importación sin tocar. Por eso el
 * superviviente es ESA fila, sea cual sea su id. Fíjate en AMOXICILINA: la fila
 * viva es 835 y el id menor es 72. Quedarse con 72 le devolvería al producto una
 * unidad que ya se vendió y dejaría la historia de esa venta colgando de una
 * fila que deja de ser la principal.
 *
 * La regla completa, aplicada a TODOS los grupos:
 *   - exactamente UNA fila con actividad → esa es la superviviente (su stock y
 *     sus lotes son los que quedan);
 *   - NINGUNA fila con actividad         → la de id MENOR, como siempre;
 *   - MÁS DE UNA fila con actividad      → el grupo se omite y se lista.
 *
 * POR QUÉ "STOCK DISTINTO" YA NO ES EL CRITERIO DE AMBIGÜEDAD
 * Que el stock difiera no dice nada por sí mismo: con una sola fila viva, la
 * diferencia se explica sola (lo vendido). Lo que de verdad no se puede resolver
 * a máquina es que DOS filas tengan historia propia: ahí hay dos hilos de
 * trazabilidad y elegir uno significa decidir qué ventas cuelgan de qué fila.
 * Eso se decide a mano, así que esos grupos se omiten, se cuentan aparte y se
 * listan con sus ids, sus stocks y su actividad.
 *
 * POR QUÉ SE QUITÓ `--incluir-ambiguos`
 * Esa bandera fusionaba los grupos de stock discordante tomando el MÁXIMO. Con
 * el criterio nuevo eso ya no tiene sentido en ninguna de las dos direcciones:
 * los grupos que antes activaba hoy se fusionan solos y con el stock de la fila
 * viva (que suele ser el MÍNIMO, no el máximo, porque es la que vendió), y los
 * que hoy quedan ambiguos tienen dos historias de venta, de modo que "tomar el
 * máximo" elegiría un stock sin mirar a qué fila pertenece la trazabilidad.
 * Mantener la bandera con el nombre viejo y una semántica nueva sería mentir en
 * la ayuda del comando; mantenerla con la semántica vieja sería ofrecer un
 * atajo que estropea justo el caso que queda por revisar. Así que se eliminó:
 * esos grupos se resuelven a mano o no se resuelven.
 *
 * POR QUÉ UN LOTE CON MOVIMIENTOS NO SE BORRA
 * Un lote que ya aparece en `movimientos_stock` o en `pedido_detalle_lotes` es
 * la prueba de qué tanda se vendió en una boleta ya emitida. Borrarlo no solo
 * perdería esa trazabilidad: `movimientos_stock.lote_id` es ON DELETE SET NULL,
 * así que el borrado la destruiría EN SILENCIO. Esos lotes se reapuntan al
 * superviviente y se dejan en `cantidad_actual = 0` / `estado = agotado`: la
 * venta sigue sabiendo de dónde salió y el lote no vuelve a entrar en el FEFO.
 *
 * POR QUÉ SIMULA POR OMISIÓN
 * Borra filas del catálogo de una botica que opera. Que haya que pedir
 * `--aplicar` obliga a leer el informe antes, y todo va en una sola
 * transacción para que un fallo a mitad no deje el inventario partido.
 */
class FusionarDuplicadosCatalogo extends Command
{
    protected $signature = 'catalogo:fusionar-duplicados
        {--aplicar : Escribe los cambios. Sin esta opción solo simula}
        {--sumar-stock : Suma el stock de las copias en el superviviente en vez de descartarlo; úsala solo si las copias corresponden a entregas físicas distintas y no a una reimportación del catálogo}';

    protected $description = 'Fusiona las filas duplicadas del catálogo sin inflar el inventario (ver --sumar-stock)';

    /**
     * Campos que definen la identidad de la mercancía.
     *
     * El precio NO está aquí a propósito (ver cabecera de la clase).
     */
    private const CAMPOS_IDENTIDAD = [
        'nombre',
        'concentracion',
        'presentacion',
        'laboratorio',
        'tipo',
        'adicional',
        'codigo_digemid',
    ];

    /**
     * Tablas que apuntan a productos.id y hay que reapuntar al superviviente.
     *
     * `lotes` NO está aquí: su tratamiento depende de la estrategia de stock y
     * se resuelve en `resolverLotes()`. Si mañana apareciera una séptima tabla,
     * el borrado de los perdedores la arrastraría en cascada sin avisar: por eso
     * la lista se mantiene explícita y a la vista.
     */
    private const TABLAS_A_REAPUNTAR = [
        'pedido_detalles',
        'movimientos_stock',
        'conteo_detalles',
        'incidencias_venta',
        'carrito',
    ];

    /**
     * Tablas cuya presencia prueba que una fila del catálogo está VIVA.
     *
     * Son las tres que dejan rastro de que esa fila se movió de verdad: un
     * movimiento de inventario, una línea de venta o una incidencia de
     * mostrador. Si una fila aparece en cualquiera de ellas, su stock es el
     * resultado de operar y no el valor clavado de la importación.
     *
     * `conteo_detalles` y `carrito` quedan FUERA a propósito, aunque también
     * apuntan a productos: un conteo por ciclos es una propuesta de inventario
     * (puede listar las dos copias sin que ninguna se haya vendido) y un
     * carrito es una intención de compra que nadie ha confirmado. Ninguna de
     * las dos explica una diferencia de stock, así que tomarlas por actividad
     * convertiría en "viva" una fila que nunca se tocó.
     */
    private const TABLAS_DE_ACTIVIDAD = [
        'movimientos_stock',
        'pedido_detalles',
        'incidencias_venta',
    ];

    public function handle(): int
    {
        $aplicar    = (bool) $this->option('aplicar');
        $sumarStock = (bool) $this->option('sumar-stock');

        $todos = $this->buscarGrupos();

        $this->newLine();
        $this->info(($aplicar ? 'APLICANDO' : 'SIMULACIÓN').' · Fusión de duplicados del catálogo');
        $this->line(str_repeat('=', 72));
        $this->line('   Estrategia de stock: '.($sumarStock
            ? 'SUMAR los lotes de las copias (--sumar-stock)'
            : 'DESCARTAR los lotes de las copias (por omisión)'));
        $this->line('   Superviviente:       la ÚNICA fila con actividad; si no hay ninguna, el id MENOR');
        $this->line('   Grupos ambiguos:     más de una fila con actividad → OMITIR y listar');

        if ($todos === []) {
            $this->newLine();
            $this->info('No hay grupos duplicados. El catálogo ya está fusionado.');

            return self::SUCCESS;
        }

        /* Los ambiguos se separan ANTES de tocar nada: con dos filas que tienen
           historia propia, decidir de qué fila cuelga cada venta es una decisión
           de negocio, no de comando. */
        $ambiguos  = array_values(array_filter($todos, fn ($g) => $g['ambiguo']));
        $aFusionar = array_values(array_filter($todos, fn ($g) => ! $g['ambiguo']));

        /* Los que hay que poder enseñar: el superviviente NO es el id menor
           porque la fila viva era otra. Son los que cambian respecto a la regla
           anterior, así que se listan uno por uno. */
        $porActividad    = array_values(array_filter($aFusionar, fn ($g) => $g['motivo'] === 'actividad'));
        $noMinimoElegido = array_values(array_filter($porActividad, fn ($g) => $g['superviviente'] !== min($g['ids'])));

        $filasAEliminar = 0;
        foreach ($aFusionar as $grupo) {
            $filasAEliminar += count($grupo['ids']) - 1;
        }

        $this->newLine();
        $this->line(sprintf('   %-44s %6d', 'Grupos duplicados encontrados', count($todos)));
        $this->line(sprintf('   %-44s %6d', 'Grupos que se fusionan', count($aFusionar)));
        $this->line(sprintf('   %-44s %6d', 'Grupos ambiguos omitidos', count($ambiguos)));
        $this->line(sprintf('   %-44s %6d', 'Filas que se eliminarán', $filasAEliminar));
        $this->line(sprintf('   %-44s %6d', 'Filas que quedarán como supervivientes', count($aFusionar)));
        $this->newLine();
        $this->line(sprintf('   %-44s %6d', 'Supervivientes elegidos por ACTIVIDAD', count($porActividad)));
        $this->line(sprintf('   %-44s %6d', '  de ellos, el superviviente NO es el id menor', count($noMinimoElegido)));
        $this->line(sprintf('   %-44s %6d', 'Supervivientes elegidos por id MENOR', count($aFusionar) - count($porActividad)));

        $this->mostrarElegidosPorActividad($noMinimoElegido);
        $this->mostrarAmbiguos($ambiguos);

        if ($aFusionar === []) {
            $this->newLine();
            $this->warn('No queda ningún grupo por fusionar.');

            return self::SUCCESS;
        }

        $this->mostrarPrimerosGrupos($aFusionar);

        /* Una sola transacción: o queda todo fusionado o no cambia nada. En
           simulación se revierte al final, de modo que el informe sale de
           ejecutar el trabajo real y no de estimarlo. */
        DB::beginTransaction();

        try {
            $resumen = $this->fusionar($aFusionar, $sumarStock);

            if ($aplicar) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->newLine();
            $this->error('La fusión falló y se revirtió entera: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->mostrarResumen($resumen, $filasAEliminar, $sumarStock);

        $this->newLine();

        if ($aplicar) {
            $this->info('Aplicado. Conviene correr ahora la migración del índice UNIQUE.');
        } else {
            $this->comment('Simulación: no se escribió nada. Añade --aplicar para ejecutarlo.');
        }

        return self::SUCCESS;
    }

    /* ==================================================================== */

    /**
     * Grupos de productos que son la misma mercancía.
     *
     * Se resuelve en una sola consulta agrupada en lugar de traer los 3 361
     * productos a PHP: GROUP BY en Postgres considera iguales dos NULL, que es
     * exactamente lo que hace falta aquí porque `adicional` y `codigo_digemid`
     * están vacíos en buena parte del catálogo.
     *
     * El stock de cada copia se mide desde los LOTES y no desde
     * `productos.stock`, porque los lotes son la fuente de verdad del
     * inventario y son los que la fusión va a mover.
     *
     * @return array<int, array{
     *     ids: array<int, int>,
     *     datos: array<string, mixed>,
     *     stocks: array<int, int>,
     *     actividad: array<int, int>,
     *     vivos: array<int, int>,
     *     superviviente: int,
     *     motivo: string,
     *     ambiguo: bool
     * }>
     */
    private function buscarGrupos(): array
    {
        $campos = implode(', ', self::CAMPOS_IDENTIDAD);

        $filas = DB::table('productos')
            ->selectRaw($campos.', string_agg(id::text, \',\' ORDER BY id) AS ids')
            ->groupBy(self::CAMPOS_IDENTIDAD)
            ->havingRaw('count(*) > 1')
            ->orderByRaw('min(id)')
            ->get();

        if ($filas->isEmpty()) {
            return [];
        }

        /* Una sola consulta para el stock por lotes de todos los productos
           implicados, en vez de una por producto dentro del bucle. */
        $idsImplicados = [];
        foreach ($filas as $fila) {
            foreach (explode(',', $fila->ids) as $id) {
                $idsImplicados[] = (int) $id;
            }
        }

        $stockPorProducto = DB::table('productos as p')
            ->leftJoin('lotes as l', 'l.producto_id', '=', 'p.id')
            ->whereIn('p.id', $idsImplicados)
            ->groupBy('p.id')
            ->selectRaw('p.id, COALESCE(SUM(l.cantidad_actual), 0) AS stock')
            ->pluck('stock', 'id');

        $actividadPorProducto = $this->contarActividad($idsImplicados);

        $grupos = [];

        foreach ($filas as $fila) {
            $ids = array_map('intval', explode(',', $fila->ids));

            $datos = [];
            foreach (self::CAMPOS_IDENTIDAD as $campo) {
                $datos[$campo] = $fila->{$campo};
            }

            $stocks    = [];
            $actividad = [];
            foreach ($ids as $id) {
                $stocks[$id]    = (int) ($stockPorProducto[$id] ?? 0);
                $actividad[$id] = (int) ($actividadPorProducto[$id] ?? 0);
            }

            /* Las filas que se han movido de verdad. Son las que mandan: su
               stock es el del anaquel. */
            $vivos = array_values(array_filter($ids, fn ($id) => $actividad[$id] > 0));

            $grupos[] = [
                'ids'       => $ids,
                'datos'     => $datos,
                'stocks'    => $stocks,
                'actividad' => $actividad,
                'vivos'     => $vivos,
                /* Una sola fila viva: su stock explica la diferencia con las
                   copias, que siguen clavadas en el valor de importación.
                   Ninguna viva: ninguna se tocó, da igual cuál se quede y el id
                   menor es la elección estable. */
                'superviviente' => count($vivos) === 1 ? $vivos[0] : $ids[0],
                'motivo'        => count($vivos) === 1 ? 'actividad' : 'id-menor',
                /* Dos filas con historia propia: hay dos hilos de trazabilidad
                   y elegir uno es decidir de qué fila cuelgan qué ventas. */
                'ambiguo'       => count($vivos) > 1,
            ];
        }

        return $grupos;
    }

    /**
     * Filas de actividad por producto, sumando las tres tablas que la prueban.
     *
     * Una consulta agregada por tabla (tres en total) en vez de una por
     * producto: son 3 346 productos implicados.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function contarActividad(array $ids): array
    {
        $total = [];

        foreach (self::TABLAS_DE_ACTIVIDAD as $tabla) {
            $conteos = DB::table($tabla)
                ->whereIn('producto_id', $ids)
                ->groupBy('producto_id')
                ->selectRaw('producto_id, count(*) AS n')
                ->pluck('n', 'producto_id');

            foreach ($conteos as $id => $n) {
                $total[(int) $id] = ($total[(int) $id] ?? 0) + (int) $n;
            }
        }

        return $total;
    }

    /**
     * Hace el trabajo. Asume que ya hay una transacción abierta.
     *
     * @param  array<int, array<string, mixed>>  $grupos
     * @return array<string, mixed>
     */
    private function fusionar(array $grupos, bool $sumarStock): array
    {
        $reapuntadas = array_fill_keys(array_merge(['lotes'], self::TABLAS_A_REAPUNTAR), 0);

        $lotesEliminados    = 0;
        $lotesConservados   = 0;
        $codigosAjustados   = 0;
        $conteosDescartados = 0;
        $preciosCambiados   = 0;
        $eliminadas         = 0;
        $stockRecalculado   = 0;
        $unidadesFantasma   = 0;
        $elegidosActividad  = 0;

        foreach ($grupos as $grupo) {
            $ids           = $grupo['ids'];
            $superviviente = (int) $grupo['superviviente'];
            $perdedores    = array_values(array_diff($ids, [$superviviente]));
            $idMayor       = max($ids);

            if ($grupo['motivo'] === 'actividad') {
                $elegidosActividad++;
            }

            /* El precio vigente es el de la última importación, es decir el
               del id mayor del grupo. Esto no cambia con la regla nueva: el
               superviviente aporta el STOCK y la trazabilidad, el id mayor
               aporta el PRECIO. */
            $precioVigente = DB::table('productos')->where('id', $idMayor)->value('precio');
            $precioActual  = DB::table('productos')->where('id', $superviviente)->value('precio');

            if ((string) $precioVigente !== (string) $precioActual) {
                DB::table('productos')->where('id', $superviviente)->update(['precio' => $precioVigente]);
                $preciosCambiados++;
            }

            $conteosDescartados += $this->resolverConteosRepetidos($superviviente, $perdedores);

            $lotes = $this->resolverLotes($superviviente, $perdedores, $sumarStock);

            $reapuntadas['lotes'] += $lotes['reapuntados'];
            $lotesEliminados      += $lotes['eliminados'];
            $lotesConservados     += $lotes['conservadosACero'];
            $codigosAjustados     += $lotes['codigosAjustados'];

            foreach (self::TABLAS_A_REAPUNTAR as $tabla) {
                $reapuntadas[$tabla] += DB::table($tabla)
                    ->whereIn('producto_id', $perdedores)
                    ->update(['producto_id' => $superviviente]);
            }

            /* Recién ahora, con los lotes del superviviente ya resueltos, el
               stock se puede recalcular desde la fuente de verdad. */
            $stock = (int) DB::table('lotes')
                ->where('producto_id', $superviviente)
                ->sum('cantidad_actual');

            DB::table('productos')->where('id', $superviviente)->update(['stock' => $stock]);
            $stockRecalculado++;

            /* Lo que habría entrado al inventario si se hubieran sumado las
               copias y no entra: el número que hay que poder enseñar para
               explicar qué se evitó. */
            $unidadesFantasma += array_sum($grupo['stocks']) - $stock;

            $eliminadas += DB::table('productos')->whereIn('id', $perdedores)->delete();
        }

        return [
            'reapuntadas'        => $reapuntadas,
            'lotesEliminados'    => $lotesEliminados,
            'lotesConservados'   => $lotesConservados,
            'codigosAjustados'   => $codigosAjustados,
            'conteosDescartados' => $conteosDescartados,
            'preciosCambiados'   => $preciosCambiados,
            'eliminadas'         => $eliminadas,
            'stockRecalculado'   => $stockRecalculado,
            'unidadesFantasma'   => $unidadesFantasma,
            'elegidosActividad'  => $elegidosActividad,
        ];
    }

    /**
     * Decide qué lotes se queda el superviviente y qué pasa con el resto.
     *
     * Con `--sumar-stock` se reapuntan todos: el inventario queda sumado.
     *
     * Por omisión el superviviente conserva SOLO sus propios lotes. Con la regla
     * nueva eso es además lo correcto por construcción: el superviviente es la
     * fila viva cuando hay una, así que sus lotes son los que han visto las
     * ventas, y los de las copias son las mismas unidades físicas ya contadas.
     * (Antes había que elegir un "donante" distinto del superviviente para los
     * grupos ambiguos incluidos a mano; esa figura desapareció junto con
     * `--incluir-ambiguos`, porque el superviviente ya es, por definición, el
     * dueño del stock bueno.)
     *
     * Los lotes que sobran se eliminan, salvo los que tengan trazabilidad, que
     * se conservan a cero.
     *
     * @param  array<int, int>  $perdedores
     * @return array{reapuntados: int, eliminados: int, conservadosACero: int, codigosAjustados: int}
     */
    private function resolverLotes(int $superviviente, array $perdedores, bool $sumarStock): array
    {
        $reapuntados      = 0;
        $eliminados       = 0;
        $conservadosACero = 0;
        $codigosAjustados = 0;

        foreach ($this->lotesDe($perdedores) as $lote) {
            if ($sumarStock) {
                $codigosAjustados += (int) $this->reapuntarLote($lote, $superviviente);
                $reapuntados++;

                continue;
            }

            if ($this->tieneTrazabilidad((int) $lote->id)) {
                /* No se puede borrar en silencio: hay una venta o un
                   movimiento que apunta a este lote. */
                $codigosAjustados += (int) $this->reapuntarLote($lote, $superviviente);
                $reapuntados++;

                DB::table('lotes')->where('id', $lote->id)->update([
                    'cantidad_actual' => 0,
                    'estado'          => 'agotado',
                ]);

                $conservadosACero++;

                continue;
            }

            $eliminados += DB::table('lotes')->where('id', $lote->id)->delete();
        }

        return compact('reapuntados', 'eliminados', 'conservadosACero', 'codigosAjustados');
    }

    /**
     * @param  array<int, int>  $productos
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function lotesDe(array $productos)
    {
        if ($productos === []) {
            return collect();
        }

        return DB::table('lotes')
            ->whereIn('producto_id', $productos)
            ->orderBy('id')
            ->get(['id', 'producto_id', 'codigo_lote', 'cantidad_actual']);
    }

    /**
     * Un lote citado por un movimiento o por el detalle de un pedido.
     *
     * `pedido_detalle_lotes.lote_id` es ON DELETE RESTRICT (el borrado
     * reventaría la transacción) y `movimientos_stock.lote_id` es ON DELETE SET
     * NULL (el borrado perdería el rastro sin decir nada). Las dos razones
     * apuntan a lo mismo: este lote no se borra.
     */
    private function tieneTrazabilidad(int $loteId): bool
    {
        if (DB::table('movimientos_stock')->where('lote_id', $loteId)->exists()) {
            return true;
        }

        return DB::table('pedido_detalle_lotes')->where('lote_id', $loteId)->exists();
    }

    /**
     * Cuelga un lote del superviviente sin chocar contra
     * `lotes_producto_codigo_unique` (producto_id, codigo_lote).
     *
     * La migración que sembró el inventario puso `codigo_lote = 'INICIAL'` en
     * TODOS los lotes heredados, así que en cuanto un lote se mueve de producto
     * el código se repite dentro del superviviente y Postgres aborta. Los lotes
     * no se fusionan entre sí: dos lotes son dos tandas físicas con su propio
     * vencimiento y juntarlos destruiría el FEFO. Cuando el código ya está
     * ocupado se le añade un sufijo que deja rastro de dónde venía el lote.
     *
     * Con la estrategia por omisión esto casi nunca pasa (los lotes de las
     * copias se eliminan, no se mueven); queda por los dos casos en que un lote
     * sí cambia de producto: `--sumar-stock` y el lote con trazabilidad.
     *
     * @return bool true si hubo que ajustar el código
     */
    private function reapuntarLote(object $lote, int $superviviente): bool
    {
        $codigo   = (string) $lote->codigo_lote;
        $original = (int) $lote->producto_id;
        $ajustado = false;

        $ocupado = fn (string $c) => DB::table('lotes')
            ->where('producto_id', $superviviente)
            ->where('codigo_lote', $c)
            ->exists();

        if ($ocupado($codigo)) {
            /* El código cabe en 60 caracteres; se recorta para que el sufijo
               entre siempre. */
            $base   = mb_substr($codigo, 0, 40);
            $sufijo = 1;

            do {
                $codigo = $base.'-DUP'.$original.($sufijo > 1 ? '-'.$sufijo : '');
                $sufijo++;
            } while ($ocupado($codigo));

            $ajustado = true;
        }

        DB::table('lotes')->where('id', $lote->id)->update([
            'producto_id' => $superviviente,
            'codigo_lote' => $codigo,
        ]);

        return $ajustado;
    }

    /**
     * Evita que el reapuntado choque contra el unique (conteo_id, producto_id).
     *
     * Un conteo por ciclos pudo proponer dos copias del mismo producto en la
     * misma sesión. Físicamente es un solo anaquel contado una vez, así que
     * sobra una fila: se conserva la que de verdad se contó y se descarta la
     * otra. Si ambas o ninguna tienen cuenta, se conserva la del superviviente
     * por ser la fila que seguirá existiendo.
     *
     * @param  array<int, int>  $perdedores
     */
    private function resolverConteosRepetidos(int $superviviente, array $perdedores): int
    {
        $descartados = 0;

        $conflictos = DB::table('conteo_detalles as entrante')
            ->join('conteo_detalles as actual', function ($union) use ($superviviente) {
                $union->on('actual.conteo_id', '=', 'entrante.conteo_id')
                    ->where('actual.producto_id', '=', $superviviente);
            })
            ->whereIn('entrante.producto_id', $perdedores)
            ->get([
                'entrante.id as entrante_id',
                'entrante.cantidad_contada as entrante_contada',
                'actual.id as actual_id',
                'actual.cantidad_contada as actual_contada',
            ]);

        foreach ($conflictos as $conflicto) {
            $sobraLaActual = $conflicto->actual_contada === null
                && $conflicto->entrante_contada !== null;

            $aBorrar = $sobraLaActual ? $conflicto->actual_id : $conflicto->entrante_id;

            $descartados += DB::table('conteo_detalles')->where('id', $aBorrar)->delete();
        }

        return $descartados;
    }

    /* ==================================================================== */

    /**
     * Los grupos en que el superviviente NO es el id menor.
     *
     * Es la parte del informe que hay que poder defender: en estos grupos la
     * fusión conserva una fila que la regla anterior habría eliminado. Se dice
     * el id elegido, su actividad y qué habría pasado con el id menor.
     *
     * @param  array<int, array<string, mixed>>  $grupos
     */
    private function mostrarElegidosPorActividad(array $grupos): void
    {
        if ($grupos === []) {
            return;
        }

        $this->newLine();
        $this->line('SUPERVIVIENTE ELEGIDO POR ACTIVIDAD (no es el id menor)');
        $this->line(str_repeat('-', 72));

        foreach ($grupos as $i => $grupo) {
            $elegido = (int) $grupo['superviviente'];
            $menor   = min($grupo['ids']);

            $this->line(sprintf('  %2d. %s', $i + 1, $this->describir($grupo['datos'])));
            $this->line('      ids:    '.implode(', ', $grupo['ids']));
            $this->line(sprintf(
                '      elegido id %d · stock %d · %d filas de actividad (movimientos/ventas/incidencias)',
                $elegido,
                $grupo['stocks'][$elegido],
                $grupo['actividad'][$elegido]
            ));
            $this->line(sprintf(
                '      motivo: es la ÚNICA fila con actividad del grupo; el id menor %d tiene 0 y stock %d (valor de importación)',
                $menor,
                $grupo['stocks'][$menor]
            ));
        }
    }

    /**
     * Los grupos que el comando se niega a adivinar.
     *
     * Con el criterio nuevo son los que tienen MÁS DE UNA fila con actividad:
     * dos historias de venta y ninguna forma de decidir a máquina de qué fila
     * cuelga cada una. Se listan con ids, stocks y actividad porque son los
     * únicos que alguien tiene que mirar a mano.
     *
     * @param  array<int, array<string, mixed>>  $ambiguos
     */
    private function mostrarAmbiguos(array $ambiguos): void
    {
        if ($ambiguos === []) {
            $this->newLine();
            $this->line('No queda ningún grupo ambiguo: en todos hay como máximo una fila con actividad.');

            return;
        }

        $this->newLine();
        $this->line('GRUPOS AMBIGUOS OMITIDOS · más de una fila con actividad, revisar a mano');
        $this->line(str_repeat('-', 72));

        foreach ($ambiguos as $i => $grupo) {
            $this->line(sprintf('  %2d. %s', $i + 1, $this->describir($grupo['datos'])));
            $this->line('      ids:       '.implode(', ', $grupo['ids']));
            $this->line('      stocks:    '.implode(', ', array_map(
                fn ($id) => $id.' → '.$grupo['stocks'][$id],
                $grupo['ids']
            )));
            $this->line('      actividad: '.implode(', ', array_map(
                fn ($id) => $id.' → '.$grupo['actividad'][$id],
                $grupo['ids']
            )));
            $this->line('      vivas:     '.implode(', ', $grupo['vivos']));
        }

        $this->newLine();
        $this->comment('   Estos grupos no se tocaron: hay dos filas con historia propia y decidir');
        $this->comment('   de qué fila cuelga cada venta es una decisión de negocio, no del comando.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $grupos
     */
    private function mostrarPrimerosGrupos(array $grupos): void
    {
        $this->newLine();
        $this->line('PRIMEROS 10 GRUPOS');
        $this->line(str_repeat('-', 72));

        foreach (array_slice($grupos, 0, 10) as $i => $grupo) {
            $ids           = $grupo['ids'];
            $superviviente = (int) $grupo['superviviente'];
            $idMayor       = max($ids);

            $precios = DB::table('productos')
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->pluck('precio', 'id');

            $this->newLine();
            $this->line(sprintf('  %2d. %s', $i + 1, $this->describir($grupo['datos'])));
            $this->line('      ids: '.implode(', ', $ids));

            foreach ($ids as $id) {
                $etiqueta = $id === $superviviente ? 'SUPERVIVIENTE' : 'se elimina';

                $this->line(sprintf(
                    '      id %-6d precio %-9s lotes suman %-6d actividad %-4d %s',
                    $id,
                    $precios[$id] ?? '-',
                    $grupo['stocks'][$id],
                    $grupo['actividad'][$id],
                    $etiqueta
                ));
            }

            $this->line(sprintf(
                '      superviviente: id %d (%s)',
                $superviviente,
                $grupo['motivo'] === 'actividad'
                    ? 'única fila con actividad del grupo'
                    : 'ninguna fila tiene actividad → id menor'
            ));
            $this->line(sprintf(
                '      precio del superviviente: %s (tomado del id mayor %d, importación más reciente)',
                $precios[$idMayor] ?? '-',
                $idMayor
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function describir(array $datos): string
    {
        return trim(implode(' · ', array_filter([
            $datos['nombre'],
            $datos['concentracion'],
            $datos['presentacion'],
            $datos['laboratorio'],
            $datos['tipo'],
        ])));
    }

    /**
     * @param  array<string, mixed>  $resumen
     */
    private function mostrarResumen(array $resumen, int $filasAEliminar, bool $sumarStock): void
    {
        $this->newLine();
        $this->line('FILAS REAPUNTADAS AL SUPERVIVIENTE');
        $this->line(str_repeat('-', 72));

        foreach ($resumen['reapuntadas'] as $tabla => $n) {
            $this->line(sprintf('   %-44s %6d', $tabla, $n));
        }

        $this->newLine();
        $this->line('EFECTOS SOBRE EL CATÁLOGO');
        $this->line(str_repeat('-', 72));
        $this->line(sprintf('   %-44s %6d', 'Productos eliminados', $resumen['eliminadas']));
        $this->line(sprintf('   %-44s %6d', 'Supervivientes elegidos por actividad', $resumen['elegidosActividad']));
        $this->line(sprintf('   %-44s %6d', 'Supervivientes con stock recalculado', $resumen['stockRecalculado']));
        $this->line(sprintf('   %-44s %6d', 'Supervivientes con precio actualizado', $resumen['preciosCambiados']));
        $this->line(sprintf('   %-44s %6d', 'Conteos repetidos descartados', $resumen['conteosDescartados']));

        $this->newLine();
        $this->line('INVENTARIO');
        $this->line(str_repeat('-', 72));
        $this->line(sprintf('   %-44s %6d', 'Lotes eliminados (unidades ya contadas)', $resumen['lotesEliminados']));
        $this->line(sprintf('   %-44s %6d', 'Lotes conservados a cero por trazabilidad', $resumen['lotesConservados']));
        $this->line(sprintf('   %-44s %6d', 'Lotes con código ajustado al reapuntar', $resumen['codigosAjustados']));
        $this->line(sprintf('   %-44s %6d', 'Unidades fantasma descartadas', $resumen['unidadesFantasma']));

        $this->newLine();

        if ($sumarStock) {
            $this->warn('   --sumar-stock activa: el stock de las copias se SUMÓ en el superviviente.');
            $this->warn('   Correcto solo si cada copia era una entrega física distinta.');
        } else {
            $this->line(sprintf(
                '   Sumar las copias habría añadido %d unidades que no están en el anaquel.',
                $resumen['unidadesFantasma']
            ));
        }

        if ($resumen['eliminadas'] !== $filasAEliminar) {
            $this->newLine();
            $this->warn(sprintf(
                '   Se esperaba eliminar %d filas y se eliminaron %d. Revisar antes de aplicar.',
                $filasAEliminar,
                $resumen['eliminadas']
            ));
        }
    }
}
