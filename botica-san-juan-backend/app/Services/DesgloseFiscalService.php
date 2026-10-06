<?php

namespace App\Services;

use App\Models\Producto;

/**
 * Reparte un importe de venta entre base imponible, IGV, exonerado e inafecto.
 *
 * POR QUÉ EXISTE ESTA CLASE
 *
 * El cálculo vivía dentro de `VentaService::registrar()`, enterrado en una
 * transacción. Cuando el carrito del portal necesitó mostrar el mismo desglose
 * —subtotal, IGV, total— la salida fácil era repetir las tres líneas de
 * aritmética en el controlador del carrito.
 *
 * Eso habría sido un error caro de encontrar: el portal y el mostrador
 * mostrarían cifras distintas el día que cambie la tasa o la regla, y nadie se
 * daría cuenta hasta que un cliente comparase lo que vio en la web con lo que
 * dice su boleta. El impuesto se calcula en un sitio o se calcula mal.
 *
 * LA REGLA, QUE NO ES LA INTUITIVA
 *
 * Los precios del catálogo YA INCLUYEN el IGV, como es costumbre en mostrador
 * en Perú. El impuesto por tanto **se extrae del precio**, no se suma encima:
 *
 *     base = precio / (1 + tasa)
 *     igv  = precio - base
 *
 * Sumar un 18 % al precio mostrado daría un total que el cliente no reconoce y
 * que no coincide con lo que paga en el mostrador.
 *
 * Lo exonerado y lo inafecto entran al total tal cual: no llevan impuesto que
 * extraer, su precio ya es el importe final. Se separan entre sí porque el
 * comprobante electrónico los declara en casillas distintas — el exonerado está
 * dentro del ámbito del impuesto pero dispensado por ley; el inafecto queda
 * fuera del ámbito.
 */
class DesgloseFiscalService
{
    /** Tasa vigente, como fracción (0.18). */
    public function tasa(): float
    {
        return (float) config('inventario.tasa_igv', 0.18);
    }

    /**
     * Clasifica un importe según la afectación del producto.
     *
     * La afectación es del PRODUCTO, no de la presentación: la misma
     * amoxicilina tributa igual suelta, en blíster o en caja.
     *
     * @return string Una de las constantes de Producto (GRAVADO|EXONERADO|INAFECTO).
     */
    public function afectacionDe(Producto $producto): string
    {
        return $producto->tipo_afectacion_igv ?? Producto::GRAVADO;
    }

    /**
     * Reparte los tres acumuladores en el desglose final.
     *
     * Recibe los importes YA sumados por afectación —no la lista de líneas—
     * porque quien recorre las líneas (la venta recorre lotes, el carrito no)
     * tiene información que a este cálculo no le hace falta.
     *
     * @param  float  $baseGravada    Importe de las líneas gravadas, con IGV incluido.
     * @param  float  $baseExonerada  Importe de las líneas exoneradas.
     * @param  float  $baseInafecta   Importe de las líneas inafectas.
     * @return array{subtotal_gravado: float, subtotal_exonerado: float, subtotal_inafecto: float, igv: float, tasa_igv: float, total: float}
     */
    public function repartir(float $baseGravada, float $baseExonerada = 0.0, float $baseInafecta = 0.0): array
    {
        $tasa = $this->tasa();

        /* Si la tasa fuese 0 no se divide: evita una base inflada y deja el
           importe tal cual, que es lo correcto con impuesto nulo. */
        $gravadoSinIgv = $tasa > 0 ? round($baseGravada / (1 + $tasa), 2) : round($baseGravada, 2);

        /* El IGV sale de la RESTA, no de multiplicar la base por la tasa.
           Multiplicar introduce un céntimo de descuadre en algunos importes
           porque la base ya venía redondeada; restando, base + igv devuelve
           siempre exactamente el importe cobrado. */
        $igv = round($baseGravada - $gravadoSinIgv, 2);

        return [
            'subtotal_gravado'   => $gravadoSinIgv,
            'subtotal_exonerado' => round($baseExonerada, 2),
            'subtotal_inafecto'  => round($baseInafecta, 2),
            'igv'                => $igv,
            'tasa_igv'           => $tasa,
            'total'              => round($baseGravada + $baseExonerada + $baseInafecta, 2),
        ];
    }
}
