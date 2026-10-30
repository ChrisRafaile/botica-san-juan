<?php

namespace App\Services\Sunat;

use App\Models\ComprobanteElectronico;
use App\Services\NombreArchivoSunat;

/**
 * Respuesta simulada del servicio, con la estructura real.
 * ---------------------------------------------------------------------------
 * POR QUE LA SIMULACION DEVUELVE UN SOBRE Y NO UN ARRAY
 *
 * Porque lo que falla el primer día de producción no es la lógica de negocio:
 * es el nombre del archivo dentro del ZIP, el Base64, o la extracción del
 * `applicationResponse` de una respuesta que trae prefijos de espacio de
 * nombres distintos a los esperados. Una simulación que devuelve un array
 * deja todo eso sin probar y da una falsa sensación de que el camino está
 * recorrido.
 *
 * Aquí se construye el CDR como XML, se comprime, se codifica y se mete en un
 * sobre. El código que lo consume no sabe si vino de aquí o de Lima.
 *
 * EL CDR QUE SE DEVUELVE ES REALISTA, NO OPTIMISTA
 *
 * Se puede pedir un rechazo o una observación con `__forzar_estado`, porque
 * un simulador que solo sabe aceptar no sirve para probar lo que de verdad
 * cuesta: qué hace el sistema cuando le dicen que no.
 */
class SimuladorSunat
{
    public function __construct(
        private readonly NombreArchivoSunat $nombres,
        private readonly EmpaquetadoSunat $empaquetado,
    ) {
    }

    /** Devuelve un sobre SOAP de respuesta con el CDR dentro. */
    public function responder(ComprobanteElectronico $comprobante, string $nombreBase, ?string $forzar = null): string
    {
        [$codigo, $descripcion, $notas] = $this->desenlace($comprobante, $forzar);

        $xml = $this->cdrXml($comprobante, $nombreBase, $codigo, $descripcion, $notas);
        $zip = $this->empaquetado->comprimir($xml, 'R-'.$nombreBase);
        $contenido = base64_encode($zip);

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <ns2:sendBillResponse xmlns:ns2="http://service.sunat.gob.pe">
                  <applicationResponse>{$contenido}</applicationResponse>
                </ns2:sendBillResponse>
              </soap:Body>
            </soap:Envelope>
            XML;
    }

    /**
     * @return array{0:string, 1:string, 2:array<int,string>}
     */
    private function desenlace(ComprobanteElectronico $comprobante, ?string $forzar): array
    {
        $estado = $forzar ?? (string) ($comprobante->sunat_payload['__forzar_estado'] ?? 'aceptada');

        return match ($estado) {
            'rechazada' => [
                '2335',
                'El documento electrónico ingresado ha sido rechazado por una inconsistencia en sus importes.',
                [],
            ],
            'observada' => [
                '4000',
                'El comprobante ha sido aceptado con observaciones.',
                ['4267 - El dato ingresado en el campo Nombre del cliente no cumple con el formato establecido.'],
            ],
            default => [
                '0',
                sprintf(
                    'La %s %s, ha sido aceptada',
                    $comprobante->tipo_comprobante === 'factura' ? 'Factura' : 'Boleta',
                    $comprobante->serie.'-'.str_pad((string) $comprobante->numero, 8, '0', STR_PAD_LEFT),
                ),
                [],
            ],
        };
    }

    /** @param array<int, string> $notas */
    private function cdrXml(
        ComprobanteElectronico $comprobante,
        string $nombreBase,
        string $codigo,
        string $descripcion,
        array $notas,
    ): string {
        $emisor = (string) config('company.ruc', '');
        $fecha = now();
        $identificador = $comprobante->serie.'-'.str_pad((string) $comprobante->numero, 8, '0', STR_PAD_LEFT);

        $lineasNota = '';
        foreach ($notas as $nota) {
            $seguro = htmlspecialchars($nota, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $lineasNota .= "\n      <cbc:Note>{$seguro}</cbc:Note>";
        }

        $descripcionSegura = htmlspecialchars($descripcion, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        /* Estructura reducida de un ApplicationResponse UBL: lleva lo que el
           sistema lee —ResponseCode, Description y Note— con sus espacios de
           nombres reales, para que el lector del CDR se ejercite contra algo
           equivalente a lo que recibirá. */
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <ar:ApplicationResponse
                xmlns:ar="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2"
                xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
                xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
              <cbc:UBLVersionID>2.0</cbc:UBLVersionID>
              <cbc:CustomizationID>1.0</cbc:CustomizationID>
              <cbc:ID>{$nombreBase}</cbc:ID>
              <cbc:IssueDate>{$fecha->format('Y-m-d')}</cbc:IssueDate>
              <cbc:IssueTime>{$fecha->format('H:i:s')}</cbc:IssueTime>
              <cbc:ResponseDate>{$fecha->format('Y-m-d')}</cbc:ResponseDate>
              <cbc:ResponseTime>{$fecha->format('H:i:s')}</cbc:ResponseTime>
              <cac:SenderParty>
                <cac:PartyIdentification>
                  <cbc:ID>20131312955</cbc:ID>
                </cac:PartyIdentification>
              </cac:SenderParty>
              <cac:ReceiverParty>
                <cac:PartyIdentification>
                  <cbc:ID>{$emisor}</cbc:ID>
                </cac:PartyIdentification>
              </cac:ReceiverParty>
              <cac:DocumentResponse>
                <cac:Response>
                  <cbc:ReferenceID>{$identificador}</cbc:ReferenceID>
                  <cbc:ResponseCode>{$codigo}</cbc:ResponseCode>
                  <cbc:Description>{$descripcionSegura}</cbc:Description>{$lineasNota}
                </cac:Response>
              </cac:DocumentResponse>
            </ar:ApplicationResponse>
            XML;
    }
}
