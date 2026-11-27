<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Detrás del balanceador de Render.
 *
 * En producción toda petición llega desde la IP interna del proxy, con la del
 * cliente en X-Forwarded-For. `$middleware->use([...])` en bootstrap/app.php
 * reemplazaba la pila global entera y con ella TrustProxies: Laravel veía la
 * IP del proxy en todas las peticiones, así que cada limitador «por IP» era un
 * único cubo para todo el público, y HSTS no salía nunca porque la petición
 * no parecía segura.
 */
class ProxyDeRenderTest extends TestCase
{
    private const PROXY = '10.214.3.7';

    private function desde(string $ipCliente, string $esquema = 'https'): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::PROXY])
            ->withHeaders([
                'X-Forwarded-For' => $ipCliente,
                'X-Forwarded-Proto' => $esquema,
            ]);
    }

    public function test_la_ip_del_cliente_sale_de_x_forwarded_for(): void
    {
        $vista = null;
        $this->app['router']->get('/api/_prueba-ip', function (\Illuminate\Http\Request $r) use (&$vista) {
            $vista = $r->ip();

            return response()->json(['ok' => true]);
        });

        $this->desde('190.237.14.88')->getJson('/api/_prueba-ip')->assertOk();

        $this->assertSame('190.237.14.88', $vista);
    }

    public function test_un_cliente_que_agota_el_limite_no_bloquea_a_otro(): void
    {
        // El limitador de recuperación permite 3 por minuto y por IP.
        for ($i = 0; $i < 3; $i++) {
            $this->desde('190.237.14.88')
                ->postJson('/api/password/olvide', ['email' => "rosa.quispe{$i}@example.com"]);
        }
        $this->desde('190.237.14.88')
            ->postJson('/api/password/olvide', ['email' => 'rosa.quispe@example.com'])
            ->assertStatus(429);

        $this->desde('181.65.200.31')
            ->postJson('/api/password/olvide', ['email' => 'jorge.mendoza@example.com'])
            ->assertStatus(200);
    }

    public function test_hsts_sale_cuando_el_proxy_termina_tls(): void
    {
        $this->desde('190.237.14.88')
            ->getJson('/api/salud')
            ->assertHeader('Strict-Transport-Security');
    }

    public function test_un_cliente_no_puede_suplantar_su_ip_anteponiendo_otra(): void
    {
        $vista = null;
        $this->app['router']->get('/api/_prueba-ip', function (\Illuminate\Http\Request $r) use (&$vista) {
            $vista = $r->ip();

            return response()->json(['ok' => true]);
        });

        // El cliente envía su propio X-Forwarded-For falso; Render añade la IP
        // real al final. Debe ganar la que añadió el proxy.
        $this->desde('1.2.3.4, 190.237.14.88')->getJson('/api/_prueba-ip')->assertOk();

        $this->assertSame('190.237.14.88', $vista);
    }
}
