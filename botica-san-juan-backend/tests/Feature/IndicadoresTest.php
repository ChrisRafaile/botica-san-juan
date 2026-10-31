<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Indicadores gerenciales.
 *
 * Lo que hay que fijar aquí no es que el endpoint responda, sino las tres
 * decisiones de cálculo que serían fáciles de romper sin notarlo:
 *
 *   - los lotes SIN fecha no se reparten entre los tramos;
 *   - los lotes AGOTADOS no cuentan, aunque estén vencidos;
 *   - los pedidos anulados no son caja.
 */
class IndicadoresTest extends TestCase
{
    use RefreshDatabase;

    private ?Usuario $admin = null;

    /** Se reutiliza: hay pruebas que consultan dos ventanas y crearla dos
        veces chocaría con el índice único del correo. */
    private function admin(): Usuario
    {
        return $this->admin ??= Usuario::create([
            'nombre' => 'Jefa', 'dni' => '20000001',
            'email' => 'jefa@prueba.test', 'password' => Hash::make('x'),
            'rol' => 'administrador',
        ]);
    }

    private function producto(string $nombre = 'PARACETAMOL'): Producto
    {
        return Producto::create([
            'nombre' => $nombre,
            'concentracion' => '500 mg',
            'laboratorio' => 'Laboratorio de prueba',
            'presentacion' => 'Caja x 10',
            'tipo' => 'medicamento',
            'categoria' => 'Analgésicos',
            'precio' => 10,
            'stock' => 100,
        ]);
    }

    private function lote(Producto $p, ?string $vence, int $cantidad): Lote
    {
        return Lote::create([
            'producto_id' => $p->id,
            'codigo_lote' => 'L'.fake()->unique()->numerify('####'),
            'fecha_vencimiento' => $vence,
            'cantidad_inicial' => $cantidad,
            'cantidad_actual' => $cantidad,
        ]);
    }

    private function indicadores(array $parametros = []): array
    {
        return $this->actingAs($this->admin())
            ->getJson('/api/tablero/indicadores?'.http_build_query($parametros))
            ->assertOk()
            ->json();
    }

    // -------------------------------------------------- semáforo

    public function test_reparte_los_lotes_en_los_tramos_correctos(): void
    {
        $p = $this->producto();

        $this->lote($p, now()->subDay()->toDateString(), 5);        // vencido
        $this->lote($p, now()->addDays(10)->toDateString(), 7);     // 30
        $this->lote($p, now()->addDays(45)->toDateString(), 3);     // 60
        $this->lote($p, now()->addDays(80)->toDateString(), 2);     // 90
        $this->lote($p, now()->addDays(200)->toDateString(), 11);   // holgado

        $tramos = collect($this->indicadores()['vencimientos']['tramos'])
            ->keyBy('clave');

        $this->assertSame(1, $tramos['vencido']['lotes']);
        $this->assertSame(1, $tramos['t30']['lotes']);
        $this->assertSame(1, $tramos['t60']['lotes']);
        $this->assertSame(1, $tramos['t90']['lotes']);
        $this->assertSame(1, $tramos['holgado']['lotes']);
        $this->assertSame(11, $tramos['holgado']['unidades']);
    }

    public function test_los_lotes_sin_fecha_se_cuentan_aparte_y_no_en_los_tramos(): void
    {
        $p = $this->producto();

        $this->lote($p, now()->addDays(10)->toDateString(), 4);
        $this->lote($p, null, 100);
        $this->lote($p, null, 50);

        $v = $this->indicadores()['vencimientos'];

        /* Repartirlos inventaría un semáforo; ignorarlos daría a entender que
           el inventario está controlado cuando no lo está. */
        $this->assertSame(2, $v['sin_fecha']['lotes']);
        $this->assertSame(150, $v['sin_fecha']['unidades']);
        $this->assertSame(1, array_sum(array_column($v['tramos'], 'lotes')));
    }

    public function test_la_cobertura_dice_que_parte_del_almacen_describe_el_semaforo(): void
    {
        $p = $this->producto();

        $this->lote($p, now()->addDays(10)->toDateString(), 1);
        foreach (range(1, 9) as $i) {
            $this->lote($p, null, 1);
        }

        $this->assertEqualsWithDelta(10.0, $this->indicadores()['vencimientos']['cobertura'], 0.001);
    }

    public function test_un_lote_agotado_no_cuenta_aunque_este_vencido(): void
    {
        $p = $this->producto();

        $agotado = $this->lote($p, now()->subMonth()->toDateString(), 10);
        $agotado->update(['cantidad_actual' => 0]);

        $v = $this->indicadores()['vencimientos'];

        /* Un lote sin existencia no es un problema de inventario, y contarlo
           infla la alarma hasta volverla ruido que nadie mira. */
        $this->assertSame(0, $v['lotes_con_existencia']);
        $this->assertSame(0, collect($v['tramos'])->firstWhere('clave', 'vencido')['lotes']);
    }

    // ---------------------------------------------------- arqueo

    public function test_el_arqueo_agrupa_por_medio_y_calcula_porcentajes(): void
    {
        $this->pedidoConPago('efectivo', 75);
        $this->pedidoConPago('efectivo', 75);
        $this->pedidoConPago('yape', 50);

        $arqueo = $this->indicadores()['arqueo'];

        $this->assertEqualsWithDelta(200.0, $arqueo['total'], 0.001);

        $efectivo = collect($arqueo['medios'])->firstWhere('medio', 'efectivo');
        $this->assertSame(2, $efectivo['operaciones']);
        $this->assertEqualsWithDelta(150.0, $efectivo['total'], 0.001);
        $this->assertEqualsWithDelta(75.0, $efectivo['porcentaje'], 0.001);
    }

    public function test_un_pedido_anulado_no_es_caja(): void
    {
        $this->pedidoConPago('efectivo', 100);
        $this->pedidoConPago('efectivo', 999, estado: 'anulado');

        $this->assertEqualsWithDelta(100.0, $this->indicadores()['arqueo']['total'], 0.001);
    }

    public function test_la_ventana_deja_fuera_lo_anterior(): void
    {
        $this->pedidoConPago('efectivo', 40);
        $this->pedidoConPago('efectivo', 500, hace: 60);

        $this->assertEqualsWithDelta(40.0, $this->indicadores(['dias' => 30])['arqueo']['total'], 0.001);
        $this->assertEqualsWithDelta(540.0, $this->indicadores(['dias' => 90])['arqueo']['total'], 0.001);
    }

    // ---------------------------------------------- más vendidos

    public function test_el_top_ordena_por_unidades_y_no_por_importe(): void
    {
        $caro = $this->producto('CARO');
        $barato = $this->producto('BARATO');

        $this->pedidoConDetalle($caro, unidades: 2, subtotal: 500);
        $this->pedidoConDetalle($barato, unidades: 40, subtotal: 80);

        $top = $this->indicadores()['mas_vendidos'];

        /* Lo que interesa para reponer es cuántas cajas salieron del anaquel,
           no cuánto facturaron. */
        $this->assertSame('BARATO', $top[0]['nombre']);
        $this->assertSame(40, $top[0]['unidades']);
    }

    // ------------------------------------------------- permisos

    public function test_un_cliente_no_ve_los_indicadores(): void
    {
        $cliente = Usuario::create([
            'nombre' => 'Cliente', 'dni' => '20000002',
            'email' => 'cliente.ind@prueba.test', 'password' => Hash::make('x'),
            'rol' => 'cliente',
        ]);

        $this->actingAs($cliente)->getJson('/api/tablero/indicadores')->assertStatus(403);
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->getJson('/api/tablero/indicadores')->assertStatus(401);
    }

    // ------------------------------------------------- ayudantes

    private function pedidoConPago(string $medio, float $monto, int $hace = 1, string $estado = 'entregado'): void
    {
        $id = DB::table('pedidos')->insertGetId([
            'fecha' => now()->subDays($hace),
            'total' => $monto,
            'estado' => $estado,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pedido_pagos')->insert([
            'pedido_id' => $id,
            'medio' => $medio,
            'monto' => $monto,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pedidoConDetalle(Producto $producto, int $unidades, float $subtotal): void
    {
        $id = DB::table('pedidos')->insertGetId([
            'fecha' => now()->subDay(),
            'total' => $subtotal,
            'estado' => 'entregado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pedido_detalles')->insert([
            'pedido_id' => $id,
            'producto_id' => $producto->id,
            'cantidad' => $unidades,
            'cantidad_unidades' => $unidades,
            'precio' => $subtotal / max(1, $unidades),
            'subtotal' => $subtotal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
