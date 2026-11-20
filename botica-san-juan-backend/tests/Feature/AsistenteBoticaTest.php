<?php

namespace Tests\Feature;

use App\Ai\Agents\AsistenteBotica;
use App\Ai\Tools\AlertasVencimientoTool;
use App\Ai\Tools\BuscarMedicamentosTool;
use App\Ai\Tools\ConsultarStockYLotesFefoTool;
use App\Ai\Tools\ResumenVentasYCajaTool;
use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Subcategoria;
use App\Services\AlertaVencimientoService;
use App\Services\CajaService;
use App\Services\ResumenTableroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request as ToolRequest;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cubre el Asistente IA de Botica usando el fake del SDK (sin red).
 */
class AsistenteBoticaTest extends TestCase
{
    use RefreshDatabase;

    private function configurarClaveGemini(?string $clave): void
    {
        config()->set('ai.providers.gemini.key', $clave);
        config()->set('ai.providers.groq.key', null);
        config()->set('ai.providers.openrouter.key', null);
    }

    public function test_groq_es_el_proveedor_primario_por_defecto(): void
    {
        config()->set('ai.providers.gemini.key', 'clave-de-prueba');
        config()->set('ai.providers.groq.key', 'clave-de-prueba');
        config()->set('ai.providers.openrouter.key', null);

        $proveedores = app(\App\Services\AsistenteService::class)->proveedoresConfigurados();

        $this->assertSame(['groq', 'gemini'], array_keys($proveedores));
        $this->assertSame('openai/gpt-oss-120b', $proveedores['groq']);
    }

    public function test_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/asistente/estado')->assertUnauthorized();
        $this->postJson('/api/asistente/mensajes', ['mensaje' => 'Hola'])->assertUnauthorized();
    }

    public function test_sin_claves_de_proveedor_devuelve_503_controlado(): void
    {
        $this->configurarClaveGemini(null);
        Sanctum::actingAs($this->crearAdministrador());

        $this->getJson('/api/asistente/estado')
            ->assertOk()
            ->assertJsonPath('habilitado', false);

        $this->postJson('/api/asistente/mensajes', ['mensaje' => '¿Hay amoxicilina?'])
            ->assertStatus(503)
            ->assertJsonStructure(['message']);
    }

    public function test_fallo_del_proveedor_devuelve_502_y_no_marca_el_asistente_como_deshabilitado(): void
    {
        $this->configurarClaveGemini('clave-de-prueba');
        Sanctum::actingAs($this->crearAdministrador());

        AsistenteBotica::fake(fn () => throw new \RuntimeException('proveedor caído'));

        $this->postJson('/api/asistente/mensajes', ['mensaje' => '¿Hay amoxicilina?'])
            ->assertStatus(502)
            ->assertJsonStructure(['message']);

        $this->getJson('/api/asistente/estado')->assertJsonPath('habilitado', true);
    }

    public function test_rol_cliente_solo_recibe_herramienta_publica_y_admin_todas(): void
    {
        $cliente = $this->crearCliente();
        $admin = $this->crearAdministrador();

        $agenteCliente = new AsistenteBotica($cliente);
        $herramientasCliente = collect($agenteCliente->tools())->map(fn ($t) => $t::class)->all();

        $this->assertSame([BuscarMedicamentosTool::class], $herramientasCliente);

        $agenteAdmin = new AsistenteBotica($admin);
        $herramientasAdmin = collect($agenteAdmin->tools())->map(fn ($t) => $t::class)->all();

        $this->assertContains(BuscarMedicamentosTool::class, $herramientasAdmin);
        $this->assertContains(ConsultarStockYLotesFefoTool::class, $herramientasAdmin);
        $this->assertContains(AlertasVencimientoTool::class, $herramientasAdmin);
        $this->assertContains(ResumenVentasYCajaTool::class, $herramientasAdmin);

        // Revalidación dentro de cada herramienta interna frente a un cliente
        $req = new ToolRequest(['termino' => 'Amoxicilina']);
        $this->assertStringContainsString('No autorizado', (string) (new ConsultarStockYLotesFefoTool($cliente))->handle($req));
        $this->assertStringContainsString('No autorizado', (string) (new AlertasVencimientoTool($cliente, app(AlertaVencimientoService::class)))->handle(new ToolRequest([])));
        $this->assertStringContainsString('No autorizado', (string) (new ResumenVentasYCajaTool($cliente, app(ResumenTableroService::class), app(CajaService::class)))->handle(new ToolRequest([])));
    }

    public function test_responde_con_fake_del_sdk_y_valida_limites(): void
    {
        $this->configurarClaveGemini('clave-ficticia-de-prueba');
        AsistenteBotica::fake([
            'Tenemos Amoxicilina 500mg disponible en mostrador.',
        ]);

        Sanctum::actingAs($this->crearCliente());

        $this->getJson('/api/asistente/estado')
            ->assertOk()
            ->assertJsonPath('habilitado', true)
            ->assertJsonPath('rol', 'cliente');

        $respuesta = $this->postJson('/api/asistente/mensajes', [
            'mensaje' => '¿Tienen amoxicilina 500mg?',
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('respuesta', 'Tenemos Amoxicilina 500mg disponible en mostrador.');

        AsistenteBotica::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'amoxicilina'));

        // Límite de longitud
        config()->set('asistente.limites.max_longitud', 50);
        $this->postJson('/api/asistente/mensajes', [
            'mensaje' => str_repeat('a', 60),
        ])->assertUnprocessable();
    }

    public function test_herramienta_de_lotes_devuelve_datos_reales_de_lote_creado(): void
    {
        $admin = $this->crearAdministrador();

        $categoria = Categoria::create(['nombre' => 'Antibioticos', 'slug' => 'antibioticos']);
        $subcategoria = Subcategoria::create([
            'categoria_id' => $categoria->id,
            'nombre'       => 'Capsulas',
            'slug'         => 'capsulas',
        ]);

        $producto = Producto::create([
            'nombre'              => 'Amoxicilina 500mg',
            'concentracion'       => '500mg',
            'laboratorio'         => 'Portugal',
            'presentacion'        => 'Capsula',
            'tipo'                => 'Generico',
            'categoria_id'        => $categoria->id,
            'subcategoria_id'     => $subcategoria->id,
            'stock'               => 25,
            'precio'              => 1.50,
            'principio_activo'    => 'Amoxicilina',
            'afecto_igv'          => true,
            'tipo_afectacion_igv' => 'gravado',
        ]);

        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'AMOX-IA-01',
            'fecha_vencimiento' => now()->startOfDay()->addDays(22),
            'cantidad_inicial'  => 25,
            'cantidad_actual'   => 25,
            'costo_unitario'    => 0.85,
            'estado'            => 'activo',
        ]);

        $herramienta = new ConsultarStockYLotesFefoTool($admin);
        $json = (string) $herramienta->handle(new ToolRequest(['termino' => 'Amoxicilina']));
        $datos = json_decode($json, true);

        $this->assertNotEmpty($datos['productos']);
        $this->assertSame('Amoxicilina 500mg', $datos['productos'][0]['nombre']);
        $this->assertSame('AMOX-IA-01', $datos['productos'][0]['lotes_fefo'][0]['codigo_lote']);
        $this->assertSame(22, $datos['productos'][0]['lotes_fefo'][0]['dias_para_vencer']);
        $this->assertSame(25, $datos['productos'][0]['lotes_fefo'][0]['cantidad_actual']);
    }
}
