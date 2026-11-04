<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Notifications\RestablecerContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Recuperacion de contrasena.
 *
 * La prueba central de este archivo no es que el restablecimiento funcione,
 * sino que el formulario NO sirva para averiguar quien tiene cuenta en la
 * botica. Un verificador de correos construido sobre un formulario publico de
 * una farmacia dice algo mas que "esta direccion existe": dice que esa persona
 * compra medicamentos aqui.
 *
 * Por eso hay una prueba que compara las dos respuestas byte a byte.
 */
class RecuperacionContrasenaTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE_VIEJA = 'clave-vieja-123';

    private function cliente(array $extra = []): Usuario
    {
        return Usuario::create(array_merge([
            'nombre' => 'Cliente Recupera',
            'dni' => '10000041',
            'email' => 'recupera@prueba.test',
            'password' => Hash::make(self::CLAVE_VIEJA),
            'rol' => 'cliente',
        ], $extra));
    }

    // ------------------------------------------------- no hay enumeracion

    public function test_la_respuesta_es_identica_exista_o_no_la_cuenta(): void
    {
        Notification::fake();

        $this->cliente(['email' => 'existe@prueba.test']);

        $conCuenta = $this->postJson('/api/password/olvide', ['email' => 'existe@prueba.test']);
        $sinCuenta = $this->postJson('/api/password/olvide', ['email' => 'no.existe@prueba.test']);

        $conCuenta->assertOk();
        $sinCuenta->assertOk();

        /* Mismo codigo y mismo cuerpo. Cualquier diferencia —un texto, una
           clave extra, un 404— convierte esto en un verificador de correos. */
        $this->assertSame($conCuenta->getStatusCode(), $sinCuenta->getStatusCode());
        $this->assertSame($conCuenta->getContent(), $sinCuenta->getContent());
    }

    public function test_el_mensaje_no_afirma_que_el_correo_se_envio(): void
    {
        Notification::fake();

        $respuesta = $this->postJson('/api/password/olvide', ['email' => 'nadie@prueba.test']);

        /* Decir "te enviamos un correo" cuando no hay cuenta es mentir. El
           mensaje tiene que ser cierto en los dos casos. */
        $this->assertStringContainsString('Si existe una cuenta', $respuesta->json('message'));
    }

    public function test_solo_se_manda_el_correo_cuando_hay_cuenta(): void
    {
        Notification::fake();

        $usuario = $this->cliente(['email' => 'si.hay@prueba.test']);

        $this->postJson('/api/password/olvide', ['email' => 'no.hay@prueba.test'])->assertOk();
        Notification::assertNothingSent();

        $this->postJson('/api/password/olvide', ['email' => 'si.hay@prueba.test'])->assertOk();
        Notification::assertSentTo($usuario, RestablecerContrasena::class);
    }

    public function test_un_correo_mal_formado_si_se_rechaza(): void
    {
        /* Aqui la validacion no filtra nada sobre quien tiene cuenta: "esto no
           es un correo" es cierto sin consultar la base. */
        $this->postJson('/api/password/olvide', ['email' => 'no-es-un-correo'])
            ->assertStatus(422);
    }

    // -------------------------------------------------- restablecer de verdad

    public function test_el_token_valido_cambia_la_contrasena(): void
    {
        $usuario = $this->cliente(['email' => 'cambia@prueba.test']);
        $token = Password::broker()->createToken($usuario);

        $this->postJson('/api/password/restablecer', [
            'token' => $token,
            'email' => 'cambia@prueba.test',
            'password' => 'clave-nueva-12345',
            'password_confirmation' => 'clave-nueva-12345',
        ])->assertOk();

        $fresco = $usuario->fresh();

        $this->assertTrue(Hash::check('clave-nueva-12345', $fresco->password));
        $this->assertFalse(Hash::check(self::CLAVE_VIEJA, $fresco->password));
    }

    public function test_el_restablecimiento_revoca_todas_las_sesiones(): void
    {
        $usuario = $this->cliente(['email' => 'revoca@prueba.test']);
        $usuario->createToken('movil');
        $usuario->createToken('escritorio');

        $this->assertSame(2, $usuario->tokens()->count());

        $this->postJson('/api/password/restablecer', [
            'token' => Password::broker()->createToken($usuario),
            'email' => 'revoca@prueba.test',
            'password' => 'clave-nueva-12345',
            'password_confirmation' => 'clave-nueva-12345',
        ])->assertOk();

        /* Se restablece la contrasena justo cuando se sospecha que la anterior
           esta en manos de otra persona. Dejar una sola sesion viva vaciaria
           el cambio de sentido. */
        $this->assertSame(0, $usuario->tokens()->count());
    }

    public function test_el_token_sirve_una_sola_vez(): void
    {
        $usuario = $this->cliente(['email' => 'una.vez@prueba.test']);
        $token = Password::broker()->createToken($usuario);

        $cuerpo = [
            'token' => $token,
            'email' => 'una.vez@prueba.test',
            'password' => 'clave-nueva-12345',
            'password_confirmation' => 'clave-nueva-12345',
        ];

        $this->postJson('/api/password/restablecer', $cuerpo)->assertOk();

        $segunda = $this->postJson('/api/password/restablecer', array_merge($cuerpo, [
            'password' => 'otra-clave-12345',
            'password_confirmation' => 'otra-clave-12345',
        ]));

        $segunda->assertStatus(422);
        $this->assertTrue(Hash::check('clave-nueva-12345', $usuario->fresh()->password));
    }

    public function test_un_token_inventado_no_cambia_nada(): void
    {
        $usuario = $this->cliente(['email' => 'inventado@prueba.test']);

        $this->postJson('/api/password/restablecer', [
            'token' => 'esto-no-es-un-token',
            'email' => 'inventado@prueba.test',
            'password' => 'clave-nueva-12345',
            'password_confirmation' => 'clave-nueva-12345',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $usuario->fresh()->password));
    }

    public function test_el_fallo_no_distingue_token_malo_de_correo_inexistente(): void
    {
        $this->cliente(['email' => 'existe2@prueba.test']);

        $tokenMalo = $this->postJson('/api/password/restablecer', [
            'token' => 'token-que-no-vale',
            'email' => 'existe2@prueba.test',
            'password' => 'clave-nueva-12345',
            'password_confirmation' => 'clave-nueva-12345',
        ]);

        $correoInexistente = $this->postJson('/api/password/restablecer', [
            'token' => 'token-que-no-vale',
            'email' => 'nadie2@prueba.test',
            'password' => 'clave-nueva-12345',
            'password_confirmation' => 'clave-nueva-12345',
        ]);

        /* Separar los dos casos le diria al atacante cual de las dos cosas
           acerto, y eso vuelve a ser enumeracion por otra puerta. */
        $this->assertSame($tokenMalo->getStatusCode(), $correoInexistente->getStatusCode());
        $this->assertSame($tokenMalo->getContent(), $correoInexistente->getContent());
    }

    public function test_el_token_de_otra_cuenta_no_sirve(): void
    {
        $uno = $this->cliente(['email' => 'uno@prueba.test', 'dni' => '10000042']);
        $otro = $this->cliente(['email' => 'otro@prueba.test', 'dni' => '10000043']);

        $this->postJson('/api/password/restablecer', [
            'token' => Password::broker()->createToken($uno),
            'email' => 'otro@prueba.test',
            'password' => 'clave-nueva-12345',
            'password_confirmation' => 'clave-nueva-12345',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $otro->fresh()->password));
    }

    // ------------------------------------------------------------- validacion

    public function test_se_exige_confirmacion_y_longitud_minima(): void
    {
        $usuario = $this->cliente(['email' => 'valida@prueba.test']);
        $token = Password::broker()->createToken($usuario);

        $this->postJson('/api/password/restablecer', [
            'token' => $token,
            'email' => 'valida@prueba.test',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/api/password/restablecer', [
            'token' => $token,
            'email' => 'valida@prueba.test',
            'password' => 'clave-nueva-12345',
            'password_confirmation' => 'otra-cosa-12345',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check(self::CLAVE_VIEJA, $usuario->fresh()->password));
    }

    // ---------------------------------------------------------- el correo

    public function test_el_correo_lleva_el_enlace_al_portal_y_no_a_la_api(): void
    {
        $usuario = $this->cliente(['email' => 'enlace@prueba.test']);
        $correo = (new RestablecerContrasena('token-abc'))->toMail($usuario);

        $accion = $correo->actionUrl;

        /* Quien recibe el correo abre el enlace en su navegador. Un enlace a
           la API le devolveria JSON. */
        $this->assertStringStartsWith(config('app.frontend_url').'/restablecer-contrasena', $accion);
        $this->assertStringContainsString('token=token-abc', $accion);
    }

    public function test_el_correo_dice_que_la_contrasena_actual_sigue_valiendo(): void
    {
        $usuario = $this->cliente(['email' => 'aviso@prueba.test']);
        $lineas = implode(' ', (new RestablecerContrasena('token-abc'))->toMail($usuario)->introLines);
        $lineas .= ' '.implode(' ', (new RestablecerContrasena('token-abc'))->toMail($usuario)->outroLines);

        /* Sin esa frase, el correo parece avisar de que ya le cambiaron la
           contrasena a alguien que no pidio nada. */
        $this->assertStringContainsString('tu contraseña actual no ha cambiado', $lineas);
    }
}
