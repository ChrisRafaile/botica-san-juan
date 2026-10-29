<?php

namespace Tests\Feature;

use App\Models\ComprobanteElectronico;
use App\Services\NombreArchivoSunat;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Convención de nombre de los archivos de comprobante.
 *
 * El nombre forma parte del contrato de envío: viaja dentro del sobre SOAP y
 * un formato incorrecto hace que el comprobante se rechace antes de que nadie
 * mire su contenido. Por eso se comprueba aquí y no se deja al criterio de
 * cada sitio que genere un archivo.
 */
class NombreArchivoSunatTest extends TestCase
{
    private NombreArchivoSunat $nombres;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nombres = new NombreArchivoSunat();
        config(['company.ruc' => '20123456789']);
    }

    public function test_construye_el_nombre_con_el_formato_de_la_sunat(): void
    {
        $this->assertSame(
            '20123456789-01-F001-00000123',
            $this->nombres->construir('20123456789', '01', 'F001', 123),
        );
    }

    public function test_el_correlativo_se_rellena_a_ocho_digitos(): void
    {
        $this->assertStringEndsWith('-00000001', $this->nombres->construir('20123456789', '03', 'B001', 1));
        $this->assertStringEndsWith('-99999999', $this->nombres->construir('20123456789', '03', 'B001', 99999999));
    }

    public function test_una_serie_que_no_corresponde_al_tipo_se_rechaza(): void
    {
        /* El caso real que esto evita: alguien añade un tipo nuevo copiando
           el anterior y deja la serie del anterior. La SUNAT lo rechazaría
           sin explicar gran cosa. */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('debería empezar por «F»');

        $this->nombres->construir('20123456789', '01', 'B001', 1);
    }

    public function test_un_ruc_que_no_tiene_once_digitos_se_rechaza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->nombres->construir('2012345', '01', 'F001', 1);
    }

    public function test_un_correlativo_fuera_de_rango_se_rechaza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->nombres->construir('20123456789', '01', 'F001', 100000000);
    }

    public function test_traduce_el_vocabulario_interno_al_catalogo_de_la_sunat(): void
    {
        $this->assertSame('01', $this->nombres->tipoDesdeNombre('factura'));
        $this->assertSame('03', $this->nombres->tipoDesdeNombre('boleta'));
        $this->assertSame('07', $this->nombres->tipoDesdeNombre('nota_credito'));
    }

    public function test_un_tipo_desconocido_no_pasa_en_silencio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->nombres->tipoDesdeNombre('ticket');
    }

    public function test_deriva_los_tres_nombres_desde_el_comprobante(): void
    {
        $comprobante = new ComprobanteElectronico([
            'tipo_comprobante' => 'boleta',
            'serie'            => 'B001',
            'numero'           => 42,
        ]);

        $this->assertSame('20123456789-03-B001-00000042.xml', $this->nombres->xml($comprobante));
        $this->assertSame('20123456789-03-B001-00000042.zip', $this->nombres->zip($comprobante));

        /* El CDR lleva el prefijo R- sobre el mismo nombre base, que es lo
           que permite emparejar comprobante y constancia mirando el
           directorio en vez de consultando la base. */
        $this->assertSame('R-20123456789-03-B001-00000042.zip', $this->nombres->cdr($comprobante));
    }
}
