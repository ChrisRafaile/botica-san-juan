<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use App\Models\Pedido;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Emisión interna de boletas y facturas.
 *
 * ALCANCE DE ESTA VERSIÓN, DICHO SIN ADORNOS
 *
 * Esto **emite el comprobante dentro del sistema**: le asigna serie y número
 * correlativo, lo liga al pedido y deja los importes desglosados listos para
 * imprimir en la térmica del mostrador y para construir el XML de SUNAT.
 *
 * Lo que NO hace, y no debe presentarse como que lo hace: no firma
 * digitalmente, no genera el XML UBL, y no envía nada a SUNAT. El comprobante
 * nace con `estado_sunat = 'pendiente'`, que es exactamente lo que es. El envío
 * real vive en `SunatClient`, hoy simulado, y se conectará cuando haya
 * certificado digital y credenciales del OSE.
 *
 * QUÉ DECIDE EL TIPO DE COMPROBANTE
 *
 * El documento del cliente, no una casilla que alguien marca:
 *
 *   RUC (11 dígitos)  → FACTURA, serie F001
 *   DNI o sin documento → BOLETA, serie B001
 *
 * Es la regla de SUNAT y no admite excepción por comodidad: una factura exige
 * RUC, y una boleta a nombre de un RUC no sirve para que la empresa use el
 * crédito fiscal.
 *
 * EL CORRELATIVO NO PUEDE REPETIRSE NUNCA
 *
 * Dos comprobantes con la misma serie y número es una contingencia tributaria,
 * no un detalle. La tabla tiene un índice `UNIQUE(serie, numero)` que lo
 * impide a nivel de motor; aquí se calcula el siguiente dentro de una
 * transacción y bloqueando la fila más alta de la serie (`lockForUpdate`), para
 * que dos ventas simultáneas en dos cajas no lean el mismo número.
 */
class ComprobanteService
{
    public const SERIE_BOLETA  = 'B001';
    public const SERIE_FACTURA = 'F001';

    public function __construct(private DesgloseFiscalService $fiscal)
    {
    }

    /**
     * Emite el comprobante de un pedido. Idempotente: si ya tiene uno, lo
     * devuelve en vez de emitir un segundo.
     *
     * Esa idempotencia no es un lujo. Un reintento del cliente, un doble clic o
     * un reenvío de la petición no pueden producir dos comprobantes del mismo
     * pedido: eso es duplicidad tributaria.
     */
    public function emitir(Pedido $pedido): ComprobanteElectronico
    {
        $yaEmitido = ComprobanteElectronico::where('pedido_id', $pedido->id)->first();
        if ($yaEmitido) {
            return $yaEmitido;
        }

        $esFactura = $this->correspondeFactura($pedido);
        $serie     = $esFactura ? self::SERIE_FACTURA : self::SERIE_BOLETA;

        /* Se reintenta porque el bloqueo de fila no cubre el primer comprobante
           de una serie: cuando aún no hay ninguno no hay fila que bloquear, y
           dos cajas simultáneas calcularían las dos el número 1. Quien de
           verdad garantiza que no haya duplicados es el índice
           `UNIQUE(serie, numero)`; esto sólo convierte ese choque en un
           reintento en lugar de en un error para el vendedor.

           Tres intentos bastan: el conflicto exige coincidencia en el mismo
           milisegundo y la probabilidad de encadenar tres es despreciable. */
        for ($intento = 1; $intento <= 3; $intento++) {
            try {
                return DB::transaction(function () use ($pedido, $serie, $esFactura) {
                    /* El bloqueo va sobre la FILA más alta de esta serie, no
                       sobre un `max()`: PostgreSQL rechaza `FOR UPDATE` junto a
                       una función de agregación ("FOR UPDATE no está permitido
                       con funciones de agregación"). */
                    $ultimo = ComprobanteElectronico::where('serie', $serie)
                        ->orderByDesc('numero')
                        ->lockForUpdate()
                        ->first();

                    return ComprobanteElectronico::create([
                        'pedido_id'         => $pedido->id,
                        'tipo_comprobante'  => $esFactura ? 'factura' : 'boleta',
                        'serie'             => $serie,
                        'numero'            => (int) ($ultimo?->numero ?? 0) + 1,
                        'cliente_nombre'    => $pedido->cliente_nombre ?: 'Cliente sin identificar',
                        'cliente_documento' => $pedido->cliente_documento,
                        'total'             => $pedido->total,
                        'estado_sunat'      => 'pendiente',
                        'fecha_emision'     => now(),
                    ]);
                });
            } catch (QueryException $e) {
                /* 23505 es violación de unicidad: otra caja ganó la carrera por
                   ese número. Cualquier otro error no es una carrera y debe
                   salir a la superficie en vez de reintentarse en bucle. */
                if ($intento === 3 || !str_contains($e->getMessage(), '23505')) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('No se pudo asignar un correlativo de comprobante.');
    }

    /**
     * Factura sólo con RUC válido en forma (11 dígitos).
     *
     * No se comprueba contra SUNAT: eso exige consultar su padrón y no es
     * trabajo de esta versión. Lo que sí se hace es no emitir una factura
     * cuando el documento no puede ser un RUC, porque sería inválida de salida.
     */
    private function correspondeFactura(Pedido $pedido): bool
    {
        if ($pedido->cliente_tipo_documento === 'ruc') {
            return true;
        }

        $doc = preg_replace('/\D/', '', (string) $pedido->cliente_documento);

        return strlen((string) $doc) === 11;
    }

    /**
     * Los datos del comprobante listos para imprimir o para armar el XML.
     *
     * Se devuelven en un solo sitio para que la térmica del mostrador y el
     * futuro XML de SUNAT partan de las MISMAS cifras. Si cada uno las
     * recalculara por su cuenta, acabarían discrepando — es el mismo error que
     * el IGV duplicado entre el portal y la venta, y por eso los importes salen
     * de `DesgloseFiscalService` y no de una suma hecha aquí.
     *
     * @return array<string, mixed>
     */
    public function datosParaImpresion(ComprobanteElectronico $comprobante): array
    {
        $pedido = $comprobante->pedido()->with('detalles.producto')->first();

        $desglose = $this->fiscal->repartir(
            (float) $pedido->subtotal_gravado + (float) $pedido->igv,
            (float) $pedido->subtotal_exonerado,
            (float) $pedido->subtotal_inafecto,
        );

        return [
            'comprobante' => [
                'tipo'          => $comprobante->tipo_comprobante,
                'serie'         => $comprobante->serie,
                /* Ocho dígitos con ceros a la izquierda: es el formato que
                   exige SUNAT y el que espera leer quien archiva en papel. */
                'numero'        => str_pad((string) $comprobante->numero, 8, '0', STR_PAD_LEFT),
                'identificador' => $comprobante->serie.'-'.str_pad((string) $comprobante->numero, 8, '0', STR_PAD_LEFT),
                'fecha_emision' => $comprobante->fecha_emision?->toIso8601String(),
                'estado_sunat'  => $comprobante->estado_sunat,
            ],
            'cliente' => [
                'nombre'         => $comprobante->cliente_nombre,
                'documento'      => $comprobante->cliente_documento,
                'tipo_documento' => $pedido->cliente_tipo_documento,
                'telefono'       => $pedido->cliente_telefono,
            ],
            'items' => $pedido->detalles->map(fn ($d) => [
                'descripcion'     => trim(($d->producto->nombre ?? 'Producto').' '.($d->producto->concentracion ?? '')),
                'unidad_venta'    => $d->unidad_venta,
                'cantidad'        => $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
                'subtotal'        => (float) $d->subtotal,
                'afectacion_igv'  => $d->tipo_afectacion_igv,
            ])->all(),
            'totales' => [
                'subtotal_gravado'   => $desglose['subtotal_gravado'],
                'subtotal_exonerado' => $desglose['subtotal_exonerado'],
                'subtotal_inafecto'  => $desglose['subtotal_inafecto'],
                'igv'                => $desglose['igv'],
                'tasa_igv'           => $desglose['tasa_igv'],
                'total'              => $desglose['total'],
            ],
            /* La leyenda es obligatoria en el comprobante impreso. */
            'leyenda' => 'Los precios incluyen IGV.',
        ];
    }
}
