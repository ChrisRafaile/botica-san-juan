<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\Subcategoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cubre el control de caja (apertura única por cajero, movimientos manuales,
 * arqueo, cierre con diferencia, historial y vinculación de ventas POS).
 */
class ControlCajaTest extends TestCase
{
    use RefreshDatabase;

    private function crearAmoxicilinaConLote(float $precio = 10.00, int $stock = 30): Producto
    {
        $categoria = Categoria::create([
            'nombre' => 'Antibioticos',
            'slug'   => 'antibioticos',
        ]);

        $subcategoria = Subcategoria::create([
            'categoria_id' => $categoria->id,
            'nombre'       => 'Tabletas',
            'slug'         => 'tabletas',
        ]);

        $producto = Producto::create([
            'nombre'              => 'Amoxicilina 500mg',
            'concentracion'       => '500mg',
            'laboratorio'         => 'Portugal',
            'presentacion'        => 'Tableta',
            'tipo'                => 'Generico',
            'categoria_id'        => $categoria->id,
            'subcategoria_id'     => $subcategoria->id,
            'stock'               => $stock,
            'precio'              => $precio,
            'principio_activo'    => 'Amoxicilina',
            'afecto_igv'          => true,
            'tipo_afectacion_igv' => 'gravado',
        ]);

        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'AMOX-CAJA-01',
            'fecha_vencimiento' => now()->addMonths(6),
            'cantidad_inicial'  => $stock,
            'cantidad_actual'   => $stock,
            'estado'            => 'activo',
        ]);

        return $producto;
    }

    public function test_autorizacion_de_control_de_caja(): void
    {
        $this->getJson('/api/caja/actual')->assertUnauthorized();

        Sanctum::actingAs($this->crearCliente());
        $this->getJson('/api/caja/actual')->assertForbidden();
        $this->postJson('/api/caja/abrir', ['monto_inicial' => 100])->assertForbidden();
    }

    public function test_impide_doble_apertura_concurrente_para_el_mismo_cajero(): void
    {
        $admin = $this->crearAdministrador();
        Sanctum::actingAs($admin);

        $primera = $this->postJson('/api/caja/abrir', [
            'monto_inicial'        => 150.00,
            'observacion_apertura' => 'Turno mañana',
        ]);
        $primera->assertCreated()
            ->assertJsonPath('sesion.estado', 'abierta')
            ->assertJsonPath('sesion.monto_inicial', 150);

        $segunda = $this->postJson('/api/caja/abrir', [
            'monto_inicial' => 200.00,
        ]);
        $segunda->assertStatus(409);

        $this->assertSame(
            1,
            SesionCaja::where('usuario_id', $admin->id)->where('estado', 'abierta')->count()
        );
    }

    public function test_vincula_venta_efectivo_calcula_esperado_y_diferencia_al_cerrar(): void
    {
        $admin = $this->crearAdministrador();
        Sanctum::actingAs($admin);

        $producto = $this->crearAmoxicilinaConLote(precio: 10.00, stock: 20);

        $apertura = $this->postJson('/api/caja/abrir', ['monto_inicial' => 100.00]);
        $apertura->assertCreated();
        $sesionId = $apertura->json('sesion.id');

        // Venta POS en efectivo: 2 unidades * S/ 10.00 = S/ 20.00
        $venta = $this->postJson('/api/pos/ventas', [
            'items' => [
                ['producto_id' => $producto->id, 'unidad_venta' => 'unidad', 'cantidad' => 2],
            ],
            'pagos' => [
                ['medio' => 'efectivo', 'monto' => 20.00, 'monto_recibido' => 50.00],
            ],
        ]);
        $venta->assertCreated()
            ->assertJsonPath('caja_vinculada', true)
            ->assertJsonPath('caja_aviso', null);

        // Ingreso manual de S/ 30.00 (sencillo adicional)
        $this->postJson('/api/caja/movimientos', [
            'tipo'   => 'ingreso',
            'monto'  => 30.00,
            'motivo' => 'Reposición de sencillo en caja',
        ])->assertCreated();

        // Egreso manual de S/ 15.00 (compra de bolsas)
        $this->postJson('/api/caja/movimientos', [
            'tipo'   => 'egreso',
            'monto'  => 15.00,
            'motivo' => 'Compra de bolsas biodegradables',
        ])->assertCreated();

        // Esperado: 100 + 20 + 30 - 15 = 135.00
        $this->getJson('/api/caja/actual')
            ->assertOk()
            ->assertJsonPath('arqueo.monto_inicial', 100)
            ->assertJsonPath('arqueo.ventas_efectivo', 20)
            ->assertJsonPath('arqueo.ingresos', 30)
            ->assertJsonPath('arqueo.egresos', 15)
            ->assertJsonPath('arqueo.monto_esperado', 135);

        // Cierre con contado S/ 130.00 -> diferencia -5.00
        $cierre = $this->postJson("/api/caja/{$sesionId}/cerrar", [
            'monto_contado'      => 130.00,
            'observacion_cierre' => 'Faltante menor por redondeo',
        ]);
        $cierre->assertOk()
            ->assertJsonPath('sesion.estado', 'cerrada')
            ->assertJsonPath('sesion.monto_esperado', 135)
            ->assertJsonPath('sesion.monto_contado', 130)
            ->assertJsonPath('sesion.diferencia', -5);

        // Doble cierre rechazado con 409
        $this->postJson("/api/caja/{$sesionId}/cerrar", [
            'monto_contado' => 130.00,
        ])->assertStatus(409);

        // Historial refleja KPIs
        $this->getJson('/api/caja/historial')
            ->assertOk()
            ->assertJsonPath('kpis.total_sesiones', 1)
            ->assertJsonPath('kpis.total_ventas_efectivo', 20)
            ->assertJsonPath('kpis.diferencia_acumulada', -5)
            ->assertJsonCount(1, 'sesiones');
    }

    public function test_venta_pos_en_efectivo_sin_caja_abierta_sigue_funcionando_y_avisa(): void
    {
        $admin = $this->crearAdministrador();
        Sanctum::actingAs($admin);

        $producto = $this->crearAmoxicilinaConLote(precio: 12.00, stock: 10);

        $venta = $this->postJson('/api/pos/ventas', [
            'items' => [
                ['producto_id' => $producto->id, 'unidad_venta' => 'unidad', 'cantidad' => 1],
            ],
            'pagos' => [
                ['medio' => 'efectivo', 'monto' => 12.00, 'monto_recibido' => 20.00],
            ],
        ]);

        $venta->assertCreated()
            ->assertJsonPath('caja_vinculada', false);
        $this->assertNotEmpty($venta->json('caja_aviso'));
    }
}
