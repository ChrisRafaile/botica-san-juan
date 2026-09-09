<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre el RF-03 (gestion del catalogo) y el RF-05 (busqueda de productos).
 */
class ProductoApiTest extends TestCase
{
    use RefreshDatabase;

    private function datosDeProducto(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Paracetamol 500 mg',
            'concentracion' => '500 mg',
            'laboratorio' => 'Medifarma',
            'presentacion' => 'Caja x 100 tabletas',
            'tipo' => 'Medicamento',
            'stock' => 120,
            'precio' => 0.50,
        ], $extra);
    }

    private function tokenAdministrador(): string
    {
        return $this->crearAdministrador()->createToken('prueba')->plainTextToken;
    }

    public function test_el_listado_de_productos_es_publico(): void
    {
        Producto::create($this->datosDeProducto());

        $this->getJson('/api/productos')->assertSuccessful();
    }

    public function test_un_administrador_registra_un_producto(): void
    {
        $token = $this->tokenAdministrador();

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/productos', $this->datosDeProducto());

        $respuesta->assertSuccessful();
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Paracetamol 500 mg',
            'stock' => 120,
        ]);
    }

    public function test_el_nombre_el_stock_y_el_precio_son_obligatorios(): void
    {
        $token = $this->tokenAdministrador();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/productos', ['concentracion' => '500 mg'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre', 'stock', 'precio']);
    }

    public function test_no_se_admite_stock_negativo(): void
    {
        $token = $this->tokenAdministrador();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/productos', $this->datosDeProducto(['stock' => -5]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('stock');
    }

    public function test_no_se_admite_precio_negativo(): void
    {
        $token = $this->tokenAdministrador();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/productos', $this->datosDeProducto(['precio' => -1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('precio');
    }

    public function test_el_codigo_de_barras_no_puede_repetirse(): void
    {
        $token = $this->tokenAdministrador();
        Producto::create($this->datosDeProducto(['codigo_barras' => '7759307002523']));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/productos', $this->datosDeProducto([
                'nombre' => 'Otro producto',
                'codigo_barras' => '7759307002523',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('codigo_barras');
    }

    public function test_la_categoria_referenciada_debe_existir(): void
    {
        $token = $this->tokenAdministrador();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/productos', $this->datosDeProducto(['categoria_id' => 99999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('categoria_id');
    }

    public function test_un_administrador_actualiza_el_stock_de_un_producto(): void
    {
        $token = $this->tokenAdministrador();
        $producto = Producto::create($this->datosDeProducto());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/productos/{$producto->id}", ['stock' => 200])
            ->assertSuccessful();

        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'stock' => 200]);
    }

    public function test_la_venta_fraccionada_admite_unidad_blister_y_caja(): void
    {
        $token = $this->tokenAdministrador();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/productos', $this->datosDeProducto([
                'venta_fraccionada' => true,
                'unidad_base' => 'unidad',
                'unidades_por_blister' => 10,
                'blisters_por_caja' => 10,
                'precio_blister' => 4.50,
                'precio_caja' => 40.00,
            ]))
            ->assertSuccessful();

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Paracetamol 500 mg',
            'unidades_por_blister' => 10,
            'blisters_por_caja' => 10,
        ]);
    }

    public function test_una_unidad_base_no_valida_se_rechaza(): void
    {
        $token = $this->tokenAdministrador();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/productos', $this->datosDeProducto(['unidad_base' => 'barril']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('unidad_base');
    }

    public function test_una_categoria_valida_se_asocia_al_producto(): void
    {
        $token = $this->tokenAdministrador();
        $categoria = Categoria::create([
            'nombre' => 'Medicamentos',
            'slug' => 'medicamentos',
            'descripcion' => 'Con y sin receta',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/productos', $this->datosDeProducto(['categoria_id' => $categoria->id]))
            ->assertSuccessful();

        $this->assertDatabaseHas('productos', ['categoria_id' => $categoria->id]);
    }
}
