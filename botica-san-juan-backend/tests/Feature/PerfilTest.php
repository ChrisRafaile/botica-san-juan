<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Support\Imagen\OptimizadorAvatar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Perfil de la cuenta en sesión.
 *
 * Lo que de verdad hay que demostrar aquí no es que la foto se guarde —eso se
 * ve a simple vista— sino que NO se pueda tocar la cuenta de otro, que el
 * campo haya dejado de ser una cadena libre y que no quede basura en el disco.
 */
class PerfilTest extends TestCase
{
    use RefreshDatabase;

    private function cliente(array $extra = []): Usuario
    {
        return Usuario::create(array_merge([
            'nombre' => 'Cliente Prueba',
            'dni' => '10000011',
            'email' => 'cliente.perfil@prueba.test',
            'password' => Hash::make('clave-de-prueba'),
            'rol' => 'cliente',
        ], $extra));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ------------------------------------------------------------- subida

    public function test_la_foto_se_persiste_en_la_base_y_no_en_el_disco(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('mi-cara.jpg', 1200, 800),
        ])->assertOk()->assertJsonPath('usuario.avatar_origen', 'personalizado');

        $avatar = (string) $usuario->fresh()->avatar_data;

        $this->assertMatchesRegularExpression('#^data:image/(webp|jpeg);base64,#', $avatar);
        $this->assertLessThan(50 * 1024, strlen($avatar));

        [$ancho, $alto] = getimagesizefromstring(base64_decode(explode(',', $avatar)[1]));
        $this->assertSame([256, 256], [$ancho, $alto]);

        /* El disco de Render se borra en cada despliegue: nada debe quedar ahí. */
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_una_foto_de_ruido_tambien_queda_bajo_los_50_kb(): void
    {
        /* El peor caso de compresión: sin él, el tope solo se probaría con
           imágenes lisas que pesan casi nada. */
        $imagen = imagecreatetruecolor(600, 600);
        for ($x = 0; $x < 600; $x++) {
            for ($y = 0; $y < 600; $y++) {
                imagesetpixel($imagen, $x, $y, random_int(0, 0xFFFFFF));
            }
        }
        $ruta = tempnam(sys_get_temp_dir(), 'ruido');
        imagepng($imagen, $ruta);

        $avatar = (new OptimizadorAvatar)->aDataUri($ruta);
        unlink($ruta);

        $this->assertLessThan(50 * 1024, strlen($avatar));
    }

    public function test_una_foto_pequena_no_se_agranda(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('../../evil.jpg', 100, 80),
        ])->assertOk();

        $binario = base64_decode(explode(',', (string) $usuario->fresh()->avatar_data)[1]);
        $this->assertSame(80, getimagesizefromstring($binario)[0]);
    }

    // --------------------------------------------------------- jerarquía

    public function test_la_jerarquia_es_foto_propia_luego_google_luego_iniciales(): void
    {
        $usuario = $this->cliente();
        $this->assertSame('iniciales', $usuario->fresh()->avatar_origen);

        $usuario->actualizarAvatarOauth('https://lh3.googleusercontent.com/a/rosa=s96-c');
        $this->assertSame('oauth', $usuario->fresh()->avatar_origen);

        $this->actingAs($usuario)->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('rosa.jpg', 300, 300),
        ])->assertOk()
            ->assertJsonPath('usuario.avatar_origen', 'personalizado')
            ->assertJsonPath('usuario.avatar_oauth_url', 'https://lh3.googleusercontent.com/a/rosa=s96-c');

        // Quitar la propia vuelve a Google, no a las iniciales.
        $this->actingAs($usuario)->deleteJson('/api/perfil/foto')->assertOk()
            ->assertJsonPath('usuario.avatar_origen', 'oauth')
            ->assertJsonPath('usuario.avatar_data', null);
    }

    public function test_el_avatar_de_google_solo_admite_https_de_google(): void
    {
        $usuario = $this->cliente();

        foreach ([
            'http://lh3.googleusercontent.com/a/x',
            'https://rastreador.test/pixel.gif',
            'https://googleusercontent.com.rastreador.test/a',
            'javascript:alert(1)',
            '',
            null,
        ] as $url) {
            $usuario->actualizarAvatarOauth($url);
        }

        $this->assertNull($usuario->fresh()->avatar_oauth_url);
    }

    public function test_la_imagen_sale_en_la_cuenta_propia_y_no_en_los_listados(): void
    {
        $admin = $this->cliente(['dni' => '10000014', 'email' => 'admin.lista@prueba.test', 'rol' => 'administrador']);
        $cliente = $this->cliente();
        $cliente->forceFill(['avatar_data' => 'data:image/webp;base64,UklGRg=='])->save();

        $this->actingAs($cliente)->getJson('/api/perfil')->assertOk()
            ->assertJsonPath('avatar_data', 'data:image/webp;base64,UklGRg==');
        $this->actingAs($cliente)->getJson('/api/user')->assertOk()
            ->assertJsonPath('avatar_data', 'data:image/webp;base64,UklGRg==');

        $this->assertStringNotContainsString(
            'UklGRg',
            (string) $this->actingAs($admin)->getJson('/api/usuarios')->assertOk()->getContent(),
        );
    }

    public function test_el_avatar_no_se_puede_escribir_por_asignacion_masiva(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->putJson('/api/perfil', [
            'nombre' => 'Rosa Quispe',
            'avatar_data' => 'data:image/svg+xml;base64,AAAA',
            'avatar_oauth_url' => 'https://rastreador.test/x',
        ])->assertOk();

        $this->assertNull($usuario->fresh()->avatar_data);
        $this->assertNull($usuario->fresh()->avatar_oauth_url);
    }

    public function test_un_archivo_que_no_es_imagen_se_rechaza(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->create('camuflado.jpg', 20, 'text/plain'),
        ])->assertStatus(422);

        $this->assertNull($usuario->fresh()->avatar_data);
    }

    public function test_una_imagen_demasiado_pesada_se_rechaza(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('enorme.jpg')->size(3 * 1024),
        ])->assertStatus(422);
    }

    public function test_sin_sesion_no_se_puede_subir(): void
    {
        $this->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('x.jpg'),
        ])->assertStatus(401);
    }

    public function test_subir_una_foto_borra_el_archivo_antiguo_del_disco(): void
    {
        Storage::disk('public')->put('perfiles/antigua.jpg', 'contenido');
        $usuario = $this->cliente(['foto_perfil' => 'storage/perfiles/antigua.jpg']);

        $this->actingAs($usuario)->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('nueva.jpg'),
        ])->assertOk();

        $this->assertNull($usuario->fresh()->foto_perfil);
        Storage::disk('public')->assertMissing('perfiles/antigua.jpg');
    }

    public function test_eliminar_la_foto_la_quita_del_perfil(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('adios.jpg'),
        ])->assertOk();

        $this->actingAs($usuario)->deleteJson('/api/perfil/foto')->assertOk()
            ->assertJsonPath('usuario.avatar_origen', 'iniciales');

        $this->assertNull($usuario->fresh()->avatar_data);
    }

    public function test_una_ruta_vieja_fuera_de_perfiles_no_se_borra(): void
    {
        /* Quedan valores de cuando el campo era texto libre. Borrar a ciegas
           lo que diga la base dejaría que un valor antiguo eligiera qué
           archivo se elimina. */
        Storage::disk('public')->put('images/catalogo.png', 'contenido');

        $usuario = $this->cliente(['foto_perfil' => 'storage/images/catalogo.png']);

        $this->actingAs($usuario)->deleteJson('/api/perfil/foto')->assertOk();

        Storage::disk('public')->assertExists('images/catalogo.png');
    }

    // ----------------------------------------------------- datos y rol

    public function test_el_perfil_no_permite_ascenderse_a_administrador(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->putJson('/api/perfil', [
            'nombre' => 'Cliente Listo',
            'rol' => 'administrador',
        ])->assertOk();

        $this->assertSame('cliente', $usuario->fresh()->rol);
        $this->assertSame('Cliente Listo', $usuario->fresh()->nombre);
    }

    public function test_el_crud_de_usuarios_ya_no_acepta_la_foto_como_texto(): void
    {
        $admin = $this->cliente([
            'dni' => '10000012',
            'email' => 'admin.perfil@prueba.test',
            'rol' => 'administrador',
        ]);
        $otro = $this->cliente(['dni' => '10000013', 'email' => 'otro@prueba.test']);

        $this->actingAs($admin)->putJson("/api/usuarios/{$otro->id}", [
            'nombre' => 'Otro',
            'foto_perfil' => '../../../etc/passwd',
        ])->assertOk();

        $this->assertNull($otro->fresh()->foto_perfil);
    }

    // ------------------------------------------------------- contraseña

    public function test_cambiar_la_contrasena_exige_la_vigente(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->putJson('/api/perfil/contrasena', [
            'contrasena_actual' => 'la-que-no-es',
            'contrasena' => 'una-clave-nueva',
            'contrasena_confirmation' => 'una-clave-nueva',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('clave-de-prueba', $usuario->fresh()->password));
    }

    public function test_con_la_contrasena_vigente_el_cambio_se_aplica(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->putJson('/api/perfil/contrasena', [
            'contrasena_actual' => 'clave-de-prueba',
            'contrasena' => 'una-clave-nueva',
            'contrasena_confirmation' => 'una-clave-nueva',
        ])->assertOk();

        $this->assertTrue(Hash::check('una-clave-nueva', $usuario->fresh()->password));
    }

    // ----------------------------------------- dirección de entrega predeterminada

    public function test_puede_guardar_y_limpiar_direccion_de_entrega_predeterminada(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->putJson('/api/perfil', [
            'direccion_entrega' => 'Jr. San Martín 420, Tarapoto',
        ])->assertOk()
          ->assertJsonPath('direccion_entrega', 'Jr. San Martín 420, Tarapoto');

        $this->assertSame('Jr. San Martín 420, Tarapoto', $usuario->fresh()->direccion_entrega);

        $this->actingAs($usuario)->putJson('/api/perfil', [
            'direccion_entrega' => null,
        ])->assertOk()
          ->assertJsonPath('direccion_entrega', null);

        $this->assertNull($usuario->fresh()->direccion_entrega);
    }

    public function test_rechaza_direccion_de_entrega_mayor_a_255_caracteres(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->putJson('/api/perfil', [
            'direccion_entrega' => str_repeat('A', 256),
        ])->assertStatus(422);
    }
}
