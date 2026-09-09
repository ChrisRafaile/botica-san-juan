<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los documentos tributarios contienen el nombre y el documento de identidad
 * del cliente. Su lectura anonima era un hallazgo de seguridad del informe
 * APF1 y esta prueba fija el comportamiento corregido.
 */
class AccesoDocumentosTributariosTest extends TestCase
{
    use RefreshDatabase;

    public static function rutasTributarias(): array
    {
        return [
            'listado de comprobantes' => ['/api/facturacion/documentos'],
            'descarga XML' => ['/api/facturacion/documentos/1/xml'],
            'descarga PDF' => ['/api/facturacion/documentos/1/pdf'],
            'listado de comisiones' => ['/api/facturacion/comisiones'],
        ];
    }

    /**
     * @dataProvider rutasTributarias
     */
    public function test_sin_token_los_documentos_tributarios_devuelven_401(string $ruta): void
    {
        $this->getJson($ruta)->assertStatus(401);
    }

    public function test_la_exportacion_gerencial_exige_rol_administrador(): void
    {
        $cliente = $this->crearCliente();
        $token = $cliente->createToken('prueba')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/reportes/gerencial/export/csv')
            ->assertStatus(403);
    }
}
