<?php

namespace Tests\Feature;

use App\Models\ClaveIdempotencia;
use App\Models\ComprobanteElectronico;
use App\Models\Lote;
use App\Models\Pago;
use App\Models\PagoEvento;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdempotenciaTest extends TestCase
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
            'precio'        => 15.00,
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

    private function cliente(): Usuario
    {
        return Usuario::create([
            'nombre'   => 'Jorge Chávez',
            'dni'      => '12348765',
            'email'    => 'jorge.chavez@boticasanjuan.pe',
            'telefono' => '987123456',
            'rol'      => 'cliente',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_misma_clave_y_mismo_cuerpo_devuelve_misma_respuesta_y_no_duplica_pedido_ni_stock(): void
    {
        $producto = $this->producto('Amoxicilina 500 mg', 10);
        $clave = 'idemp-confirmar-001';

        $cuerpo = [
            'items'             => [['producto_id' => $producto->id, 'cantidad' => 2]],
            'metodo_pago'       => 'contra_entrega',
            'cliente_nombre'    => 'Cliente Frecuente',
            'cliente_documento' => '12345678',
            'cliente_telefono'  => '999888777',
        ];

        // Primera petición
        $res1 = $this->withHeader('Idempotency-Key', $clave)
            ->postJson('/api/pedidos/confirmar', $cuerpo)
            ->assertStatus(201);

        $pedidoId = $res1->json('pedido.id');
        $this->assertSame(8, (int) $producto->fresh()->stock);
        $this->assertDatabaseCount('pedidos', 1);
        $this->assertDatabaseCount('comprobantes_electronicos', 1);

        // Segunda petición idéntica con la misma clave
        $res2 = $this->withHeader('Idempotency-Key', $clave)
            ->postJson('/api/pedidos/confirmar', $cuerpo)
            ->assertStatus(201)
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($pedidoId, $res2->json('pedido.id'));
        $this->assertSame(8, (int) $producto->fresh()->stock, 'No debe descontar stock por segunda vez');
        $this->assertDatabaseCount('pedidos', 1);
        $this->assertDatabaseCount('comprobantes_electronicos', 1);
    }

    public function test_misma_clave_con_cuerpo_distinto_responde_422_conflicto(): void
    {
        $producto = $this->producto('Paracetamol 500 mg', 10);
        $clave = 'idemp-conflicto-001';

        $this->withHeader('Idempotency-Key', $clave)
            ->postJson('/api/pedidos/confirmar', [
                'items'             => [['producto_id' => $producto->id, 'cantidad' => 1]],
                'cliente_nombre'    => 'Juan Pérez',
                'cliente_documento' => '12345678',
                'cliente_telefono'  => '999888777',
            ])->assertStatus(201);

        // Segunda petición con misma clave pero cuerpo diferente
        $this->withHeader('Idempotency-Key', $clave)
            ->postJson('/api/pedidos/confirmar', [
                'items'             => [['producto_id' => $producto->id, 'cantidad' => 2]],
                'cliente_nombre'    => 'Juan Pérez',
                'cliente_documento' => '12345678',
                'cliente_telefono'  => '999888777',
            ])->assertStatus(422)
            ->assertJsonPath('codigo', 'idempotencia_conflicto');
    }

    public function test_clave_mal_formada_responde_400(): void
    {
        $producto = $this->producto();

        // Menos de 8 caracteres
        $this->withHeader('Idempotency-Key', 'abc')
            ->postJson('/api/pedidos/confirmar', [
                'items'             => [['producto_id' => $producto->id, 'cantidad' => 1]],
                'cliente_nombre'    => 'Juan Pérez',
                'cliente_documento' => '12345678',
                'cliente_telefono'  => '999888777',
            ])->assertStatus(400);

        // Caracteres no permitidos (ej. espacios o signos especiales)
        $this->withHeader('Idempotency-Key', 'clave con espacios invalidos')
            ->postJson('/api/pedidos/confirmar', [
                'items'             => [['producto_id' => $producto->id, 'cantidad' => 1]],
                'cliente_nombre'    => 'Juan Pérez',
                'cliente_documento' => '12345678',
                'cliente_telefono'  => '999888777',
            ])->assertStatus(400);
    }

    public function test_sin_cabecera_funciona_como_antes(): void
    {
        $producto = $this->producto('Ibuprofeno 400 mg', 10);

        $res = $this->postJson('/api/pedidos/confirmar', [
            'items'             => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'cliente_nombre'    => 'Sin Clave',
            'cliente_documento' => '12345678',
            'cliente_telefono'  => '999888777',
        ])->assertStatus(201);

        $this->assertFalse($res->headers->has('Idempotency-Replayed'));
        $this->assertSame(9, (int) $producto->fresh()->stock);
    }

    public function test_simulacion_de_pago_repetida_no_duplica_eventos(): void
    {
        $producto = $this->producto('Naproxeno 550 mg', 10);
        $cliente = $this->cliente();
        Sanctum::actingAs($cliente);

        $resConfirmar = $this->postJson('/api/pedidos/confirmar', [
            'items'       => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'metodo_pago' => 'tarjeta',
        ])->assertStatus(201);

        $pedidoId = $resConfirmar->json('pedido.id');
        $resPago = $this->postJson("/api/pedidos/{$pedidoId}/pago")->assertStatus(200);
        $referencia = $resPago->json('pago.referencia');

        $clave = 'idemp-simular-001';

        $cuerpoSimulacion = ['resultado' => 'aprobar', 'marca_tarjeta' => 'visa'];

        // Primera simulación
        $resSim1 = $this->withHeader('Idempotency-Key', $clave)
            ->postJson("/api/pagos/{$referencia}/simular", $cuerpoSimulacion)
            ->assertStatus(200);

        $eventosCount = PagoEvento::count();

        // Segunda simulación con la misma clave
        $resSim2 = $this->withHeader('Idempotency-Key', $clave)
            ->postJson("/api/pagos/{$referencia}/simular", $cuerpoSimulacion)
            ->assertStatus(200)
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($eventosCount, PagoEvento::count(), 'No debe registrar un segundo evento de pago');
    }

    public function test_una_respuesta_409_no_deja_la_clave_ocupada(): void
    {
        $producto = $this->producto('Amoxicilina 500 mg', 1);
        $cliente = $this->cliente();
        Sanctum::actingAs($cliente);

        $clave = 'idemp-liberada-001';

        // Intento 1: pedir 5 unidades cuando solo hay 1 -> 409 stock insuficiente
        $this->withHeader('Idempotency-Key', $clave)
            ->postJson('/api/pedidos/confirmar', [
                'items' => [['producto_id' => $producto->id, 'cantidad' => 5]],
            ])->assertStatus(409);

        // La clave no debe haberse quedado guardada como completada ni en curso
        $this->assertDatabaseMissing('claves_idempotencia', ['clave' => $clave]);

        // Aumentar stock para que la siguiente petición sea válida
        $producto->lotes()->first()->update(['cantidad_actual' => 10]);
        $producto->update(['stock' => 10]);

        // Reintento con la misma clave: debe procesarse como nueva y tener éxito (201)
        $this->withHeader('Idempotency-Key', $clave)
            ->postJson('/api/pedidos/confirmar', [
                'items' => [['producto_id' => $producto->id, 'cantidad' => 5]],
            ])->assertStatus(201);
    }
}
