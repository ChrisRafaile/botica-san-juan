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
 *   php artisan catalogo:fusionar-duplicados --incluir-ambiguos    → fusiona los dudosos
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
 * POR QUÉ EL STOCK **NO** SE SUMA (y esto es lo que cambió)
 * La primera versión de este comando reapuntaba los lotes de las copias al
 * superviviente, de modo que el stock quedaba sumado. Medido contra la base
 * real, eso está al revés del dato: de los 869 grupos, 858 tienen el stock
 * IDÉNTICO en todas sus copias (8,8,8,8 · 10,10,10,10 · 12,12,12,12). Cuatro
 * copias con exactamente la misma cantidad no son cuatro entregas distintas:
 * son la MISMA mercancía física, reimportada cuatro veces desde el mismo
 * archivo. Sumarla llevaba el inventario de esos grupos de 4 434 a 16 975
 * unidades, es decir, hacía que el punto de venta prometiera existencias que no
 * están en el anaquel. Y una promesa de stock que el anaquel no puede cumplir
 * cuesta más que un catálogo duplicado.
 *
 * Por omisión, entonces: el superviviente conserva SOLO sus propios lotes y los
 * de las copias se eliminan, porque representan las mismas unidades físicas ya
 * contadas. El stock se recalcula desde los lotes que le quedan. La bandera
 * `--sumar-stock` restaura el comportamiento anterior para el caso en que las
 * copias sí sean entregas físicas distintas.
 *
 * POR QUÉ LOS GRUPOS AMBIGUOS NO SE TOCAN
 * Los 11 grupos cuyo stock DIFIERE entre copias (3,5,5,5 · 1,4,4,4) no se
 * pueden resolver sin adivinar: puede ser una reimportación tras una venta o
 * pueden ser dos entregas. Adivinar ahí cuesta inventario real, así que se
 * omiten, se cuentan aparte y se listan con sus ids y sus stocks para revisión
 * manual. `--incluir-ambiguos` los fusiona tomando el stock MÁXIMO, por si el
 * dueño decide resolverlos en bloque.
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
        {--sumar-stock : Suma el stock de las copias en el superviviente en vez de descartarlo; úsala solo si las copias corresponden a entregas físicas distintas y no a una reimportación del catálogo}
        {--incluir-ambiguos : Fusiona también los grupos cuyo stock difiere entre copias, tomando el stock MÁXIMO del grupo}';

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

    public function handle(): int
    {
        $aplicar         = (bool) $this->option('aplicar');
        $sumarStock      = (bool) $this->option('sumar-stock');
        $incluirAmbiguos = (bool) $this->option('incluir-ambiguos');

        $todos = $this->buscarGrupos();

        $this->newLine();
        $this->info(($aplicar ? 'APLICANDO' : 'SIMULACIÓN').' · Fusión de duplicados del catálogo');
        $this->line(str_repeat('=', 72));
        $this->line('   Estrategia de stock: '.($sumarStock
            ? 'SUMAR los lotes de las copias (--sumar-stock)'
            : 'DESCARTAR los lotes de las copias (por omisión)'));
        $this->line('   Grupos ambiguos:     '.($incluirAmbiguos
            ? 'FUSIONAR tomando el máximo (--incluir-ambiguos)'
            : 'OMITIR y listar para revisión manual'));

        if ($todos === []) {
            $this->newLine();
            $this->info('No hay grupos duplicados. El catálogo ya está fusionado.');

            return self::SUCCESS;
        }

        /* Los ambiguos se separan ANTES de tocar nada: con la estrategia por
           omisión decidir su stock sería adivinar, y adivinar cuesta
           inventario real. */
        $ambiguos  = array_values(array_filter($todos, fn ($g) => $g['ambiguo']));
        $aFusionar = $incluirAmbiguos
            ? $todos
            : array_values(array_filter($todos, fn ($g) => ! $g['ambiguo']));

        $filasAEliminar = 0;
        foreach ($aFusionar as $grupo) {
            $filasAEliminar += count($grupo['ids']) - 1;
        }

        $this->newLine();
        $this->line(sprintf('   %-44s %6d', 'Grupos duplicados encontrados', count($todos)));
        $this->line(sprintf('   %-44s %6d', 'Grupos que se fusionan', count($aFusionar)));
        $this->line(sprintf('   %-44s %6d', 'Grupos ambiguos omitidos', $incluirAmbiguos ? 0 : count($ambiguos)));
        $this->line(sprintf('   %-44s %6d', 'Filas que se eliminarán', $filasAEliminar));
        $this->line(sprintf('   %-44s %6d', 'Filas que quedarán como supervivientes', count($aFusionar)));

        $this->mostrarAmbiguos($ambiguos, $incluirAmbiguos);

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

        $grupos = [];

        foreach ($filas as $fila) {
            $ids = array_map('intval', explode(',', $fila->ids));

            $datos = [];
            foreach (self::CAMPOS_IDENTIDAD as $campo) {
                $datos[$campo] = $fila->{$campo};
            }

            $stocks = [];
            foreach ($ids as $id) {
                $stocks[$id] = (int) ($stockPorProducto[$id] ?? 0);
            }

            $grupos[] = [
                'ids'     => $ids,
                'datos'   => $datos,
                'stocks'  => $stocks,
                /* Si todas las copias traen la misma cantidad, es la misma
                   mercancía contada varias veces. Si difieren, no hay forma de
                   saberlo sin mirar las compras. */
                'ambiguo' => count(array_unique($stocks)) > 1,
            ];
        }

        return $grupos;
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

        foreach ($grupos as $grupo) {
            $ids           = $grupo['ids'];
            $superviviente = $ids[0];
            $perdedores    = array_slice($ids, 1);
            $idMayor       = end($ids);

            /* El precio vigente es el de la última importación, es decir el
               del id mayor del grupo. */
            $precioVigente = DB::table('productos')->where('id', $idMayor)->value('precio');
            $precioActual  = DB::table('productos')->where('id', $superviviente)->value('precio');

            if ((string) $precioVigente !== (string) $precioActual) {
                DB::table('productos')->where('id', $superviviente)->update(['precio' => $precioVigente]);
                $preciosCambiados++;
            }

            $conteosDescartados += $this->resolverConteosRepetidos($superviviente, $perdedores);

            $lotes = $this->resolverLotes($grupo, $superviviente, $perdedores, $sumarStock);

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
        ];
    }

    /**
     * Decide qué lotes se queda el superviviente y qué pasa con el resto.
     *
     * Con `--sumar-stock` se reapuntan todos: el inventario queda sumado.
     *
     * Por omisión el superviviente conserva solo los lotes del DONANTE, que es
     * él mismo salvo en un grupo ambiguo fusionado con `--incluir-ambiguos`,
     * donde el donante es la copia de stock máximo (es lo que significa "tomar
     * el máximo" sin romper la invariante `productos.stock = SUM(lotes)`).
     * Los lotes que sobran son las mismas unidades físicas ya contadas: se
     * eliminan, salvo los que tengan trazabilidad, que se conservan a cero.
     *
     * @param  array<string, mixed>  $grupo
     * @param  array<int, int>  $perdedores
     * @return array{reapuntados: int, eliminados: int, conservadosACero: int, codigosAjustados: int}
     */
    private function resolverLotes(array $grupo, int $superviviente, array $perdedores, bool $sumarStock): array
    {
        $reapuntados      = 0;
        $eliminados       = 0;
        $conservadosACero = 0;
        $codigosAjustados = 0;

        if ($sumarStock) {
            foreach ($this->lotesDe($perdedores) as $lote) {
                $codigosAjustados += (int) $this->reapuntarLote($lote, $superviviente);
                $reapuntados++;
            }

            return compact('reapuntados', 'eliminados', 'conservadosACero', 'codigosAjustados');
        }

        /* El donante define el stock final. Con stock idéntico en todas las
           copias es el propio superviviente; en un grupo ambiguo incluido a
           mano, la copia que más tiene. */
        $donante = $superviviente;

        if ($grupo['ambiguo']) {
            $maximo = max($grupo['stocks']);
            foreach ($grupo['stocks'] as $id => $stock) {
                if ($stock === $maximo) {
                    $donante = (int) $id;
                    break;
                }
            }
        }

        /* Primero se vacía lo que no aporta, para que el reapuntado del donante
           no choque contra lotes que están a punto de desaparecer. */
        $aDescartar = array_values(array_diff(array_merge([$superviviente], $perdedores), [$donante]));

        foreach ($this->lotesDe($aDescartar) as $lote) {
            if ($this->tieneTrazabilidad((int) $lote->id)) {
                /* No se puede borrar en silencio: hay una venta o un
                   movimiento que apunta a este lote. */
                if ((int) $lote->producto_id !== $superviviente) {
                    $codigosAjustados += (int) $this->reapuntarLote($lote, $superviviente);
                    $reapuntados++;
                }

                DB::table('lotes')->where('id', $lote->id)->update([
                    'cantidad_actual' => 0,
                    'estado'          => 'agotado',
                ]);

                $conservadosACero++;

                continue;
            }

            $eliminados += DB::table('lotes')->where('id', $lote->id)->delete();
        }

        if ($donante !== $superviviente) {
            foreach ($this->lotesDe([$donante]) as $lote) {
                $codigosAjustados += (int) $this->reapuntarLote($lote, $superviviente);
                $reapuntados++;
            }
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
     * Los grupos que el comando se niega a adivinar.
     *
     * Se listan con ids y stocks porque son los únicos que alguien tiene que
     * mirar a mano: son once, no ochocientos.
     *
     * @param  array<int, array<string, mixed>>  $ambiguos
     */
    private function mostrarAmbiguos(array $ambiguos, bool $incluirAmbiguos): void
    {
        if ($ambiguos === []) {
            return;
        }

        $this->newLine();
        $this->line($incluirAmbiguos
            ? 'GRUPOS AMBIGUOS FUSIONADOS TOMANDO EL MÁXIMO (--incluir-ambiguos)'
            : 'GRUPOS AMBIGUOS OMITIDOS · el stock difiere entre copias, revisar a mano');
        $this->line(str_repeat('-', 72));

        foreach ($ambiguos as $i => $grupo) {
            $descripcion = $this->describir($grupo['datos']);

            $this->line(sprintf('  %2d. %s', $i + 1, $descripcion));
            $this->line('      ids:    '.implode(', ', $grupo['ids']));
            $this->line('      stocks: '.implode(', ', array_map(
                fn ($id) => $id.' → '.$grupo['stocks'][$id],
                $grupo['ids']
            )));
            $this->line('      máximo: '.max($grupo['stocks']));
        }

        if (! $incluirAmbiguos) {
            $this->newLine();
            $this->comment('   Estos grupos no se tocaron. Con --incluir-ambiguos se fusionan tomando el máximo.');
        }
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
            $superviviente = $ids[0];
            $idMayor       = end($ids);

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
                    '      id %-6d precio %-9s lotes suman %-6d  %s',
                    $id,
                    $precios[$id] ?? '-',
                    $grupo['stocks'][$id],
                    $etiqueta
                ));
            }

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
