<?php

namespace App\Console\Commands;

use App\Models\Lote;
use App\Models\MovimientoStock;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoDetalleLote;
use App\Models\Producto;
use App\Services\DesgloseFiscalService;
use App\Services\VentaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Venta de mostrador de punta a punta sobre el catálogo real, con verificación.
 *
 *   php artisan pos:venta-demostracion              → simula y revierte
 *   php artisan pos:venta-demostracion --aplicar     → la deja registrada
 *
 * QUÉ SE DEMUESTRA AQUÍ, Y POR QUÉ NO BASTABA CON `pos:probar`
 *
 * `pos:probar` ya verifica FEFO, venta parcial, multi-lote y desglose de IGV,
 * pero lo hace sobre productos que él mismo crea. Eso comprueba las REGLAS; no
 * comprueba que funcionen sobre el catálogo que de verdad tiene la botica
 * después de fusionar los 869 grupos duplicados.
 *
 * Esta orden vende un producto REAL del catálogo depurado y después va a la base
 * a comprobar, dato por dato, que la venta dejó todo coherente:
 *
 *   1. El stock salió de los lotes correctos y en orden FEFO (primero el que
 *      antes vence), repartiéndose entre varios si hizo falta.
 *   2. Cada unidad entregada tiene su fila en `pedido_detalle_lotes`. Sin esa
 *      tabla no hay trazabilidad: ante una alerta sanitaria sobre un lote, no
 *      habría forma de saber a quién se le vendió.
 *   3. Cada movimiento quedó en `movimientos_stock` con su stock anterior y
 *      posterior, y los dos encajan con el descuento real.
 *   4. No quedó ninguna fila huérfana apuntando a algo que no existe.
 *   5. El desglose fiscal del pedido coincide **exactamente** con el que
 *      calcula `DesgloseFiscalService`, que es el mismo que usa el carrito del
 *      portal. Si el portal y la boleta se separasen, aquí se vería.
 *
 * POR QUÉ SIMULA POR DEFECTO
 *
 * Una venta descuenta inventario de verdad. Igual que en la fusión de
 * duplicados, lo prudente es poder ver el informe completo antes de escribir
 * nada: sin `--aplicar` todo ocurre dentro de una transacción que se revierte al
 * final, y las cifras que imprime son las mismas que si se hubiera aplicado.
 */
class VentaDemostracionPos extends Command
{
    protected $signature = 'pos:venta-demostracion
                            {--aplicar : Confirma la venta en lugar de revertirla}
                            {--producto= : Id del producto a vender. Por omisión, uno con varios lotes}
                            {--cantidad=5 : Unidades a vender}';

    protected $description = 'Venta de mostrador sobre el catálogo real, verificando lotes, movimientos e integridad';

    private int $fallos = 0;

    public function handle(VentaService $ventas, DesgloseFiscalService $fiscal): int
    {
        $aplicar  = (bool) $this->option('aplicar');
        $cantidad = max(1, (int) $this->option('cantidad'));

        $producto = $this->elegirProducto();
        if (!$producto) {
            $this->error('No hay ningún producto con stock vendible en el catálogo.');

            return self::FAILURE;
        }

        $this->cabecera($aplicar);

        /* --- ANTES --------------------------------------------------------- */
        $lotesAntes = $this->fotoDeLotes($producto->id);
        $disponible = array_sum(array_column($lotesAntes, 'cantidad'));
        $pedidosAntes = Pedido::count();
        $movimientosAntes = MovimientoStock::count();

        $this->info('PRODUCTO');
        $this->line(sprintf('  id %d · %s %s', $producto->id, $producto->nombre, $producto->concentracion));
        $this->line(sprintf('  %s · %s', $producto->presentacion, $producto->laboratorio));
        $this->line(sprintf('  precio S/ %s · afectación IGV %s · receta: %s',
            number_format((float) $producto->precio, 2),
            $fiscal->afectacionDe($producto),
            $producto->requiere_receta ? 'SÍ' : 'no',
        ));
        $this->newLine();

        $this->info('LOTES DISPONIBLES ANTES DE LA VENTA  (orden FEFO: antes vence, antes sale)');
        foreach ($lotesAntes as $l) {
            $this->line(sprintf('  lote %-14s vence %-12s  %3d unidades',
                $l['codigo'], $l['vence'] ?? 'sin fecha', $l['cantidad']));
        }
        $this->line(sprintf('  %s', str_repeat('-', 56)));
        $this->line(sprintf('  TOTAL VENDIBLE %d unidades · se van a vender %d', $disponible, $cantidad));
        $this->newLine();

        if ($cantidad > $disponible) {
            $this->warn(sprintf(
                '  Se piden %d y hay %d: la venta será PARCIAL y debe quedar registrada una incidencia.',
                $cantidad, $disponible
            ));
            $this->newLine();
        }

        /* --- LA VENTA ------------------------------------------------------ */
        DB::beginTransaction();

        $pedido = $ventas->registrar(
            items: [[
                'producto_id'  => $producto->id,
                'unidad_venta' => 'unidad',
                'cantidad'     => $cantidad,
            ]],
            datosCliente: [
                'cliente_nombre'   => 'Venta de demostración APF2',
                'observacion'      => 'Generada por pos:venta-demostracion para evidencia técnica.',
            ],
            origen: Pedido::ORIGEN_POS,
        );

        /* --- DESPUÉS ------------------------------------------------------- */
        $lotesDespues = $this->fotoDeLotes($producto->id, incluirVacios: true);

        $this->info('PEDIDO REGISTRADO');
        $this->line(sprintf('  id %d · origen %s · estado %s', $pedido->id, $pedido->origen, $pedido->estado));
        $this->newLine();

        $this->info('DESGLOSE FISCAL');
        $esperado = $fiscal->repartir(
            (float) $pedido->subtotal_gravado + (float) $pedido->igv,
            (float) $pedido->subtotal_exonerado,
            (float) $pedido->subtotal_inafecto,
        );
        $this->line(sprintf('  base imponible   S/ %10s', number_format((float) $pedido->subtotal_gravado, 2)));
        $this->line(sprintf('  IGV (%.0f %%)       S/ %10s', $esperado['tasa_igv'] * 100, number_format((float) $pedido->igv, 2)));
        if ((float) $pedido->subtotal_exonerado > 0) {
            $this->line(sprintf('  exonerado        S/ %10s', number_format((float) $pedido->subtotal_exonerado, 2)));
        }
        $this->line(sprintf('  TOTAL            S/ %10s', number_format((float) $pedido->total, 2)));
        $this->newLine();

        $this->verificar(
            abs((float) $pedido->total - ((float) $pedido->subtotal_gravado + (float) $pedido->igv
                + (float) $pedido->subtotal_exonerado + (float) $pedido->subtotal_inafecto)) < 0.02,
            'base + IGV + exonerado + inafecto = total',
        );
        $this->verificar(
            abs($esperado['igv'] - (float) $pedido->igv) < 0.02,
            'el IGV del pedido coincide con DesgloseFiscalService (el que usa el carrito del portal)',
        );

        /* --- LOTES --------------------------------------------------------- */
        $this->newLine();
        $this->info('LOTES DESPUÉS DE LA VENTA');

        $porCodigo = collect($lotesAntes)->keyBy('codigo');
        $descontadoTotal = 0;

        foreach ($lotesDespues as $l) {
            $antes = $porCodigo[$l['codigo']]['cantidad'] ?? 0;
            $delta = $antes - $l['cantidad'];
            $descontadoTotal += $delta;

            $this->line(sprintf('  lote %-14s vence %-12s  %3d → %3d   %s',
                $l['codigo'], $l['vence'] ?? 'sin fecha', $antes, $l['cantidad'],
                $delta > 0 ? "(-{$delta})" : '(sin cambio)',
            ));
        }

        $entregadas = (int) $pedido->detalles->sum('cantidad_unidades');
        $this->newLine();
        $this->verificar(
            $descontadoTotal === $entregadas,
            sprintf('lo descontado de los lotes (%d) es lo entregado en el pedido (%d)', $descontadoTotal, $entregadas),
        );
        $this->verificar($this->seRespetoFefo($lotesAntes, $lotesDespues), 'el descuento siguió el orden FEFO');

        /* --- TRAZABILIDAD POR LOTE ----------------------------------------- */
        $this->newLine();
        $this->info('TRAZABILIDAD · pedido_detalle_lotes');

        $asignaciones = PedidoDetalleLote::whereIn(
            'pedido_detalle_id',
            $pedido->detalles->pluck('id')
        )->get();

        foreach ($asignaciones as $a) {
            $this->line(sprintf('  detalle %-5d lote %-14s %3d unidades  (vence %s)',
                $a->pedido_detalle_id, $a->codigo_lote, $a->cantidad_unidades, $a->fecha_vencimiento ?? 'sin fecha'));
        }

        $this->verificar($asignaciones->isNotEmpty(), 'la venta dejó asignaciones por lote');
        $this->verificar(
            (int) $asignaciones->sum('cantidad_unidades') === $entregadas,
            sprintf('las unidades asignadas a lotes (%d) cuadran con las entregadas (%d)',
                (int) $asignaciones->sum('cantidad_unidades'), $entregadas),
        );
        $this->verificar(
            $asignaciones->every(fn ($a) => $a->lote_id !== null),
            'ninguna asignación quedó sin lote (la trazabilidad no tiene huecos)',
        );

        /* --- MOVIMIENTOS DE STOCK ------------------------------------------ */
        $this->newLine();
        $this->info('MOVIMIENTOS DE STOCK');

        $movimientos = MovimientoStock::where('referencia_tipo', 'pedido')
            ->where('referencia_id', $pedido->id)
            ->orderBy('id')
            ->get();

        foreach ($movimientos as $m) {
            $this->line(sprintf('  %-10s lote %-6s cantidad %3d   stock %3d → %3d',
                $m->tipo, $m->lote_id ?? '—', $m->cantidad, $m->stock_anterior, $m->stock_posterior));
        }

        $this->verificar($movimientos->isNotEmpty(), 'la venta dejó movimientos de stock');
        /* `cantidad` lleva SIGNO: negativa en venta y vencimiento, positiva en
           ingreso, y con el signo de la diferencia en un ajuste. Por eso la
           identidad es una SUMA y no una resta. La primera versión de esta
           comprobación restaba y fallaba sobre una venta correcta. */
        $this->verificar(
            $movimientos->every(fn ($m) => $m->stock_anterior + $m->cantidad === $m->stock_posterior),
            'en cada movimiento, stock_anterior + cantidad = stock_posterior (la cantidad lleva signo)',
        );
        $this->verificar(
            $movimientos->every(fn ($m) => $m->cantidad < 0),
            'los movimientos de una venta son todos de salida (cantidad negativa)',
        );
        $this->verificar(
            MovimientoStock::count() - $movimientosAntes === $movimientos->count(),
            'no se crearon movimientos de más fuera de esta venta',
        );

        /* --- INTEGRIDAD REFERENCIAL ---------------------------------------- */
        $this->newLine();
        $this->info('INTEGRIDAD REFERENCIAL');

        $this->verificar(
            PedidoDetalle::whereDoesntHave('pedido')->count() === 0,
            'no hay detalles de pedido huérfanos',
        );
        $this->verificar(
            DB::table('pedido_detalle_lotes')
                ->leftJoin('pedido_detalles', 'pedido_detalles.id', '=', 'pedido_detalle_lotes.pedido_detalle_id')
                ->whereNull('pedido_detalles.id')->count() === 0,
            'no hay asignaciones de lote huérfanas',
        );
        $this->verificar(
            DB::table('movimientos_stock')
                ->leftJoin('productos', 'productos.id', '=', 'movimientos_stock.producto_id')
                ->whereNull('productos.id')->count() === 0,
            'ningún movimiento apunta a un producto inexistente',
        );
        $this->verificar(
            DB::table('pedido_detalles')
                ->leftJoin('productos', 'productos.id', '=', 'pedido_detalles.producto_id')
                ->whereNull('productos.id')->count() === 0,
            'ninguna línea de venta apunta a un producto inexistente',
        );
        $this->verificar(
            Pedido::count() - $pedidosAntes === 1,
            'se creó exactamente un pedido',
        );

        /* --- CIERRE -------------------------------------------------------- */
        $this->newLine();

        if ($aplicar) {
            DB::commit();
            $this->info(sprintf('VENTA APLICADA. Pedido %d registrado en la base.', $pedido->id));
        } else {
            DB::rollBack();
            $this->comment('SIMULACIÓN: la transacción se revirtió. Nada quedó escrito.');
            $this->comment('Para registrarla de verdad:  php artisan pos:venta-demostracion --aplicar');
        }

        $this->newLine();

        if ($this->fallos > 0) {
            $this->error(sprintf('%d comprobación(es) FALLARON.', $this->fallos));

            return self::FAILURE;
        }

        $this->info('Todas las comprobaciones pasaron.');

        return self::SUCCESS;
    }

    /** Producto con varios lotes disponibles; si no hay, el de más stock. */
    private function elegirProducto(): ?Producto
    {
        if ($id = $this->option('producto')) {
            return Producto::find((int) $id);
        }

        $conVariosLotes = Lote::query()
            ->selectRaw('producto_id, COUNT(*) AS lotes')
            ->disponible()->groupBy('producto_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('lotes')->first();

        if ($conVariosLotes) {
            return Producto::find($conVariosLotes->producto_id);
        }

        $conStock = Lote::query()
            ->selectRaw('producto_id, SUM(cantidad_actual) AS u')
            ->disponible()->groupBy('producto_id')
            ->orderByDesc('u')->first();

        return $conStock ? Producto::find($conStock->producto_id) : null;
    }

    /** @return array<int, array{codigo:string, vence:?string, cantidad:int}> */
    private function fotoDeLotes(int $productoId, bool $incluirVacios = false): array
    {
        $query = Lote::where('producto_id', $productoId);

        if (!$incluirVacios) {
            $query->disponible();
        }

        return $query->orderByRaw('fecha_vencimiento ASC NULLS LAST')
            ->get()
            ->map(fn ($l) => [
                'codigo'   => (string) $l->codigo_lote,
                'vence'    => $l->fecha_vencimiento?->toDateString(),
                'cantidad' => (int) $l->cantidad_actual,
            ])
            ->all();
    }

    /**
     * FEFO: ningún lote posterior puede haberse tocado si otro anterior quedó
     * con existencias. Se comprueba sobre la foto ordenada por vencimiento.
     */
    private function seRespetoFefo(array $antes, array $despues): bool
    {
        $porCodigo = collect($despues)->keyBy('codigo');
        $vistoConSaldo = false;

        foreach ($antes as $l) {
            $queda = $porCodigo[$l['codigo']]['cantidad'] ?? 0;
            $seTomo = $l['cantidad'] - $queda > 0;

            if ($seTomo && $vistoConSaldo) {
                return false;
            }

            if ($queda > 0) {
                $vistoConSaldo = true;
            }
        }

        return true;
    }

    private function verificar(bool $condicion, string $que): void
    {
        if ($condicion) {
            $this->line("  <fg=green>✓</> {$que}");

            return;
        }

        $this->line("  <fg=red>✗</> {$que}");
        $this->fallos++;
    }

    private function cabecera(bool $aplicar): void
    {
        $this->newLine();
        $this->line(str_repeat('=', 78));
        $this->info('  VENTA DE MOSTRADOR SOBRE EL CATÁLOGO REAL  '.($aplicar ? '[APLICANDO]' : '[SIMULACIÓN]'));
        $this->line(str_repeat('=', 78));
        $this->line(sprintf('  Catálogo: %d productos · %s',
            Producto::count(), now()->format('Y-m-d H:i')));
        $this->newLine();
    }
}
