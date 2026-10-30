<?php

namespace App\Services\Sunat;

use RuntimeException;
use ZipArchive;

/**
 * Empaquetado de un comprobante para el envío a la SUNAT.
 * ---------------------------------------------------------------------------
 * QUE HACE Y QUE NO
 *
 * Toma un XML ya listo y lo deja en la forma exacta en que viaja: comprimido
 * en ZIP, el ZIP codificado en Base64 y ese texto metido en el cuerpo de un
 * sobre SOAP. Y hace el camino inverso con la respuesta, que llega con la
 * misma envoltura.
 *
 * **No firma.** La firma digital necesita un certificado tributario del
 * negocio, que es un trámite y no una línea de código; mientras no exista,
 * este servicio empaqueta lo que se le dé. Ver docs/FACTURACION-SUNAT.md.
 *
 * POR QUE TANTA ENVOLTURA PARA MANDAR UN XML
 *
 * Suena redundante —un XML dentro de un ZIP dentro de un Base64 dentro de
 * otro XML— y conviene entender el motivo antes de intentar simplificarlo.
 * SOAP transporta texto, y un ZIP es binario: sin Base64 no cabe. Y el ZIP
 * existe porque el servicio espera un paquete nombrado, no un documento
 * suelto: el nombre del archivo dentro del ZIP forma parte de lo que se
 * valida. Quitar cualquiera de las dos capas rompe el contrato.
 *
 * EL DETALLE QUE MAS SE OLVIDA
 *
 * El ZIP tiene que contener el XML con el nombre normalizado
 * —RUC-TIPO-SERIE-CORRELATIVO.xml— y nada más. Un ZIP con el archivo dentro
 * de una carpeta, o con el nombre cambiado, se rechaza sin mirar su
 * contenido.
 */
class EmpaquetadoSunat
{
    /** Espacios de nombres del sobre. Fijos: no son configurables. */
    private const NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const NS_SERVICIO = 'http://service.sunat.gob.pe';
    private const NS_WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    /**
     * Comprime el XML en un ZIP que contiene un único archivo con el nombre
     * normalizado.
     *
     * @param  string  $nombreBase  sin extensión: 20123456789-01-F001-00000123
     * @return string  contenido binario del ZIP
     */
    public function comprimir(string $xml, string $nombreBase): string
    {
        /* ZipArchive trabaja sobre un archivo del disco, no sobre memoria, de
           modo que se usa un temporal y se borra siempre, incluso si algo
           falla por el camino. Dejar temporales sueltos en el contenedor de
           Render es una fuga lenta que nadie nota hasta que no queda sitio. */
        $temporal = tempnam(sys_get_temp_dir(), 'sunat_');

        if ($temporal === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal para comprimir el comprobante.');
        }

        try {
            $zip = new ZipArchive();

            if ($zip->open($temporal, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
                throw new RuntimeException('No se pudo abrir el archivo ZIP para escritura.');
            }

            /* Sin carpetas y con el nombre exacto: el servicio valida el
               nombre del archivo que encuentra dentro. */
            $zip->addFromString($nombreBase.'.xml', $xml);
            $zip->close();

            $contenido = file_get_contents($temporal);

            if ($contenido === false) {
                throw new RuntimeException('No se pudo leer el ZIP recién creado.');
            }

            return $contenido;
        } finally {
            @unlink($temporal);
        }
    }

    /**
     * Extrae el primer XML de un ZIP recibido.
     *
     * Se usa con el CDR, que llega comprimido igual que viajó el comprobante.
     */
    public function extraerXml(string $zipBinario): string
    {
        $temporal = tempnam(sys_get_temp_dir(), 'cdr_');

        if ($temporal === false || file_put_contents($temporal, $zipBinario) === false) {
            throw new RuntimeException('No se pudo escribir el ZIP recibido para leerlo.');
        }

        try {
            $zip = new ZipArchive();

            if ($zip->open($temporal) !== true) {
                throw new RuntimeException('El archivo recibido no es un ZIP válido.');
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nombre = (string) $zip->getNameIndex($i);

                /* Se ignora lo que no sea XML en la raíz: algunos paquetes
                   traen directorios de metadatos que no interesan. */
                if (! str_ends_with(strtolower($nombre), '.xml')) {
                    continue;
                }

                $contenido = $zip->getFromIndex($i);
                $zip->close();

                if ($contenido === false) {
                    throw new RuntimeException("No se pudo extraer «{$nombre}» del ZIP.");
                }

                return $contenido;
            }

            $zip->close();
            throw new RuntimeException('El ZIP no contiene ningún archivo XML.');
        } finally {
            @unlink($temporal);
        }
    }

    /**
     * Arma el sobre SOAP completo, con la cabecera de seguridad y el adjunto.
     *
     * @param  string  $nombreZip  con extensión: 20123456789-01-F001-00000123.zip
     * @param  string  $zipBinario contenido del ZIP, sin codificar
     */
    public function sobreSoap(string $nombreZip, string $zipBinario, CredencialesSunat $credenciales): string
    {
        $contenido = base64_encode($zipBinario);

        /* El usuario que viaja es RUC + usuario SOL secundario pegados, sin
           separador. No es el usuario principal: la SUNAT lo rechaza por
           política, y es un error que cuesta horas descubrir porque el
           mensaje de vuelta no lo dice con claridad. */
        $usuario = $this->escapar($credenciales->usuarioCompleto());
        $clave = $this->escapar($credenciales->clave);
        $nombre = $this->escapar($nombreZip);

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <soapenv:Envelope xmlns:soapenv="{$this->nsSoap()}" xmlns:ser="{$this->nsServicio()}">
              <soapenv:Header>
                <wsse:Security xmlns:wsse="{$this->nsWsse()}">
                  <wsse:UsernameToken>
                    <wsse:Username>{$usuario}</wsse:Username>
                    <wsse:Password>{$clave}</wsse:Password>
                  </wsse:UsernameToken>
                </wsse:Security>
              </soapenv:Header>
              <soapenv:Body>
                <ser:sendBill>
                  <fileName>{$nombre}</fileName>
                  <contentFile>{$contenido}</contentFile>
                </ser:sendBill>
              </soapenv:Body>
            </soapenv:Envelope>
            XML;
    }

    /**
     * Saca el ZIP del CDR de la respuesta SOAP.
     *
     * La respuesta trae el mismo juego de muñecas al revés: dentro del sobre
     * hay un `applicationResponse` en Base64 que es un ZIP, y dentro de ese
     * ZIP está el XML del CDR.
     *
     * @return string  contenido binario del ZIP del CDR
     */
    public function cdrDesdeRespuesta(string $sobreRespuesta): string
    {
        if (! preg_match('/<(?:\w+:)?applicationResponse>(.*?)<\/(?:\w+:)?applicationResponse>/s', $sobreRespuesta, $coincidencias)) {
            throw new RuntimeException('La respuesta no contiene un applicationResponse: no hay CDR que guardar.');
        }

        $decodificado = base64_decode(trim($coincidencias[1]), true);

        if ($decodificado === false) {
            throw new RuntimeException('El applicationResponse no es Base64 válido.');
        }

        return $decodificado;
    }

    private function escapar(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function nsSoap(): string
    {
        return self::NS_SOAP;
    }

    private function nsServicio(): string
    {
        return self::NS_SERVICIO;
    }

    private function nsWsse(): string
    {
        return self::NS_WSSE;
    }
}
