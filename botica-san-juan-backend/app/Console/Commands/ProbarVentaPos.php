<?php

namespace App\Console\Commands;

use App\Models\IncidenciaVenta;
use App\Models\Lote;
use App\Models\MovimientoStock;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\InventarioService;
use App\Services\VentaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prueba de extremo a extremo de las reglas del punto de venta.
 *
 * Trabaja sobre un producto de laboratorio creado al vuelo y deshace todo al
 * terminar, para no ensuciar el inventario real de la botica.
 *
 *   php artisan pos:probar
 */
class ProbarVentaPos extends Command
{
    protected $signature = 'pos:probar';
    protected $description = 'Verifica FEFO, venta parcial, multi-lote y desglose de IGV';

    public function handle(InventarioService $inventario, VentaService $ventas): int
    {
        $this->newLine();
        $this->info('=== PRUEBA DEL PUNTO DE VENTA ===');
        $this->line('Se ejecuta dentro de una transaccion que se revierte al final.');
        $this->newLine();

        DB::beginTransaction();

        try {
            /* ---- Escenario ------------------------------------------------
               Un producto que se vende por unidad, blister de 10 y caja de 5
               blisteres (50 unidades). Tres lotes con vencimientos distintos,
               uno de ellos ya vencido. */
            $producto = Producto::create([
                'nombre' => 'PRUEBA POS - Paracetamol',
                'concentracion' => '500 mg',
                'laboratorio' => 'Laboratorio de prueba',
                'presentacion' => 'Tableta',
                'tipo' => 'Medicamento',
                'unidad_base' => 'unidad',
                'venta_fraccionada' => true,
                'unidades_por_blister' => 10,
                'blisters_por_caja' => 5,
                'stock' => 0,
                'stock_minimo' => 5,
                'stock_reposicion' => 20,
                'precio' => 1.00,
                'precio_blister' => 8.50,   // con descuento: 10 sueltas serian 10.00
                'precio_caja' => 38.00,     // con descuento: 5 blisteres serian 42.50
                'afecto_igv' => true,
            ]);

            $inventario->ingresar($producto, 3,  'LOTE-VENCE-PRONTO', now()->addDays(15)->toDateString());
            $inventario->ingresar($producto, 20, 'LOTE-MEDIO',        now()->addDays(120)->toDateString());
            $inventario->ingresar($producto, 50, 'LOTE-LEJANO',       now()->addDays(400)->toDateString());
            $inventario->ingresar($producto, 99, 'LOTE-VENCIDO',      now()->subDays(10)->toDateString());

            $producto->refresh();

            $this->line("Producto creado. Stock total (incluye vencido): {$producto->stock}");
            $disponible = $inventario->disponible($producto->id);
            $this->line("Disponible real para vender: {$disponible}");

            $ok = true;

            /* ---- 1. Los lotes vencidos no se venden ---------------------- */
            $this->newLine();
            $this->info('1) El lote vencido queda excluido');
            if ($disponible === 73) {
                $this->line('   OK  73 unidades disponibles (3 + 20 + 50). Las 99 vencidas se excluyen.');
            } else {
                $this->error("   FALLO  se esperaban 73 y hay {$disponible}");
                $ok = false;
            }

            /* ---- 2. Conversion de unidades y precios propios ------------- */
            $this->newLine();
            $this->info('2) Conversion de unidades y precio por forma de venta');
            $fBlister = $inventario->factorUnidades($producto, 'blister');
            $fCaja    = $inventario->factorUnidades($producto, 'caja');
            $pBlister = $inventario->precioUnidadVenta($producto, 'blister');
            $pCaja    = $inventario->precioUnidadVenta($producto, 'caja');
            $this->line("   1 blister = {$fBlister} unidades, precio {$pBlister} (10 sueltas costarian 10.00)");
            $this->line("   1 caja    = {$fCaja} unidades, precio {$pCaja} (5 blisteres costarian 42.50)");
            if ($fBlister !== 10 || $fCaja !== 50 || (float) $pCaja !== 38.00) {
                $this->error('   FALLO en la conversion o el precio');
                $ok = false;
            } else {
                $this->line('   OK  el descuento por presentacion se respeta');
            }

            /* ---- 3. FEFO repartiendo entre lotes ------------------------- */
            $this->newLine();
            $this->info('3) FEFO: se piden 5 unidades y el lote mas proximo solo tiene 3');
            $sim = $inventario->simularFefo($producto->id, 5);
            foreach ($sim['asignaciones'] as $a) {
                $this->line("   {$a['cantidad']} unidades del lote {$a['codigo_lote']} (vence {$a['fecha_vencimiento']})");
            }
            $repartoCorrecto = count($sim['asignaciones']) === 2
                && $sim['asignaciones'][0]['cantidad'] === 3
                && $sim['asignaciones'][1]['cantidad'] === 2;
            if ($repartoCorrecto) {
                $this->line('   OK  3 del lote proximo a vencer + 2 del siguiente');
            } else {
                $this->error('   FALLO  el reparto FEFO no es el esperado');
                $ok = false;
            }

            /* ---- 4. Venta real con multi-lote ---------------------------- */
            $this->newLine();
            $this->info('4) Venta de 5 unidades: se registra el desglose por lote');
            $venta = $ventas->registrar(
                items: [['producto_id' => $producto->id, 'unidad_venta' => 'unidad', 'cantidad' => 5]],
                datosCliente: ['cliente_nombre' => 'Cliente de prueba'],
                vendedorId: null,
            );
            $detalle = $venta->detalles->first();
            $this->line("   Lineas en la boleta: {$venta->detalles->count()} (el cliente ve una sola)");
            $this->line("   Lotes usados internamente: {$detalle->lotesAsignados->count()}");
            if ($venta->detalles->count() === 1 && $detalle->lotesAsignados->count() === 2) {
                $this->line('   OK  una linea para el cliente, dos lotes en la trazabilidad');
            } else {
                $this->error('   FALLO  el desglose por lotes no cuadra');
                $ok = false;
            }

            /* ---- 5. Venta parcial con incidencia ------------------------- */
            $this->newLine();
            $this->info('5) Venta parcial: quedan 68 y se piden 2 cajas (100 unidades)');
            $ventaParcial = $ventas->registrar(
                items: [['producto_id' => $producto->id, 'unidad_venta' => 'caja', 'cantidad' => 2]],
                datosCliente: ['cliente_nombre' => 'Cliente de prueba 2'],
            );
            $d2 = $ventaParcial->detalles->first();
            $inc = IncidenciaVenta::where('pedido_id', $ventaParcial->id)->first();

            $this->line("   Entregado: {$d2->cantidad_unidades} unidades, facturado como {$d2->cantidad} {$d2->unidad_venta}");
            if ($inc) {
                $this->line("   Incidencia: pidio {$inc->cantidad_solicitada}, se entregaron {$inc->cantidad_atendida}, faltaron {$inc->faltante()}");
            }

            $producto->refresh();
            $this->line("   Stock resultante: {$producto->stock} (nunca negativo)");

            if ($d2->cantidad_unidades === 68 && $inc !== null && $producto->stock >= 0) {
                $this->line('   OK  se vendio lo disponible y quedo constancia del faltante');
            } else {
                $this->error('   FALLO  la venta parcial no se comporto como se esperaba');
                $ok = false;
            }

            /* ---- 6. Trazabilidad de los movimientos --------------------- */
            $this->newLine();
            $this->info('6) Libro de movimientos');
            $movs = MovimientoStock::where('producto_id', $producto->id)->count();
            $ventasMov = MovimientoStock::where('producto_id', $producto->id)->where('tipo', 'venta')->count();
            $this->line("   Movimientos registrados: {$movs} (de ellos {$ventasMov} de venta)");
            $this->line($movs > 0 ? '   OK  cada cambio de stock dejo asiento' : '   FALLO  no hay movimientos');

            /* ---- 7. Desglose fiscal ------------------------------------- */
            $this->newLine();
            $this->info('7) Desglose de IGV');
            $this->line("   Total: {$venta->total} | Gravado: {$venta->subtotal_gravado} | IGV: {$venta->igv} | Exonerado: {$venta->subtotal_exonerado}");
            $suma = round((float) $venta->subtotal_gravado + (float) $venta->igv + (float) $venta->subtotal_exonerado, 2);
            if (abs($suma - (float) $venta->total) < 0.02) {
                $this->line('   OK  base + IGV + exonerado = total');
            } else {
                $this->error("   FALLO  el desglose no suma: {$suma} vs {$venta->total}");
                $ok = false;
            }

            /* ---- 8. Cobro: medios de pago ------------------------------- */
            $this->newLine();
            $this->info('8) Cobro y medios de pago');

            /* Los pasos anteriores agotaron el stock vendible: lo unico que
               queda es el lote vencido, que no se vende. Se repone para poder
               probar el cobro, que es lo que interesa aqui. */
            $inventario->ingresar($producto, 30, 'LOTE-PARA-COBROS', now()->addDays(300)->toDateString());
            $producto->refresh();

            /* Sin declarar pagos se asume efectivo exacto. */
            $venta->refresh()->load('pagos');
            $this->line("   Venta anterior sin declarar cobro -> medio_pago: {$venta->medio_pago}");
            if ($venta->medio_pago !== 'efectivo' || $venta->pagos->count() !== 1) {
                $this->error('   FALLO  no se asumio efectivo exacto');
                $ok = false;
            } else {
                $this->line('   OK  efectivo exacto por defecto');
            }

            /* Efectivo con vuelto. */
            $conVuelto = $ventas->registrar(
                items: [['producto_id' => $producto->id, 'unidad_venta' => 'unidad', 'cantidad' => 1]],
                pagos: [['medio' => 'efectivo', 'monto_recibido' => 50.00]],
            );
            $pagoEfectivo = $conVuelto->pagos->first();
            $vueltoEsperado = round(50.00 - (float) $conVuelto->total, 2);
            $this->line("   Total S/ {$conVuelto->total} | recibido S/ 50.00 | vuelto S/ {$pagoEfectivo->vuelto}");
            if (abs((float) $pagoEfectivo->vuelto - $vueltoEsperado) > 0.005) {
                $this->error('   FALLO  el vuelto no cuadra');
                $ok = false;
            } else {
                $this->line('   OK  vuelto calculado sobre el total real');
            }

            /* Pago dividido: efectivo + Yape. */
            $mixta = $ventas->registrar(
                items: [['producto_id' => $producto->id, 'unidad_venta' => 'unidad', 'cantidad' => 2]],
            );
            $this->line("   (control) venta de 2 unidades: S/ {$mixta->total}");
            $mitad = round((float) $mixta->total / 2, 2);
            $resto = round((float) $mixta->total - $mitad, 2);
            $dividida = $ventas->registrar(
                items: [['producto_id' => $producto->id, 'unidad_venta' => 'unidad', 'cantidad' => 2]],
                pagos: [
                    ['medio' => 'efectivo', 'monto' => $mitad, 'monto_recibido' => $mitad],
                    ['medio' => 'yape', 'monto' => $resto, 'referencia' => '00123456'],
                ],
            );
            $this->line("   Dividida: efectivo S/ {$mitad} + yape S/ {$resto} -> medio_pago: {$dividida->medio_pago}");
            $enGaveta = $dividida->efectivoEnGaveta();
            $this->line("   Efectivo que entra a la gaveta: S/ {$enGaveta} (de un total de S/ {$dividida->total})");
            if ($dividida->medio_pago !== 'mixto' || abs($enGaveta - $mitad) > 0.005) {
                $this->error('   FALLO  el pago dividido no se resolvio bien');
                $ok = false;
            } else {
                $this->line('   OK  pago dividido registrado y gaveta separada del Yape');
            }

            /* Un cobro que no cuadra debe rechazarse. */
            try {
                $ventas->registrar(
                    items: [['producto_id' => $producto->id, 'unidad_venta' => 'unidad', 'cantidad' => 1]],
                    pagos: [['medio' => 'tarjeta', 'monto' => 0.01]],
                );
                $this->error('   FALLO  acepto un cobro que no cubre la venta');
                $ok = false;
            } catch (\RuntimeException $e) {
                $this->line('   Rechazado: '.$e->getMessage());
                $this->line('   OK  cobro descuadrado rechazado');
            }

            /* De la tarjeta no se da vuelto. */
            try {
                $ventas->registrar(
                    items: [['producto_id' => $producto->id, 'unidad_venta' => 'unidad', 'cantidad' => 1]],
                    pagos: [['medio' => 'tarjeta', 'monto' => 999.00]],
                );
                $this->error('   FALLO  acepto cobrar de mas con tarjeta');
                $ok = false;
            } catch (\RuntimeException $e) {
                $this->line('   Rechazado: '.$e->getMessage());
                $this->line('   OK  no se cobra de mas por medios sin vuelto');
            }

            $this->newLine();
            $this->line(str_repeat('-', 60));
            if ($ok) {
                $this->info('RESULTADO: todas las reglas de negocio se cumplen.');
            } else {
                $this->error('RESULTADO: hay reglas que no se cumplen. Revisar arriba.');
            }

            DB::rollBack();
            $this->newLine();
            $this->line('Transaccion revertida: el inventario real quedo intacto.');

            return $ok ? self::SUCCESS : self::FAILURE;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->newLine();
            $this->error('EXCEPCION: ' . $e->getMessage());
            $this->line($e->getFile() . ':' . $e->getLine());

            return self::FAILURE;
        }
    }
}
