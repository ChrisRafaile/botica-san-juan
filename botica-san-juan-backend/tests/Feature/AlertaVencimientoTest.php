<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Subcategoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cubre las alertas de vencimiento por lote (buckets 30/60/90d, valor en
 * riesgo) y el semáforo FEFO en el endpoint de búsqueda del POS.
 */
class AlertaVencimientoTest extends TestCase
{
    use RefreshDatabase;

    private function crearProductoConLotes(): Producto
    {
        $categoria = Categoria::create([
            'nombre' => 'Antibioticos',
            'slug'   => 'antibioticos',
        ]);

        $subcategoria = Subcategoria::create([
            'categoria_id' => $categoria->id,
            'nombre'       => 'Capsulas',
            'slug'         => 'capsulas',
        ]);

        return Producto::create([
            'nombre'              => 'Amoxicilina 500mg',
            'concentracion'       => '500mg',
            'laboratorio'         => 'Portugal',
            'presentacion'        => 'Capsula',
            'tipo'                => 'Generico',
            'categoria_id'        => $categoria->id,
            'subcategoria_id'     => $subcategoria->id,
            'stock'               => 55,
            'precio'              => 1.50,
            'principio_activo'    => 'Amoxicilina',
            'afecto_igv'          => true,
            'tipo_afectacion_igv' => 'gravado',
        ]);
    }

    public function test_calcula_buckets_y_valor_en_riesgo_a_90_dias(): void
    {
        $admin = $this->crearAdministrador();
        Sanctum::actingAs($admin);

        $producto = $this->crearProductoConLotes();
        $hoy = now()->startOfDay();

        // Lote vencido (no entra en valor_en_riesgo_90d sino en valor_vencido)
        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'LOT-VENC-01',
            'fecha_vencimiento' => $hoy->copy()->subDays(5),
            'cantidad_inicial'  => 10,
            'cantidad_actual'   => 10,
            'costo_unitario'    => 2.00,
            'estado'            => 'activo',
        ]);

        // <=30 días: 10 * 1.50 = 15.00
        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'LOT-30-01',
            'fecha_vencimiento' => $hoy->copy()->addDays(15),
            'cantidad_inicial'  => 10,
            'cantidad_actual'   => 10,
            'costo_unitario'    => 1.50,
            'estado'            => 'activo',
        ]);

        // <=60 días: 20 * 1.00 = 20.00
        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'LOT-60-01',
            'fecha_vencimiento' => $hoy->copy()->addDays(45),
            'cantidad_inicial'  => 20,
            'cantidad_actual'   => 20,
            'costo_unitario'    => 1.00,
            'estado'            => 'activo',
        ]);

        // <=90 días: 5 * 3.00 = 15.00
        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'LOT-90-01',
            'fecha_vencimiento' => $hoy->copy()->addDays(80),
            'cantidad_inicial'  => 5,
            'cantidad_actual'   => 5,
            'costo_unitario'    => 3.00,
            'estado'            => 'activo',
        ]);

        // Vigente (>90 días): no entra en valor en riesgo 90d
        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'LOT-VIG-01',
            'fecha_vencimiento' => $hoy->copy()->addDays(180),
            'cantidad_inicial'  => 10,
            'cantidad_actual'   => 10,
            'costo_unitario'    => 5.00,
            'estado'            => 'activo',
        ]);

        // Agotado: no debe aparecer
        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'LOT-AGOT-01',
            'fecha_vencimiento' => $hoy->copy()->addDays(10),
            'cantidad_inicial'  => 10,
            'cantidad_actual'   => 0,
            'costo_unitario'    => 4.00,
            'estado'            => 'agotado',
        ]);

        $respuesta = $this->getJson('/api/inventario/vencimientos');

        $respuesta->assertOk()
            ->assertJsonPath('resumen.total', 5)
            ->assertJsonPath('resumen.vencido', 1)
            ->assertJsonPath('resumen.<=30', 1)
            ->assertJsonPath('resumen.<=60', 1)
            ->assertJsonPath('resumen.<=90', 1)
            ->assertJsonPath('resumen.vigente', 1)
            ->assertJsonPath('resumen.valor_en_riesgo_90d', 50)
            ->assertJsonPath('resumen.valor_vencido', 20);

        $filtrado = $this->getJson('/api/inventario/vencimientos?bucket=' . urlencode('<=30'));
        $filtrado->assertOk()
            ->assertJsonCount(1, 'lotes')
            ->assertJsonPath('lotes.0.codigo_lote', 'LOT-30-01')
            ->assertJsonPath('lotes.0.dias_para_vencer', 15)
            ->assertJsonPath('lotes.0.bucket', '<=30');
    }

    public function test_autorizacion_de_endpoint_vencimientos(): void
    {
        $this->getJson('/api/inventario/vencimientos')->assertUnauthorized();

        Sanctum::actingAs($this->crearCliente());
        $this->getJson('/api/inventario/vencimientos')->assertForbidden();

        Sanctum::actingAs($this->crearAdministrador());
        $this->getJson('/api/inventario/vencimientos?bucket=invalido')->assertUnprocessable();
    }

    public function test_pos_devuelve_dias_para_vencer_del_primer_lote_fefo(): void
    {
        Sanctum::actingAs($this->crearAdministrador());

        $producto = $this->crearProductoConLotes();
        $hoy = now()->startOfDay();

        // Lote vencido: FEFO lo excluye porque no es vendible
        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'LOT-VENC',
            'fecha_vencimiento' => $hoy->copy()->subDays(3),
            'cantidad_inicial'  => 5,
            'cantidad_actual'   => 5,
            'estado'            => 'activo',
        ]);

        // Primer lote vendible por FEFO: 18 días
        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'LOT-FEFO-1',
            'fecha_vencimiento' => $hoy->copy()->addDays(18),
            'cantidad_inicial'  => 10,
            'cantidad_actual'   => 10,
            'estado'            => 'activo',
        ]);

        // Segundo lote vendible: 120 días
        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'LOT-FEFO-2',
            'fecha_vencimiento' => $hoy->copy()->addDays(120),
            'cantidad_inicial'  => 20,
            'cantidad_actual'   => 20,
            'estado'            => 'activo',
        ]);

        $respuesta = $this->getJson('/api/pos/productos?q=Amoxicilina');

        $respuesta->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stock_disponible', 30)
            ->assertJsonPath('data.0.dias_para_vencer', 18)
            ->assertJsonPath('data.0.nivel_vencimiento', 'critico');
    }
}
