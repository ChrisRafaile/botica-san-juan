<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\Pagos\EstadoPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TtlReservaPedidoTest extends TestCase
{
    use RefreshDatabase;

    private function producto(string $nombre = 'Amoxicilina 500 mg', int $stock = 10): Producto
    {
        $p = Producto::create([
            'nombre'        => $nombre,
            'concentracion' => '500 mg',
            'presentacion'  => 'Caja',
            'laboratorio'   => 'Laboratorio San Juan',
            'tipo'          => 'TABLETA',
            'stock'         => $stock,
            'precio'        => 12.00,
        ]);

        Lote::create([
            'producto_id'       => $p->id,
            'codigo_lote'       => 'LOTE-'.uniqid(),
            'cantidad_inicial'  => $stock,
            'cantidad_actual'   => $stock,
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'estado'            => 'activo',
        ]);

        return $p;
    }

    private function cliente(string $dni = '44556677'): Usuario
    {
        return Usuario::create([
            'nombre'   => 'Mariana Costa',
            'dni'      => $dni,
            'email'    => "mariana_{$dni}@boticasanjuan.pe",
            'telefono' => '988776655',
            'rol'      => 'cliente',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_expira_en_se_fija_unicamente_con_cliente_autenticado_y_metodo_tarjeta(): void
    {
        $producto = $this->producto();
        $cliente = $this->cliente('71234567');

        // 1. Invitado con tarjeta -> expira_en = null
        $resInvitado = $this->postJson('/api/pedidos/confirmar', [
            'items'             => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'metodo_pago'       => 'tarjeta',
            'cliente_nombre'    => 'Invitado Juan',
            'cliente_documento' => '12345678',
            'cliente_telefono'  => '999888777',
        ])->assertStatus(201);
        $this->assertNull(Pedido::find($resInvitado->json('pedido.id'))->expira_en);

        // 2. Cliente con sesión y pago contra_entrega -> expira_en = null
        Sanctum::actingAs($cliente);
        $resContraEntrega = $this->postJson('/api/pedidos/confirmar', [
            'items'       => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'metodo_pago' => 'contra_entrega',
        ])->assertStatus(201);
        $this->assertNull(Pedido::find($resContraEntrega->json('pedido.id'))->expira_en);

        // 3. Cliente con sesión y pago tarjeta -> expira_en != null
        $resTarjeta = $this->postJson('/api/pedidos/confirmar', [
            'items'       => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'metodo_pago' => 'tarjeta',
        ])->assertStatus(201);
        $pedidoTarjeta = Pedido::find($resTarjeta->json('pedido.id'));
        $this->assertNotNull($pedidoTarjeta->expira_en);
        $this->assertTrue($pedidoTarjeta->expira_en->isFuture());
    }

    public function test_comando_libera_pedidos_vencidos_y_respeta_vigentes_pagados_y_sin_expiracion(): void
    {
        $producto = $this->producto('Ibuprofeno 400 mg', 20);
        $cliente = $this->cliente('82345678');

        // 1. Pedido vencido (expira_en en el pasado)
        $pedidoVencido = Pedido::create([
            'usuario_id'   => $cliente->id,
            'origen'       => Pedido::ORIGEN_WEB,
            'estado'       => Pedido::ESTADO_PENDIENTE,
            'estado_pago'  => EstadoPago::PENDIENTE,
            'total'        => 24.00,
            'expira_en'    => now()->subMinutes(5),
            'fecha'        => now(),
            'fecha_pedido' => now(),
        ]);
        $detalleVencido = $pedidoVencido->pedidoDetalles()->create([
            'producto_id'       => $producto->id,
            'unidad_venta'      => 'unidad',
            'factor_unidades'   => 1,
            'cantidad'          => 2,
            'cantidad_unidades' => 2,
            'precio'            => 12.00,
            'subtotal'          => 24.00,
        ]);
        $lote = $producto->lotes()->first();
        $lote->update(['cantidad_actual' => 8]); // se descontaron 2
        $producto->update(['stock' => 8]);
        $detalleVencido->lotesAsignados()->create([
            'lote_id'           => $lote->id,
            'cantidad_unidades' => 2,
            'codigo_lote'       => $lote->codigo_lote,
        ]);

        // 2. Pedido vigente (expira_en en el futuro)
        $pedidoVigente = Pedido::create([
            'usuario_id'   => $cliente->id,
            'origen'       => Pedido::ORIGEN_WEB,
            'estado'       => Pedido::ESTADO_PENDIENTE,
            'estado_pago'  => EstadoPago::PENDIENTE,
            'total'        => 12.00,
            'expira_en'    => now()->addMinutes(10),
            'fecha'        => now(),
            'fecha_pedido' => now(),
        ]);

        // 3. Pedido pagado pero con expira_en pasado (no debe anularse)
        $pedidoPagado = Pedido::create([
            'usuario_id'   => $cliente->id,
            'origen'       => Pedido::ORIGEN_WEB,
            'estado'       => Pedido::ESTADO_CONFIRMADO,
            'estado_pago'  => EstadoPago::PAGADO,
            'total'        => 12.00,
            'expira_en'    => now()->subMinutes(5),
            'fecha'        => now(),
            'fecha_pedido' => now(),
        ]);

        // 4. Pedido sin expiración (contra entrega)
        $pedidoSinExp = Pedido::create([
            'usuario_id'   => $cliente->id,
            'origen'       => Pedido::ORIGEN_WEB,
            'estado'       => Pedido::ESTADO_PENDIENTE,
            'estado_pago'  => EstadoPago::PENDIENTE,
            'total'        => 12.00,
            'expira_en'    => null,
            'fecha'        => now(),
            'fecha_pedido' => now(),
        ]);

        // Ejecutar comando
        Artisan::call('pedidos:liberar-expirados');

        $this->assertSame(Pedido::ESTADO_ANULADO, $pedidoVencido->fresh()->estado);
        $this->assertSame('expirado', $pedidoVencido->fresh()->motivo_anulacion);
        $this->assertSame(10, (int) $producto->fresh()->stock, 'El stock del pedido vencido debió restituirse');

        $this->assertSame(Pedido::ESTADO_PENDIENTE, $pedidoVigente->fresh()->estado);
        $this->assertSame(Pedido::ESTADO_CONFIRMADO, $pedidoPagado->fresh()->estado);
        $this->assertSame(Pedido::ESTADO_PENDIENTE, $pedidoSinExp->fresh()->estado);
    }

    public function test_dry_run_del_comando_no_escribe_en_la_base(): void
    {
        $producto = $this->producto('Paracetamol 500 mg', 10);
        $cliente = $this->cliente('99887766');

        $pedido = Pedido::create([
            'usuario_id'   => $cliente->id,
            'origen'       => Pedido::ORIGEN_WEB,
            'estado'       => Pedido::ESTADO_PENDIENTE,
            'estado_pago'  => EstadoPago::PENDIENTE,
            'total'        => 12.00,
            'expira_en'    => now()->subMinutes(1),
            'fecha'        => now(),
            'fecha_pedido' => now(),
        ]);

        Artisan::call('pedidos:liberar-expirados', ['--dry-run' => true]);

        $this->assertSame(Pedido::ESTADO_PENDIENTE, $pedido->fresh()->estado);
        $this->assertNull($pedido->fresh()->anulado_en);
    }

    public function test_iniciar_pago_sobre_reserva_vencida_responde_409(): void
    {
        $producto = $this->producto('Naproxeno 550 mg', 5);
        $cliente = $this->cliente('12312312');
        Sanctum::actingAs($cliente);

        $res = $this->postJson('/api/pedidos/confirmar', [
            'items'       => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'metodo_pago' => 'tarjeta',
        ])->assertStatus(201);

        $pedido = Pedido::find($res->json('pedido.id'));
        $pedido->update(['expira_en' => now()->subMinutes(1)]);

        $this->postJson("/api/pedidos/{$pedido->id}/pago")
            ->assertStatus(409)
            ->assertJsonPath('motivo', 'reserva_vencida');
    }

    public function test_pagar_limpia_expira_en(): void
    {
        $producto = $this->producto('Ciprofloxacino 500 mg', 5);
        $cliente = $this->cliente('78787878');
        Sanctum::actingAs($cliente);

        $res = $this->postJson('/api/pedidos/confirmar', [
            'items'       => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'metodo_pago' => 'tarjeta',
        ])->assertStatus(201);

        $pedidoId = $res->json('pedido.id');
        $pedido = Pedido::find($pedidoId);
        $this->assertNotNull($pedido->expira_en);

        $resPago = $this->postJson("/api/pedidos/{$pedidoId}/pago")->assertStatus(200);
        $referencia = $resPago->json('pago.referencia');

        // Simular pago aprobado
        $this->postJson("/api/pagos/{$referencia}/simular", [
            'resultado' => 'aprobar',
        ])->assertStatus(200);

        $this->assertNull($pedido->fresh()->expira_en, 'Al pagar exitosamente, la reserva se consumió y expira_en queda en null');
        $this->assertSame(EstadoPago::PAGADO, $pedido->fresh()->estado_pago);
    }
}
