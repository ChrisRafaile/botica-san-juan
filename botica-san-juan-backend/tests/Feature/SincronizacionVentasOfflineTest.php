<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Subcategoria;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sincronización de las ventas cobradas sin conexión.
 *
 * Lo que de verdad hay que demostrar aquí no es que el endpoint guarde una
 * venta —eso ya lo hace el punto de venta normal—, sino las tres propiedades
 * de las que depende que la caja pueda reintentar sin miedo:
 *
 *   1. reenviar el mismo lote no duplica ventas ni comprobantes;
 *   2. una venta mala no arrastra a las buenas del mismo lote;
 *   3. la fecha que queda registrada es la del mostrador, no la del envío.
 *
 * Y una cuarta que es una decisión de negocio, no un detalle técnico: una
 * venta offline no se rechaza porque el stock central ya no alcance, porque
 * el producto ya salió del anaquel.
 */
class SincronizacionVentasOfflineTest extends TestCase
{
    use RefreshDatabase;

    private const RUTA = '/api/v1/ventas/sincronizar';

    private function operador(): Usuario
    {
        $usuario = Usuario::create([
            'nombre'   => 'Vendedora de turno',
            'dni'      => '70707070',
            'email'    => 'caja@prueba.local',
            'password' => Hash::make(Str::random(32)),
            'rol'      => 'administrador',
        ]);

        Sanctum::actingAs($usuario);

        return $usuario;
    }

    private function producto(int $stock = 20, float $precio = 5.00): Producto
    {
        $categoria = Categoria::firstOrCreate(
            ['slug' => 'analgesicos'],
            ['nombre' => 'Analgésicos', 'descripcion' => 'Categoría de prueba'],
        );

        $subcategoria = Subcategoria::firstOrCreate(
            ['slug' => 'tabletas'],
            ['categoria_id' => $categoria->id, 'nombre' => 'Tabletas', 'descripcion' => 'Subcategoría de prueba'],
        );

        $producto = Producto::create([
            'nombre'          => 'Paracetamol 500 mg '.Str::random(5),
            'concentracion'   => '500 mg',
            'laboratorio'     => 'Laboratorio de prueba',
            'presentacion'    => 'Caja x 100 tabletas',
            'tipo'            => 'medicamento',
            'categoria_id'    => $categoria->id,
            'subcategoria_id' => $subcategoria->id,
            'stock'           => $stock,
            'stock_minimo'    => 1,
            'precio'          => $precio,
        ]);

        /* La venta recorre lotes por FEFO; sin lote no hay existencias que
           descontar por mucho que la columna `stock` diga otra cosa. */
        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'PRUEBA-'.$producto->id,
            'cantidad_inicial'  => $stock,
            'cantidad_actual'   => $stock,
            'fecha_vencimiento' => null,
            'estado'            => 'activo',
        ]);

        return $producto;
    }

    /** @return array<string, mixed> */
    private function venta(Producto $producto, array $sobrescribir = []): array
    {
        return array_merge([
            'uuid_local'        => (string) Str::uuid(),
            'nota_contingencia' => 'NV-000001',
            'registrada_en'     => now()->subHours(5)->toIso8601String(),
            'cliente'           => ['nombre' => 'Consumidor final', 'tipo_documento' => 'sin_documento'],
            'items'             => [
                ['producto_id' => $producto->id, 'unidad_venta' => 'unidad', 'cantidad' => 2],
            ],
        ], $sobrescribir);
    }

    // ------------------------------------------------------------ registro

    public function test_una_venta_offline_se_registra_con_su_correlativo_oficial(): void
    {
        $this->operador();
        $producto = $this->producto(stock: 20, precio: 5.00);
        $venta    = $this->venta($producto);

        $respuesta = $this->postJson(self::RUTA, [
            'dispositivo' => 'caja-01',
            'ventas'      => [$venta],
        ]);

        $respuesta->assertOk();
        $respuesta->assertJsonPath('procesadas', 1);
        $respuesta->assertJsonPath('resultados.0.estado', 'creada');

        $this->assertMatchesRegularExpression(
            '/^B001-\d{8}$/',
            $respuesta->json('resultados.0.comprobante'),
            'La venta sincronizada debe recibir un correlativo oficial de la serie de boletas.',
        );

        $this->assertDatabaseHas('pedidos', [
            'uuid_local'         => $venta['uuid_local'],
            'nota_contingencia'  => 'NV-000001',
            'dispositivo_origen' => 'caja-01',
            'origen'             => Pedido::ORIGEN_POS,
        ]);

        $this->assertSame(18, (int) $producto->fresh()->stock, 'La sincronización debe descontar el stock central.');
    }

    public function test_conserva_la_fecha_del_mostrador_y_no_la_del_envio(): void
    {
        $this->operador();
        $producto = $this->producto();

        $momentoVenta = now()->subDay()->setTime(10, 30);

        $this->postJson(self::RUTA, [
            'ventas' => [$this->venta($producto, ['registrada_en' => $momentoVenta->toIso8601String()])],
        ])->assertOk();

        $pedido = Pedido::whereNotNull('uuid_local')->firstOrFail();

        $this->assertSame(
            $momentoVenta->format('Y-m-d H:i'),
            $pedido->fecha->format('Y-m-d H:i'),
            'La fecha de la venta es la del mostrador: usar la del envío la metería en el día equivocado del registro de ventas.',
        );
        $this->assertNotNull($pedido->registrado_offline_en);
    }

    // -------------------------------------------------------- idempotencia

    public function test_reenviar_el_mismo_uuid_no_duplica_la_venta_ni_el_comprobante(): void
    {
        $this->operador();
        $producto = $this->producto(stock: 20);
        $venta    = $this->venta($producto);

        $primera = $this->postJson(self::RUTA, ['ventas' => [$venta]]);
        $primera->assertOk()->assertJsonPath('resultados.0.estado', 'creada');

        $segunda = $this->postJson(self::RUTA, ['ventas' => [$venta]]);
        $segunda->assertOk()->assertJsonPath('resultados.0.estado', 'duplicada');

        $this->assertSame(
            $primera->json('resultados.0.pedido_id'),
            $segunda->json('resultados.0.pedido_id'),
            'El reenvío debe devolver el pedido que ya existía, no uno nuevo.',
        );
        $this->assertSame(
            $primera->json('resultados.0.comprobante'),
            $segunda->json('resultados.0.comprobante'),
            'Dos comprobantes para una sola venta es una contingencia tributaria.',
        );

        $this->assertSame(1, Pedido::where('uuid_local', $venta['uuid_local'])->count());
        $this->assertDatabaseCount('comprobantes_electronicos', 1);

        /* Y, sobre todo: el stock se descuenta una sola vez. Un reenvío que
           volviera a descontar dejaría el inventario corrupto sin que nadie
           lo notara hasta el conteo. */
        $this->assertSame(18, (int) $producto->fresh()->stock);
    }

    public function test_el_reenvio_queda_anotado_como_advertencia_en_la_bitacora(): void
    {
        $this->operador();
        $venta = $this->venta($this->producto());

        $this->postJson(self::RUTA, ['ventas' => [$venta]])->assertOk();
        $this->postJson(self::RUTA, ['ventas' => [$venta]])->assertOk();

        $this->assertDatabaseHas('eventos_operacion', [
            'codigo'     => 'SYNC_VENTA_DUPLICADA',
            'nivel'      => 'warning',
            'uuid_local' => $venta['uuid_local'],
        ]);
        $this->assertDatabaseHas('eventos_operacion', [
            'codigo' => 'SYNC_VENTA_CREADA',
            'nivel'  => 'info',
        ]);
    }

    // ------------------------------------------------- aislamiento del lote

    public function test_una_venta_rechazada_no_arrastra_a_las_demas_del_lote(): void
    {
        $this->operador();
        $producto = $this->producto(stock: 20);

        $buena   = $this->venta($producto);
        $mala    = $this->venta($producto, [
            'uuid_local' => (string) Str::uuid(),
            'items'      => [['producto_id' => 999999, 'unidad_venta' => 'unidad', 'cantidad' => 1]],
        ]);
        $otra    = $this->venta($producto, ['uuid_local' => (string) Str::uuid()]);

        $respuesta = $this->postJson(self::RUTA, ['ventas' => [$buena, $mala, $otra]]);

        $respuesta->assertOk();
        $respuesta->assertJsonPath('procesadas', 3);
        $respuesta->assertJsonPath('resultados.0.estado', 'creada');
        $respuesta->assertJsonPath('resultados.1.estado', 'rechazada');
        $respuesta->assertJsonPath('resultados.2.estado', 'creada');

        $this->assertNotEmpty($respuesta->json('resultados.1.motivo'));

        $this->assertSame(2, Pedido::whereNotNull('uuid_local')->count());
        $this->assertDatabaseMissing('pedidos', ['uuid_local' => $mala['uuid_local']]);

        $this->assertDatabaseHas('eventos_operacion', [
            'codigo'     => 'SYNC_VENTA_RECHAZADA',
            'nivel'      => 'error',
            'uuid_local' => $mala['uuid_local'],
        ]);
    }

    // ----------------------------------------------------- regla de negocio

    public function test_la_venta_se_registra_aunque_el_stock_central_ya_no_alcance(): void
    {
        $this->operador();
        $producto = $this->producto(stock: 1, precio: 5.00);

        /* La caja vendió dos unidades sin conexión. Para cuando sincroniza,
           la otra caja ya despachó una: en el sistema solo queda una. El
           producto, sin embargo, ya se lo llevó el cliente. */
        $respuesta = $this->postJson(self::RUTA, [
            'ventas' => [$this->venta($producto)],
        ]);

        $respuesta->assertOk();
        $respuesta->assertJsonPath('resultados.0.estado', 'creada');

        $this->assertNotEmpty(
            $respuesta->json('resultados.0.incidencias'),
            'La diferencia no se pierde: queda como incidencia para el conteo por ciclos.',
        );

        $this->assertDatabaseHas('eventos_operacion', [
            'codigo' => 'SYNC_STOCK_INSUFICIENTE',
            'nivel'  => 'warning',
        ]);
    }

    // ------------------------------------------------------------ contrato

    public function test_sin_sesion_la_sincronizacion_responde_401(): void
    {
        $this->postJson(self::RUTA, ['ventas' => []])->assertStatus(401);
    }

    public function test_el_lote_tiene_un_tope_de_cincuenta_ventas(): void
    {
        $this->operador();
        $producto = $this->producto(stock: 500);

        $ventas = [];
        for ($i = 0; $i < 51; $i++) {
            $ventas[] = $this->venta($producto, ['uuid_local' => (string) Str::uuid()]);
        }

        $this->postJson(self::RUTA, ['ventas' => $ventas])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ventas');
    }

    public function test_un_uuid_que_no_es_uuid_se_rechaza_en_la_validacion(): void
    {
        $this->operador();

        $this->postJson(self::RUTA, [
            'ventas' => [$this->venta($this->producto(), ['uuid_local' => 'caja-01-venta-7'])],
        ])->assertStatus(422)->assertJsonValidationErrors('ventas.0.uuid_local');
    }

    // ------------------------------------- idempotencia del cobro en linea

    public function test_el_cobro_en_linea_con_uuid_no_se_registra_dos_veces(): void
    {
        $this->operador();
        $producto = $this->producto(stock: 20, precio: 5.00);
        $uuid     = (string) Str::uuid();

        $cuerpo = [
            'uuid_local'        => $uuid,
            'nota_contingencia' => 'NV-000009',
            'items'             => [
                ['producto_id' => $producto->id, 'unidad_venta' => 'unidad', 'cantidad' => 2],
            ],
        ];

        $primera = $this->postJson('/api/pos/ventas', $cuerpo);
        $primera->assertStatus(201);

        /* El caso que esto protege: la peticion salio, el servidor registro y
           la respuesta se perdio. La caja reintenta con el mismo UUID. */
        $segunda = $this->postJson('/api/pos/ventas', $cuerpo);
        $segunda->assertStatus(200)->assertJsonPath('duplicada', true);

        $this->assertSame(1, Pedido::where('uuid_local', $uuid)->count());
        $this->assertSame(
            18,
            (int) $producto->fresh()->stock,
            'El reintento no puede volver a descontar stock.',
        );
    }

    public function test_el_cobro_en_linea_sigue_funcionando_sin_uuid(): void
    {
        $this->operador();
        $producto = $this->producto(stock: 10, precio: 4.00);

        $this->postJson('/api/pos/ventas', [
            'items' => [['producto_id' => $producto->id, 'unidad_venta' => 'unidad', 'cantidad' => 1]],
        ])->assertStatus(201);

        $this->assertSame(9, (int) $producto->fresh()->stock);
    }

    // ----------------------------------------- catalogo para la caja local

    public function test_el_catalogo_para_la_caja_no_expone_stock(): void
    {
        $this->operador();
        $this->producto(stock: 20, precio: 5.00);

        $respuesta = $this->getJson('/api/pos/catalogo');

        $respuesta->assertOk();
        $respuesta->assertJsonPath('total', 1);

        $primero = $respuesta->json('data.0');

        /* El stock no viaja a proposito: cambia con cada venta de cualquier
           caja y una cifra guardada invita a prometer lo que ya no hay. */
        $this->assertArrayNotHasKey('stock', $primero);
        $this->assertArrayNotHasKey('stock_disponible', $primero);
        $this->assertArrayNotHasKey('stock_contable', $primero);

        /* Lo que si tiene que viajar: lo necesario para buscar y poner precio. */
        $this->assertArrayHasKey('nombre', $primero);
        $this->assertArrayHasKey('principio_activo', $primero);
        $this->assertArrayHasKey('codigo_barras', $primero);
        $this->assertArrayHasKey('precio', $primero);
        $this->assertArrayHasKey('tipo_afectacion_igv', $primero);
    }

    public function test_el_catalogo_incremental_solo_devuelve_lo_que_cambio(): void
    {
        $this->operador();
        $viejo = $this->producto();

        \Illuminate\Support\Facades\DB::table('productos')
            ->where('id', $viejo->id)
            ->update(['updated_at' => now()->subDays(3)]);

        $corte = now()->subDay()->toIso8601String();

        $this->getJson('/api/pos/catalogo?desde='.urlencode($corte))
            ->assertOk()
            ->assertJsonPath('total', 0);

        $nuevo = $this->producto();

        $this->getJson('/api/pos/catalogo?desde='.urlencode($corte))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $nuevo->id);
    }

    public function test_el_catalogo_exige_sesion(): void
    {
        $this->getJson('/api/pos/catalogo')->assertStatus(401);
    }
}
