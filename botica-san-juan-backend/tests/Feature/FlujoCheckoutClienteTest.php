<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Pago;
use App\Models\PagoEvento;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\Pagos\EstadoPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cubre el flujo completo de checkout del cliente autenticado:
 * carrito -> modalidad de entrega y boleta/factura -> detalle para pago ->
 * pasarela simulada -> estado final -> aparicion en pedidos y descarga PDF.
 */
class FlujoCheckoutClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.izipay.mode', 'simulado');
    }

    private function clienteConDni(string $dni = '44556677', string $email = 'cliente.e2e@prueba.local'): Usuario
    {
        return Usuario::create([
            'nombre'   => 'Lucía Mendoza',
            'dni'      => $dni,
            'telefono' => '987654321',
            'email'    => $email,
            'password' => Hash::make(str()->random(32)),
            'rol'      => 'cliente',
        ]);
    }

    /** @return array{0: Producto, 1: Lote, 2: Lote} */
    private function productoConDosLotes(float $precio = 25.00): array
    {
        $producto = Producto::create([
            'nombre'        => 'Ibuprofeno 400 mg',
            'concentracion' => '400 mg',
            'presentacion'  => 'Caja x 20',
            'laboratorio'   => 'LAB E2E',
            'tipo'          => 'TABLETA',
            'stock'         => 10,
            'precio'        => $precio,
        ]);

        $loteProximo = Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'L-FEFO-1',
            'cantidad_inicial'  => 4,
            'cantidad_actual'   => 4,
            'fecha_vencimiento' => now()->addMonths(2)->toDateString(),
            'estado'            => 'activo',
        ]);

        $loteLejano = Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'L-FEFO-2',
            'cantidad_inicial'  => 6,
            'cantidad_actual'   => 6,
            'fecha_vencimiento' => now()->addMonths(12)->toDateString(),
            'estado'            => 'activo',
        ]);

        return [$producto, $loteProximo, $loteLejano];
    }

    public function test_flujo_completo_de_cliente_con_entrega_factura_pago_simulado_y_descarga_pdf(): void
    {
        [$producto, $loteProximo, $loteLejano] = $this->productoConDosLotes(25.00);
        $cliente = $this->clienteConDni();
        $token = $cliente->createToken('e2e')->plainTextToken;

        // 1. Confirmar pedido con entrega a domicilio y Factura (RUC ficticio de prueba)
        $confirmacion = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pedidos/confirmar', [
                'items' => [['producto_id' => $producto->id, 'cantidad' => 6]],
                'modalidad_entrega' => 'entrega',
                'direccion' => 'Av. Los Pinos 450, San Juan de Lurigancho',
                'tipo_comprobante' => 'factura',
                'cliente_documento' => '20999888777',
                'cliente_nombre' => 'Comercial Salud EIRL',
            ]);

        $confirmacion->assertStatus(201)
            ->assertJsonPath('modalidad_entrega', 'entrega')
            ->assertJsonPath('comprobante.tipo', 'factura');

        $pedidoId = (int) $confirmacion->json('pedido.id');
        $comprobanteId = (int) $confirmacion->json('comprobante.id');
        $this->assertGreaterThan(0, $comprobanteId);
        $this->assertStringStartsWith('F001-', (string) $confirmacion->json('comprobante.identificador'));

        // Descuento FEFO real sobre lotes: agota primero el lote que vence antes (4) y toma 2 del segundo
        $this->assertSame(0, (int) $loteProximo->fresh()->cantidad_actual);
        $this->assertSame(4, (int) $loteLejano->fresh()->cantidad_actual);
        $this->assertSame('Av. Los Pinos 450, San Juan de Lurigancho', Pedido::findOrFail($pedidoId)->observacion);

        // 2. El cliente carga su pedido desde la vista de pago sin necesitar rol admin
        $detalle = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pedidos/{$pedidoId}/mi-pedido");

        $detalle->assertOk()
            ->assertJsonPath('id', $pedidoId)
            ->assertJsonPath('pedido_detalles.0.producto.nombre', 'Ibuprofeno 400 mg')
            ->assertJsonPath('comprobante_electronico.id', $comprobanteId);

        // 3. Inicia cobro en modo simulado
        $inicioPago = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pedidos/{$pedidoId}/pago");

        $inicioPago->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('checkout.modo', 'simulado');

        $referencia = (string) $inicioPago->json('pago.referencia');
        $this->assertNotEmpty($referencia);

        // 4. Completa cobro en pasarela simulada enviando unicamente marca y ultimos 4 digitos
        $simulacion = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pagos/{$referencia}/simular", [
                'resultado' => 'aprobar',
                'marca_tarjeta' => 'amex',
                'ultimos4' => '1005',
            ]);

        $simulacion->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('es_final', true)
            ->assertJsonPath('pago.estado', EstadoPago::PAGADO)
            ->assertJsonPath('pago.marca_tarjeta', 'AMEX')
            ->assertJsonPath('pago.ultimos4', '1005');

        $this->assertSame(EstadoPago::PAGADO, Pedido::findOrFail($pedidoId)->estado_pago);

        // Verifica que nunca se guardaron numeros completos ni CVV
        $pago = Pago::where('referencia_pedido', $referencia)->firstOrFail();
        $eventoJson = json_encode(PagoEvento::where('pago_id', $pago->id)->firstOrFail()->payload);
        $this->assertStringNotContainsString('"pan"', (string) $eventoJson);
        $this->assertStringNotContainsString('"cvv"', (string) $eventoJson);

        // 5. Consulta de estado final y comprobante asociado
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pagos/{$referencia}/estado")
            ->assertOk()
            ->assertJsonPath('es_final', true)
            ->assertJsonPath('pago.estado', EstadoPago::PAGADO)
            ->assertJsonPath('comprobante.id', $comprobanteId);

        // 6. Aparece de inmediato en historial de pedidos, comprobantes y descarga PDF autenticado
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pedidos/usuario/{$cliente->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $pedidoId, 'estado_pago' => EstadoPago::PAGADO]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/comprobantes/mios')
            ->assertOk()
            ->assertJsonPath('data.0.id', $comprobanteId);

        $pdf = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/facturacion/documentos/{$comprobanteId}/pdf");

        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_valida_direccion_en_entrega_y_ruc_en_factura(): void
    {
        [$producto] = $this->productoConDosLotes();
        $cliente = $this->clienteConDni();
        $token = $cliente->createToken('validacion')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pedidos/confirmar', [
                'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
                'modalidad_entrega' => 'entrega',
                'tipo_comprobante' => 'boleta',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['direccion']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pedidos/confirmar', [
                'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
                'modalidad_entrega' => 'recojo',
                'tipo_comprobante' => 'factura',
                'cliente_documento' => '12345678',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cliente_documento']);
    }

    public function test_otro_cliente_no_puede_leer_mi_pedido_ni_simular_pago_ajeno(): void
    {
        [$producto] = $this->productoConDosLotes();
        $dueno = $this->clienteConDni('44556677', 'dueno.e2e@prueba.local');
        $intruso = $this->clienteConDni('88990011', 'intruso.e2e@prueba.local');

        $tokenDueno = $dueno->createToken('dueno')->plainTextToken;
        $tokenIntruso = $intruso->createToken('intruso')->plainTextToken;

        $r = $this->withHeader('Authorization', "Bearer {$tokenDueno}")
            ->postJson('/api/pedidos/confirmar', [
                'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
                'modalidad_entrega' => 'recojo',
                'tipo_comprobante' => 'boleta',
            ])->assertStatus(201);

        $pedidoId = (int) $r->json('pedido.id');

        $inicio = $this->withHeader('Authorization', "Bearer {$tokenDueno}")
            ->postJson("/api/pedidos/{$pedidoId}/pago")
            ->assertOk();
        $referencia = (string) $inicio->json('pago.referencia');

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$tokenIntruso}")
            ->getJson("/api/pedidos/{$pedidoId}/mi-pedido")
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$tokenIntruso}")
            ->postJson("/api/pagos/{$referencia}/simular", ['resultado' => 'aprobar'])
            ->assertStatus(403);
    }

    public function test_simular_pago_devuelve_404_cuando_la_pasarela_esta_en_modo_api(): void
    {
        [$producto] = $this->productoConDosLotes();
        $cliente = $this->clienteConDni();
        $token = $cliente->createToken('modo-api')->plainTextToken;

        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pedidos/confirmar', [
                'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            ])->assertStatus(201);

        $pedidoId = (int) $r->json('pedido.id');
        $inicio = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pedidos/{$pedidoId}/pago")
            ->assertOk();
        $referencia = (string) $inicio->json('pago.referencia');

        config()->set('services.izipay.mode', 'api');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pagos/{$referencia}/simular", ['resultado' => 'aprobar'])
            ->assertStatus(404);
    }

    public function test_simular_pago_devuelve_404_y_no_cambia_estado_en_entorno_production_aunque_modo_sea_simulado(): void
    {
        [$producto] = $this->productoConDosLotes();
        $cliente = $this->clienteConDni();
        $token = $cliente->createToken('prod-simulado')->plainTextToken;

        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pedidos/confirmar', [
                'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            ])->assertStatus(201);

        $pedidoId = (int) $r->json('pedido.id');
        $inicio = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pedidos/{$pedidoId}/pago")
            ->assertOk();
        $referencia = (string) $inicio->json('pago.referencia');

        // Simula ausencia de IZIPAY_MODE en produccion (config conserva 'simulado' por omision)
        config()->set('services.izipay.mode', 'simulado');
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->postJson("/api/pagos/{$referencia}/simular", ['resultado' => 'aprobar'])
                ->assertStatus(404);

            $pago = Pago::where('referencia_pedido', $referencia)->firstOrFail();
            $servicioRes = app(\App\Services\Pagos\PagoService::class)->simularDesenlace($pago, true, 'visa', '4242');

            $this->assertFalse($servicioRes['ok']);
            $this->assertSame('pendiente', Pedido::findOrFail($pedidoId)->estado_pago);
            $this->assertSame(EstadoPago::PROCESANDO, $pago->fresh()->estado);
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }
    }
}
