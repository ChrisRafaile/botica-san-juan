<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\Subcategoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cubre las métricas extendidas del tablero administrativo (comparativa 7d vs
 * semana anterior, Top 5 productos y distribución por método de pago).
 */
class TableroDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_tablero_devuelve_comparativa_semanal_metodos_pago_y_top_5(): void
    {
        Sanctum::actingAs($this->crearAdministrador());

        $categoria = Categoria::create(['nombre' => 'Analgesicos', 'slug' => 'analgesicos']);
        $subcategoria = Subcategoria::create([
            'categoria_id' => $categoria->id,
            'nombre'       => 'Tabletas',
            'slug'         => 'tabletas',
        ]);

        $productos = [];
        foreach (['Amoxicilina', 'Paracetamol', 'Ibuprofeno', 'Azitromicina', 'Omeprazol', 'Cetirizina'] as $i => $nombre) {
            $productos[] = Producto::create([
                'nombre'              => $nombre,
                'concentracion'       => '500mg',
                'laboratorio'         => 'Portugal',
                'presentacion'        => 'Tableta',
                'tipo'                => 'Generico',
                'categoria_id'        => $categoria->id,
                'subcategoria_id'     => $subcategoria->id,
                'stock'               => 50,
                'precio'              => 10.00,
                'principio_activo'    => $nombre,
                'afecto_igv'          => true,
                'tipo_afectacion_igv' => 'gravado',
            ]);
        }

        // Pedido de hoy en efectivo (S/ 100.00)
        $pedidoHoy = Pedido::create([
            'fecha'      => now(),
            'total'      => 100.00,
            'estado'     => 'completado',
            'medio_pago' => 'efectivo',
            'origen'     => 'pos',
        ]);

        foreach ($productos as $idx => $prod) {
            PedidoDetalle::create([
                'pedido_id'         => $pedidoHoy->id,
                'producto_id'       => $prod->id,
                'cantidad'          => 10 - $idx,
                'cantidad_unidades' => 10 - $idx,
                'precio'            => 10.00,
                'precio_unitario'   => 10.00,
                'subtotal'          => (10 - $idx) * 10.00,
            ]);
        }

        // Pedido hace 8 días (semana anterior) por Yape (S/ 40.00)
        Pedido::create([
            'fecha'      => now()->subDays(8),
            'total'      => 40.00,
            'estado'     => 'completado',
            'medio_pago' => 'yape',
            'origen'     => 'pos',
        ]);

        // Pedido anulado hoy: no debe sumar
        Pedido::create([
            'fecha'      => now(),
            'total'      => 500.00,
            'estado'     => 'anulado',
            'medio_pago' => 'tarjeta',
            'origen'     => 'pos',
        ]);

        $respuesta = $this->getJson('/api/tablero');

        $respuesta->assertOk()
            ->assertJsonCount(7, 'comparativa_semanal.dias')
            ->assertJsonPath('comparativa_semanal.total_actual', 100)
            ->assertJsonPath('comparativa_semanal.total_anterior', 40)
            ->assertJsonPath('comparativa_semanal.variacion_absoluta', 60)
            ->assertJsonCount(5, 'mas_vendidos')
            ->assertJsonPath('mas_vendidos.0.nombre', 'Amoxicilina');

        $metodos = collect($respuesta->json('metodos_pago'))->keyBy('medio');
        $this->assertEquals(100, $metodos['efectivo']['importe']);
        $this->assertEquals(40, $metodos['yape']['importe']);
        $this->assertFalse($metodos->has('tarjeta'));
    }
}
