<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Las cifras del portal del cliente las calcula la base, no el navegador.
 * ---------------------------------------------------------------------------
 * Es la quinta vez que este proyecto tropieza con lo mismo: una vista que
 * recibe una pagina de registros, cuenta sobre ella y presenta el resultado
 * como el total. El caso mas visible fue la pantalla de productos mostrando
 * «2 en stock» sobre un catalogo entero.
 *
 * Estas pruebas fijan dos cosas: que las cifras salen de SQL y son correctas,
 * y que un cliente no ve en su resumen ni un solo pedido de otro.
 */
class ResumenDelClienteTest extends TestCase
{
    use RefreshDatabase;

    private function cliente(string $dni): Usuario
    {
        return Usuario::create([
            'nombre'   => 'Cliente '.$dni,
            'email'    => $dni.'@resumen.local',
            'dni'      => $dni,
            'password' => Hash::make('clave-de-prueba-'.$dni),
            'rol'      => 'cliente',
        ]);
    }

    private function pedido(Usuario $duenyo, string $estado, float $total, ?string $fecha = null): Pedido
    {
        return Pedido::create([
            'usuario_id'       => $duenyo->id,
            'origen'           => Pedido::ORIGEN_WEB,
            'cliente_nombre'   => $duenyo->nombre,
            'cliente_documento'=> $duenyo->dni,
            'fecha'            => $fecha ?? now(),
            'estado'           => $estado,
            'total'            => $total,
        ]);
    }

    /**
     * Compara importes por valor y no por tipo.
     *
     * `json_encode(150.00)` emite `150`, que PHP decodifica como entero. Una
     * asercion estricta falla aunque el contrato sea correcto: no esta
     * comprobando el importe, esta comprobando como serializa JSON. Ya ocurrio
     * en las pruebas del carrito del APF2.
     */
    private function assertImporte(float $esperado, mixed $recibido, string $campo): void
    {
        $this->assertEqualsWithDelta($esperado, (float) $recibido, 0.001, $campo);
    }

    public function test_sin_token_no_hay_resumen(): void
    {
        $this->getJson('/api/pedidos/mi-resumen')->assertStatus(401);
    }

    public function test_un_cliente_sin_pedidos_recibe_ceros_y_no_un_error(): void
    {
        /* El portal lo abre tambien quien acaba de registrarse. Una respuesta
           vacia o un fallo obligaria a la vista a inventar el caso. */
        Sanctum::actingAs($this->cliente('20000001'));

        $this->getJson('/api/pedidos/mi-resumen')
            ->assertOk()
            ->assertJsonPath('data.pedidos.total', 0)
            ->assertJsonPath('data.gasto.total', 0)
            ->assertJsonPath('data.ultimo_pedido', null)
            ->assertJsonPath('data.comprobantes', 0);
    }

    public function test_cuenta_los_pedidos_por_estado(): void
    {
        $cliente = $this->cliente('20000002');

        $this->pedido($cliente, Pedido::ESTADO_PENDIENTE, 10.00);
        $this->pedido($cliente, Pedido::ESTADO_CONFIRMADO, 20.00);
        $this->pedido($cliente, Pedido::ESTADO_COMPLETADO, 30.00);
        $this->pedido($cliente, Pedido::ESTADO_ANULADO, 40.00);

        Sanctum::actingAs($cliente);

        $this->getJson('/api/pedidos/mi-resumen')
            ->assertOk()
            ->assertJsonPath('data.pedidos.total', 4)
            /* Abiertos = pendiente + confirmado: los que todavia esperan algo. */
            ->assertJsonPath('data.pedidos.abiertos', 2)
            ->assertJsonPath('data.pedidos.completados', 1)
            ->assertJsonPath('data.pedidos.anulados', 1);
    }

    public function test_el_gasto_no_incluye_los_pedidos_anulados(): void
    {
        /* Un pedido anulado no se pago. Sumarlo le diria al cliente que gasto
           un dinero que sigue en su bolsillo. */
        $cliente = $this->cliente('20000003');

        $this->pedido($cliente, Pedido::ESTADO_COMPLETADO, 100.00);
        $this->pedido($cliente, Pedido::ESTADO_PENDIENTE, 50.00);
        $this->pedido($cliente, Pedido::ESTADO_ANULADO, 999.00);

        Sanctum::actingAs($cliente);

        $respuesta = $this->getJson('/api/pedidos/mi-resumen')->assertOk();

        $this->assertImporte(150.00, $respuesta->json('data.gasto.total'), 'gasto total');
    }

    public function test_el_gasto_reciente_solo_mira_los_ultimos_treinta_dias(): void
    {
        $cliente = $this->cliente('20000004');

        $this->pedido($cliente, Pedido::ESTADO_COMPLETADO, 25.00, now()->subDays(3)->toDateTimeString());
        $this->pedido($cliente, Pedido::ESTADO_COMPLETADO, 80.00, now()->subDays(90)->toDateTimeString());

        Sanctum::actingAs($cliente);

        $respuesta = $this->getJson('/api/pedidos/mi-resumen')->assertOk();

        $this->assertImporte(105.00, $respuesta->json('data.gasto.total'), 'gasto total');
        $this->assertImporte(25.00, $respuesta->json('data.gasto.ultimos_30_dias'), 'gasto reciente');
    }

    public function test_el_ultimo_pedido_es_el_mas_reciente(): void
    {
        $cliente = $this->cliente('20000005');

        $this->pedido($cliente, Pedido::ESTADO_COMPLETADO, 10.00, now()->subDays(5)->toDateTimeString());
        $reciente = $this->pedido($cliente, Pedido::ESTADO_PENDIENTE, 60.00, now()->toDateTimeString());

        Sanctum::actingAs($cliente);

        $this->getJson('/api/pedidos/mi-resumen')
            ->assertOk()
            ->assertJsonPath('data.ultimo_pedido.id', $reciente->id)
            ->assertJsonPath('data.ultimo_pedido.estado', Pedido::ESTADO_PENDIENTE);
    }

    public function test_el_resumen_no_cuenta_pedidos_de_otros_clientes(): void
    {
        /* La prueba que importa: el endpoint deduce el duenyo del token, asi
           que no hay nada que un consumidor pueda falsear para colar los
           pedidos de otra persona en su propio resumen. */
        $mio  = $this->cliente('20000006');
        $otro = $this->cliente('20000007');

        $this->pedido($mio, Pedido::ESTADO_COMPLETADO, 10.00);
        $this->pedido($otro, Pedido::ESTADO_COMPLETADO, 500.00);
        $this->pedido($otro, Pedido::ESTADO_PENDIENTE, 300.00);

        Sanctum::actingAs($mio);

        $respuesta = $this->getJson('/api/pedidos/mi-resumen')
            ->assertOk()
            ->assertJsonPath('data.pedidos.total', 1);

        $this->assertImporte(10.00, $respuesta->json('data.gasto.total'), 'gasto total');
    }
}
