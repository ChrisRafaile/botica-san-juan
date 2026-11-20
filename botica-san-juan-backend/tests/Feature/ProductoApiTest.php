<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_pos_explora_el_catalogo_por_categoria_sin_termino_y_devuelve_imagen(): void
    {
        $token = $this->tokenAdministrador();
        $analgesicos = Categoria::create(['nombre' => 'Analgésicos', 'slug' => 'analgesicos', 'descripcion' => 'Dolor']);
        $antibioticos = Categoria::create(['nombre' => 'Antibióticos', 'slug' => 'antibioticos', 'descripcion' => 'Infecciones']);

        Producto::create($this->datosDeProducto([
            'nombre' => 'PARACETAMOL 500 mg',
            'categoria_id' => $analgesicos->id,
            'imagen' => 'storage/productos/paracetamol.jpg',
        ]));
        Producto::create($this->datosDeProducto([
            'nombre' => 'AMOXICILINA 500 mg',
            'categoria_id' => $antibioticos->id,
        ]));

        $cabecera = ['Authorization' => "Bearer {$token}"];

        // Sin `explorar`, un término vacío sigue sin devolver nada.
        $this->withHeaders($cabecera)->getJson('/api/pos/productos')->assertOk()->assertJsonCount(0, 'data');

        $this->withHeaders($cabecera)->getJson('/api/pos/productos?explorar=1')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->withHeaders($cabecera)
            ->getJson("/api/pos/productos?explorar=1&categoria_id={$analgesicos->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'PARACETAMOL 500 mg')
            ->assertJsonPath('data.0.imagen', 'storage/productos/paracetamol.jpg')
            ->assertJsonPath('data.0.categoria_id', $analgesicos->id);
    }

    public function test_busqueda_en_productos_y_pos_ignora_tildes_y_mayusculas(): void
    {
        $amoxicilina = Producto::create($this->datosDeProducto([
            'nombre' => 'AMOXICILINA 500 mg',
            'principio_activo' => 'Amoxicilina trihidrato',
            'codigo_barras' => '7750000111222',
        ]));
        $aspirina = Producto::create($this->datosDeProducto([
            'nombre' => 'ÁCIDO ACETILSALICÍLICO 100 mg',
            'principio_activo' => 'Ácido acetilsalicílico',
            'codigo_barras' => '7750000333444',
        ]));

        // Búsqueda sin tildes y en minúsculas encuentra ambos casos reales
        $this->getJson('/api/productos?q=amoxicilina')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $amoxicilina->id);

        $this->getJson('/api/productos?q=acido%20acetilsalicilico')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $aspirina->id);

        // Búsqueda con caracteres especiales LIKE tratados literalmente
        $this->getJson('/api/productos?q=%25')
            ->assertOk()
            ->assertJsonPath('total', 0);

        // POS comparte el mismo scope sin tildes y mantiene código de barras exacto
        $token = $this->tokenAdministrador();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pos/productos?q=acido')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $aspirina->id);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pos/productos?q=7750000111222')
            ->assertOk()
            ->assertJsonPath('data.0.id', $amoxicilina->id);
    }

    public function test_el_orden_destacados_prioriza_disponibles_fefo_con_foto_real(): void
    {
        // Stock contable alto pero lote vencido: debe ir al bloque final (no disponible por FEFO)
        $conStockSoloVencido = Producto::create($this->datosDeProducto([
            'nombre' => 'AAA Vencido Con Stock Contable',
            'stock' => 99,
            'imagen' => 'storage/productos/1001.webp',
        ]));
        Lote::create([
            'producto_id' => $conStockSoloVencido->id,
            'codigo_lote' => 'LOT-VENC-1',
            'fecha_vencimiento' => now()->subDays(5),
            'cantidad_inicial' => 99,
            'cantidad_actual' => 99,
            'estado' => 'activo',
        ]);

        // Disponible FEFO pero con ilustración referencial
        $disponibleSinFoto = Producto::create($this->datosDeProducto([
            'nombre' => 'BBB Disponible Referencial',
            'stock' => 5,
            'imagen' => 'images/default_image.png',
        ]));
        Lote::create([
            'producto_id' => $disponibleSinFoto->id,
            'codigo_lote' => 'LOT-OK-2',
            'fecha_vencimiento' => now()->addMonths(6),
            'cantidad_inicial' => 5,
            'cantidad_actual' => 5,
            'estado' => 'activo',
        ]);

        // Disponible FEFO con fotografía real: debe quedar primero aun empezando por ZZZ
        $disponibleConFoto = Producto::create($this->datosDeProducto([
            'nombre' => 'ZZZ Disponible Con Foto',
            'stock' => 10,
            'imagen' => 'storage/productos/2002.webp',
        ]));
        Lote::create([
            'producto_id' => $disponibleConFoto->id,
            'codigo_lote' => 'LOT-OK-1',
            'fecha_vencimiento' => now()->addMonths(8),
            'cantidad_inicial' => 10,
            'cantidad_actual' => 10,
            'estado' => 'activo',
        ]);

        $respuesta = $this->getJson('/api/productos?orden=destacados')->assertOk();
        $ids = array_column($respuesta->json('data'), 'id');

        $this->assertSame(
            [$disponibleConFoto->id, $disponibleSinFoto->id, $conStockSoloVencido->id],
            $ids
        );
    }

    public function test_el_administrador_puede_subir_reemplazar_y_quitar_imagen_de_producto(): void
    {
        Storage::fake('public');
        $token = $this->tokenAdministrador();
        $archivo = UploadedFile::fake()->image('caja.webp', 600, 600);

        $creado = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/productos', $this->datosDeProducto(['imagen' => $archivo]))
            ->assertCreated();

        $rutaInicial = $creado->json('imagen');
        $this->assertStringStartsWith('storage/productos/', $rutaInicial);
        Storage::disk('public')->assertExists(substr($rutaInicial, strlen('storage/')));

        // Edición multipart mediante POST + _method=PUT
        $nuevaImagen = UploadedFile::fake()->image('nueva.jpg', 500, 500);
        $actualizado = $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/productos/{$creado->json('id')}", [
                '_method' => 'PUT',
                'nombre' => 'Paracetamol Actualizado',
                'imagen' => $nuevaImagen,
            ])
            ->assertOk();

        $rutaNueva = $actualizado->json('imagen');
        $this->assertNotSame($rutaInicial, $rutaNueva);
        Storage::disk('public')->assertMissing(substr($rutaInicial, strlen('storage/')));
        Storage::disk('public')->assertExists(substr($rutaNueva, strlen('storage/')));

        // Quitar imagen limpia el archivo huérfano y restablece la marca por defecto
        $sinImagen = $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/productos/{$creado->json('id')}", [
                '_method' => 'PUT',
                'quitar_imagen' => '1',
            ])
            ->assertOk();

        $this->assertSame('images/default_image.png', $sinImagen->json('imagen'));
        Storage::disk('public')->assertMissing(substr($rutaNueva, strlen('storage/')));
    }

    public function test_solo_el_administrador_puede_crear_o_actualizar_productos(): void
    {
        $producto = Producto::create($this->datosDeProducto());

        $this->postJson('/api/productos', $this->datosDeProducto())
            ->assertUnauthorized();

        $clienteToken = $this->crearCliente()->createToken('cliente')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$clienteToken}")
            ->postJson('/api/productos', $this->datosDeProducto())
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$clienteToken}")
            ->putJson("/api/productos/{$producto->id}", ['nombre' => 'No Permitido'])
            ->assertForbidden();
    }

    public function test_rechaza_imagenes_invalidas_muy_pesadas_o_de_dimensiones_excesivas(): void
    {
        Storage::fake('public');
        $producto = Producto::create($this->datosDeProducto());
        $adminToken = $this->tokenAdministrador();

        // Formato no permitido
        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson('/api/productos', $this->datosDeProducto([
                'imagen' => UploadedFile::fake()->create('archivo.txt', 20, 'text/plain'),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('imagen');

        // Peso mayor a 2 MB
        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson('/api/productos', $this->datosDeProducto([
                'imagen' => UploadedFile::fake()->create('pesada.jpg', 2500, 'image/jpeg'),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('imagen');

        // Dimensiones mayores a 4000 px
        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson("/api/productos/{$producto->id}", [
                '_method' => 'PUT',
                'imagen' => UploadedFile::fake()->image('gigante.png', 4100, 10),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('imagen');
    }
}
