<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Notifications\VerificarCorreo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Verificacion del correo de la cuenta.
 *
 * Lo que estas pruebas protegen no es que el correo se envie —eso lo ve
 * cualquiera— sino los bordes donde la verificacion deja de significar algo:
 *
 *   1. Que un enlace sin firma, o con la firma alterada, no verifique nada.
 *   2. Que el enlace deje de valer si el usuario cambia de correo.
 *   3. Que cambiar el correo desde el perfil RETIRE la verificacion anterior.
 *   4. Que un fallo del servicio de correo no tumbe el registro.
 */
class CorreoVerificacionTest extends TestCase
{
    use RefreshDatabase;

    private function cliente(array $extra = []): Usuario
    {
        return Usuario::create(array_merge([
            'nombre' => 'Cliente Correo',
            'dni' => '10000031',
            'email' => 'cliente.correo@prueba.test',
            'password' => Hash::make('clave-de-prueba'),
            'rol' => 'cliente',
        ], $extra));
    }

    /** Direccion del portal a la que redirige la API con cada resultado. */
    private function portal(string $resultado): string
    {
        return config('app.frontend_url').'/verificar-correo?resultado='.$resultado;
    }

    /** Enlace firmado tal como lo construye la notificacion. */
    private function enlaceFirmado(Usuario $usuario, ?string $correoParaElHash = null): string
    {
        return URL::temporarySignedRoute('verificacion.verificar', now()->addHour(), [
            'id' => $usuario->id,
            'hash' => sha1($correoParaElHash ?? $usuario->email),
        ]);
    }

    // ------------------------------------------------------------ registro

    public function test_el_registro_deja_la_cuenta_sin_verificar_y_manda_el_correo(): void
    {
        Notification::fake();

        $respuesta = $this->postJson('/api/register', [
            'nombre' => 'Nueva Clienta',
            'dni' => '10000032',
            'email' => 'nueva.clienta@prueba.test',
            'password' => 'clave-de-prueba',
            'acepta_terminos' => true,
        ]);

        $respuesta->assertCreated()->assertJsonPath('verificacion_enviada', true);

        $usuario = Usuario::where('email', 'nueva.clienta@prueba.test')->firstOrFail();

        $this->assertNull($usuario->email_verified_at, 'Una cuenta recien creada no puede nacer verificada.');
        Notification::assertSentTo($usuario, VerificarCorreo::class);
    }

    public function test_un_fallo_del_servicio_de_correo_no_tumba_el_registro(): void
    {
        /* Si el envio revienta, la cuenta ya existe en la base. Devolver 500
           dejaria al cliente creyendo que su registro fallo, con una
           contrasena que no volvera a ver. */
        Notification::fake();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('servicio caido'));

        $respuesta = $this->postJson('/api/register', [
            'nombre' => 'Clienta Sin Correo',
            'dni' => '10000033',
            'email' => 'sin.correo@prueba.test',
            'password' => 'clave-de-prueba',
            'acepta_terminos' => true,
        ]);

        $respuesta->assertCreated()->assertJsonPath('verificacion_enviada', false);
        $this->assertDatabaseHas('usuarios', ['email' => 'sin.correo@prueba.test']);
    }

    // ------------------------------------------------------- el enlace vale

    public function test_el_enlace_firmado_verifica_la_cuenta(): void
    {
        $usuario = $this->cliente(['email' => 'verifica@prueba.test']);
        $usuario->forceFill(['email_verified_at' => null])->save();

        $this->get($this->enlaceFirmado($usuario))
            ->assertRedirect($this->portal('confirmado'));

        $this->assertNotNull($usuario->fresh()->email_verified_at);
    }

    public function test_pulsar_el_enlace_dos_veces_no_es_un_error(): void
    {
        $usuario = $this->cliente(['email' => 'dos.veces@prueba.test']);
        $usuario->forceFill(['email_verified_at' => null])->save();

        $enlace = $this->enlaceFirmado($usuario);

        $this->get($enlace)->assertRedirect($this->portal('confirmado'));

        /* El cliente que vuelve a pulsar el enlace no ha hecho nada mal: su
           correo esta confirmado. Un 4xx obligaria al portal a ensenarle un
           error por un exito. */
        $this->get($enlace)->assertRedirect($this->portal('ya-confirmado'));
    }

    // ------------------------------------------------------ el enlace no vale

    public function test_sin_firma_no_se_verifica_nada(): void
    {
        $usuario = $this->cliente(['email' => 'sin.firma@prueba.test']);
        $usuario->forceFill(['email_verified_at' => null])->save();

        $this->get(sprintf('/api/email/verificar/%d/%s', $usuario->id, sha1($usuario->email)))
            ->assertRedirect($this->portal('invalido'));

        $this->assertNull($usuario->fresh()->email_verified_at);
    }

    public function test_una_firma_manipulada_no_se_verifica(): void
    {
        $usuario = $this->cliente(['email' => 'manipulada@prueba.test']);
        $usuario->forceFill(['email_verified_at' => null])->save();

        $roto = substr($this->enlaceFirmado($usuario), 0, -4).'0000';

        $this->get($roto)->assertRedirect($this->portal('invalido'));
        $this->assertNull($usuario->fresh()->email_verified_at);
    }

    public function test_un_enlace_emitido_para_otro_correo_no_vale(): void
    {
        /* Caso real: la persona pide la verificacion, luego cambia su correo
           en el perfil, y despues pulsa el enlace viejo. Ese enlace acredita
           una direccion que ya no es la de la cuenta. */
        $usuario = $this->cliente(['email' => 'nuevo@prueba.test']);
        $usuario->forceFill(['email_verified_at' => null])->save();

        $enlaceViejo = $this->enlaceFirmado($usuario, 'anterior@prueba.test');

        $this->get($enlaceViejo)->assertRedirect($this->portal('invalido'));

        $this->assertNull($usuario->fresh()->email_verified_at);
    }

    public function test_un_enlace_caducado_no_vale(): void
    {
        $usuario = $this->cliente(['email' => 'caducado@prueba.test']);
        $usuario->forceFill(['email_verified_at' => null])->save();

        $enlace = URL::temporarySignedRoute('verificacion.verificar', now()->subMinute(), [
            'id' => $usuario->id,
            'hash' => sha1($usuario->email),
        ]);

        $this->get($enlace)->assertRedirect($this->portal('invalido'));
        $this->assertNull($usuario->fresh()->email_verified_at);
    }

    // ------------------------------------------------------------- reenvio

    public function test_el_reenvio_exige_sesion(): void
    {
        /* Si aceptara un correo arbitrario, seria un motor de envio gratuito
           contra terceros y delataria que cuentas existen. */
        $this->postJson('/api/email/verificacion/reenviar')->assertUnauthorized();
    }

    public function test_el_reenvio_manda_el_correo_a_la_cuenta_en_sesion(): void
    {
        Notification::fake();

        $usuario = $this->cliente(['email' => 'reenvio@prueba.test']);
        $usuario->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($usuario)
            ->postJson('/api/email/verificacion/reenviar')
            ->assertOk()
            ->assertJsonPath('enviado', true);

        Notification::assertSentTo($usuario, VerificarCorreo::class);
    }

    public function test_no_se_reenvia_a_quien_ya_esta_verificado(): void
    {
        Notification::fake();

        $usuario = $this->cliente(['email' => 'ya.verificado@prueba.test']);
        $usuario->markEmailAsVerified();

        $this->actingAs($usuario)
            ->postJson('/api/email/verificacion/reenviar')
            ->assertOk()
            ->assertJsonPath('enviado', false);

        Notification::assertNothingSent();
    }

    // ------------------------------------------- cambiar el correo del perfil

    public function test_cambiar_el_correo_retira_la_verificacion_anterior(): void
    {
        Notification::fake();

        $usuario = $this->cliente(['email' => 'antes@prueba.test']);
        $usuario->markEmailAsVerified();

        $this->actingAs($usuario)
            ->putJson('/api/perfil', ['email' => 'despues@prueba.test'])
            ->assertOk();

        /* Sin esto, bastaria registrarse con un correo propio, confirmarlo y
           apuntar luego la cuenta a una direccion que nadie ha comprobado,
           conservando el sello de verificada. */
        $this->assertNull($usuario->fresh()->email_verified_at);
        Notification::assertSentTo($usuario, VerificarCorreo::class);
    }

    public function test_cambiar_solo_las_mayusculas_no_obliga_a_reverificar(): void
    {
        Notification::fake();

        $usuario = $this->cliente(['email' => 'mayusculas@prueba.test']);
        $usuario->markEmailAsVerified();

        $this->actingAs($usuario)
            ->putJson('/api/perfil', ['email' => 'Mayusculas@Prueba.test'])
            ->assertOk();

        // Es el mismo buzon. Pedir una verificacion nueva seria molestar por nada.
        $this->assertNotNull($usuario->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }
}
