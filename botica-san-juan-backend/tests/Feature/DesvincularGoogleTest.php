<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Desvincular Google sin quedarse fuera de la cuenta.
 * ---------------------------------------------------------------------------
 * EL FALLO QUE ESTAS PRUEBAS IMPIDEN
 *
 * La columna `password` nunca es nula. Una cuenta creada con Google lleva un
 * hash aleatorio de 64 caracteres que nadie ha visto nunca. Si el endpoint se
 * limitara a borrar `google_id`, esa cuenta se quedaría sin ninguna vía de
 * entrada: el botón de Google ya no la reconoce y la contraseña no existe
 * para nadie.
 *
 * Lo peor de ese fallo es CUÁNDO se nota. La sesión en curso sigue abierta, así
 * que al pulsar el botón todo parece correcto. El problema aparece al día
 * siguiente, cuando ya no hay forma de arreglarlo desde dentro.
 *
 * De ahí que la mitad de estas pruebas comprueben que el endpoint se NIEGA.
 */
class DesvincularGoogleTest extends TestCase
{
    use RefreshDatabase;

    /** Cuenta nacida con Google: sin DNI y con una contraseña que nadie conoce. */
    private function cuentaDeGoogle(array $extra = []): Usuario
    {
        $usuario = new Usuario([
            'nombre' => 'Cliente de Google',
            'email' => 'nacido.en.google@prueba.test',
            'dni' => null,
            'rol' => 'cliente',
            'google_id' => '101234567890123456789',
            'google_vinculado_en' => now(),
        ]);

        /* Igual que GoogleController: el hash va directo, SIN pasar por
           `establecerContrasena`, porque nadie eligió esta contraseña. */
        $usuario->forceFill(array_merge([
            'password' => Hash::make(Str::random(64)),
            'email_verified_at' => now(),
        ], $extra))->save();

        return $usuario;
    }

    /** Cuenta registrada por el formulario que después vinculó Google. */
    private function cuentaConContrasena(array $extra = []): Usuario
    {
        $usuario = new Usuario([
            'nombre' => 'Cliente con Contrasena',
            'email' => 'con.contrasena@prueba.test',
            'dni' => '10000021',
            'rol' => 'cliente',
            'google_id' => '209876543210987654321',
            'google_vinculado_en' => now(),
        ]);

        $usuario->establecerContrasena('clave-de-prueba');
        $usuario->forceFill(array_merge(['email_verified_at' => now()], $extra))->save();

        return $usuario;
    }

    // --------------------------------------------------- se niega

    public function test_una_cuenta_nacida_con_google_no_puede_desvincularla(): void
    {
        $usuario = $this->cuentaDeGoogle();

        $respuesta = $this->actingAs($usuario)->deleteJson('/api/perfil/google');

        $respuesta->assertStatus(422);

        /* Lo que importa no es solo el 422: es que el vínculo SIGA ahí. Un
           error devuelto después de haber borrado la columna dejaría la cuenta
           igual de perdida. */
        $this->assertNotNull($usuario->fresh()->google_id);
    }

    public function test_el_motivo_le_dice_a_la_persona_que_hacer(): void
    {
        $usuario = $this->cuentaDeGoogle();

        $respuesta = $this->actingAs($usuario)->deleteJson('/api/perfil/google');

        /* Un «no se puede» a secas deja a alguien atascado. La salida real es
           «olvidé mi contraseña», que va al correo que Google ya verificó, y
           el mensaje tiene que nombrarla. */
        $respuesta->assertJsonPath('errors.google.0', fn ($mensaje) => str_contains((string) $mensaje, 'contraseña'));
    }

    public function test_con_contrasena_pero_sin_correo_verificado_tampoco(): void
    {
        /* Aquí sí queda una credencial al quitar Google, pero no queda forma de
           RECUPERARLA: el enlace de restablecimiento iría a una dirección que
           nadie ha confirmado. Es la misma cuenta perdida, solo más tarde. */
        $usuario = $this->cuentaConContrasena(['email_verified_at' => null]);

        $this->actingAs($usuario)->deleteJson('/api/perfil/google')->assertStatus(422);

        $this->assertNotNull($usuario->fresh()->google_id);
    }

    public function test_una_cuenta_sin_google_no_desvincula_nada(): void
    {
        $usuario = new Usuario([
            'nombre' => 'Cliente sin Google',
            'email' => 'sin.google@prueba.test',
            'dni' => '10000022',
            'rol' => 'cliente',
        ]);
        $usuario->establecerContrasena('clave-de-prueba');
        $usuario->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($usuario)->deleteJson('/api/perfil/google')->assertStatus(422);
    }

    public function test_sin_sesion_responde_401(): void
    {
        /* Se crea por el modelo y no con `actingAs`, porque `actingAs`
           persiste durante todo el método y arruinaría la comprobación. */
        $this->cuentaConContrasena();

        $this->deleteJson('/api/perfil/google')->assertStatus(401);
    }

    // --------------------------------------------------- se permite

    public function test_con_contrasena_y_correo_verificado_se_desvincula(): void
    {
        $usuario = $this->cuentaConContrasena();

        $respuesta = $this->actingAs($usuario)->deleteJson('/api/perfil/google');

        $respuesta->assertOk();

        $fresco = $usuario->fresh();

        $this->assertNull($fresco->google_id);
        $this->assertNull($fresco->google_vinculado_en);
        $this->assertFalse($fresco->tiene_google);
    }

    public function test_desvincular_no_toca_la_contrasena_ni_la_verificacion(): void
    {
        /* El error fácil sería «limpiar» de paso lo que vino de Google. Eso
           dejaría la cuenta sin correo verificado justo después de quitarle la
           otra vía de acceso. */
        $usuario = $this->cuentaConContrasena();

        $this->actingAs($usuario)->deleteJson('/api/perfil/google')->assertOk();

        $fresco = $usuario->fresh();

        $this->assertTrue(Hash::check('clave-de-prueba', $fresco->password));
        $this->assertNotNull($fresco->email_verified_at);
        $this->assertNotNull($fresco->contrasena_establecida_en);
    }

    public function test_el_cambio_queda_en_la_bitacora(): void
    {
        $usuario = $this->cuentaConContrasena();

        $this->actingAs($usuario)->deleteJson('/api/perfil/google')->assertOk();

        /* Perder una vía de acceso es justo el tipo de cambio que alguien
           querrá reconstruir después. */
        $this->assertDatabaseHas('eventos_operacion', [
            'codigo' => 'PERFIL_GOOGLE_DESVINCULADO',
        ]);
    }

    // --------------------------------------------------- la vía de escape

    public function test_restablecer_la_contrasena_habilita_la_desvinculacion(): void
    {
        /* Esta es la prueba que cierra el círculo: sin ella, el 422 de arriba
           sería un callejón sin salida. Una cuenta nacida con Google pide su
           contraseña por correo, la establece, y entonces sí puede desvincular.
           Si este camino se rompiera, el bloqueo se volvería permanente. */
        $usuario = $this->cuentaDeGoogle();

        $this->assertFalse($usuario->puede_desvincular_google);

        $token = Password::broker()->createToken($usuario);

        $this->postJson('/api/password/restablecer', [
            'token' => $token,
            'email' => $usuario->email,
            'password' => 'contrasena-nueva-propia',
            'password_confirmation' => 'contrasena-nueva-propia',
        ])->assertOk();

        $fresco = $usuario->fresh();

        $this->assertNotNull($fresco->contrasena_establecida_en);
        $this->assertTrue($fresco->puede_desvincular_google);

        $this->actingAs($fresco)->deleteJson('/api/perfil/google')->assertOk();
    }

    public function test_cambiar_la_contrasena_desde_el_perfil_tambien_la_marca(): void
    {
        $usuario = $this->cuentaDeGoogle();

        /* Este camino exige la contraseña actual, así que una cuenta nacida con
           Google no puede usarlo: no la conoce. Se comprueba con una cuenta que
           sí la tiene, para verificar que el cambio conserva la marca. */
        $conClave = $this->cuentaConContrasena();

        $this->actingAs($conClave)->putJson('/api/perfil/contrasena', [
            'contrasena_actual' => 'clave-de-prueba',
            'contrasena' => 'otra-clave-distinta',
            'contrasena_confirmation' => 'otra-clave-distinta',
        ])->assertOk();

        $this->assertNotNull($conClave->fresh()->contrasena_establecida_en);
        $this->assertNull($usuario->fresh()->contrasena_establecida_en);
    }

    // --------------------------------------------------- lo que ve el portal

    public function test_el_perfil_dice_si_se_puede_desvincular(): void
    {
        /* El portal necesita saberlo ANTES de mostrar el botón. Si tuviera que
           intentarlo para averiguarlo, la única forma de enterarse sería un
           error. */
        $usuario = $this->cuentaDeGoogle();

        $this->actingAs($usuario)->getJson('/api/perfil')
            ->assertOk()
            ->assertJsonPath('tiene_google', true)
            ->assertJsonPath('tiene_contrasena', false)
            ->assertJsonPath('puede_desvincular_google', false);
    }

    public function test_el_perfil_nunca_expone_el_identificador_de_google(): void
    {
        $usuario = $this->cuentaDeGoogle();

        $this->actingAs($usuario)->getJson('/api/perfil')
            ->assertOk()
            ->assertJsonMissing(['google_id' => $usuario->google_id]);
    }
}
