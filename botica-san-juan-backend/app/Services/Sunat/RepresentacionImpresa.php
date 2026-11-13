<?php

namespace App\Services\Sunat;

use App\Models\ComprobanteElectronico;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\ComprobanteService;
use App\Services\DesgloseFiscalService;
use App\Services\NombreArchivoSunat;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentoPdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Representación impresa de la boleta y de la factura electrónica.
 * ---------------------------------------------------------------------------
 * QUE ES Y QUE NO ES
 *
 * El comprobante electrónico, para la SUNAT, es el XML. Lo que el cliente se
 * lleva en la mano es su «representación impresa»: un documento que tiene que
 * decir lo mismo que el XML y llevar unos datos mínimos —RUC y denominación
 * en un recuadro, serie y correlativo, datos del adquirente, detalle, totales
 * por tipo de operación, importe en letras y un código QR con un contenido
 * fijado por la SUNAT—.
 *
 * POR QUE VIVE AQUI Y NO EN EL CONTROLADOR
 *
 * Todo esto estaba en `FacturacionController::descargarPdf`, con dos
 * problemas que solo se ven al compararlo con la norma:
 *
 *  - el IGV se calculaba como `total / 1.18` sobre el total entero, así que
 *    una venta con productos exonerados imprimía un impuesto que nadie cobró;
 *  - el QR llevaba una cadena propia (ticket y hash internos), no la que lee
 *    la aplicación de la SUNAT.
 *
 * Las cifras salen ahora de `ComprobanteService::datosParaImpresion`, que es
 * lo que ya usa el portal para mostrar el comprobante: PDF y pantalla parten
 * del mismo desglose y no pueden discrepar.
 */
class RepresentacionImpresa
{
    public const DENOMINACION_BOLETA  = 'BOLETA DE VENTA ELECTRÓNICA';
    public const DENOMINACION_FACTURA = 'FACTURA ELECTRÓNICA';

    /** Catálogo 06 de la SUNAT: tipo de documento de identidad. */
    public const DOC_DNI           = '1';
    public const DOC_CARNE_EXTRANJ = '4';
    public const DOC_RUC           = '6';

    /* «-» es el valor con el que se declara al adquirente no identificado:
       una boleta por menos de S/ 700 no obliga a pedir el documento. */
    public const DOC_SIN_DOCUMENTO = '-';

    private const ETIQUETAS_DOCUMENTO = [
        self::DOC_DNI           => 'DNI',
        self::DOC_CARNE_EXTRANJ => 'CARNÉ DE EXTRANJERÍA',
        self::DOC_RUC           => 'RUC',
        self::DOC_SIN_DOCUMENTO => 'SIN DOCUMENTO',
    ];

    private const TIPOS_INTERNOS = [
        'dni' => self::DOC_DNI,
        'ce'  => self::DOC_CARNE_EXTRANJ,
        'ruc' => self::DOC_RUC,
    ];

    private const MONEDAS = [
        'PEN' => ['nombre' => 'SOLES', 'simbolo' => 'S/'],
        'USD' => ['nombre' => 'DÓLARES AMERICANOS', 'simbolo' => 'US$'],
    ];

    private const MEDIOS_DE_PAGO = [
        'efectivo'      => 'Efectivo',
        'tarjeta'       => 'Tarjeta',
        'yape'          => 'Yape',
        'plin'          => 'Plin',
        'transferencia' => 'Transferencia',
        'mixto'         => 'Mixto',
    ];

    public function __construct(
        private readonly ComprobanteService $comprobantes,
        private readonly DesgloseFiscalService $fiscal,
        private readonly NombreArchivoSunat $nombres,
        private readonly ImporteEnLetras $letras,
    ) {
    }

    /**
     * El PDF listo para descargar o guardar.
     */
    public function pdf(ComprobanteElectronico $comprobante): DocumentoPdf
    {
        /* Sin subconjunto de fuente, dompdf incrusta DejaVu Sans entera y una
           boleta de una línea pesa cerca de 900 KB. Con él solo viajan los
           glifos usados: importa cuando el cliente la descarga con datos
           móviles o se adjunta a un correo. */
        return Pdf::setOption('isFontSubsettingEnabled', true)
            ->loadView('pdf.comprobante', $this->datos($comprobante))
            ->setPaper('a4');
    }

    /** Nombre del archivo que recibe el cliente: B001-00000012.pdf */
    public function nombreDeArchivo(ComprobanteElectronico $comprobante): string
    {
        return $this->identificador($comprobante).'.pdf';
    }

    /**
     * Todo lo que la plantilla imprime, ya resuelto.
     *
     * La plantilla no calcula nada: si lo hiciera, la cifra impresa y la del
     * QR —que se arma aquí— podrían separarse por un redondeo.
     *
     * @return array<string, mixed>
     */
    public function datos(ComprobanteElectronico $comprobante): array
    {
        $comprobante->loadMissing('pedido');
        $pedido = $comprobante->pedido;

        $base    = $pedido ? $this->comprobantes->datosParaImpresion($comprobante) : null;
        $totales = $this->totales($comprobante, $base['totales'] ?? null);
        $moneda  = strtoupper((string) ($pedido?->moneda ?: 'PEN'));
        $moneda  = isset(self::MONEDAS[$moneda]) ? $moneda : 'PEN';
        $cliente = $this->cliente($comprobante, $pedido);
        $fecha   = $comprobante->fecha_emision ?? now();
        $cadena  = $this->cadenaQr($comprobante, $totales, $cliente);

        return [
            'emisor' => [
                'ruc'              => (string) config('company.ruc'),
                'razon_social'     => (string) config('company.razon_social'),
                'nombre_comercial' => (string) config('company.nombre_comercial'),
                'direccion'        => (string) config('company.direccion'),
                'telefono'         => (string) config('company.telefono'),
                'email'            => (string) config('company.email'),
            ],
            'comprobante' => [
                'tipo'          => $comprobante->tipo_comprobante,
                'codigo_tipo'   => $this->nombres->tipoDesdeNombre((string) $comprobante->tipo_comprobante),
                'denominacion'  => $this->denominacion($comprobante),
                'serie'         => (string) $comprobante->serie,
                'numero'        => $this->correlativo($comprobante),
                'identificador' => $this->identificador($comprobante),
                'fecha_emision' => $fecha->format('d/m/Y'),
                'hora_emision'  => $fecha->format('H:i'),
                'moneda'        => $moneda,
                'moneda_nombre' => self::MONEDAS[$moneda]['nombre'],
                'simbolo'       => self::MONEDAS[$moneda]['simbolo'],
                'medio_pago'    => $this->medioDePago($pedido),
                'hash'          => $comprobante->hash_documento,
            ],
            'cliente'           => $cliente,
            'items'             => $this->items($comprobante, $base['items'] ?? [], $totales['tasa_igv']),
            'totales'           => $totales,
            'importe_en_letras' => $this->letras->leyenda($totales['total'], $moneda),
            'qr' => [
                'cadena' => $cadena,
                'imagen' => $this->imagenQr($cadena),
            ],
            'leyenda' => 'Representación impresa de la '.$this->denominacion($comprobante).'.',
            'logo'    => $this->logo(),
        ];
    }

    public function denominacion(ComprobanteElectronico $comprobante): string
    {
        return $comprobante->tipo_comprobante === 'factura'
            ? self::DENOMINACION_FACTURA
            : self::DENOMINACION_BOLETA;
    }

    /**
     * Contenido del código QR, en el orden que fija la SUNAT:
     *
     *   RUC | TIPO | SERIE | NUMERO | IGV | TOTAL | FECHA | TIPO DOC | Nº DOC |
     *
     * La barra final no es un descuido: el formato la lleva, y detrás iría el
     * valor resumen de la firma el día que el XML se firme.
     *
     * @param  array{igv: float, total: float}|null  $totales
     * @param  array{tipo_documento: string, numero_documento: string}|null  $cliente
     */
    public function cadenaQr(ComprobanteElectronico $comprobante, ?array $totales = null, ?array $cliente = null): string
    {
        $comprobante->loadMissing('pedido');

        $totales ??= $this->totales(
            $comprobante,
            $comprobante->pedido ? $this->comprobantes->datosParaImpresion($comprobante)['totales'] : null,
        );
        $cliente ??= $this->cliente($comprobante, $comprobante->pedido);

        return implode('|', [
            (string) config('company.ruc'),
            $this->nombres->tipoDesdeNombre((string) $comprobante->tipo_comprobante),
            (string) $comprobante->serie,
            $this->correlativo($comprobante),
            number_format($totales['igv'], 2, '.', ''),
            number_format($totales['total'], 2, '.', ''),
            ($comprobante->fecha_emision ?? now())->format('Y-m-d'),
            $cliente['tipo_documento'],
            $cliente['numero_documento'],
        ]).'|';
    }

    /**
     * Totales por tipo de operación, cuadrados al céntimo.
     *
     * Lo normal es que vengan del pedido, donde `VentaService` los dejó
     * calculados al vender. Pero hay comprobantes cuyo pedido no los tiene
     * —los pedidos anteriores al desglose fiscal, que el panel factura en
     * bloque—: ahí la suma da cero y no coincide con el total del
     * comprobante. En ese caso todo el importe se trata como gravado, que es
     * lo que hacía el PDF anterior, pero pasando por el mismo reparto para
     * que base + IGV devuelva exactamente el total.
     *
     * @param  array<string, float>|null  $delPedido
     * @return array{gravada: float, exonerada: float, inafecta: float, igv: float, tasa_igv: float, total: float}
     */
    private function totales(ComprobanteElectronico $comprobante, ?array $delPedido): array
    {
        $total = round((float) $comprobante->total, 2);

        if ($delPedido === null || abs($delPedido['total'] - $total) >= 0.005) {
            $delPedido = $this->fiscal->repartir($total);
        }

        return [
            'gravada'   => $delPedido['subtotal_gravado'],
            'exonerada' => $delPedido['subtotal_exonerado'],
            'inafecta'  => $delPedido['subtotal_inafecto'],
            'igv'       => $delPedido['igv'],
            'tasa_igv'  => $delPedido['tasa_igv'],
            'total'     => $delPedido['total'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     * @return array<int, array<string, mixed>>
     */
    private function items(ComprobanteElectronico $comprobante, array $lineas, float $tasa): array
    {
        /* Un comprobante sin líneas existe: los que se emitieron sobre pedidos
           importados sin detalle. Se imprime una línea por el total antes que
           una tabla vacía, que parecería un documento roto. */
        if ($lineas === []) {
            $lineas = [[
                'descripcion'     => 'Venta general',
                'unidad_venta'    => 'unidad',
                'cantidad'        => 1,
                'precio_unitario' => (float) $comprobante->total,
                'subtotal'        => (float) $comprobante->total,
                'afectacion_igv'  => Producto::GRAVADO,
            ]];
        }

        return array_map(function (array $linea) use ($tasa) {
            $precio  = (float) $linea['precio_unitario'];
            $gravado = ($linea['afectacion_igv'] ?? Producto::GRAVADO) === Producto::GRAVADO;

            return [
                'cantidad'        => $linea['cantidad'],
                'unidad'          => mb_strtoupper((string) ($linea['unidad_venta'] ?: 'unidad')),
                'descripcion'     => (string) $linea['descripcion'],
                /* El valor unitario es el precio sin impuesto. Es informativo:
                   los totales NO se obtienen sumando esta columna, porque
                   redondear línea a línea descuadra céntimos. */
                'valor_unitario'  => $gravado && $tasa > 0 ? round($precio / (1 + $tasa), 2) : $precio,
                'precio_unitario' => $precio,
                'importe'         => (float) $linea['subtotal'],
            ];
        }, $lineas);
    }

    /**
     * @return array{tipo_documento: string, etiqueta_documento: string, numero_documento: string, nombre: string, direccion: ?string}
     */
    private function cliente(ComprobanteElectronico $comprobante, ?Pedido $pedido): array
    {
        $numero = trim((string) $comprobante->cliente_documento);
        $tipo   = $this->tipoDeDocumento($comprobante, $pedido, $numero);

        if ($tipo === self::DOC_SIN_DOCUMENTO) {
            $numero = '-';
        }

        /* El portal guarda la dirección de entrega en `observacion`; en
           mostrador ese mismo campo es una nota libre del vendedor, que no
           tiene por qué salir impresa en el comprobante del cliente. */
        $direccion = $pedido?->origen === Pedido::ORIGEN_WEB
            ? (trim((string) $pedido->observacion) ?: null)
            : null;

        return [
            'tipo_documento'     => $tipo,
            'etiqueta_documento' => self::ETIQUETAS_DOCUMENTO[$tipo],
            'numero_documento'   => $numero,
            'nombre'             => (string) ($comprobante->cliente_nombre ?: 'Cliente sin identificar'),
            'direccion'          => $direccion,
        ];
    }

    private function tipoDeDocumento(ComprobanteElectronico $comprobante, ?Pedido $pedido, string $numero): string
    {
        if ($numero === '') {
            return self::DOC_SIN_DOCUMENTO;
        }

        /* Una factura sólo puede ir a un RUC: es lo que decidió su emisión. */
        if ($comprobante->tipo_comprobante === 'factura') {
            return self::DOC_RUC;
        }

        $declarado = (string) $pedido?->cliente_tipo_documento;

        if (isset(self::TIPOS_INTERNOS[$declarado])) {
            return self::TIPOS_INTERNOS[$declarado];
        }

        /* Sin tipo declarado se deduce por la forma, como hace la emisión.
           Un número que no es DNI ni RUC sigue siendo un documento: imprimir
           «sin documento» junto a un adquirente que sí se identificó sería
           falso. El catálogo 06 no tiene un código genérico de identidad (el
           «0» es para no domiciliados), y de los tipos que el sistema acepta
           el único que queda es el carné de extranjería. */
        return match (strlen(preg_replace('/\D/', '', $numero) ?? '')) {
            8       => self::DOC_DNI,
            11      => self::DOC_RUC,
            default => self::DOC_CARNE_EXTRANJ,
        };
    }

    private function medioDePago(?Pedido $pedido): string
    {
        $medio = strtolower(trim((string) $pedido?->medio_pago));

        /* Un encargo del portal todavía no tiene cobro registrado en el
           pedido. Se dice así en vez de inventar «efectivo». */
        return self::MEDIOS_DE_PAGO[$medio] ?? ($medio !== '' ? ucfirst($medio) : 'No registrado');
    }

    /** Ocho dígitos: es como se identifica el comprobante en todo el sistema. */
    private function correlativo(ComprobanteElectronico $comprobante): string
    {
        return str_pad((string) $comprobante->numero, 8, '0', STR_PAD_LEFT);
    }

    private function identificador(ComprobanteElectronico $comprobante): string
    {
        return $comprobante->serie.'-'.$this->correlativo($comprobante);
    }

    /**
     * El QR como imagen incrustable. Devuelve null si no se pudo generar: un
     * PDF sin QR sigue siendo legible, y un error aquí no debe impedir que el
     * cliente descargue su comprobante.
     */
    private function imagenQr(string $cadena): ?string
    {
        try {
            $opciones = new QROptions([
                'outputType'   => 'png',
                /* Nivel Q: es el que pide la SUNAT para la representación
                   impresa (aguanta un 25 % de daño en el papel térmico). */
                'eccLevel'     => EccLevel::Q,
                'scale'        => 6,
                'outputBase64' => true,
            ]);

            return (new QRCode($opciones))->render($cadena);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Logotipo propio, si se configuró uno. No hay imagen por omisión: sin
     * archivo, la cabecera se compone solo con tipografía.
     */
    private function logo(): ?string
    {
        $relativa = (string) config('company.logo_path');
        $ruta     = $relativa !== '' ? public_path($relativa) : null;

        if (! $ruta || ! is_file($ruta)) {
            return null;
        }

        $contenido = @file_get_contents($ruta);

        if ($contenido === false) {
            return null;
        }

        $mime = strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) === 'png' ? 'image/png' : 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($contenido);
    }
}
