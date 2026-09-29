<?php

namespace App\Console\Commands;

use App\Models\Lote;
use App\Models\Producto;
use App\Services\InventarioService;
use App\Services\VentaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Venta por unidad, blíster y caja sobre un producto REAL del catálogo.
 *
 *   php artisan pos:probar-presentaciones
 *
 * `pos:probar` valida las reglas con un producto inventado. Esto es distinto:
 * toma uno de los 3361 productos migrados de verdad, le carga la configuración
 * de presentaciones que hoy le falta, y comprueba que con datos reales el
 * sistema convierte unidades, aplica el precio propio de cada presentación y
 * descuenta el stock correctamente.
 *
 * Todo ocurre dentro de una transacción que se revierte: el catálogo real no
 * se modifica. Es una prueba, no una carga de datos.
 */
class ProbarPresentaciones extends Command
{
    protected $signature = 'pos:probar-presentaciones {--producto= : ID del producto a usar}';
    protected $description = 'Prueba venta por unidad, blister y caja con un producto real (revierte)';

    private bool $ok = true;

    public function handle(InventarioService $inventario, VentaService $ventas): int
    {
        $this->newLine();
        $this->info('Venta por presentaciones sobre un producto real del catalogo');
        $this->line(str_repeat('=', 66));

        DB::beginTransaction();

        try {
            $producto = $this->elegirProducto();

            $this->newLine();
            $this->line("Producto: [{$producto->id}] {$producto->nombre} {$producto->concentracion}");
            $this->line("Presentacion en catalogo: {$producto->presentacion}");
            $this->line("Precio unitario actual: S/ ".number_format((float) $producto->precio, 2));
            $this->line("Stock actual: {$producto->stock}");

            $this->configurar($producto);
            $this->probarConversion($inventario, $producto);
            $this->probarStockDisponible($inventario, $producto);
            $this->probarVentas($ventas, $inventario, $producto);
            $this->probarFraccionamiento($ventas, $inventario, $producto);

            $this->newLine();
            $this->line(str_repeat('-', 66));

            if ($this->ok) {
                $this->info('RESULTADO: las tres presentaciones funcionan con datos reales.');
            } else {
                $this->error('RESULTADO: hay fallos. Revisar arriba.');
            }

            $this->newLine();
            $this->comment('Transaccion revertida: el catalogo real quedo intacto.');

            return $this->ok ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('EXCEPCION: '.$e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());

            return self::FAILURE;
        } finally {
            DB::rollBack();
        }
    }

    private function elegirProducto(): Producto
    {
        if ($id = $this->option('producto')) {
            $producto = Producto::find($id);

            if ($producto === null) {
                throw new RuntimeException("No existe el producto {$id}.");
            }

            return $producto;
        }

        /* El de mayor stock del catálogo. No se exige un mínimo alto porque el
           stock real es pequeño (máximo 14 unidades por producto): la prueba se
           adapta a lo que hay en lugar de pedir datos que no existen. */
        $producto = Producto::where('precio', '>', 0)
            ->orderByDesc('stock')
            ->first();

        if ($producto === null || (int) $producto->stock < 6) {
            throw new RuntimeException('No hay ningun producto con stock suficiente para la prueba.');
        }

        return $producto;
    }

    /**
     * Carga la configuración de presentaciones que al producto le falta.
     *
     * Los números imitan un blíster de 10 tabletas y una caja de 10 blísteres,
     * con el descuento por volumen que la botica aplica de verdad: el blíster
     * sale más barato que 10 sueltas, y la caja más barata que 10 blísteres.
     */
    private array $tamanos = [];

    private function configurar(Producto $producto): void
    {
        $unitario = (float) $producto->precio;
        $stock    = (int) $producto->stock;

        /* Las presentaciones se dimensionan para que quepan en el stock real:
           hay que poder vender una unidad, un blíster y una caja seguidos y que
           todavía sobre algo para la prueba de entrega parcial. Con un blíster
           de 10 y una caja de 100 —lo habitual en una botica— la prueba no
           podría ejecutarse, porque ningún producto pasa de 14 unidades. */
        $porBlister = max(2, intdiv($stock, 5));
        $porCaja    = 2;

        $this->tamanos = [
            'unidad'  => 1,
            'blister' => $porBlister,
            'caja'    => $porBlister * $porCaja,
        ];

        $producto->update([
            'venta_fraccionada'    => true,
            'unidades_por_blister' => $porBlister,
            'blisters_por_caja'    => $porCaja,
            /* Descuento por volumen, como aplica la botica de verdad: el
               blíster sale más barato que las sueltas y la caja que los
               blísteres. */
            'precio_blister'       => round($unitario * $porBlister * 0.90, 2),
            'precio_caja'          => round($unitario * $porBlister * $porCaja * 0.80, 2),
        ]);

        $producto->refresh();

        $this->newLine();
        $this->line('Configuracion cargada para la prueba (dimensionada al stock real):');
        $this->line("   1 blister = {$porBlister} unidades   S/ ".number_format((float) $producto->precio_blister, 2));
        $this->line("   1 caja    = {$this->tamanos['caja']} unidades  S/ ".number_format((float) $producto->precio_caja, 2));
    }

    private function probarConversion(InventarioService $inventario, Producto $producto): void
    {
        $this->paso('1) Conversion de unidades y precio por presentacion');

        $fUnidad  = $inventario->factorUnidades($producto, 'unidad');
        $fBlister = $inventario->factorUnidades($producto, 'blister');
        $fCaja    = $inventario->factorUnidades($producto, 'caja');

        $pUnidad  = $inventario->precioUnidadVenta($producto, 'unidad');
        $pBlister = $inventario->precioUnidadVenta($producto, 'blister');
        $pCaja    = $inventario->precioUnidadVenta($producto, 'caja');

        $porBlister = $this->tamanos['blister'];
        $porCaja    = intdiv($this->tamanos['caja'], $porBlister);

        $this->line("   unidad  x{$fUnidad}  S/ ".number_format($pUnidad, 2));
        $this->line("   blister x{$fBlister}  S/ ".number_format($pBlister, 2)
            ." ({$porBlister} sueltas: S/ ".number_format($pUnidad * $porBlister, 2).')');
        $this->line("   caja    x{$fCaja}  S/ ".number_format($pCaja, 2)
            ." ({$porCaja} blisteres: S/ ".number_format($pBlister * $porCaja, 2).')');

        $this->verificar(
            $fUnidad === 1 && $fBlister === $porBlister && $fCaja === $this->tamanos['caja'],
            'los factores de conversion son correctos'
        );
        $this->verificar($pBlister < $pUnidad * $porBlister, 'el blister es mas barato que las unidades sueltas');
        $this->verificar($pCaja < $pBlister * $porCaja, 'la caja es mas barata que los blisteres sueltos');
    }

    private function probarStockDisponible(InventarioService $inventario, Producto $producto): void
    {
        $this->paso('2) El maximo vendible se expresa en cada presentacion');

        $disponible  = $inventario->disponible($producto->id);
        $enBlisteres = intdiv($disponible, $this->tamanos['blister']);
        $enCajas     = intdiv($disponible, $this->tamanos['caja']);

        $this->line("   Disponible: {$disponible} unidades = {$enBlisteres} blisteres = {$enCajas} cajas");

        $this->verificar($disponible > 0, 'el producto tiene stock vendible');
        $this->verificar(
            $enBlisteres * $this->tamanos['blister'] <= $disponible
                && $enCajas * $this->tamanos['caja'] <= $disponible,
            'el maximo por presentacion nunca supera el stock real'
        );
    }

    private function probarVentas(VentaService $ventas, InventarioService $inventario, Producto $producto): void
    {
        $this->paso('3) Vender una unidad, un blister y una caja descuenta lo correcto');

        foreach ($this->tamanos as $unidad => $esperado) {
            $antes = $inventario->disponible($producto->id);

            if ($antes < $esperado) {
                $this->line("   (se omite {$unidad}: hacen falta {$esperado} unidades y hay {$antes})");
                continue;
            }

            $venta = $ventas->registrar(
                items: [['producto_id' => $producto->id, 'unidad_venta' => $unidad, 'cantidad' => 1]],
            );

            $despues = $inventario->disponible($producto->id);
            $descontado = $antes - $despues;
            $detalle = $venta->detalles->first();

            $this->line(sprintf(
                '   1 %-7s -> S/ %7s | descuenta %3d u. | linea: %d %s | %d unidades',
                $unidad,
                number_format((float) $venta->total, 2),
                $descontado,
                $detalle->cantidad,
                $detalle->unidad_venta,
                $detalle->cantidad_unidades
            ));

            $this->verificar($descontado === $esperado, "vender 1 {$unidad} descuenta {$esperado} unidades");
            $this->verificar(
                $detalle->unidad_venta === $unidad && (int) $detalle->cantidad === 1,
                "la linea se factura como 1 {$unidad}, no convertida a sueltas"
            );

            $precioEsperado = $inventario->precioUnidadVenta($producto, $unidad);
            $this->verificar(
                abs((float) $venta->total - $precioEsperado) < 0.02,
                "se cobro el precio propio de {$unidad}, no el unitario por {$esperado}"
            );
        }
    }

    /**
     * El caso que más se equivoca en los POS: piden 2 cajas y sólo alcanza para
     * una y media. Lo entregado no es un número entero de cajas, así que
     * facturar "1.5 cajas" no tiene sentido — se factura en unidades sueltas.
     */
    private function probarFraccionamiento(VentaService $ventas, InventarioService $inventario, Producto $producto): void
    {
        $this->paso('4) Entrega parcial que no completa la presentacion');

        $disponible  = $inventario->disponible($producto->id);
        $porCajaUnid = $this->tamanos['caja'];

        if ($disponible < 1) {
            $this->line('   (se omite: no queda stock tras las pruebas anteriores)');

            return;
        }

        /* Se piden mas cajas de las que alcanzan, a proposito. */
        $cajasPedidas = intdiv($disponible, $porCajaUnid) + 1;
        $unidadesPedidas = $cajasPedidas * $porCajaUnid;

        $venta = $ventas->registrar(
            items: [['producto_id' => $producto->id, 'unidad_venta' => 'caja', 'cantidad' => $cajasPedidas]],
        );

        $detalle = $venta->detalles->first();
        $incidencia = $venta->incidencias->first();

        $this->line("   Se pidieron {$cajasPedidas} cajas ({$unidadesPedidas} u.), habia {$disponible} u.");
        $this->line("   Facturado: {$detalle->cantidad} {$detalle->unidad_venta} = {$detalle->cantidad_unidades} unidades por S/ ".number_format((float) $venta->total, 2));
        $this->line("   Incidencia: pidio {$incidencia?->cantidad_solicitada}, entrego {$incidencia?->cantidad_atendida}");

        $this->verificar((int) $detalle->cantidad_unidades === $disponible, 'se entrego todo lo que habia, ni una unidad mas');
        $this->verificar($inventario->disponible($producto->id) === 0, 'el stock quedo en cero, nunca negativo');
        $this->verificar($incidencia !== null, 'quedo constancia del faltante');

        /* Si lo entregado no completa cajas enteras, debe facturarse suelto. */
        if ($disponible % $porCajaUnid !== 0) {
            $this->verificar(
                $detalle->unidad_venta === 'unidad',
                'al no completar cajas enteras, la linea se reexpresa en unidades sueltas'
            );

            $precioUnitario = $inventario->precioUnidadVenta($producto, 'unidad');
            $this->verificar(
                abs((float) $venta->total - $precioUnitario * $disponible) < 0.05,
                'se cobro a precio unitario lo que no llego a completar una caja'
            );
        }
    }

    private function paso(string $titulo): void
    {
        $this->newLine();
        $this->info($titulo);
    }

    private function verificar(bool $condicion, string $queSeEsperaba): void
    {
        if ($condicion) {
            $this->line("   OK    {$queSeEsperaba}");

            return;
        }

        $this->error("   FALLO  {$queSeEsperaba}");
        $this->ok = false;
    }
}
