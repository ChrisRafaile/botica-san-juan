<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre el RF-01 (inicio de sesion con credenciales cifradas) y el
 * RNF-02 (bcrypt, bloqueo tras 5 intentos fallidos en 1 minuto).
 */
class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_registrado_inicia_sesion_y_recibe_un_token(): void
    {
        $this->crearCliente();

        $respuesta = $this->postJson('/api/login', [
            'dni' => '20000002',
            'password' => 'Clave#Segura2026',
        ]);

        $respuesta->assertOk()
            ->assertJsonStructure(['user' => ['id', 'nombre', 'email', 'rol'], 'token', 'token_type'])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.rol', 'cliente');

        $this->assertNotEmpty($respuesta->json('token'));
    }

    public function test_la_contrasena_nunca_se_expone_en_la_respuesta(): void
    {
        $this->crearCliente();

        $respuesta = $this->postJson('/api/login', [
            'dni' => '20000002',
            'password' => 'Clave#Segura2026',
        ]);

        $respuesta->assertOk()
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.mfa_secret');
    }

    public function test_la_contrasena_se_almacena_cifrada_con_bcrypt(): void
    {
        $usuario = $this->crearCliente();

        $this->assertNotSame('Clave#Segura2026', $usuario->password);
        $this->assertStringStartsWith('$2y$', $usuario->password);
        $this->assertTrue(password_verify('Clave#Segura2026', $usuario->password));
    }

    public function test_credenciales_invalidas_devuelven_401_con_mensaje_generico(): void
    {
        $this->crearCliente();

        $respuesta = $this->postJson('/api/login', [
            'dni' => '20000002',
            'password' => 'contrasena-incorrecta',
        ]);

        $respuesta->assertStatus(401);

        // El mensaje no debe revelar si fallo el DNI o la contrasena.
        $this->assertStringNotContainsStringIgnoringCase('dni no existe', $respuesta->json('message'));
    }

    public function test_un_dni_inexistente_devuelve_401_y_no_404(): void
    {
        $this->postJson('/api/login', [
            'dni' => '99999999',
            'password' => 'cualquiera',
        ])->assertStatus(401);
    }

    public function test_el_dni_es_obligatorio_y_de_ocho_digitos(): void
    {
        $this->postJson('/api/login', ['password' => 'algo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('dni');

        $this->postJson('/api/login', ['dni' => '123', 'password' => 'algo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('dni');
    }

    public function test_la_contrasena_es_obligatoria(): void
    {
        $this->postJson('/api/login', ['dni' => '20000002'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_la_cuenta_se_bloquea_tras_cinco_intentos_fallidos(): void
    {
        $this->crearCliente();

        for ($intento = 1; $intento <= 5; $intento++) {
            $this->postJson('/api/login', [
                'dni' => '20000002',
                'password' => 'incorrecta',
            ])->assertStatus(401);
        }

        // El sexto intento se rechaza aunque la contrasena sea correcta.
        // El bloqueo puede provenir del middleware throttle:login o de la
        // limitacion propia del controlador; ambos responden 429.
        $respuesta = $this->postJson('/api/login', [
            'dni' => '20000002',
            'password' => 'Clave#Segura2026',
        ]);

        $respuesta->assertStatus(429);
        $this->assertNotEmpty($respuesta->json('message') ?? $respuesta->headers->get('Retry-After'));
    }

    public function test_un_usuario_autenticado_puede_cerrar_sesion(): void
    {
        $usuario = $this->crearCliente();
        $token = $usuario->createToken('prueba')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertSuccessful();
    }

    public function test_cerrar_sesion_sin_token_devuelve_401(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }
}
