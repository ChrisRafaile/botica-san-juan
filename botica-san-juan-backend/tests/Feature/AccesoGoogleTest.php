<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as UsuarioSocialite;
use Mockery;
use Tests\TestCase;

/**
 * Acceso con Google.
 *
 * Lo que de verdad hay que demostrar aquí no es que se pueda entrar —eso lo
 * hace Google— sino las cinco cosas que impiden que este botón sea una puerta
 * trasera:
 *
 *   1. Que el token de sesión NO viaje en la URL.
 *   2. Que el código de intercambio sirva una sola vez y caduque.
 *   3. Que un retorno sin `state` válido no cree ninguna sesión.
 *   4. Que un correo sin verificar no vincule nada.
 *   5. Que entrar con Google no ascienda a nadie ni cambie su contraseña.
 */
class AccesoGoogleTest extends TestCase
{
    use RefreshDatabase;

    private int $dni = 30000000;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'id-de-prueba',
            'services.google.client_secret' => 'secreto-de-prueba',
            'services.google.redirect' => 'http://localhost/api/auth/google/callback',
            'app.frontend_url' => 'http://localhost:5173',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function usuario(array $extra = []): Usuario
    {
        $this->dni++;

        return Usuario::create(array_merge([
            'nombre' => 'Persona '.$this->dni,
            'dni' => (string) $this->dni,
            'email' => "persona{$this->dni}@prueba.test",
            'password' => Hash::make('clave-de-prueba'),
            'rol' => 'cliente',
        ], $extra));
    }

    /** Sustituye a Google por una cuenta de mentira con los datos que se quiera. */
    private function fingirGoogle(string $id, string $correo, string $nombre = 'Rosa Quispe', bool $verificado = true): void
    {
        $cuenta = new UsuarioSocialite;
        $cuenta->map(['id' => $id, 'email' => $correo, 'name' => $nombre]);
        $cuenta->setRaw(['email_verified' => $verificado]);

        $proveedor = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $proveedor->shouldReceive('stateless')->andReturnSelf();
        $proveedor->shouldReceive('user')->andReturn($cuenta);

        Socialite::shouldReceive('driver')->with('google')->andReturn($proveedor);
    }

    /**
     * Producto con existencias REALES.
     *
     * `productos.stock` no basta: la venta recorre los lotes por FEFO y la
     * fuente de verdad es `lotes.cantidad_actual`. Sin lote, el checkout
     * responde «no hay stock» aunque la columna diga 50.
     */
    private function productoVendible(string $nombre): Producto
    {
        $producto = Producto::create([
            'nombre' => $nombre, 'concentracion' => '500 mg',
            'laboratorio' => 'Lab', 'presentacion' => 'Caja', 'tipo' => 'medicamento',
            'categoria' => 'General', 'precio' => 10, 'stock' => 50,
        ]);

        Lote::create([
            'producto_id' => $producto->id,
            'codigo_lote' => 'L-'.uniqid(),
            'cantidad_inicial' => 50,
            'cantidad_actual' => 50,
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'estado' => 'activo',
        ]);

        return $producto;
    }

    /** Deja preparado un `state` válido, como si la ida hubiera ocurrido. */
    private function stateValido(): string
    {
        $state = str_repeat('a', 40);
        Cache::put('google:state:'.hash('sha256', $state), '127.0.0.1', 300);

        return $state;
    }

    private function volver(string $state): \Illuminate\Testing\TestResponse
    {
        return $this->get("/api/auth/google/callback?state={$state}&code=codigo-de-google");
    }

    /** Extrae el código del parámetro de la redirección al portal. */
    private function codigoDe(\Illuminate\Testing\TestResponse $respuesta): ?string
    {
        parse_str((string) parse_url((string) $respuesta->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query['codigo'] ?? null;
    }

    // ---------------------------------------------------------------- ida

    public function test_sin_credenciales_configuradas_el_acceso_no_se_ofrece(): void
    {
        config(['services.google.client_id' => null]);

        $this->getJson('/api/auth/google/redirect')->assertStatus(503);
    }

    public function test_la_url_de_google_se_entrega_con_un_state(): void
    {
        $respuesta = $this->getJson('/api/auth/google/redirect')->assertOk();

        $url = $respuesta->json('url');

        $this->assertStringStartsWith('https://accounts.google.com/', $url);
        $this->assertStringContainsString('state=', $url);
        /* `select_account` evita que Google reutilice en silencio la sesión
           abierta: en un equipo compartido eso es entrar como otra persona. */
        $this->assertStringContainsString('prompt=select_account', $url);
    }

    // -------------------------------------------------------------- vuelta

    public function test_el_token_no_viaja_en_la_url_de_retorno(): void
    {
        $this->fingirGoogle('g-1', 'rosa@gmail.com');

        $respuesta = $this->volver($this->stateValido());
        $destino = (string) $respuesta->headers->get('Location');

        /* Un token en la URL queda en el historial, en la cabecera Referer y
           en los registros de cualquier intermediario. Lo que viaja es un
           código de un solo uso. */
        $this->assertStringContainsString('codigo=', $destino);
        $this->assertStringNotContainsString('token=', $destino);

        $usuario = Usuario::where('email', 'rosa@gmail.com')->firstOrFail();
        $this->assertSame(0, $usuario->tokens()->count());
    }

    public function test_un_retorno_sin_state_no_crea_ninguna_cuenta(): void
    {
        $this->fingirGoogle('g-2', 'intruso@gmail.com');

        $this->get('/api/auth/google/callback?code=codigo-de-google')
            ->assertRedirectContains('error=state');

        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_un_state_inventado_tampoco_vale(): void
    {
        $this->fingirGoogle('g-3', 'intruso2@gmail.com');

        $this->volver(str_repeat('z', 40))->assertRedirectContains('error=state');

        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_el_state_sirve_una_sola_vez(): void
    {
        $this->fingirGoogle('g-4', 'rosa4@gmail.com');

        $state = $this->stateValido();
        $this->volver($state)->assertRedirectContains('codigo=');

        /* Reutilizarlo es la señal de un reenvío del retorno, no de una
           persona entrando. */
        $this->volver($state)->assertRedirectContains('error=state');
    }

    public function test_un_correo_sin_verificar_no_vincula_nada(): void
    {
        $existente = $this->usuario(['email' => 'victima@gmail.com']);
        $this->fingirGoogle('g-5', 'victima@gmail.com', verificado: false);

        $this->volver($this->stateValido())->assertRedirectContains('error=correo-sin-verificar');

        /* Si Google no acredita el control del buzón, vincular permitiría
           apropiarse de la cuenta de quien se registró con esa dirección. */
        $this->assertNull($existente->fresh()->google_id);
    }

    public function test_cancelar_en_google_no_es_un_error_del_sistema(): void
    {
        $this->get('/api/auth/google/callback?error=access_denied')
            ->assertRedirectContains('error=cancelado');
    }

    // ----------------------------------------------------- alta y vinculacion

    public function test_una_cuenta_nueva_nace_sin_dni_y_con_el_correo_verificado(): void
    {
        $this->fingirGoogle('g-6', 'nueva@gmail.com', 'Rosa Quispe Mamani');

        $this->volver($this->stateValido())->assertRedirectContains('codigo=');

        $usuario = Usuario::where('email', 'nueva@gmail.com')->firstOrFail();

        /* Google no sabe el DNI de nadie y no se inventa: iría a un
           comprobante tributario. */
        $this->assertNull($usuario->dni);
        $this->assertNotNull($usuario->email_verified_at);
        $this->assertSame('cliente', $usuario->rol);
        $this->assertSame('Rosa Quispe Mamani', $usuario->nombre);
    }

    public function test_se_vincula_a_la_cuenta_que_ya_existia_con_ese_correo(): void
    {
        $existente = $this->usuario(['email' => 'rosa7@gmail.com']);
        $claveAnterior = $existente->password;

        $this->fingirGoogle('g-7', 'rosa7@gmail.com');
        $this->volver($this->stateValido())->assertRedirectContains('codigo=');

        $fresco = $existente->fresh();

        $this->assertSame('g-7', $fresco->google_id);
        $this->assertNotNull($fresco->google_vinculado_en);
        /* Vincular no cambia la contraseña: quien entraba con DNI y clave
           sigue pudiendo. */
        $this->assertSame($claveAnterior, $fresco->password);
        $this->assertDatabaseCount('usuarios', 1);
    }

    public function test_entrar_con_google_no_asciende_a_nadie(): void
    {
        $cliente = $this->usuario(['email' => 'cliente8@gmail.com', 'rol' => 'cliente']);

        $this->fingirGoogle('g-8', 'cliente8@gmail.com');
        $this->volver($this->stateValido());

        $this->assertSame('cliente', $cliente->fresh()->rol);
    }

    public function test_un_correo_que_ya_cuelga_de_otra_cuenta_de_google_no_se_roba(): void
    {
        $this->usuario(['email' => 'compartido@gmail.com', 'google_id' => 'g-original']);

        $this->fingirGoogle('g-impostor', 'compartido@gmail.com');
        $this->volver($this->stateValido())->assertRedirectContains('error=cuenta-ocupada');

        $this->assertSame('g-original', Usuario::where('email', 'compartido@gmail.com')->value('google_id'));
    }

    public function test_manda_el_identificador_de_google_y_no_el_correo(): void
    {
        $usuario = $this->usuario(['email' => 'viejo@gmail.com', 'google_id' => 'g-10']);

        /* Una persona puede cambiar el correo de su cuenta de Google. El
           identificador no cambia, y por eso es el que vincula. */
        $this->fingirGoogle('g-10', 'nuevo@gmail.com');
        $this->volver($this->stateValido())->assertRedirectContains('codigo=');

        $this->assertSame('nuevo@gmail.com', $usuario->fresh()->email);
        $this->assertDatabaseCount('usuarios', 1);
    }

    // ------------------------------------------------------------ intercambio

    public function test_el_codigo_se_canjea_por_el_token(): void
    {
        $this->fingirGoogle('g-11', 'rosa11@gmail.com');
        $codigo = $this->codigoDe($this->volver($this->stateValido()));

        $respuesta = $this->postJson('/api/auth/google/intercambio', ['codigo' => $codigo])
            ->assertOk();

        $this->assertNotEmpty($respuesta->json('token'));
        $this->assertSame('rosa11@gmail.com', $respuesta->json('user.email'));
        $this->assertTrue($respuesta->json('necesita_dni'));
    }

    public function test_el_codigo_sirve_una_sola_vez(): void
    {
        $this->fingirGoogle('g-12', 'rosa12@gmail.com');
        $codigo = $this->codigoDe($this->volver($this->stateValido()));

        $this->postJson('/api/auth/google/intercambio', ['codigo' => $codigo])->assertOk();

        /* Un código del historial del navegador no puede servir para abrir
           una segunda sesión. */
        $this->postJson('/api/auth/google/intercambio', ['codigo' => $codigo])->assertStatus(422);
    }

    public function test_un_codigo_inventado_no_abre_sesion(): void
    {
        $this->postJson('/api/auth/google/intercambio', ['codigo' => str_repeat('x', 48)])
            ->assertStatus(422);
    }

    public function test_la_respuesta_no_filtra_el_identificador_de_google(): void
    {
        $this->fingirGoogle('g-13', 'rosa13@gmail.com');
        $codigo = $this->codigoDe($this->volver($this->stateValido()));

        $respuesta = $this->postJson('/api/auth/google/intercambio', ['codigo' => $codigo]);

        $this->assertStringNotContainsString('g-13', $respuesta->getContent());
        $this->assertTrue($respuesta->json('user.tiene_google'));
    }

    // --------------------------------------------- el hueco del DNI, cerrado

    public function test_el_checkout_pide_el_documento_a_quien_no_tiene_dni(): void
    {
        $this->fingirGoogle('g-14', 'rosa14@gmail.com');
        $this->volver($this->stateValido());

        $usuario = Usuario::where('email', 'rosa14@gmail.com')->firstOrFail();

        $producto = $this->productoVendible('PARACETAMOL 500 mg');

        /* Sin esto el pedido saldría con `cliente_documento` nulo: una boleta
           sin adquirente. */
        $this->actingAs($usuario)->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('cliente_documento');
    }

    public function test_quien_ya_tiene_dni_no_lo_vuelve_a_teclear(): void
    {
        $usuario = $this->usuario();

        $producto = $this->productoVendible('IBUPROFENO 400 mg');

        $respuesta = $this->actingAs($usuario)->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ]);

        $this->assertTrue($respuesta->isSuccessful(), 'Respuesta: '.$respuesta->getContent());
        $this->assertSame($usuario->dni, $respuesta->json('pedido.cliente_documento'));
    }

    public function test_el_dni_se_completa_una_vez_desde_el_perfil(): void
    {
        $this->fingirGoogle('g-15', 'rosa15@gmail.com');
        $this->volver($this->stateValido());

        $usuario = Usuario::where('email', 'rosa15@gmail.com')->firstOrFail();

        $this->actingAs($usuario)->putJson('/api/perfil', ['dni' => '47382910'])->assertOk();

        $this->assertSame('47382910', $usuario->fresh()->dni);
    }

    public function test_un_dni_ya_registrado_no_se_cambia_desde_el_perfil(): void
    {
        $usuario = $this->usuario();

        /* Ya figura en los comprobantes emitidos: corregirlo desde una
           pantalla de perfil es reescribir el pasado. */
        $this->actingAs($usuario)->putJson('/api/perfil', ['dni' => '11111111'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('dni');
    }

    public function test_no_se_puede_completar_con_el_dni_de_otra_persona(): void
    {
        $otra = $this->usuario();

        $this->fingirGoogle('g-16', 'rosa16@gmail.com');
        $this->volver($this->stateValido());
        $usuario = Usuario::where('email', 'rosa16@gmail.com')->firstOrFail();

        $this->actingAs($usuario)->putJson('/api/perfil', ['dni' => $otra->dni])
            ->assertStatus(422)
            ->assertJsonValidationErrors('dni');
    }
}
