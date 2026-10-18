<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use App\Models\Pedido;
use App\Models\SerieComprobante;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
 * impide a nivel de motor; el siguiente número sale de la tabla
 * `series_comprobante`, cuya fila se bloquea (`lockForUpdate`) dentro de la
 * misma transacción que crea el comprobante, para que dos ventas simultáneas en
 * dos cajas se pongan en cola en vez de leer el mismo número. Además un UNIQUE
 * parcial sobre `pedido_id` garantiza un comprobante por pedido.
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
        return $this->emitirConDatos(
            $pedido,
            $this->correspondeFactura($pedido) ? 'factura' : 'boleta',
            [
                'cliente_nombre'    => $pedido->cliente_nombre ?: 'Cliente sin identificar',
                'cliente_documento' => $pedido->cliente_documento,
                'total'             => $pedido->total,
                'fecha_emision'     => now(),
            ]
        );
    }

    /**
     * Punto único de asignación de correlativo. `emitir` y la generación
     * masiva del panel de facturación pasan por aquí, cada una con sus propios
     * datos de cliente, para que exista UN solo camino que reserve números.
     *
     * Todo ocurre en una transacción corta:
     *   1. se bloquea la fila de la serie (`FOR UPDATE`): la segunda caja
     *      espera aquí y, al liberarse, lee el contador ya incrementado, sin
     *      chocar ni reintentar;
     *   2. con la serie bloqueada se comprueba si el pedido ya tiene
     *      comprobante (la otra petición pudo confirmarlo mientras esperábamos);
     *   3. se incrementa el contador y se crea el comprobante. Si el INSERT
     *      falla, el incremento se revierte con él: no hay huecos por error.
     *
     * Dentro de esta transacción no se genera XML, no se firma y no se llama a
     * SUNAT.
     *
     * @param  'boleta'|'factura'  $tipo
     * @param  array<string, mixed>  $datos  cliente_nombre, cliente_documento, total, fecha_emision
     */
    public function emitirConDatos(Pedido $pedido, string $tipo, array $datos): ComprobanteElectronico
    {
        $serie = $tipo === 'factura' ? self::SERIE_FACTURA : self::SERIE_BOLETA;

        try {
            /* El segundo argumento reintenta solo ante deadlock (40P01) o fallo
               de serialización; no hay reintento por violación de unicidad. */
            return DB::transaction(function () use ($pedido, $tipo, $serie, $datos) {
                $fila = SerieComprobante::bloquear($serie, $tipo);

                $yaEmitido = ComprobanteElectronico::where('pedido_id', $pedido->id)->first();
                if ($yaEmitido) {
                    return $yaEmitido;
                }

                /* Red de seguridad ante un contador desfasado (alguien insertó
                   comprobantes a mano o restauró un respaldo parcial): nunca se
                   emite por debajo de lo ya existente en la serie. Sólo avanza,
                   así que no puede crear duplicados. */
                $maximoReal = (int) ComprobanteElectronico::where('serie', $serie)->max('numero');
                $numero     = max($fila->ultimo_numero, $maximoReal) + 1;

                if ($maximoReal > $fila->ultimo_numero) {
                    Log::warning('Contador de serie desfasado; se corrige al máximo emitido.', [
                        'serie'         => $serie,
                        'ultimo_numero' => $fila->ultimo_numero,
                        'maximo_real'   => $maximoReal,
                    ]);
                }

                $fila->ultimo_numero = $numero;
                $fila->save();

                return ComprobanteElectronico::create([
                    'pedido_id'         => $pedido->id,
                    'tipo_comprobante'  => $tipo,
                    'serie'             => $serie,
                    'numero'            => $numero,
                    'cliente_nombre'    => $datos['cliente_nombre'],
                    'cliente_documento' => $datos['cliente_documento'] ?? null,
                    'total'             => $datos['total'],
                    'estado_sunat'      => 'pendiente',
                    'fecha_emision'     => $datos['fecha_emision'] ?? now(),
                ]);
            }, 3);
        } catch (QueryException $e) {
            /* Segunda red: si el UNIQUE parcial de `pedido_id` saltó (otra
               conexión lo confirmó entre medias), la emisión ya existe y se
               devuelve. Cualquier otro error sube tal cual. */
            if (str_contains($e->getMessage(), 'comprobantes_electronicos_pedido_id_unique_activo')) {
                $existente = ComprobanteElectronico::where('pedido_id', $pedido->id)->first();
                if ($existente) {
                    return $existente;
                }
            }

            throw $e;
        }
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
