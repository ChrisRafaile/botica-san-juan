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
     * Lecturas de gestion que tambien son solo del personal de la botica.
     *
     * Estaban abiertas a cualquier usuario autenticado. Un cliente registrado
     * podia consultar la venta del dia, el listado completo de pedidos con
     * nombres y documentos de otros clientes, los proveedores y las compras.
     * No era una mutacion, asi que el control por rol no las cubria.
     */
    public static function lecturasDeGestion(): array
    {
        return [
            'tablero'             => ['get', '/api/tablero'],
            'listado de pedidos'  => ['get', '/api/pedidos'],
            'resumen de pedidos'  => ['get', '/api/pedidos/resumen'],
            'resumen de stock'    => ['get', '/api/productos/resumen'],
            'reporte gerencial'   => ['get', '/api/reportes/gerencial'],
            'registro de ventas'  => ['get', '/api/reportes/registro-ventas'],
            'proveedores'         => ['get', '/api/proveedores'],
            'busqueda del punto de venta' => ['get', '/api/pos/productos?q=a'],
        ];
    }

    /**
     * @dataProvider lecturasDeGestion
     */
    public function test_con_rol_cliente_las_lecturas_de_gestion_devuelven_403(string $metodo, string $ruta): void
    {
        $token = $this->crearCliente()->createToken('prueba')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->json($metodo, $ruta)
            ->assertStatus(403);
    }

    /**
     * El formulario de contacto del portal es publico y su bandeja no.
     *
     * Exigir sesion para escribir a la botica deja fuera justo a quien
     * todavia no es cliente; dejar leer los mensajes a cualquiera expone
     * nombres, correos y consultas de terceros.
     */
    public function test_cualquiera_puede_escribir_a_la_botica_pero_no_leer_los_mensajes(): void
    {
        $this->postJson('/api/contacto', [
            'nombre'  => 'Visitante',
            'email'   => 'visitante@ejemplo.pe',
            'mensaje' => 'Quisiera saber si tienen un medicamento.',
        ])->assertStatus(201);

        $this->assertDatabaseHas('contacto', ['email' => 'visitante@ejemplo.pe']);

        /* Sin telefono ni motivo: son NOT NULL en la tabla y el servidor los
           rellena, porque quien escribe no siempre deja telefono. */

        $this->getJson('/api/contacto')->assertStatus(401);

        $cliente = $this->crearCliente();
        $this->withHeader('Authorization', 'Bearer ' . $cliente->createToken('prueba')->plainTextToken)
            ->getJson('/api/contacto')
            ->assertStatus(403);
    }

    public function test_un_cliente_no_puede_leer_los_pedidos_de_otro(): void
    {
        $propio = $this->crearCliente();
        /* Correo y DNI distintos: el ayudante usa valores fijos y crear dos
           clientes seguidos chocaba contra el indice unico. */
        $ajeno = $this->crearCliente([
            'email' => 'otro.cliente@boticasanjuan.pe',
            'dni'   => '87654321',
        ]);

        $token = $propio->createToken('prueba')->plainTextToken;

        /* El identificador viaja en la direccion. Antes bastaba con cambiarlo
           para leer el historial de compras de cualquier otra persona. */
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pedidos/usuario/{$ajeno->id}")
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pedidos/usuario/{$propio->id}")
            ->assertStatus(200);
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
