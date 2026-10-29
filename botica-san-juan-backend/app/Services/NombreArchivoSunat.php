<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use InvalidArgumentException;

/**
 * Nombre normalizado de los archivos de comprobante electrónico.
 * ---------------------------------------------------------------------------
 * POR QUE EL NOMBRE DEL ARCHIVO ES UNA REGLA Y NO UNA PREFERENCIA
 *
 * En el envío directo a la SUNAT, el nombre del archivo forma parte del
 * contrato: el ZIP que se manda viaja con su nombre dentro del sobre SOAP, y
 * un nombre que no respete el formato hace que el comprobante se rechace
 * antes de que nadie mire su contenido. No es un detalle de organización de
 * carpetas.
 *
 * El formato es:
 *
 *     RUC - TIPO - SERIE - CORRELATIVO
 *     20123456789-01-F001-00000123
 *
 * donde el tipo es el catálogo 01 de la SUNAT: 01 factura, 03 boleta,
 * 07 nota de crédito, 08 nota de débito.
 *
 * LA VALIDACION QUE PARECE EXCESIVA Y NO LO ES
 *
 * La letra de la serie tiene que corresponder al tipo: las facturas usan
 * series que empiezan por F y las boletas por B. Enviar el tipo 01 con una
 * serie «B001» es una incoherencia que la SUNAT rechaza, y es un error fácil
 * de cometer el día que alguien añada un tipo nuevo y copie el caso anterior.
 * Por eso aquí se comprueba y se lanza excepción: más vale que falle en
 * desarrollo, con el mensaje delante, que descubrirlo por un comprobante
 * rechazado en producción.
 *
 * ESTE PROYECTO TODAVIA NO ENVIA A LA SUNAT
 *
 * La integración está en modo simulado, como explica docs/FACTURACION-SUNAT.md.
 * La convención se adopta ya de todas formas, porque es gratis hacerlo bien
 * desde el principio y carísimo renombrar archivos de los que ya dependen
 * registros guardados.
 */
class NombreArchivoSunat
{
    /** Catálogo 01 de la SUNAT: tipo de comprobante. */
    public const TIPO_FACTURA       = '01';
    public const TIPO_BOLETA        = '03';
    public const TIPO_NOTA_CREDITO  = '07';
    public const TIPO_NOTA_DEBITO   = '08';

    /** Letra con la que debe empezar la serie de cada tipo. */
    private const LETRA_DE_SERIE = [
        self::TIPO_FACTURA      => 'F',
        self::TIPO_BOLETA       => 'B',
        self::TIPO_NOTA_CREDITO => 'F',
        self::TIPO_NOTA_DEBITO  => 'F',
    ];

    private const NOMBRES = [
        'factura'      => self::TIPO_FACTURA,
        'boleta'       => self::TIPO_BOLETA,
        'nota_credito' => self::TIPO_NOTA_CREDITO,
        'nota_debito'  => self::TIPO_NOTA_DEBITO,
    ];

    /**
     * Nombre base, sin extensión: 20123456789-01-F001-00000123
     */
    public function base(ComprobanteElectronico $comprobante): string
    {
        return $this->construir(
            ruc: $this->ruc(),
            tipo: $this->tipoDesdeNombre((string) $comprobante->tipo_comprobante),
            serie: (string) $comprobante->serie,
            numero: (int) $comprobante->numero,
        );
    }

    public function xml(ComprobanteElectronico $comprobante): string
    {
        return $this->base($comprobante).'.xml';
    }

    public function zip(ComprobanteElectronico $comprobante): string
    {
        return $this->base($comprobante).'.zip';
    }

    /**
     * Nombre del archivo de la constancia de recepción.
     *
     * La SUNAT devuelve el CDR con el prefijo «R-» sobre el mismo nombre
     * base. Mantenerlo permite emparejar comprobante y constancia mirando el
     * directorio, sin consultar la base.
     */
    public function cdr(ComprobanteElectronico $comprobante): string
    {
        return 'R-'.$this->base($comprobante).'.zip';
    }

    public function construir(string $ruc, string $tipo, string $serie, int $numero): string
    {
        $ruc = preg_replace('/\D/', '', $ruc) ?? '';

        if (strlen($ruc) !== 11) {
            throw new InvalidArgumentException(
                "El RUC del emisor debe tener 11 dígitos; se recibió «{$ruc}». Revisa COMPANY_RUC."
            );
        }

        if (! isset(self::LETRA_DE_SERIE[$tipo])) {
            throw new InvalidArgumentException("Tipo de comprobante no contemplado: «{$tipo}».");
        }

        $serie = strtoupper(trim($serie));

        if (! preg_match('/^[A-Z][A-Z0-9]{3}$/', $serie)) {
            throw new InvalidArgumentException(
                "La serie «{$serie}» no tiene el formato esperado: una letra y tres caracteres."
            );
        }

        $esperada = self::LETRA_DE_SERIE[$tipo];

        if ($serie[0] !== $esperada) {
            throw new InvalidArgumentException(
                "La serie «{$serie}» no corresponde al tipo {$tipo}: debería empezar por «{$esperada}»."
            );
        }

        if ($numero < 1 || $numero > 99999999) {
            throw new InvalidArgumentException(
                "El correlativo {$numero} está fuera del rango permitido de ocho dígitos."
            );
        }

        return sprintf('%s-%s-%s-%08d', $ruc, $tipo, $serie, $numero);
    }

    /** Traduce el vocabulario interno al catálogo 01 de la SUNAT. */
    public function tipoDesdeNombre(string $nombre): string
    {
        $clave = strtolower(trim($nombre));

        if (! isset(self::NOMBRES[$clave])) {
            throw new InvalidArgumentException("Tipo de comprobante desconocido: «{$nombre}».");
        }

        return self::NOMBRES[$clave];
    }

    private function ruc(): string
    {
        return (string) config('company.ruc', '');
    }
}
