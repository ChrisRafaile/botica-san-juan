<?php

namespace Tests\Feature;

use App\Models\ComprobanteElectronico;
use App\Models\Lote;
use App\Models\MovimientoStock;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\Pagos\EstadoPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CancelacionPedidoTest extends TestCase
{
    use RefreshDatabase;

    private function crearProductoConLotes(string $nombre, array $lotesData): Producto
    {
        $stockTotal = array_sum(array_column($lotesData, 'cantidad'));

        $producto = Producto::create([
            'nombre'        => $nombre,
            'concentracion' => '500 mg',
            'presentacion'  => 'Caja x 30',
            'laboratorio'   => 'Laboratorios Farmacéuticos San Juan',
            'tipo'          => 'TABLETA',
            'stock'         => $stockTotal,
            'precio'        => 15.50,
        ]);

        foreach ($lotesData as $data) {
            Lote::create([
                'producto_id'       => $producto->id,
                'codigo_lote'       => $data['codigo'],
                'cantidad_inicial'  => $data['cantidad'],
                'cantidad_actual'   => $data['cantidad'],
                'fecha_vencimiento' => $data['vencimiento'] ?? now()->addMonths(12)->toDateString(),
                'estado'            => $data['estado'] ?? 'activo',
            ]);
        }

        return $producto;
    }

    private function crearClienteCancelacion(string $nombre = 'Carlos Mendoza', string $dni = '43218765'): Usuario
    {
        return Usuario::create([
            'nombre'   => $nombre,
            'dni'      => $dni,
            'email'    => "cliente_{$dni}@boticasanjuan.pe",
            'telefono' => '987654321',
            'rol'      => 'cliente',
            'password' => bcrypt('password123'),
        ]);
    }

    private function crearAdmin(): Usuario
    {
        return Usuario::create([
            'nombre'   => 'Dra. María Ramos (Regente)',
            'dni'      => '10987654',
            'email'    => 'regente@boticasanjuan.pe',
            'telefono' => '999888777',
            'rol'      => 'administrador',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_dueno_cancela_pedido_y_cada_lote_recupera_exactamente_sus_unidades_con_reparto_fefo(): void
    {
        // Dos lotes: Lote A con 2 unidades (vence en 2 meses) y Lote B con 5 unidades (vence en 6 meses)
        $amoxicilina = $this->crearProductoConLotes('Amoxicilina 500 mg', [
            ['codigo' => 'LOTE-AMOX-01', 'cantidad' => 2, 'vencimiento' => now()->addMonths(2)->toDateString()],
            ['codigo' => 'LOTE-AMOX-02', 'cantidad' => 5, 'vencimiento' => now()->addMonths(6)->toDateString()],
        ]);

        $cliente = $this->crearClienteCancelacion('Rosa Flores', '72345678');
        Sanctum::actingAs($cliente);

        // Pedir 4 unidades: deben salir 2 del Lote 01 (quedando agotado) y 2 del Lote 02 (quedando 3)
        $respuestaConfirmar = $this->postJson('/api/pedidos/confirmar', [
            'items' => [
                ['producto_id' => $amoxicilina->id, 'cantidad' => 4],
            ],
            'metodo_pago'       => 'tarjeta',
            'modalidad_entrega' => 'recojo',
            'tipo_comprobante'  => 'boleta',
        ])->assertStatus(201);

        $pedidoId = $respuestaConfirmar->json('pedido.id');

        $lote1 = Lote::where('codigo_lote', 'LOTE-AMOX-01')->first();
        $lote2 = Lote::where('codigo_lote', 'LOTE-AMOX-02')->first();

        $this->assertSame(0, $lote1->cantidad_actual);
        $this->assertSame('agotado', $lote1->estado);
        $this->assertSame(3, $lote2->cantidad_actual);
        $this->assertSame(3, (int) $amoxicilina->fresh()->stock);

        // Cancelar pedido
        $respuestaCancelar = $this->postJson("/api/pedidos/{$pedidoId}/cancelar")
            ->assertStatus(200)
            ->assertJsonPath('pedido.estado', 'anulado')
            ->assertJsonPath('pedido.estado_pago', 'cancelado')
            ->assertJsonPath('comprobante.estado_sunat', 'anulada');

        // Cada lote recupera exactamente sus unidades originales
        $lote1->refresh();
        $lote2->refresh();
        $this->assertSame(2, $lote1->cantidad_actual);
        $this->assertSame('activo', $lote1->estado, 'El lote agotado debe reactivarse a activo tras la devolución');
        $this->assertSame(5, $lote2->cantidad_actual);
        $this->assertSame(7, (int) $amoxicilina->fresh()->stock, 'El stock denormalizado de productos debe cuadrar con la suma');

        // Movimientos de stock registrados
        $movimientos = MovimientoStock::where('referencia_tipo', 'pedido')
            ->where('referencia_id', $pedidoId)
            ->where('tipo', 'anulacion')
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(2, $movimientos);
        $this->assertSame(2, $movimientos[0]->cantidad);
        $this->assertSame($lote1->id, $movimientos[0]->lote_id);
        $this->assertSame(2, $movimientos[1]->cantidad);
        $this->assertSame($lote2->id, $movimientos[1]->lote_id);
    }

    public function test_lote_vencido_recupera_cantidad_pero_no_cambia_su_estado_a_activo(): void
    {
        $paracetamol = $this->crearProductoConLotes('Paracetamol 500 mg', [
            ['codigo' => 'LOTE-PARA-VENCIDO', 'cantidad' => 3, 'vencimiento' => now()->addDays(1)->toDateString()],
        ]);

        $cliente = $this->crearClienteCancelacion('José Huamán', '45892312');
        Sanctum::actingAs($cliente);

        $res = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $paracetamol->id, 'cantidad' => 3]],
            'metodo_pago' => 'contra_entrega',
        ])->assertStatus(201);

        $pedidoId = $res->json('pedido.id');
        $lote = Lote::where('codigo_lote', 'LOTE-PARA-VENCIDO')->first();

        // Forzar estado vencido
        $lote->update(['estado' => 'vencido', 'fecha_vencimiento' => now()->subDay()->toDateString()]);

        $this->postJson("/api/pedidos/{$pedidoId}/cancelar")->assertStatus(200);

        $lote->refresh();
        $this->assertSame(3, $lote->cantidad_actual);
        $this->assertSame('vencido', $lote->estado, 'Un lote vencido no debe reactivarse a activo');
    }

    public function test_otro_cliente_recibe_403_al_intentar_cancelar(): void
    {
        $producto = $this->crearProductoConLotes('Ibuprofeno 400 mg', [
            ['codigo' => 'LOTE-IBU-01', 'cantidad' => 5],
        ]);

        $dueno = $this->crearClienteCancelacion('Dueño Real', '88776655');
        Sanctum::actingAs($dueno);

        $res = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertStatus(201);

        $pedidoId = $res->json('pedido.id');

        // Otro cliente
        $usurpador = $this->crearClienteCancelacion('Cliente Extraño', '11223344');
        Sanctum::actingAs($usurpador);

        $this->postJson("/api/pedidos/{$pedidoId}/cancelar")->assertStatus(403);
    }

    public function test_administrador_si_puede_cancelar_pedido_de_otro_usuario(): void
    {
        $producto = $this->crearProductoConLotes('Naproxeno 550 mg', [
            ['codigo' => 'LOTE-NAP-01', 'cantidad' => 10],
        ]);

        $cliente = $this->crearClienteCancelacion('Pedro Castillo', '55667788');
        Sanctum::actingAs($cliente);

        $res = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 2]],
        ])->assertStatus(201);

        $pedidoId = $res->json('pedido.id');

        // Administrador cancela
        $admin = $this->crearAdmin();
        Sanctum::actingAs($admin);

        $this->postJson("/api/pedidos/{$pedidoId}/cancelar")
            ->assertStatus(200)
            ->assertJsonPath('pedido.motivo_anulacion', 'administrador');
    }

    public function test_pedido_pagado_no_se_puede_cancelar_y_responde_409(): void
    {
        $producto = $this->crearProductoConLotes('Omeprazol 20 mg', [
            ['codigo' => 'LOTE-OME-01', 'cantidad' => 5],
        ]);

        $cliente = $this->crearClienteCancelacion('Valeria Paz', '33445566');
        Sanctum::actingAs($cliente);

        $res = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertStatus(201);

        $pedido = Pedido::find($res->json('pedido.id'));
        $pedido->update(['estado_pago' => EstadoPago::PAGADO]);

        $this->postJson("/api/pedidos/{$pedido->id}/cancelar")
            ->assertStatus(409)
            ->assertJsonPath('motivo', 'pago_confirmado');
    }

    public function test_pedido_ya_anulado_responde_409(): void
    {
        $producto = $this->crearProductoConLotes('Loratadina 10 mg', [
            ['codigo' => 'LOTE-LOR-01', 'cantidad' => 5],
        ]);

        $cliente = $this->crearClienteCancelacion('Julia Medina', '66554433');
        Sanctum::actingAs($cliente);

        $res = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertStatus(201);

        $pedidoId = $res->json('pedido.id');

        $this->postJson("/api/pedidos/{$pedidoId}/cancelar")->assertStatus(200);
        $this->postJson("/api/pedidos/{$pedidoId}/cancelar")
            ->assertStatus(409)
            ->assertJsonPath('motivo', 'ya_anulado');
    }

    public function test_pedido_con_comprobante_aceptado_responde_409(): void
    {
        $producto = $this->crearProductoConLotes('Azitromicina 500 mg', [
            ['codigo' => 'LOTE-AZI-01', 'cantidad' => 5],
        ]);

        $cliente = $this->crearClienteCancelacion('César Vallejo', '12121212');
        Sanctum::actingAs($cliente);

        $res = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertStatus(201);

        $pedido = Pedido::find($res->json('pedido.id'));
        $pedido->comprobanteElectronico->update(['estado_sunat' => 'aceptada']);

        $this->postJson("/api/pedidos/{$pedido->id}/cancelar")
            ->assertStatus(409)
            ->assertJsonPath('motivo', 'comprobante_aceptado');
    }

    public function test_venta_de_mostrador_no_se_puede_cancelar_y_responde_409(): void
    {
        $producto = $this->crearProductoConLotes('Cetirizina 10 mg', [
            ['codigo' => 'LOTE-CET-01', 'cantidad' => 10],
        ]);

        $admin = $this->crearAdmin();

        $venta = app(\App\Services\VentaService::class)->registrar(
            items: [['producto_id' => $producto->id, 'cantidad' => 1]],
            origen: Pedido::ORIGEN_POS,
            vendedorId: $admin->id,
        );

        Sanctum::actingAs($admin);

        $this->postJson("/api/pedidos/{$venta->id}/cancelar")
            ->assertStatus(409)
            ->assertJsonPath('motivo', 'venta_mostrador');
    }
}
