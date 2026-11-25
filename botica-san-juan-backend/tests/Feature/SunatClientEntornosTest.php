<?php

namespace Tests\Feature;

use App\Services\SunatClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SunatClientEntornosTest extends TestCase
{
    private const BOLETA = ['tipo_comprobante' => 'boleta', 'serie' => 'B001', 'numero' => 7, 'total' => 25.0];

    private function configurar(string $modo, string $url = 'https://ose.botica.test/v1', string $token = 'token-ficticio'): void
    {
        config(['services.sunat.mode' => $modo, 'services.sunat.base_url' => $url, 'services.sunat.token' => $token]);
        Http::preventStrayRequests();
        Http::fake(['*/comprobantes' => Http::response(['ok' => true, 'status' => 'aceptada', 'code' => '0', 'ticket' => 'T-1'])]);
    }

    private function enviar(array $extra = []): array
    {
        return app(SunatClient::class)->send(self::BOLETA + $extra);
    }

    public function test_simulado_no_sale_a_la_red(): void
    {
        $this->configurar('simulado');

        $this->assertSame('aceptada', $this->enviar(['__force_status' => 'aceptada'])['status']);
        Http::assertNothingSent();
    }

    public function test_beta_envia_al_microservicio_identificando_el_entorno(): void
    {
        $this->configurar('beta');

        $this->assertTrue($this->enviar(['__force_status' => 'rechazada'])['ok']);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://ose.botica.test/v1/comprobantes'
            && $r->hasHeader('X-Sunat-Entorno', 'beta')
            && $r->hasHeader('Authorization', 'Bearer token-ficticio')
            && ! array_key_exists('__force_status', $r->data()));
    }

    public function test_produccion_se_rechaza_fuera_de_un_entorno_de_produccion(): void
    {
        $this->configurar('produccion');

        $this->assertSame('CFG05', $this->enviar()['code']);
        Http::assertNothingSent();
    }

    public function test_produccion_envia_cuando_el_entorno_lo_es(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->configurar('produccion');

        $this->assertTrue($this->enviar()['ok']);
        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Sunat-Entorno', 'produccion'));
    }

    public function test_api_conserva_su_contrato_historico(): void
    {
        $this->configurar('api', 'http://ose-interno.test');

        $this->assertTrue($this->enviar()['ok']);
        Http::assertSent(fn (Request $r) => $r->url() === 'http://ose-interno.test/comprobantes'
            && ! $r->hasHeader('X-Sunat-Entorno'));
    }

    #[DataProvider('configuracionesInvalidas')]
    public function test_una_configuracion_invalida_no_envia_nada(string $modo, string $url, string $token, string $codigo): void
    {
        $this->configurar($modo, $url, $token);

        $resultado = $this->enviar();

        $this->assertFalse($resultado['ok']);
        $this->assertSame($codigo, $resultado['code']);
        Http::assertNothingSent();
    }

    public static function configuracionesInvalidas(): array
    {
        return [
            'modo mal escrito' => ['prodccion', 'https://ose.botica.test', 't', 'CFG02'],
            'sin url' => ['beta', '', 't', 'CFG01'],
            'sin token' => ['beta', 'https://ose.botica.test', '', 'CFG01'],
            'web de SUNAT' => ['beta', 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta', 't', 'CFG03'],
            'http remoto' => ['beta', 'http://ose.botica.test', 't', 'CFG04'],
            'no es url' => ['beta', 'ose', 't', 'CFG04'],
        ];
    }
}
