<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre los indicadores de monitoreo del SLA (endpoints de salud) y las
 * cabeceras de seguridad exigidas por el RNF-02.
 */
class SaludYSeguridadTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_endpoint_de_salud_responde_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['status', 'service', 'timestamp']);
    }

    public function test_el_endpoint_de_salud_de_base_de_datos_responde_ok(): void
    {
        $this->getJson('/api/health/db')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'connected');
    }

    public function test_las_respuestas_incluyen_las_cabeceras_de_seguridad(): void
    {
        $respuesta = $this->getJson('/api/health');

        $respuesta->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');
        $respuesta->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_cada_respuesta_lleva_un_identificador_de_peticion(): void
    {
        $respuesta = $this->getJson('/api/health');

        // El middleware AttachRequestId permite correlacionar los registros con
        // los incidentes reportados por el usuario.
        $this->assertNotEmpty(
            $respuesta->headers->get('X-Request-Id') ?? $respuesta->headers->get('X-Request-ID'),
            'La respuesta deberia incluir la cabecera X-Request-Id.'
        );
    }
}
