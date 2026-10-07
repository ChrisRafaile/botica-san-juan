<?php

namespace Tests\Feature;

use App\Models\ComprobanteElectronico;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Checkout del portal por los dos caminos.
 *
 * El endpoint vivía dentro del grupo que exige sesión, así que encargar
 * obligaba a registrarse: el cliente ya había elegido, ya sabía el precio, y en
 * el último paso se le pedía crear una cuenta. Ahora admite las dos vías y el
 * control está donde corresponde — el invitado da nombre, documento y teléfono,
 * y el servidor revalida stock y precios en cualquier caso.
 *
 * Lo que estas pruebas fijan, además de que funcione, es que **abrir la ruta no
 * abrió un agujero**: el precio sigue viniendo del servidor, el stock se sigue
 * respetando y el comprobante no se puede duplicar.
 */
class CheckoutPortalTest extends TestCase
{
    use RefreshDatabase;

    private function producto(float $precio = 20.00, int $unidades = 10): Producto
    {
        $p = Producto::create([
            'nombre'        => 'PRODUCTO CHECKOUT '.uniqid(),
            'concentracion' => '250 mg',
            'presentacion'  => 'Caja',
            'laboratorio'   => 'LAB PRUEBA',
            'tipo'          => 'TABLETA',
            'stock'         => $unidades,
            'precio'        => $precio,
        ]);

        Lote::create([
            'producto_id'       => $p->id,
            'codigo_lote'       => 'L-'.uniqid(),
            'cantidad_inicial'  => $unidades,
            'cantidad_actual'   => $unidades,
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'estado'            => 'activo',
        ]);

        return $p;
    }

    private function cliente(): Usuario
    {
        return Usuario::create([
            'nombre'   => 'Cliente Con Cuenta',
            'dni'      => '45678912',
            'email'    => 'cliente.checkout@prueba.local',
            'password' => Hash::make(str()->random(32)),
            'rol'      => 'cliente',
        ]);
    }

    // ---------------------------------------------------------------- invitado

    public function test_un_invitado_puede_encargar_sin_crear_cuenta(): void
    {
        $producto = $this->producto(20.00, 10);

        $r = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 2]],
            'cliente_nombre'    => 'Juana Pérez',
            'cliente_documento' => '12345678',
            'cliente_telefono'  => '999111222',
        ]);

        $r->assertStatus(201);
        $r->assertJsonPath('seguimiento.invitado', true);

        $pedido = Pedido::find($r->json('pedido.id'));
        $this->assertNull($pedido->usuario_id, 'un encargo de invitado no se liga a ninguna cuenta');
        $this->assertSame('Juana Pérez', $pedido->cliente_nombre);
        $this->assertSame('999111222', $pedido->cliente_telefono);
        $this->assertSame(Pedido::ORIGEN_WEB, $pedido->origen);
    }

    public function test_al_invitado_se_le_exigen_los_tres_datos_minimos(): void
    {
        $producto = $this->producto();

        $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['cliente_nombre', 'cliente_documento', 'cliente_telefono']);
    }

    public function test_el_documento_tiene_que_ser_un_dni_o_un_ruc(): void
    {
        $producto = $this->producto();

        foreach (['123', '123456789', 'ABCDEFGH'] as $malo) {
            $this->postJson('/api/pedidos/confirmar', [
                'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
                'cliente_nombre'    => 'Quien Sea',
                'cliente_documento' => $malo,
                'cliente_telefono'  => '999111222',
            ])->assertStatus(422)->assertJsonValidationErrors(['cliente_documento']);
        }
    }

    // ------------------------------------------------------------ con sesión

    public function test_un_cliente_con_sesion_encarga_sin_repetir_sus_datos(): void
    {
        $producto = $this->producto();
        $cliente  = $this->cliente();
        Sanctum::actingAs($cliente);

        $r = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ]);

        $r->assertStatus(201);
        $r->assertJsonPath('seguimiento.invitado', false);

        $pedido = Pedido::find($r->json('pedido.id'));
        $this->assertSame($cliente->id, $pedido->usuario_id, 'el pedido queda ligado a su cuenta');

        /* Los datos se COPIAN al pedido, no se dejan sólo en la cuenta: el
           comprobante debe decir a quién se le vendió el día que se emitió,
           aunque mañana esa persona cambie su nombre en el perfil. */
        $this->assertSame($cliente->nombre, $pedido->cliente_nombre);
        $this->assertSame($cliente->dni, $pedido->cliente_documento);
    }

    public function test_el_pedido_de_un_cliente_aparece_en_su_panel(): void
    {
        $producto = $this->producto();
        $cliente  = $this->cliente();
        Sanctum::actingAs($cliente);

        $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertStatus(201);

        /* Sin esto el pedido existiría pero el cliente no tendría dónde verlo,
           que desde su punto de vista es lo mismo que no haberlo hecho. */
        $r = $this->getJson("/api/pedidos/usuario/{$cliente->id}");
        $r->assertOk();
        $this->assertNotEmpty($r->json(), 'el panel del cliente debe listar su pedido');
    }

    public function test_el_pedido_aparece_en_el_panel_del_administrador(): void
    {
        $producto = $this->producto();

        $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'cliente_nombre'    => 'Invitado Visible',
            'cliente_documento' => '12345678',
            'cliente_telefono'  => '999111222',
        ])->assertStatus(201);

        Sanctum::actingAs(Usuario::create([
            'nombre'   => 'Admin Checkout',
            'dni'      => '11223344',
            'email'    => 'admin.checkout@prueba.local',
            'password' => Hash::make(str()->random(32)),
            'rol'      => 'administrador',
        ]));

        $r = $this->getJson('/api/pedidos');
        $r->assertOk();
        $this->assertStringContainsString('Invitado Visible', json_encode($r->json()));
    }

    // -------------------------------------------------------- comprobante

    public function test_un_dni_emite_boleta_B001(): void
    {
        $producto = $this->producto();

        $r = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'cliente_nombre'    => 'Persona Natural',
            'cliente_documento' => '12345678',
            'cliente_telefono'  => '999111222',
        ])->assertStatus(201);

        $r->assertJsonPath('comprobante.tipo', 'boleta');
        $this->assertStringStartsWith('B001-', $r->json('comprobante.identificador'));
        $r->assertJsonPath('comprobante.estado_sunat', 'pendiente');
    }

    public function test_un_ruc_emite_factura_F001(): void
    {
        $producto = $this->producto();

        $r = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'cliente_nombre'    => 'Empresa SAC',
            'cliente_documento' => '20123456789',
            'cliente_telefono'  => '999111222',
        ])->assertStatus(201);

        /* El tipo lo decide el documento, no una casilla: una factura exige RUC
           y una boleta a nombre de un RUC no da crédito fiscal. */
        $r->assertJsonPath('comprobante.tipo', 'factura');
        $this->assertStringStartsWith('F001-', $r->json('comprobante.identificador'));
    }

    public function test_el_correlativo_avanza_y_no_se_repite(): void
    {
        $producto = $this->producto(20.00, 50);

        $numeros = [];
        for ($i = 0; $i < 3; $i++) {
            $r = $this->postJson('/api/pedidos/confirmar', [
                'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
                'cliente_nombre'    => "Cliente $i",
                'cliente_documento' => '12345678',
                'cliente_telefono'  => '999111222',
            ])->assertStatus(201);

            $numeros[] = $r->json('comprobante.identificador');
        }

        $this->assertCount(3, array_unique($numeros), 'dos comprobantes con el mismo número es una contingencia tributaria');
    }

    public function test_un_pedido_no_puede_tener_dos_comprobantes(): void
    {
        $producto = $this->producto();

        $r = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'cliente_nombre'    => 'Doble Clic',
            'cliente_documento' => '12345678',
            'cliente_telefono'  => '999111222',
        ])->assertStatus(201);

        $pedidoId = $r->json('pedido.id');

        /* Reemitir debe devolver el mismo, no crear otro: un doble clic o un
           reintento de red no pueden producir duplicidad tributaria. */
        $servicio = app(\App\Services\ComprobanteService::class);
        $servicio->emitir(Pedido::find($pedidoId));

        $this->assertSame(1, ComprobanteElectronico::where('pedido_id', $pedidoId)->count());
    }

    // --------------------------------------------------- lo que NO cambió

    public function test_el_precio_lo_sigue_poniendo_el_servidor(): void
    {
        $producto = $this->producto(20.00, 10);

        $r = $this->postJson('/api/pedidos/confirmar', [
            /* El navegador manda un precio inventado. Abrir la ruta al público
               no puede significar fiarse de lo que mande. */
            'items' => [['producto_id' => $producto->id, 'cantidad' => 2, 'precio' => 0.01]],
            'cliente_nombre'    => 'Listillo',
            'cliente_documento' => '12345678',
            'cliente_telefono'  => '999111222',
        ])->assertStatus(201);

        $this->assertEqualsWithDelta(40.00, (float) $r->json('pedido.total'), 0.01);
    }

    public function test_sin_stock_responde_422_y_no_un_error_del_servidor(): void
    {
        $producto = $this->producto(20.00, 1);
        Lote::where('producto_id', $producto->id)->update(['cantidad_actual' => 0]);

        $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 5]],
            'cliente_nombre'    => 'Sin Suerte',
            'cliente_documento' => '12345678',
            'cliente_telefono'  => '999111222',
        ])->assertStatus(422)->assertJsonPath('sin_stock', true);
    }

    public function test_el_desglose_del_pedido_usa_el_mismo_calculo_fiscal_que_el_carrito(): void
    {
        /* S/ 118.00 con IGV incluido => base 100.00, IGV 18.00. */
        $producto = $this->producto(118.00, 10);

        $r = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'cliente_nombre'    => 'Contribuyente',
            'cliente_documento' => '12345678',
            'cliente_telefono'  => '999111222',
        ])->assertStatus(201);

        $pedido = Pedido::find($r->json('pedido.id'));

        $this->assertEqualsWithDelta(100.00, (float) $pedido->subtotal_gravado, 0.02);
        $this->assertEqualsWithDelta(18.00, (float) $pedido->igv, 0.02);
        $this->assertEqualsWithDelta(118.00, (float) $pedido->total, 0.02);
    }
}
