<?php

namespace Tests\Feature;

use App\Mail\BienvenidaCliente;
use App\Mail\CompraRegistrada;
use App\Models\EventoOperacion;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Notifications\RestablecerContrasena;
use App\Notifications\VerificarCorreo;
use App\Services\Correo\CorreoTransaccional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as UsuarioSocialite;
use Mockery;
use Tests\TestCase;

class CorreosTransaccionalesTest extends TestCase
{
    use RefreshDatabase;

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

    private function cliente(array $extra = []): Usuario
    {
        return Usuario::create(array_merge([
            'nombre' => 'Lucía Paredes',
            'dni' => '45678912',
            'telefono' => '987654321',
            'email' => 'lucia.paredes@prueba.test',
            'password' => Hash::make('clave-segura-123'),
            'rol' => 'cliente',
        ], $extra));
    }

    private function productoConLote(string $nombre = 'AMOXICILINA 500 MG', float $precio = 18.50, int $unidades = 10): Producto
    {
        $producto = Producto::create([
            'nombre' => $nombre,
            'concentracion' => '500 mg',
            'presentacion' => 'Caja x 12',
            'laboratorio' => 'LAB PRUEBA',
            'tipo' => 'CAPSULA',
            'stock' => $unidades,
            'precio' => $precio,
        ]);

        Lote::create([
            'producto_id' => $producto->id,
            'codigo_lote' => 'L-'.uniqid(),
            'cantidad_inicial' => $unidades,
            'cantidad_actual' => $unidades,
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'estado' => 'activo',
        ]);

        return $producto;
    }

    private function fingirGoogle(string $id, string $correo, string $nombre = 'Rosa Quispe'): void
    {
        $cuenta = new UsuarioSocialite;
        $cuenta->map(['id' => $id, 'email' => $correo, 'name' => $nombre]);
        $cuenta->setRaw(['email_verified' => true]);

        $proveedor = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $proveedor->shouldReceive('stateless')->andReturnSelf();
        $proveedor->shouldReceive('user')->andReturn($cuenta);

        Socialite::shouldReceive('driver')->with('google')->andReturn($proveedor);
    }

    private function stateGoogle(): string
    {
        $state = str_repeat('b', 40);
        Cache::put('google:state:'.hash('sha256', $state), '127.0.0.1', 300);

        return $state;
    }

    // --------------------------------------------------------- bienvenida

    public function test_el_registro_envia_bienvenida_una_sola_vez(): void
    {
        Notification::fake();
        Mail::fake();

        $respuesta = $this->postJson('/api/register', [
            'nombre' => 'Camila Torres',
            'dni' => '71234567',
            'email' => 'camila.torres@prueba.test',
            'password' => 'clave-segura-123',
            'acepta_terminos' => true,
        ]);

        $respuesta->assertCreated()->assertJsonPath('verificacion_enviada', true);

        $usuario = Usuario::where('email', 'camila.torres@prueba.test')->firstOrFail();

        Notification::assertSentTo($usuario, VerificarCorreo::class);
        Mail::assertSent(BienvenidaCliente::class, 1);
        Mail::assertSent(BienvenidaCliente::class, fn (BienvenidaCliente $mail) => $mail->hasTo('camila.torres@prueba.test'));
    }

    public function test_un_fallo_del_mailer_no_tumba_el_registro_y_deja_evento_sin_el_correo(): void
    {
        Notification::fake();
        Mail::shouldReceive('to')
            ->andThrow(new \RuntimeException('Resend rechazo camila.fallo@prueba.test por cuota'));

        $respuesta = $this->postJson('/api/register', [
            'nombre' => 'Camila Fallo',
            'dni' => '71234568',
            'email' => 'camila.fallo@prueba.test',
            'password' => 'clave-segura-123',
            'acepta_terminos' => true,
        ]);

        $respuesta->assertCreated()->assertJsonPath('verificacion_enviada', true);
        $this->assertDatabaseHas('usuarios', ['email' => 'camila.fallo@prueba.test']);

        $evento = EventoOperacion::where('codigo', EventoOperacion::CORREO_FALLIDO)->first();
        $this->assertNotNull($evento);
        $this->assertSame('bienvenida', $evento->contexto['tipo'] ?? null);
        $this->assertSame(\RuntimeException::class, $evento->contexto['excepcion'] ?? null);

        $serializado = json_encode($evento->toArray());
        $this->assertStringNotContainsString('camila.fallo@prueba.test', (string) $serializado);
    }

    public function test_google_envia_bienvenida_solo_en_alta_nueva_y_no_en_vinculacion_ni_login(): void
    {
        Mail::fake();

        // 1. Alta nueva con Google -> sí envía bienvenida
        $this->fingirGoogle('google-nuevo-1', 'nueva.google@prueba.test', 'Rosa Quispe');
        $this->get('/api/auth/google/callback?state='.$this->stateGoogle().'&code=c1')
            ->assertRedirect();

        Mail::assertSent(BienvenidaCliente::class, 1);
        Mail::assertSent(BienvenidaCliente::class, fn (BienvenidaCliente $mail) => $mail->hasTo('nueva.google@prueba.test'));

        // 2. Login posterior con el mismo Google ID -> no vuelve a enviar
        $this->fingirGoogle('google-nuevo-1', 'nueva.google@prueba.test', 'Rosa Quispe');
        $this->get('/api/auth/google/callback?state='.$this->stateGoogle().'&code=c2')
            ->assertRedirect();

        Mail::assertSent(BienvenidaCliente::class, 1);

        // 3. Vinculación de cuenta existente por correo -> no envía bienvenida
        $this->cliente([
            'dni' => '72345678',
            'email' => 'existente.sin.google@prueba.test',
        ]);
        $this->fingirGoogle('google-vinc-2', 'existente.sin.google@prueba.test', 'Lucía Paredes');
        $this->get('/api/auth/google/callback?state='.$this->stateGoogle().'&code=c3')
            ->assertRedirect();

        Mail::assertSent(BienvenidaCliente::class, 1);
    }

    // --------------------------------------------------- confirmacion compra

    public function test_compra_con_sesion_envia_correo_con_resumen_y_sin_datos_sensibles(): void
    {
        Mail::fake();

        $producto = $this->productoConLote('PARACETAMOL 500 MG', 12.50, 10);
        $cliente = $this->cliente([
            'dni' => '45678912',
            'telefono' => '999888777',
            'email' => 'compradora@prueba.test',
        ]);
        Sanctum::actingAs($cliente);

        $respuesta = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 2]],
            'modalidad_entrega' => 'entrega',
            'direccion' => 'Av. Los Próceres 450, San Juan de Lurigancho',
        ]);

        $respuesta->assertStatus(201);
        $pedidoId = $respuesta->json('pedido.id');
        $identificador = $respuesta->json('comprobante.identificador');

        Mail::assertSent(CompraRegistrada::class, 1);
        Mail::assertSent(CompraRegistrada::class, function (CompraRegistrada $mail) use ($pedidoId, $identificador) {
            $html = $mail->render();

            $this->assertTrue($mail->hasTo('compradora@prueba.test'));
            $this->assertStringContainsString("#{$pedidoId}", $html);
            $this->assertStringContainsString($identificador, $html);
            $this->assertStringContainsString('PARACETAMOL 500 MG', $html);
            $this->assertStringContainsString('S/ 25.00', $html);
            $this->assertStringContainsString('Entrega a domicilio', $html);
            $this->assertStringContainsString('pendiente', $html);

            // Privacidad: sin DNI, teléfono ni dirección
            $this->assertStringNotContainsString('45678912', $html);
            $this->assertStringNotContainsString('999888777', $html);
            $this->assertStringNotContainsString('Av. Los Próceres 450', $html);

            return true;
        });
    }

    public function test_compra_como_invitado_no_envia_correo(): void
    {
        Mail::fake();

        $producto = $this->productoConLote('IBUPROFENO 400 MG', 10.00, 10);

        $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
            'cliente_nombre' => 'Invitado Sin Correo',
            'cliente_documento' => '12345678',
            'cliente_telefono' => '911222333',
        ])->assertStatus(201);

        Mail::assertNothingSent();
    }

    public function test_compra_con_stock_insuficiente_no_envia_correo(): void
    {
        Mail::fake();

        $producto = $this->productoConLote('AZITROMICINA 500 MG', 25.00, 1);
        $cliente = $this->cliente(['email' => 'sin.stock@prueba.test']);
        Sanctum::actingAs($cliente);

        $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 5]],
        ])->assertStatus(409);

        Mail::assertNothingSent();
    }

    public function test_fallo_al_enviar_correo_de_compra_mantiene_201_pedido_y_stock(): void
    {
        Mail::shouldReceive('to')
            ->andThrow(new \RuntimeException('Error de Resend para falla.compra@prueba.test'));

        $producto = $this->productoConLote('NAPROXENO 550 MG', 15.00, 8);
        $cliente = $this->cliente(['email' => 'falla.compra@prueba.test']);
        Sanctum::actingAs($cliente);

        $respuesta = $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 3]],
        ]);

        $respuesta->assertStatus(201);
        $pedidoId = $respuesta->json('pedido.id');

        $this->assertDatabaseHas('pedidos', ['id' => $pedidoId, 'usuario_id' => $cliente->id]);
        $this->assertSame(5, Lote::where('producto_id', $producto->id)->firstOrFail()->cantidad_actual);

        $evento = EventoOperacion::where('codigo', EventoOperacion::CORREO_FALLIDO)
            ->where('pedido_id', $pedidoId)
            ->first();
        $this->assertNotNull($evento);
        $this->assertStringNotContainsString('falla.compra@prueba.test', (string) json_encode($evento->toArray()));
    }

    // --------------------------------------------------------- recuperacion

    public function test_recuperacion_registra_envio_cuando_existe_cuenta_y_nada_cuando_no_existe(): void
    {
        Notification::fake();

        $usuario = $this->cliente(['email' => 'recupera.ok@prueba.test']);

        $this->postJson('/api/password/olvide', ['email' => 'no.existe@prueba.test'])->assertOk();
        $this->assertSame(0, EventoOperacion::count());

        $this->postJson('/api/password/olvide', ['email' => 'recupera.ok@prueba.test'])->assertOk();
        Notification::assertSentTo($usuario, RestablecerContrasena::class);

        $this->assertDatabaseHas('eventos_operacion', [
            'codigo' => EventoOperacion::CORREO_ENVIADO,
            'usuario_id' => $usuario->id,
        ]);
    }

    public function test_recuperacion_responde_byte_a_byte_identico_cuando_el_transporte_falla(): void
    {
        Notification::fake();
        Notification::shouldReceive('send')
            ->andThrow(new \RuntimeException('Resend caido para falla.rec@prueba.test'));

        $this->cliente(['email' => 'falla.rec@prueba.test']);

        $conCuentaFallida = $this->postJson('/api/password/olvide', ['email' => 'falla.rec@prueba.test']);
        $sinCuenta = $this->postJson('/api/password/olvide', ['email' => 'inexistente@prueba.test']);

        $conCuentaFallida->assertOk();
        $sinCuenta->assertOk();

        $this->assertSame($conCuentaFallida->getStatusCode(), $sinCuenta->getStatusCode());
        $this->assertSame($conCuentaFallida->getContent(), $sinCuenta->getContent());

        $this->assertDatabaseHas('eventos_operacion', [
            'codigo' => EventoOperacion::CORREO_FALLIDO,
        ]);
    }

    public function test_reenviar_verificacion_no_lanza_500_cuando_falla_el_transporte(): void
    {
        Notification::fake();
        Notification::shouldReceive('send')
            ->andThrow(new \RuntimeException('Fallo temporal de Resend'));

        $usuario = $this->cliente(['email' => 'reenvio.falla@prueba.test']);
        $usuario->forceFill(['email_verified_at' => null])->save();
        Sanctum::actingAs($usuario);

        $respuesta = $this->postJson('/api/email/verificacion/reenviar');

        $respuesta->assertOk()
            ->assertJsonPath('enviado', false);
        $this->assertStringContainsString('No pudimos enviar', (string) $respuesta->json('message'));
    }

    public function test_una_solicitud_limitada_por_el_broker_no_se_anota_como_enviada(): void
    {
        Notification::fake();
        $this->cliente(['email' => 'repite@prueba.test']);

        $this->postJson('/api/password/olvide', ['email' => 'repite@prueba.test'])->assertOk();
        $this->postJson('/api/password/olvide', ['email' => 'repite@prueba.test'])->assertOk();

        $this->assertSame(1, EventoOperacion::where('codigo', EventoOperacion::CORREO_ENVIADO)->count());
        $this->assertSame(0, EventoOperacion::where('codigo', EventoOperacion::CORREO_FALLIDO)->count());
    }

    public function test_el_correo_no_agrega_los_detalles_a_la_respuesta_de_confirmar(): void
    {
        Mail::fake();
        $producto = $this->productoConLote('LORATADINA 10 MG', 6.00, 10);
        Sanctum::actingAs($this->cliente());

        $this->postJson('/api/pedidos/confirmar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertStatus(201)->assertJsonMissingPath('pedido.pedido_detalles.0.producto.stock');
    }

    public function test_el_cliente_de_resend_tiene_tiempo_de_espera(): void
    {
        config(['services.resend.key' => 're_clave_ficticia']);

        $cliente = app('resend');
        $leer = fn (object $o, string $p) => (new \ReflectionProperty($o, $p))->getValue($o);
        $guzzle = $leer($leer($cliente, 'transporter'), 'client');

        $this->assertSame(8, $guzzle->getConfig('timeout'));
        $this->assertSame(3, $guzzle->getConfig('connect_timeout'));
    }

    // --------------------------------------------------- guardas produccion

    public function test_produccion_con_mailer_log_registra_transporte_invalido_y_devuelve_false(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'mail.default' => 'log',
            'services.resend.key' => 're_clave_ficticia',
        ]);

        $enviador = app(CorreoTransaccional::class);
        $ejecutado = false;

        $resultado = $enviador->enviar('bienvenida', function () use (&$ejecutado) {
            $ejecutado = true;
        });

        $this->assertFalse($resultado);
        $this->assertFalse($ejecutado);
        $this->assertDatabaseHas('eventos_operacion', [
            'codigo' => EventoOperacion::CORREO_TRANSPORTE_INVALIDO,
            'nivel' => EventoOperacion::NIVEL_ERROR,
        ]);
    }

    public function test_produccion_con_resend_sin_clave_registra_transporte_invalido_y_devuelve_false(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'mail.default' => 'resend',
            'services.resend.key' => '',
        ]);

        $enviador = app(CorreoTransaccional::class);
        $ejecutado = false;

        $resultado = $enviador->enviar('compra_registrada', function () use (&$ejecutado) {
            $ejecutado = true;
        });

        $this->assertFalse($resultado);
        $this->assertFalse($ejecutado);
        $this->assertDatabaseHas('eventos_operacion', [
            'codigo' => EventoOperacion::CORREO_TRANSPORTE_INVALIDO,
            'nivel' => EventoOperacion::NIVEL_ERROR,
        ]);
    }
}
