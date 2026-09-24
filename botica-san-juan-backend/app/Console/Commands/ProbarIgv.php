<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Services\InventarioService;
use App\Services\VentaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Tratamiento del IGV en la venta.
 *
 *   php artisan igv:probar
 *
 * La regla que se comprueba aquí, y que es el motivo de este comando:
 * la presentación comercial NO decide el tratamiento tributario. Unidad,
 * blíster y caja son el mismo medicamento vendido en distinta cantidad y a
 * distinto precio; si está exonerado, lo está en las tres formas, y si está
 * gravado, también.
 *
 * Es el tipo de error que no salta a la vista: bastaría que alguien aplicase
 * el IGV sobre el precio de la presentación en lugar de sobre el producto para
 * que una caja tributara distinto que diez unidades sueltas del mismo
 * medicamento. La venta seguiría "funcionando" y el descuadre sólo aparecería
 * en la declaración.
 *
 * Todo ocurre dentro de una transacción que se revierte.
 */
class ProbarIgv extends Command
{
    protected $signature = 'igv:probar';
    protected $description = 'Comprueba que gravado y exonerado se calculan bien en las tres presentaciones';

    private bool $ok = true;

    public function handle(InventarioService $inventario, VentaService $ventas): int
    {
        $this->newLine();
        $this->info('Tratamiento del IGV en la venta');
        $this->line(str_repeat('=', 64));

        DB::beginTransaction();

        try {
            $tasa = (float) config('inventario.tasa_igv', 0.18);
            $this->line('Tasa configurada: '.($tasa * 100).' %');

            $gravado   = $this->crearProducto('PRUEBA GRAVADO', Producto::GRAVADO, null, $inventario);
            $exonerado = $this->crearProducto('PRUEBA EXONERADO', Producto::EXONERADO, 'ley_27450', $inventario);

            $this->laPresentacionNoCambiaElTrato($ventas, $inventario, $gravado, $tasa, 'GRAVADO');
            $this->laPresentacionNoCambiaElTrato($ventas, $inventario, $exonerado, $tasa, 'EXONERADO');
            $this->ventaMixta($ventas, $gravado, $exonerado, $tasa);
            $this->elEspejoSeMantiene($gravado, $exonerado);

            $this->newLine();
            $this->line(str_repeat('-', 64));

            if ($this->ok) {
                $this->info('RESULTADO: el IGV se aplica por producto, no por presentación.');
            } else {
                $this->error('RESULTADO: hay fallos. Revisar arriba.');
            }

            $this->newLine();
            $this->comment('Transaccion revertida: no quedo nada en la base.');

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

    private function crearProducto(
        string $nombre,
        string $afectacion,
        ?string $baseLegal,
        InventarioService $inventario,
    ): Producto {
        $producto = Producto::create([
            'nombre'                 => $nombre,
            'concentracion'          => '500 mg',
            'laboratorio'            => 'Laboratorio de prueba',
            'presentacion'           => 'Tableta',
            'tipo'                   => 'Medicamento',
            'precio'                 => 10.00,
            'stock'                  => 0,
            'stock_minimo'           => 5,
            'stock_reposicion'       => 20,
            'unidad_base'            => 'unidad',
            'venta_fraccionada'      => true,
            'unidades_por_blister'   => 10,
            'blisters_por_caja'      => 10,
            'precio_blister'         => 90.00,
            'precio_caja'            => 800.00,
            'tipo_afectacion_igv'    => $afectacion,
            'base_legal_exoneracion' => $baseLegal,
        ]);

        $inventario->ingresar($producto, 500, 'LOTE-IGV', now()->addYear()->toDateString());

        return $producto->fresh();
    }

    /**
     * El corazón de la prueba: vender el MISMO producto en las tres
     * presentaciones debe dar el mismo tratamiento en las tres.
     */
    private function laPresentacionNoCambiaElTrato(
        VentaService $ventas,
        InventarioService $inventario,
        Producto $producto,
        float $tasa,
        string $etiqueta,
    ): void {
        $this->newLine();
        $this->info("Producto {$etiqueta}: unidad, blister y caja");

        $resultados = [];

        foreach (['unidad', 'blister', 'caja'] as $unidad) {
            $venta = $ventas->registrar(
                items: [['producto_id' => $producto->id, 'unidad_venta' => $unidad, 'cantidad' => 1]],
            );

            $total     = (float) $venta->total;
            $gravado   = (float) $venta->subtotal_gravado;
            $exonerado = (float) $venta->subtotal_exonerado;
            $igv       = (float) $venta->igv;

            $this->line(sprintf(
                '   1 %-7s  total %8s  gravado %8s  exonerado %8s  IGV %7s',
                $unidad,
                number_format($total, 2),
                number_format($gravado, 2),
                number_format($exonerado, 2),
                number_format($igv, 2)
            ));

            $resultados[$unidad] = compact('total', 'gravado', 'exonerado', 'igv');

            /* La línea guarda la foto del tratamiento. */
            $detalle = $venta->detalles->first();
            $this->verificar(
                $detalle->tipo_afectacion_igv === $producto->tipo_afectacion_igv,
                "la linea de {$unidad} guarda el tratamiento del producto"
            );

            if ($producto->estaExonerado()) {
                $this->verificar(abs($igv) < 0.005, "vender por {$unidad} no genera IGV");
                $this->verificar(abs($exonerado - $total) < 0.02, "el total de {$unidad} va entero a exonerado");
                $this->verificar(abs($gravado) < 0.005, "nada de {$unidad} se registra como gravado");
            } else {
                $esperado = round($total - $total / (1 + $tasa), 2);
                $this->verificar(abs($igv - $esperado) < 0.02, "el IGV de {$unidad} se extrae del precio, no se suma");
                $this->verificar(abs($exonerado) < 0.005, "nada de {$unidad} se registra como exonerado");
            }
        }

        /* La proporción impuesto/total debe ser la misma en las tres.
           "La misma" con una salvedad que no es un defecto: el importe se
           redondea al céntimo, y un céntimo pesa más sobre S/ 10 que sobre
           S/ 800. Por eso no se comparan las proporciones entre sí —saldrían
           distintas en el cuarto decimal por pura aritmética— sino cada una
           contra la proporción teórica, admitiendo medio céntimo de holgura.
           Exigir igualdad exacta haría fallar una prueba con el código bien. */
        $teorica = $producto->estaExonerado() ? 0.0 : $tasa / (1 + $tasa);

        $this->line('   Proporción IGV/total: '.implode(' · ', array_map(
            fn ($u, $r) => "{$u} ".number_format(($r['total'] > 0 ? $r['igv'] / $r['total'] : 0) * 100, 4).' %',
            array_keys($resultados),
            $resultados
        )).'   (teórica '.number_format($teorica * 100, 4).' %)');

        $desviados = [];

        foreach ($resultados as $unidad => $r) {
            /* Se compara en soles, no en porcentaje: el error admisible es el
               del redondeo al céntimo, y eso es una cifra absoluta. */
            $desvio = abs($r['igv'] - $r['total'] * $teorica);

            if ($desvio > 0.005) {
                $desviados[] = "{$unidad} (".number_format($desvio, 4).' S/)';
            }
        }

        $this->verificar(
            $desviados === [],
            'las tres presentaciones tributan igual, salvo el redondeo al centimo'
            .($desviados === [] ? '' : ' — se desvian: '.implode(', ', $desviados))
        );
    }

    /** Una venta con las dos cosas a la vez, que es lo habitual en mostrador. */
    private function ventaMixta(VentaService $ventas, Producto $gravado, Producto $exonerado, float $tasa): void
    {
        $this->newLine();
        $this->info('Venta mixta: un gravado y un exonerado en la misma boleta');

        $venta = $ventas->registrar(items: [
            ['producto_id' => $gravado->id,   'unidad_venta' => 'blister', 'cantidad' => 1],
            ['producto_id' => $exonerado->id, 'unidad_venta' => 'caja',    'cantidad' => 1],
        ]);

        $total     = (float) $venta->total;
        $base      = (float) $venta->subtotal_gravado;
        $exo       = (float) $venta->subtotal_exonerado;
        $inafecto  = (float) $venta->subtotal_inafecto;
        $igv       = (float) $venta->igv;

        $this->line("   Total S/ ".number_format($total, 2));
        $this->line("   Base gravada S/ ".number_format($base, 2)." + IGV S/ ".number_format($igv, 2)
            ." + exonerado S/ ".number_format($exo, 2)." + inafecto S/ ".number_format($inafecto, 2));

        $this->verificar(
            abs(($base + $igv + $exo + $inafecto) - $total) < 0.02,
            'base + IGV + exonerado + inafecto = total'
        );
        $this->verificar(abs($exo - 800.00) < 0.02, 'la caja exonerada entra completa como exonerada');
        $this->verificar(
            abs($igv - round(90.00 - 90.00 / (1 + $tasa), 2)) < 0.02,
            'el IGV sale solo del blister gravado'
        );

        $tipos = $venta->detalles->pluck('tipo_afectacion_igv')->sort()->values()->all();
        $this->line('   Tratamientos guardados en las lineas: '.implode(', ', $tipos));
        $this->verificar($tipos === ['10', '20'], 'cada linea conserva su propio tratamiento');
    }

    /** El booleano heredado no puede contradecir a la afectación. */
    private function elEspejoSeMantiene(Producto $gravado, Producto $exonerado): void
    {
        $this->newLine();
        $this->info('Coherencia entre afecto_igv y tipo_afectacion_igv');

        $this->verificar($gravado->fresh()->afecto_igv === true, 'el gravado queda con afecto_igv = true');
        $this->verificar($exonerado->fresh()->afecto_igv === false, 'el exonerado queda con afecto_igv = false');

        /* Al volver a gravado debe soltarse la base legal: ya no aplica. */
        $exonerado->tipo_afectacion_igv = Producto::GRAVADO;
        $exonerado->save();
        $vuelto = $exonerado->fresh();

        $this->verificar($vuelto->afecto_igv === true, 'al volver a gravado, el espejo se actualiza solo');
        $this->verificar($vuelto->base_legal_exoneracion === null, 'al volver a gravado, se suelta la base legal');
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
