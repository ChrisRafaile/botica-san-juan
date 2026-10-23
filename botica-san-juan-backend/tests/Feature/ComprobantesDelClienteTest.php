<?php

namespace Tests\Feature;

use App\Models\ComprobanteElectronico;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Un comprobante solo lo ve quien hizo la compra.
 * ---------------------------------------------------------------------------
 * ESTAS PRUEBAS NACEN DE UN FALLO REAL, NO DE UN SUPUESTO
 *
 * Las rutas de descarga de XML y PDF estaban dentro del grupo autenticado pero
 * sin control de rol ni de propiedad: el controlador hacia `findOrFail($id)` y
 * entregaba el documento. Cualquier cuenta con sesion iniciada podia recorrer
 * identificadores y bajarse los comprobantes de los demas, con nombre, DNI o
 * RUC, productos comprados e importe.
 *
 * Es la segunda vez que ocurre lo mismo: el defecto D-05 del APF2 fue
 * exactamente esto en el historial de pedidos. La primera vez se corrigio
 * escribiendo la comprobacion a mano dentro del metodo, y por eso volvio a
 * pasar en otro endpoint. Ahora la regla vive en una politica y estas pruebas
 * la fijan para que no se afloje por comodidad.
 */
class ComprobantesDelClienteTest extends TestCase
{
    use RefreshDatabase;

    private function cliente(string $dni, string $nombre = 'Cliente'): Usuario
    {
        return Usuario::create([
            'nombre'   => $nombre,
            'email'    => $dni.'@prueba.local',
            'dni'      => $dni,
            'password' => Hash::make('clave-de-prueba-'.$dni),
            'rol'      => 'cliente',
        ]);
    }

    private function administrador(): Usuario
    {
        return Usuario::create([
            'nombre'   => 'Administradora',
            'email'    => 'admin.comprobantes@prueba.local',
            'dni'      => '99999999',
            'password' => Hash::make('clave-de-prueba-admin'),
            'rol'      => 'administrador',
        ]);
    }

    /** Pedido con su comprobante. `$duenyo` a null simula una compra de invitado. */
    private function comprobanteDe(?Usuario $duenyo, float $total = 50.00): ComprobanteElectronico
    {
        $pedido = Pedido::create([
            'usuario_id'       => $duenyo?->id,
            'origen'           => Pedido::ORIGEN_WEB,
            'cliente_nombre'   => $duenyo?->nombre ?? 'Invitado de mostrador',
            'cliente_documento'=> $duenyo?->dni ?? '00000001',
            'fecha'            => now(),
            'estado'           => 'pendiente',
            'total'            => $total,
        ]);

        return ComprobanteElectronico::create([
            'pedido_id'        => $pedido->id,
            'tipo_comprobante' => 'boleta',
            'serie'            => 'B001',
            'numero'           => random_int(1, 99999),
            'cliente_nombre'   => $pedido->cliente_nombre,
            'cliente_documento'=> $pedido->cliente_documento,
            'total'            => $total,
            'estado_sunat'     => 'pendiente',
            'fecha_emision'    => now(),
        ]);
    }

    public function test_el_duenyo_puede_ver_su_comprobante(): void
    {
        $duenyo = $this->cliente('10000011', 'Rosa');
        $comprobante = $this->comprobanteDe($duenyo);

        Sanctum::actingAs($duenyo);

        $this->getJson("/api/comprobantes/{$comprobante->id}")
            ->assertOk()
            ->assertJsonPath('data.comprobante.serie', 'B001');
    }

    public function test_un_cliente_no_puede_ver_el_comprobante_de_otro(): void
    {
        /* El caso que estaba abierto. Si esta prueba se pone en verde por
           accidente —porque alguien quita la politica— significa que el
           agujero volvio. */
        $duenyo  = $this->cliente('10000012', 'Rosa');
        $intruso = $this->cliente('10000013', 'Otro');
        $comprobante = $this->comprobanteDe($duenyo);

        Sanctum::actingAs($intruso);

        $this->getJson("/api/comprobantes/{$comprobante->id}")->assertStatus(403);
    }

    public function test_un_cliente_no_puede_descargar_el_xml_ni_el_pdf_de_otro(): void
    {
        /* Las dos rutas por las que el fallo estaba realmente abierto. */
        $duenyo  = $this->cliente('10000014');
        $intruso = $this->cliente('10000015');
        $comprobante = $this->comprobanteDe($duenyo);

        Sanctum::actingAs($intruso);

        $this->getJson("/api/facturacion/documentos/{$comprobante->id}/xml")->assertStatus(403);
        $this->getJson("/api/facturacion/documentos/{$comprobante->id}/pdf")->assertStatus(403);
    }

    public function test_el_administrador_ve_cualquier_comprobante(): void
    {
        /* Quien atiende el mostrador emite los comprobantes: los ve por
           definicion del puesto. */
        $duenyo = $this->cliente('10000016');
        $comprobante = $this->comprobanteDe($duenyo);

        Sanctum::actingAs($this->administrador());

        $this->getJson("/api/comprobantes/{$comprobante->id}")->assertOk();
    }

    public function test_sin_token_no_se_ve_ningun_comprobante(): void
    {
        $comprobante = $this->comprobanteDe($this->cliente('10000017'));

        $this->getJson("/api/comprobantes/{$comprobante->id}")->assertStatus(401);
        $this->getJson('/api/comprobantes/mios')->assertStatus(401);
    }

    public function test_el_comprobante_de_una_compra_de_invitado_no_tiene_duenyo_en_el_portal(): void
    {
        /* Un pedido de invitado guarda el documento de identidad pero no la
           cuenta. Dejar que un cliente registrado lo reclamara por coincidir
           el DNI convertiria el portal en una forma de heredar las compras de
           cualquiera cuyo documento se conozca. Se recoge en el mostrador. */
        $comprobante = $this->comprobanteDe(null);
        $curioso = $this->cliente('00000001', 'Mismo documento que el invitado');

        Sanctum::actingAs($curioso);

        $this->getJson("/api/comprobantes/{$comprobante->id}")->assertStatus(403);
    }

    public function test_el_listado_solo_devuelve_los_comprobantes_propios(): void
    {
        $duenyo = $this->cliente('10000018');
        $otro   = $this->cliente('10000019');

        $mio  = $this->comprobanteDe($duenyo, 30.00);
        $ajeno = $this->comprobanteDe($otro, 80.00);

        Sanctum::actingAs($duenyo);

        $respuesta = $this->getJson('/api/comprobantes/mios')->assertOk();

        $ids = array_column($respuesta->json('data'), 'id');

        $this->assertContains($mio->id, $ids);
        $this->assertNotContains($ajeno->id, $ids);
        $this->assertSame(1, $respuesta->json('total'));
    }

    public function test_el_listado_no_filtra_datos_internos_del_negocio(): void
    {
        /* La tabla guarda el payload y la respuesta de SUNAT. No le sirven de
           nada al cliente y pueden contener informacion interna, asi que la
           respuesta elige los campos uno a uno en vez de volcar el modelo. */
        $duenyo = $this->cliente('10000020');
        $this->comprobanteDe($duenyo);

        Sanctum::actingAs($duenyo);

        $fila = $this->getJson('/api/comprobantes/mios')->json('data.0');

        $this->assertArrayNotHasKey('sunat_payload', $fila);
        $this->assertArrayNotHasKey('sunat_response', $fila);
        $this->assertArrayNotHasKey('hash_documento', $fila);
        $this->assertArrayHasKey('correlativo', $fila);
    }
}
