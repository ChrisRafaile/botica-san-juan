<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Subcategoria;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Cubre el RF-07 (descuento automatico de stock al confirmar una venta) y el
 * RNF-05 (integridad transaccional), hallazgo critico C-02 de la auditoria.
 */
class ConfirmacionVentaTest extends TestCase
{
    use RefreshDatabase;

    private function crearProducto(int $stock, float $precio): Producto
    {
        $categoria = Categoria::create([
            'nombre' => 'Analgesicos',
            'slug' => 'analgesicos',
            'descripcion' => 'Categoria de prueba',
        ]);

        $subcategoria = Subcategoria::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Tabletas',
            'slug' => 'tabletas',
            'descripcion' => 'Subcategoria de prueba',
        ]);

        return Producto::create($this->datosProducto($categoria->id, $subcategoria->id, [
            'stock' => $stock,
            'precio' => $precio,
        ]));
    }

    /**
     * La tabla de productos declara varias columnas no nulas heredadas del
     * esquema original; el helper las completa para que la prueba se centre
     * en el stock y el precio.
     */
    private function datosProducto(int $categoriaId, int $subcategoriaId, array $atributos = []): array
    {
        return array_merge([
            'nombre' => 'Paracetamol 500 mg',
            'concentracion' => '500 mg',
            'laboratorio' => 'Laboratorio de prueba',
            'presentacion' => 'Caja x 100 tabletas',
            'tipo' => 'medicamento',
            'categoria_id' => $categoriaId,
            'subcategoria_id' => $subcategoriaId,
            'stock' => 10,
            'stock_minimo' => 1,
            'precio' => 3.50,
        ], $atributos);
    }

    public function test_confirmar_una_venta_descuenta_el_stock(): void
    {
        $producto = $this->crearProducto(stock: 10, precio: 3.50);
        $cliente = $this->crearCliente();
        $token = $cliente->createToken('prueba')->plainTextToken;

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pedidos/confirmar', [
                'items' => [['producto_id' => $producto->id, 'cantidad' => 3]],
            ]);

        $respuesta->assertStatus(201);
        $this->assertSame(7, (int) $producto->fresh()->stock);
        $this->assertDatabaseHas('pedido_detalles', [
            'producto_id' => $producto->id,
            'cantidad' => 3,
        ]);
    }

    public function test_el_total_lo_calcula_el_servidor_y_no_el_consumidor(): void
    {
        $producto = $this->crearProducto(stock: 10, precio: 20.00);
        $cliente = $this->crearCliente();
        $token = $cliente->createToken('prueba')->plainTextToken;

        // El consumidor intenta imponer un total de un sol por dos unidades.
        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pedidos/confirmar', [
                'items' => [['producto_id' => $producto->id, 'cantidad' => 2]],
                'total' => 1.00,
            ]);

        $respuesta->assertStatus(201);
        $this->assertSame('40.00', (string) $respuesta->json('pedido.total'));
    }

    public function test_stock_insuficiente_rechaza_la_venta_y_no_altera_existencias(): void
    {
        $producto = $this->crearProducto(stock: 2, precio: 5.00);
        $cliente = $this->crearCliente();
        $token = $cliente->createToken('prueba')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pedidos/confirmar', [
                'items' => [['producto_id' => $producto->id, 'cantidad' => 5]],
            ])
            ->assertStatus(422);

        $this->assertSame(2, (int) $producto->fresh()->stock);
        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_una_linea_invalida_no_persiste_ninguna_linea(): void
    {
        $disponible = $this->crearProducto(stock: 10, precio: 4.00);
        $agotado = Producto::create($this->datosProducto(
            (int) $disponible->categoria_id,
            (int) $disponible->subcategoria_id,
            ['nombre' => 'Ibuprofeno 400 mg', 'stock' => 0, 'precio' => 6.00]
        ));

        $cliente = $this->crearCliente();

        try {
            app(VentaService::class)->confirmar((int) $cliente->id, [
                ['producto_id' => $disponible->id, 'cantidad' => 1],
                ['producto_id' => $agotado->id, 'cantidad' => 1],
            ]);
            $this->fail('La venta debio rechazarse por stock insuficiente.');
        } catch (ValidationException $e) {
            // Comportamiento esperado.
        }

        $this->assertSame(10, (int) $disponible->fresh()->stock);
        $this->assertDatabaseCount('pedidos', 0);
        $this->assertDatabaseCount('pedido_detalles', 0);
    }

    public function test_las_lineas_repetidas_del_mismo_producto_se_consolidan(): void
    {
        $producto = $this->crearProducto(stock: 5, precio: 2.00);
        $cliente = $this->crearCliente();

        $pedido = app(VentaService::class)->confirmar((int) $cliente->id, [
            ['producto_id' => $producto->id, 'cantidad' => 2],
            ['producto_id' => $producto->id, 'cantidad' => 2],
        ]);

        $this->assertSame(1, (int) $producto->fresh()->stock);
        $this->assertSame('8.00', (string) $pedido->total);
    }

    public function test_sin_token_la_confirmacion_de_venta_responde_401(): void
    {
        $this->postJson('/api/pedidos/confirmar', ['items' => []])->assertStatus(401);
    }
}
