<?php

namespace Tests\Feature;

use App\Models\ComprobanteElectronico;
use App\Services\Sunat\ConstanciaRecepcion;
use App\Services\Sunat\CredencialesSunat;
use App\Services\Sunat\EmpaquetadoSunat;
use App\Services\Sunat\SimuladorSunat;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Empaquetado y constancia de recepción.
 *
 * Lo que de verdad hay que demostrar aquí es que la envoltura se construye y
 * se deshace bien, porque es lo que falla el primer día en producción: el
 * nombre del archivo dentro del ZIP, el Base64, y la extracción del
 * applicationResponse de una respuesta con prefijos distintos a los
 * esperados.
 */
class EmpaquetadoSunatTest extends TestCase
{
    private EmpaquetadoSunat $empaquetado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empaquetado = new EmpaquetadoSunat();
        config(['company.ruc' => '20123456789']);
    }

    private function comprobante(array $extra = []): ComprobanteElectronico
    {
        return new ComprobanteElectronico(array_merge([
            'tipo_comprobante' => 'factura',
            'serie' => 'F001',
            'numero' => 123,
        ], $extra));
    }

    // ----------------------------------------------------------- ZIP

    public function test_el_zip_contiene_un_solo_archivo_con_el_nombre_normalizado(): void
    {
        $zip = $this->empaquetado->comprimir('<Invoice/>', '20123456789-01-F001-00000123');

        $temporal = tempnam(sys_get_temp_dir(), 'prueba_');
        file_put_contents($temporal, $zip);

        $archivo = new ZipArchive();
        $this->assertTrue($archivo->open($temporal) === true);

        /* Un solo archivo, sin carpetas: un ZIP con el documento dentro de un
           directorio se rechaza sin mirar su contenido. */
        $this->assertSame(1, $archivo->numFiles);
        $this->assertSame('20123456789-01-F001-00000123.xml', $archivo->getNameIndex(0));
        $this->assertSame('<Invoice/>', $archivo->getFromIndex(0));

        $archivo->close();
        @unlink($temporal);
    }

    public function test_el_xml_sobrevive_al_viaje_de_ida_y_vuelta(): void
    {
        $original = '<Invoice><Nota>Ñandú · 18 % · "comillas"</Nota></Invoice>';

        $zip = $this->empaquetado->comprimir($original, '20123456789-03-B001-00000001');

        $this->assertSame($original, $this->empaquetado->extraerXml($zip));
    }

    public function test_un_zip_sin_xml_se_rechaza_con_un_motivo(): void
    {
        $temporal = tempnam(sys_get_temp_dir(), 'vacio_');
        $archivo = new ZipArchive();
        $archivo->open($temporal, ZipArchive::OVERWRITE | ZipArchive::CREATE);
        $archivo->addFromString('leeme.txt', 'nada util');
        $archivo->close();

        $contenido = file_get_contents($temporal);
        @unlink($temporal);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no contiene ningún archivo XML');

        $this->empaquetado->extraerXml((string) $contenido);
    }

    // ---------------------------------------------------------- SOAP

    public function test_el_sobre_lleva_el_ruc_pegado_al_usuario_sol(): void
    {
        $credenciales = new CredencialesSunat('20123456789', 'CAJERO01', 'clave-de-prueba');

        $sobre = $this->empaquetado->sobreSoap('x.zip', 'contenido', $credenciales);

        /* RUC y usuario van juntos y sin separador: es la regla que más se
           incumple y el servicio no lo explica al rechazar. */
        $this->assertStringContainsString('<wsse:Username>20123456789CAJERO01</wsse:Username>', $sobre);
        $this->assertStringContainsString('<fileName>x.zip</fileName>', $sobre);
        $this->assertStringContainsString(base64_encode('contenido'), $sobre);
    }

    public function test_un_usuario_sol_vacio_se_rechaza_antes_de_enviar_nada(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('usuario SOL secundario');

        new CredencialesSunat('20123456789', '', 'clave');
    }

    public function test_la_clave_no_aparece_en_la_representacion_para_la_bitacora(): void
    {
        $credenciales = new CredencialesSunat('20123456789', 'CAJERO01', 'secreto-que-no-debe-salir');

        $registrado = json_encode($credenciales->paraBitacora());

        $this->assertStringNotContainsString('secreto-que-no-debe-salir', (string) $registrado);
        $this->assertStringContainsString('20123456789CAJERO01', (string) $registrado);
    }

    // ----------------------------------------------------------- CDR

    public function test_extrae_el_cdr_de_una_respuesta_con_prefijos_distintos(): void
    {
        $simulador = app(SimuladorSunat::class);
        $sobre = $simulador->responder($this->comprobante(), '20123456789-01-F001-00000123');

        $zipCdr = $this->empaquetado->cdrDesdeRespuesta($sobre);
        $xml = $this->empaquetado->extraerXml($zipCdr);

        $this->assertStringContainsString('ApplicationResponse', $xml);
        $this->assertStringContainsString('<cbc:ResponseCode>0</cbc:ResponseCode>', $xml);
    }

    public function test_una_respuesta_sin_application_response_no_pasa_por_buena(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no hay CDR que guardar');

        $this->empaquetado->cdrDesdeRespuesta('<soap:Envelope><soap:Body/></soap:Envelope>');
    }

    public function test_traduce_los_codigos_de_respuesta_a_los_tres_estados(): void
    {
        $constancias = app(ConstanciaRecepcion::class);

        $this->assertSame('aceptada', $constancias->estadoDesdeCodigo('0'));
        $this->assertSame('rechazada', $constancias->estadoDesdeCodigo('2335'));

        /* 4000 en adelante es observado, que es ACEPTADO con reparos.
           Tratarlo como rechazo llevaría a reenviar un comprobante que la
           SUNAT ya tiene, es decir, a duplicarlo. */
        $this->assertSame('observada', $constancias->estadoDesdeCodigo('4000'));
        $this->assertSame('observada', $constancias->estadoDesdeCodigo('4267'));
    }

    public function test_lee_el_codigo_la_descripcion_y_las_notas_del_cdr(): void
    {
        $simulador = app(SimuladorSunat::class);
        $constancias = app(ConstanciaRecepcion::class);

        $sobre = $simulador->responder($this->comprobante(), '20123456789-01-F001-00000123', 'observada');
        $xml = $this->empaquetado->extraerXml($this->empaquetado->cdrDesdeRespuesta($sobre));

        $lectura = $constancias->leerXml($xml);

        $this->assertSame('4000', $lectura['codigo']);
        $this->assertSame('observada', $lectura['estado']);
        $this->assertNotEmpty($lectura['notas']);
    }

    public function test_un_cdr_sin_codigo_de_respuesta_se_rechaza(): void
    {
        $constancias = app(ConstanciaRecepcion::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no trae ResponseCode');

        $constancias->leerXml('<ApplicationResponse><cbc:Description>x</cbc:Description></ApplicationResponse>');
    }
}
