<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * El preflight del navegador decide si un POST con cabecera propia llega a
 * salir. Sin `Idempotency-Key` en la lista, iniciar un pago desde el portal
 * en producción fallaba antes de tocar el servidor.
 */
class CorsPagosTest extends TestCase
{
    public function test_el_preflight_de_iniciar_pago_permite_la_cabecera_de_idempotencia(): void
    {
        $origen = (array) config('cors.allowed_origins');
        $this->assertNotEmpty($origen, 'No hay orígenes permitidos configurados.');

        $respuesta = $this->call('OPTIONS', '/api/pedidos/1/pago', [], [], [], [
            'HTTP_ORIGIN' => $origen[0],
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type,idempotency-key',
        ]);

        $respuesta->assertSuccessful();
        $permitidas = strtolower((string) $respuesta->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('idempotency-key', $permitidas);
        $this->assertStringContainsString('authorization', $permitidas);
    }

    public function test_sin_credenciales_de_izipay_el_cobro_falla_con_mensaje_claro_y_sin_excepcion(): void
    {
        config()->set('services.izipay.mode', 'api');
        config()->set('services.izipay.username', null);
        config()->set('services.izipay.password', null);

        $operacion = app(\App\Services\Pagos\IzipayClient::class)
            ->crearOperacion('BSJ-00000001-abcdef', 40.0, 'PEN', 'cliente@example.com');

        $this->assertFalse($operacion['ok']);
        $this->assertStringContainsString('pago con tarjeta no está disponible', $operacion['mensaje']);
        $this->assertStringNotContainsString('IZIPAY_', $operacion['mensaje']);
    }
}
