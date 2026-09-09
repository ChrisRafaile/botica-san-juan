<?php

namespace Tests\Feature;

use App\Models\Categoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre el RF-02 (roles que determinan las funcionalidades accesibles).
 *
 * Verifica el criterio de aceptacion declarado en el checklist de puesta en
 * produccion: las mutaciones administrativas responden 200 con token de
 * administrador, 403 con token de cliente y 401 sin token.
 */
class AutorizacionRolTest extends TestCase
{
    use RefreshDatabase;

    /** Rutas de mutacion que solo debe alcanzar un administrador. */
    public static function rutasAdministrativas(): array
    {
        return [
            'crear producto' => ['post', '/api/productos'],
            'crear categoria' => ['post', '/api/categorias'],
            'crear subcategoria' => ['post', '/api/subcategorias'],
            'crear proveedor' => ['post', '/api/proveedores'],
            'crear compra' => ['post', '/api/compras'],
            'listar usuarios' => ['get', '/api/usuarios'],
        ];
    }

    /**
     * @dataProvider rutasAdministrativas
     */
    public function test_sin_token_las_rutas_administrativas_devuelven_401(string $metodo, string $ruta): void
    {
        $this->json($metodo, $ruta)->assertStatus(401);
    }

    /**
     * @dataProvider rutasAdministrativas
     */
    public function test_con_rol_cliente_las_rutas_administrativas_devuelven_403(string $metodo, string $ruta): void
    {
        $cliente = $this->crearCliente();
        $token = $cliente->createToken('prueba')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->json($metodo, $ruta)
            ->assertStatus(403);
    }

    public function test_un_administrador_puede_crear_una_categoria(): void
    {
        $admin = $this->crearAdministrador();
        $token = $admin->createToken('prueba')->plainTextToken;

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/categorias', [
                'nombre' => 'Dermocosmetica',
                'descripcion' => 'Linea de cuidado de la piel.',
            ]);

        $respuesta->assertSuccessful();
        $this->assertDatabaseHas('categorias', ['nombre' => 'Dermocosmetica']);
    }

    public function test_un_cliente_no_puede_eliminar_una_categoria(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Medicamentos',
            'slug' => 'medicamentos',
            'descripcion' => 'Categoria de prueba',
        ]);

        $cliente = $this->crearCliente();
        $token = $cliente->createToken('prueba')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/categorias/{$categoria->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
    }

    public function test_el_catalogo_de_lectura_es_publico(): void
    {
        $this->getJson('/api/productos')->assertSuccessful();
        $this->getJson('/api/categorias')->assertSuccessful();
    }

    /**
     * Regresion: una peticion sin la cabecera Accept: application/json hacia una
     * ruta protegida provocaba que Laravel intentara redirigir a la ruta nombrada
     * 'login' (inexistente en una API) y devolviera 500 en lugar de 401.
     */
    public function test_sin_cabecera_json_una_ruta_protegida_responde_401_y_no_500(): void
    {
        $respuesta = $this->get('/api/usuarios', ['Accept' => 'text/html']);

        $respuesta->assertStatus(401);
        $this->assertJson($respuesta->getContent());
    }
}
