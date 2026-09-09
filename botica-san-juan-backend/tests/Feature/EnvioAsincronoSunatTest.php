<?php

namespace Tests\Feature;

use App\Jobs\EnviarComprobanteASunat;
use App\Models\ComprobanteElectronico;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verifica que el envio del comprobante al operador de servicios electronicos
 * se resuelva de forma asincrona y que sea idempotente ante reejecuciones.
 */
class EnvioAsincronoSunatTest extends TestCase
{
    use RefreshDatabase;

    private function crearComprobante(string $estado = 'pendiente'): ComprobanteElectronico
    {
        $cliente = $this->crearCliente();

        $pedido = Pedido::create([
            'usuario_id' => $cliente->id,
            'fecha_pedido' => now(),
            'total' => 120.00,
            'estado' => 'confirmado',
        ]);

        return ComprobanteElectronico::create([
            'pedido_id' => $pedido->id,
            'tipo_comprobante' => 'boleta',
            'serie' => 'B001',
            'numero' => 1,
            'cliente_nombre' => $cliente->nombre,
            'cliente_documento' => $cliente->dni,
            'total' => 120.00,
            'estado_sunat' => $estado,
            'fecha_emision' => now(),
        ]);
    }

    public function test_el_envio_se_encola_y_responde_202(): void
    {
        Queue::fake();

        $doc = $this->crearComprobante();
        $admin = $this->crearAdministrador();
        $token = $admin->createToken('prueba')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/facturacion/documentos/{$doc->id}/enviar-sunat-async")
            ->assertStatus(202);

        Queue::assertPushed(EnviarComprobanteASunat::class);
    }

    public function test_un_comprobante_ya_aceptado_no_se_reenvia(): void
    {
        Queue::fake();

        $doc = $this->crearComprobante('aceptada');
        $admin = $this->crearAdministrador();
        $token = $admin->createToken('prueba')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/facturacion/documentos/{$doc->id}/enviar-sunat-async")
            ->assertStatus(200);

        Queue::assertNothingPushed();
    }

    public function test_el_trabajo_resuelve_el_estado_del_comprobante(): void
    {
        $doc = $this->crearComprobante();

        (new EnviarComprobanteASunat($doc->id, 'aceptada'))->handle(app(\App\Services\SunatClient::class));

        $doc->refresh();
        $this->assertSame('aceptada', $doc->estado_sunat);
        $this->assertNotNull($doc->sunat_ticket);
        $this->assertNotNull($doc->fecha_envio_sunat);
    }

    public function test_reejecutar_el_trabajo_sobre_un_comprobante_aceptado_no_lo_altera(): void
    {
        $doc = $this->crearComprobante();
        $client = app(\App\Services\SunatClient::class);

        (new EnviarComprobanteASunat($doc->id, 'aceptada'))->handle($client);
        $ticketOriginal = $doc->fresh()->sunat_ticket;

        (new EnviarComprobanteASunat($doc->id, 'rechazada'))->handle($client);

        $doc->refresh();
        $this->assertSame('aceptada', $doc->estado_sunat);
        $this->assertSame($ticketOriginal, $doc->sunat_ticket);
    }

    public function test_un_cliente_no_puede_encolar_el_envio(): void
    {
        $doc = $this->crearComprobante();

        // crearComprobante() ya consumio el cliente por defecto; este segundo
        // usuario necesita correo y documento distintos.
        $cliente = $this->crearCliente([
            'email' => 'otro.cliente@boticasanjuan.pe',
            'dni' => '20000003',
        ]);
        $token = $cliente->createToken('prueba')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/facturacion/documentos/{$doc->id}/enviar-sunat-async")
            ->assertStatus(403);
    }
}
