<?php

namespace Tests\Feature;

use App\Models\Usuario;
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

    public function test_la_foto_se_guarda_en_el_disco_y_queda_en_el_perfil(): void
    {
        $usuario = $this->cliente();

        $respuesta = $this->actingAs($usuario)->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('mi-cara.jpg', 400, 400),
        ]);

        $respuesta->assertOk();

        $ruta = $usuario->fresh()->foto_perfil;

        $this->assertStringStartsWith('storage/perfiles/', (string) $ruta);
        Storage::disk('public')->assertExists(str_replace('storage/', '', (string) $ruta));
    }

    public function test_el_nombre_del_archivo_no_es_el_que_envia_el_cliente(): void
    {
        $usuario = $this->cliente();

        /* Un nombre con recorrido de ruta. Si se respetara el nombre de
           origen, el archivo acabaría fuera de la carpeta de perfiles. */
        $this->actingAs($usuario)->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('../../evil.jpg', 100, 100),
        ])->assertOk();

        $ruta = (string) $usuario->fresh()->foto_perfil;

        $this->assertStringNotContainsString('..', $ruta);
        $this->assertStringNotContainsString('evil', $ruta);
    }

    public function test_un_archivo_que_no_es_imagen_se_rechaza(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->create('camuflado.jpg', 20, 'text/plain'),
        ])->assertStatus(422);

        $this->assertNull($usuario->fresh()->foto_perfil);
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

    public function test_al_cambiar_la_foto_se_borra_la_anterior(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('primera.jpg'),
        ])->assertOk();

        $primera = str_replace('storage/', '', (string) $usuario->fresh()->foto_perfil);

        $this->actingAs($usuario)->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('segunda.jpg'),
        ])->assertOk();

        /* Sin esto, cada cambio de foto deja un archivo huérfano que nadie
           recoge nunca. */
        Storage::disk('public')->assertMissing($primera);
    }

    public function test_eliminar_la_foto_la_quita_del_perfil_y_del_disco(): void
    {
        $usuario = $this->cliente();

        $this->actingAs($usuario)->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('adios.jpg'),
        ])->assertOk();

        $ruta = str_replace('storage/', '', (string) $usuario->fresh()->foto_perfil);

        $this->actingAs($usuario)->deleteJson('/api/perfil/foto')->assertOk();

        $this->assertNull($usuario->fresh()->foto_perfil);
        Storage::disk('public')->assertMissing($ruta);
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
}
