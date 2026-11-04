<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Recetas médicas.
 *
 * Lo que estas pruebas protegen no es que el archivo se guarde —eso se ve a
 * simple vista— sino las cuatro cosas que hacen que este módulo no sea un
 * problema:
 *
 *   1. Que la receta de una persona no la pueda leer otra.
 *   2. Que el archivo no quede accesible desde el disco público.
 *   3. Que un pedido con medicamentos bajo receta no se pueda completar sin
 *      una receta validada, ni siquiera llamando a la API directamente.
 *   4. Que validar y rechazar dejen constancia de quién lo decidió.
 */
class RecetaTest extends TestCase
{
    use RefreshDatabase;

    private int $dni = 20000000;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function usuario(string $rol = 'cliente', ?string $email = null): Usuario
    {
        $this->dni++;

        return Usuario::create([
            'nombre' => 'Persona '.$this->dni,
            'dni' => (string) $this->dni,
            'email' => $email ?? "persona{$this->dni}@prueba.test",
            'password' => Hash::make('clave-de-prueba'),
            'rol' => $rol,
        ]);
    }

    private function producto(bool $requiereReceta): Producto
    {
        return Producto::create([
            'nombre' => $requiereReceta ? 'AMOXICILINA 500 mg' : 'PARACETAMOL 500 mg',
            'concentracion' => '500 mg',
            'laboratorio' => 'Lab',
            'presentacion' => 'Caja',
            'tipo' => 'medicamento',
            'categoria' => 'General',
            'precio' => 10,
            'stock' => 50,
            'requiere_receta' => $requiereReceta,
        ]);
    }

    private function pedido(Usuario $cliente, bool $conProductoBajoReceta = true): Pedido
    {
        $pedido = Pedido::create([
            'usuario_id' => $cliente->id,
            'origen' => Pedido::ORIGEN_WEB,
            'estado' => Pedido::ESTADO_PENDIENTE,
            'total' => 10,
            'fecha' => now(),
        ]);

        PedidoDetalle::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $this->producto($conProductoBajoReceta)->id,
            'cantidad' => 1,
            'precio' => 10,
            'subtotal' => 10,
        ]);

        return $pedido;
    }

    private function imagen(): UploadedFile
    {
        return UploadedFile::fake()->image('receta.jpg', 900, 1200);
    }

    private function pdf(): UploadedFile
    {
        /* Un PDF de verdad, con su cabecera: `create()` a secas produce un
           archivo vacío cuyo tipo real no es application/pdf. */
        $ruta = tempnam(sys_get_temp_dir(), 'rec').'.pdf';
        file_put_contents($ruta, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");

        return new UploadedFile($ruta, 'receta.pdf', 'application/pdf', null, true);
    }

    // ------------------------------------------------------------- subida

    public function test_el_cliente_adjunta_una_imagen_a_su_pedido(): void
    {
        $cliente = $this->usuario();
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()])
            ->assertCreated()
            ->assertJsonPath('receta.estado', Receta::ESTADO_PENDIENTE)
            ->assertJsonPath('receta.es_imagen', true);

        $this->assertDatabaseHas('recetas', ['pedido_id' => $pedido->id, 'version' => 1]);
    }

    public function test_tambien_acepta_un_pdf(): void
    {
        $cliente = $this->usuario();
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->pdf()])
            ->assertCreated()
            ->assertJsonPath('receta.es_imagen', false)
            ->assertJsonPath('receta.tipo_mime', 'application/pdf');
    }

    public function test_el_archivo_va_al_disco_privado_y_no_al_publico(): void
    {
        $cliente = $this->usuario();
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()])
            ->assertCreated();

        $ruta = Receta::where('pedido_id', $pedido->id)->value('archivo');

        /* En el disco público bastaría adivinar la URL para leer la receta de
           otra persona. Una receta lleva el nombre del paciente y, por el
           medicamento, su diagnóstico. */
        Storage::disk('local')->assertExists($ruta);
        Storage::disk('public')->assertMissing($ruta);
        $this->assertStringStartsWith('recetas/', $ruta);
    }

    public function test_la_respuesta_no_filtra_la_ruta_del_archivo(): void
    {
        $cliente = $this->usuario();
        $pedido = $this->pedido($cliente);

        $respuesta = $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()]);

        /* Publicar la ruta invita a construir la URL a mano y a probar si el
           disco está servido directamente. */
        $respuesta->assertJsonMissingPath('receta.archivo');
        $this->assertStringNotContainsString('recetas/', $respuesta->getContent());
    }

    public function test_el_nombre_del_archivo_no_es_el_que_envia_el_cliente(): void
    {
        $cliente = $this->usuario();
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)->postJson("/api/pedidos/{$pedido->id}/receta", [
            'archivo' => UploadedFile::fake()->image('../../.env.jpg', 400, 400),
        ])->assertCreated();

        $ruta = Receta::where('pedido_id', $pedido->id)->value('archivo');

        $this->assertStringNotContainsString('..', $ruta);
        $this->assertStringNotContainsString('.env', $ruta);
    }

    public function test_se_rechaza_un_ejecutable_disfrazado_de_imagen(): void
    {
        $cliente = $this->usuario();
        $pedido = $this->pedido($cliente);

        /* Un UploadedFile REAL, no `UploadedFile::fake()`.
           ----------------------------------------------------------------
           La primera versión de esta prueba usaba el falso y pasaba con 201,
           y el fallo estaba en la prueba: `Illuminate\Http\Testing\File`
           deduce el tipo de la EXTENSIÓN, así que un archivo PHP llamado
           «receta.jpg» se presentaba como image/jpeg y la comprobación no
           tenía nada real que mirar. Estaba probando el andamio de pruebas,
           no la validación.

           Con un archivo de verdad, `getMimeType()` lee el contenido y
           devuelve text/plain. */
        $ruta = tempnam(sys_get_temp_dir(), 'falso').'.jpg';
        file_put_contents($ruta, '<?php echo "esto no es una imagen";');
        $falso = new UploadedFile($ruta, 'receta.jpg', 'image/jpeg', null, true);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $falso])
            ->assertStatus(422);

        $this->assertDatabaseCount('recetas', 0);
    }

    public function test_se_rechaza_un_archivo_demasiado_grande(): void
    {
        $cliente = $this->usuario();
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)->postJson("/api/pedidos/{$pedido->id}/receta", [
            'archivo' => UploadedFile::fake()->image('enorme.jpg')->size(9 * 1024),
        ])->assertStatus(422);
    }

    // ------------------------------------------------------- sustitución

    public function test_adjuntar_otra_receta_sustituye_la_anterior_y_borra_su_archivo(): void
    {
        $cliente = $this->usuario();
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()]);

        $primera = Receta::where('pedido_id', $pedido->id)->value('archivo');

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()])
            ->assertCreated()
            ->assertJsonPath('receta.version', 2);

        /* Un documento clínico que ya no vale no se queda ocupando disco ni
           esperando a que alguien lo lea por error. */
        Storage::disk('local')->assertMissing($primera);
        $this->assertDatabaseCount('recetas', 1);
    }

    public function test_una_receta_ya_validada_no_se_sustituye_desde_el_portal(): void
    {
        $cliente = $this->usuario();
        $admin = $this->usuario('administrador');
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()]);

        $this->actingAs($admin)->putJson("/api/pedidos/{$pedido->id}/receta/revision", [
            'estado' => Receta::ESTADO_VALIDADA,
        ])->assertOk();

        /* El boticario la miró y decidió. Dejar que el cliente la cambie
           después convierte esa decisión en provisional sin avisar a nadie. */
        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()])
            ->assertStatus(409);
    }

    public function test_tras_un_rechazo_si_se_puede_adjuntar_otra(): void
    {
        $cliente = $this->usuario();
        $admin = $this->usuario('administrador');
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()]);

        $this->actingAs($admin)->putJson("/api/pedidos/{$pedido->id}/receta/revision", [
            'estado' => Receta::ESTADO_RECHAZADA,
            'motivo_rechazo' => 'La foto está cortada y no se ve la firma del médico.',
        ])->assertOk();

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()])
            ->assertCreated()
            ->assertJsonPath('receta.estado', Receta::ESTADO_PENDIENTE)
            ->assertJsonPath('receta.motivo_rechazo', null);
    }

    // ------------------------------------------------------------ propiedad

    public function test_otro_cliente_no_puede_ver_la_receta(): void
    {
        $duena = $this->usuario();
        $ajeno = $this->usuario();
        $pedido = $this->pedido($duena);

        $this->actingAs($duena)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()]);

        /* Exigir identidad no es exigir propiedad: ese fue el defecto D-05 en
           las descargas de comprobantes. */
        $this->actingAs($ajeno)->getJson("/api/pedidos/{$pedido->id}/receta")->assertForbidden();
        $this->actingAs($ajeno)->get("/api/pedidos/{$pedido->id}/receta/archivo")->assertForbidden();
    }

    public function test_otro_cliente_no_puede_adjuntar_en_un_pedido_ajeno(): void
    {
        $duena = $this->usuario();
        $ajeno = $this->usuario();
        $pedido = $this->pedido($duena);

        $this->actingAs($ajeno)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()])
            ->assertForbidden();

        $this->assertDatabaseCount('recetas', 0);
    }

    public function test_sin_sesion_no_se_llega_al_archivo(): void
    {
        $cliente = $this->usuario();
        $pedido = $this->pedido($cliente);

        /* La receta se crea por el modelo, sin pasar por `actingAs`.
           ----------------------------------------------------------------
           La primera versión subía el archivo con `actingAs($cliente)` y
           después pedía el archivo «sin sesión»; daba 200 y el fallo era de
           la prueba: `actingAs` deja autenticada la cuenta para TODO el
           resto del método, así que la segunda petición seguía llevando
           sesión. No había forma de que diera 401. */
        Receta::create([
            'pedido_id' => $pedido->id,
            'subida_por_id' => $cliente->id,
            'archivo' => Receta::CARPETA.'/prueba.jpg',
            'nombre_original' => 'receta.jpg',
            'tipo_mime' => 'image/jpeg',
            'tamano_bytes' => 1024,
        ]);

        $this->getJson("/api/pedidos/{$pedido->id}/receta/archivo")->assertUnauthorized();
        $this->getJson("/api/pedidos/{$pedido->id}/receta")->assertUnauthorized();
    }

    public function test_el_administrador_si_puede_verla(): void
    {
        $cliente = $this->usuario();
        $admin = $this->usuario('administrador');
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()]);

        $this->actingAs($admin)->getJson("/api/pedidos/{$pedido->id}/receta")->assertOk();
        $this->actingAs($admin)->get("/api/pedidos/{$pedido->id}/receta/archivo")->assertOk();
    }

    public function test_un_pedido_de_mostrador_sin_cuenta_no_pertenece_a_cualquiera(): void
    {
        $cliente = $this->usuario();

        $pedido = Pedido::create([
            'usuario_id' => null,
            'origen' => Pedido::ORIGEN_POS,
            'estado' => Pedido::ESTADO_COMPLETADO,
            'total' => 10,
            'fecha' => now(),
        ]);

        /* `null === null` daría acceso a cualquier cuenta con sesión. */
        $this->actingAs($cliente)
            ->getJson("/api/pedidos/{$pedido->id}/receta")
            ->assertForbidden();
    }

    // ------------------------------------------------------------- revisión

    public function test_solo_el_administrador_revisa(): void
    {
        $cliente = $this->usuario();
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()]);

        $this->actingAs($cliente)->putJson("/api/pedidos/{$pedido->id}/receta/revision", [
            'estado' => Receta::ESTADO_VALIDADA,
        ])->assertForbidden();

        $this->assertSame(Receta::ESTADO_PENDIENTE, Receta::first()->estado);
    }

    public function test_la_validacion_deja_constancia_de_quien_decidio(): void
    {
        $cliente = $this->usuario();
        $admin = $this->usuario('administrador');
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()]);

        $this->actingAs($admin)->putJson("/api/pedidos/{$pedido->id}/receta/revision", [
            'estado' => Receta::ESTADO_VALIDADA,
        ])->assertOk();

        $receta = Receta::first();

        /* Sin esto, «validada» no significa nada: no se sabría quién lo dijo. */
        $this->assertSame($admin->id, $receta->revisada_por_id);
        $this->assertNotNull($receta->revisada_en);
    }

    public function test_no_se_puede_rechazar_sin_motivo(): void
    {
        $cliente = $this->usuario();
        $admin = $this->usuario('administrador');
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()]);

        /* Un rechazo sin motivo deja al cliente adivinando qué repetir, y
           acaba en una segunda receta igual de inservible. */
        $this->actingAs($admin)->putJson("/api/pedidos/{$pedido->id}/receta/revision", [
            'estado' => Receta::ESTADO_RECHAZADA,
        ])->assertStatus(422)->assertJsonValidationErrors('motivo_rechazo');
    }

    public function test_validar_borra_un_motivo_de_rechazo_anterior(): void
    {
        $cliente = $this->usuario();
        $admin = $this->usuario('administrador');
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()]);

        $this->actingAs($admin)->putJson("/api/pedidos/{$pedido->id}/receta/revision", [
            'estado' => Receta::ESTADO_RECHAZADA,
            'motivo_rechazo' => 'No se lee la firma.',
        ]);

        $this->actingAs($admin)->putJson("/api/pedidos/{$pedido->id}/receta/revision", [
            'estado' => Receta::ESTADO_VALIDADA,
        ])->assertOk();

        // Un motivo de rechazo colgando de una receta validada se contradice.
        $this->assertNull(Receta::first()->motivo_rechazo);
    }

    // --------------------------------------- la regla que protege al cliente

    public function test_no_se_completa_un_pedido_bajo_receta_sin_receta(): void
    {
        $admin = $this->usuario('administrador');
        $pedido = $this->pedido($this->usuario(), conProductoBajoReceta: true);

        $this->actingAs($admin)->putJson("/api/pedidos/{$pedido->id}", [
            'estado' => Pedido::ESTADO_COMPLETADO,
        ])->assertStatus(422);

        $this->assertSame(Pedido::ESTADO_PENDIENTE, $pedido->fresh()->estado);
    }

    public function test_no_se_completa_con_la_receta_todavia_sin_validar(): void
    {
        $cliente = $this->usuario();
        $admin = $this->usuario('administrador');
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()]);

        /* Adjuntar no es acreditar. Entre lo uno y lo otro está la persona
           que la mira. */
        $this->actingAs($admin)->putJson("/api/pedidos/{$pedido->id}", [
            'estado' => Pedido::ESTADO_COMPLETADO,
        ])->assertStatus(422);
    }

    public function test_con_la_receta_validada_si_se_completa(): void
    {
        $cliente = $this->usuario();
        $admin = $this->usuario('administrador');
        $pedido = $this->pedido($cliente);

        $this->actingAs($cliente)
            ->postJson("/api/pedidos/{$pedido->id}/receta", ['archivo' => $this->imagen()]);

        $this->actingAs($admin)->putJson("/api/pedidos/{$pedido->id}/receta/revision", [
            'estado' => Receta::ESTADO_VALIDADA,
        ]);

        $this->actingAs($admin)->putJson("/api/pedidos/{$pedido->id}", [
            'estado' => Pedido::ESTADO_COMPLETADO,
        ])->assertOk();

        $this->assertSame(Pedido::ESTADO_COMPLETADO, $pedido->fresh()->estado);
    }

    public function test_un_pedido_sin_productos_bajo_receta_se_completa_sin_mas(): void
    {
        $admin = $this->usuario('administrador');
        $pedido = $this->pedido($this->usuario(), conProductoBajoReceta: false);

        /* La regla no puede estorbar al 95 % de los pedidos, que son
           paracetamol y pañales. */
        $this->actingAs($admin)->putJson("/api/pedidos/{$pedido->id}", [
            'estado' => Pedido::ESTADO_COMPLETADO,
        ])->assertOk();
    }

    public function test_confirmar_si_se_permite_mientras_se_revisa_la_receta(): void
    {
        $admin = $this->usuario('administrador');
        $pedido = $this->pedido($this->usuario());

        /* Preparar el pedido mientras el químico farmacéutico mira la receta
           es el flujo normal de la botica. Lo que no puede es salir. */
        $this->actingAs($admin)->putJson("/api/pedidos/{$pedido->id}", [
            'estado' => Pedido::ESTADO_CONFIRMADO,
        ])->assertOk();
    }

    // ---------------------------------------------------------- cola y estado

    public function test_la_cola_de_pendientes_es_solo_del_administrador(): void
    {
        $cliente = $this->usuario();

        $this->actingAs($cliente)->getJson('/api/recetas/pendientes')->assertForbidden();
        $this->actingAs($this->usuario('administrador'))->getJson('/api/recetas/pendientes')->assertOk();
    }

    public function test_el_estado_dice_si_el_pedido_necesita_receta(): void
    {
        $cliente = $this->usuario();
        $conReceta = $this->pedido($cliente, conProductoBajoReceta: true);
        $sinReceta = $this->pedido($cliente, conProductoBajoReceta: false);

        $this->actingAs($cliente)->getJson("/api/pedidos/{$conReceta->id}/receta")
            ->assertOk()
            ->assertJsonPath('requiere_receta', true)
            ->assertJsonPath('receta', null);

        $this->actingAs($cliente)->getJson("/api/pedidos/{$sinReceta->id}/receta")
            ->assertOk()
            ->assertJsonPath('requiere_receta', false);
    }
}
