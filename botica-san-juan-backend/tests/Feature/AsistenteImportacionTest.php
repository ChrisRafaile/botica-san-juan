<?php

namespace Tests\Feature;

use App\Ai\Agents\AsistenteBotica;
use App\Ai\Tools\ImportarInventarioDesdeExcelTool;
use App\Models\EventoOperacion;
use App\Models\Lote;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\Importador\ImportadorInventarioService;
use App\Services\Importador\LectorArchivoInventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request as ToolRequest;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AsistenteImportacionTest extends TestCase
{
    use RefreshDatabase;

    private function crearFarmaceutico(): Usuario
    {
        return Usuario::create([
            'nombre' => 'Químico Farmacéutico',
            'email' => 'qf.prueba@boticasanjuan.pe',
            'password' => 'Clave#Segura2026',
            'dni' => '30000003',
            'telefono' => '987000003',
            'rol' => 'cliente',
            'mfa_enabled' => false,
        ]);
    }

    public function test_requiere_autenticacion_y_rol_administrador(): void
    {
        $csv = UploadedFile::fake()->createWithContent(
            'inventario.csv',
            "producto,concentracion,laboratorio,lote,fecha_vencimiento,stock,precio_venta\nAmoxicilina,500mg,Portugal,L-01,2027-08-15,10,1.50\n"
        );

        $this->postJson('/api/asistente/importaciones/previsualizar', ['archivo' => $csv])
            ->assertUnauthorized();

        $cliente = $this->crearCliente();
        Sanctum::actingAs($cliente);

        $this->postJson('/api/asistente/importaciones/previsualizar', ['archivo' => $csv])
            ->assertForbidden();
        $this->postJson('/api/asistente/importaciones/confirmar', ['token_confirmacion' => 'token-falso'])
            ->assertForbidden();

        $farmaceutico = $this->crearFarmaceutico();
        Sanctum::actingAs($farmaceutico);

        $this->postJson('/api/asistente/importaciones/previsualizar', ['archivo' => $csv])
            ->assertForbidden();
        $this->postJson('/api/asistente/importaciones/confirmar', ['token_confirmacion' => 'token-falso'])
            ->assertForbidden();

        // La herramienta solo se expone al administrador y revalida dentro de handle().
        $toolsFarmaceutico = collect((new AsistenteBotica($farmaceutico))->tools())
            ->map(fn ($t) => $t::class)
            ->all();
        $this->assertNotContains(ImportarInventarioDesdeExcelTool::class, $toolsFarmaceutico);

        $admin = $this->crearAdministrador();
        $toolsAdmin = collect((new AsistenteBotica($admin))->tools())
            ->map(fn ($t) => $t::class)
            ->all();
        $this->assertContains(ImportarInventarioDesdeExcelTool::class, $toolsAdmin);

        $toolFarmaceutico = new ImportarInventarioDesdeExcelTool(
            $farmaceutico,
            app(ImportadorInventarioService::class),
        );
        $this->assertStringContainsString(
            'No autorizado',
            (string) $toolFarmaceutico->handle(new ToolRequest(['token_confirmacion' => 'cualquiera']))
        );
    }

    public function test_rechaza_archivo_con_extension_xlsx_falsa_por_mime_real(): void
    {
        Sanctum::actingAs($this->crearAdministrador());

        $falsoXlsx = UploadedFile::fake()->createWithContent(
            'falso.xlsx',
            '<?php echo "no es un excel";'
        );

        $this->postJson('/api/asistente/importaciones/previsualizar', ['archivo' => $falsoXlsx])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_previsualiza_csv_con_tildes_fechas_mixtas_decimales_con_coma_y_confirma_con_tool(): void
    {
        $admin = $this->crearAdministrador();
        Sanctum::actingAs($admin);

        $fechaCercana = now()->addDays(15)->format('d/m/Y');
        $fechaLejanaIso = now()->addMonths(14)->format('Y-m-d');

        $contenidoCsv = "\xEF\xBB\xBF"
            . "Producto;Concentración;Laboratorio;Lote;Fecha Vencimiento;Stock;Precio Venta\n"
            . "Amoxicilina;500mg;Portugal;AMOX-2026A;{$fechaCercana};30;\"1,80\"\n"
            . "Paracetamol;500mg;Genfar;PARA-2027B;{$fechaLejanaIso};50;\"0,90\"\n";

        $archivo = UploadedFile::fake()->createWithContent('reposicion.csv', $contenidoCsv);

        $respPreview = $this->postJson('/api/asistente/importaciones/previsualizar', [
            'archivo' => $archivo,
        ])->assertOk();

        $respPreview->assertJsonPath('formato', 'csv');
        $respPreview->assertJsonPath('resumen.total_filas', 2);
        $respPreview->assertJsonPath('resumen.validas', 2);
        $respPreview->assertJsonPath('resumen.con_errores', 0);

        $token = $respPreview->json('token_confirmacion');
        $this->assertNotEmpty($token);

        // Nada se ha escrito aún en base de datos.
        $this->assertDatabaseCount('productos', 0);
        $this->assertDatabaseCount('lotes', 0);
        $this->assertDatabaseCount('eventos_operacion', 0);

        // Confirmación mediante la tool de IA.
        $tool = new ImportarInventarioDesdeExcelTool($admin, app(ImportadorInventarioService::class));
        $salidaTool = json_decode((string) $tool->handle(new ToolRequest([
            'token_confirmacion' => $token,
        ])), true);

        $this->assertSame('confirmada', $salidaTool['estado']);
        $this->assertSame(2, $salidaTool['resultado']['productos_creados']);
        $this->assertSame(2, $salidaTool['resultado']['lotes_creados']);
        $this->assertSame(80, $salidaTool['resultado']['unidades_ingresadas']);

        $amox = Producto::where('nombre', 'Amoxicilina')->firstOrFail();
        $this->assertSame(30, (int) $amox->stock);
        $this->assertSame('1.80', (string) $amox->precio);

        $loteAmox = Lote::where('producto_id', $amox->id)->where('codigo_lote', 'AMOX-2026A')->firstOrFail();
        $this->assertSame(30, (int) $loteAmox->cantidad_actual);
        $this->assertSame('activo', $loteAmox->estado);

        $this->assertSame(2, MovimientoStock::count());
        $this->assertDatabaseHas('eventos_operacion', [
            'codigo' => 'IMPORTACION_INVENTARIO',
            'usuario_id' => $admin->id,
            'origen' => 'asistente_ia',
        ]);

        // El token es de un solo uso: reutilizarlo debe fallar y no duplicar stock.
        $reintento = (string) $tool->handle(new ToolRequest(['token_confirmacion' => $token]));
        $this->assertStringContainsString('inválido, ha caducado o ya fue utilizado', $reintento);
        $this->assertSame(30, (int) $amox->fresh()->stock);
    }

    public function test_previsualiza_xlsx_detecta_duplicados_y_suma_lote_existente_fefo(): void
    {
        $admin = $this->crearAdministrador();
        Sanctum::actingAs($admin);

        $producto = Producto::create([
            'nombre' => 'Amoxicilina',
            'concentracion' => '500mg',
            'laboratorio' => 'Portugal',
            'presentacion' => 'Unidad',
            'tipo' => 'Medicamento',
            'stock' => 20,
            'precio' => 1.50,
            'afecto_igv' => true,
            'tipo_afectacion_igv' => Producto::GRAVADO,
        ]);

        Lote::create([
            'producto_id' => $producto->id,
            'codigo_lote' => 'AMOX-EXISTENTE',
            'fecha_vencimiento' => now()->addMonths(8)->toDateString(),
            'cantidad_inicial' => 20,
            'cantidad_actual' => 20,
            'estado' => 'activo',
        ]);

        $rutaTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_import_' . uniqid() . '.xlsx';
        $fecha1 = now()->addMonths(8)->format('d/m/Y');
        $fecha2 = now()->addMonths(18)->format('Y-m-d');

        LectorArchivoInventarioService::generarXlsx(
            $rutaTmp,
            ['producto', 'concentracion', 'laboratorio', 'lote', 'fecha_vencimiento', 'stock', 'precio_venta'],
            [
                ['Amoxicilina', '500mg', 'Portugal', 'AMOX-EXISTENTE', $fecha1, 15, '1,75'],
                ['Amoxicilina', '500mg', 'Portugal', 'AMOX-EXISTENTE', $fecha1, 15, '1,75'], // Duplicado intra-archivo
                ['Amoxicilina', '500mg', 'Portugal', 'AMOX-NUEVO', $fecha2, 25, '1.75'],
                ['Ibuprofeno', '400mg', 'Genfar', 'IBU-ERR', 'fecha-mala', -5, '0'], // Fila con errores
            ],
        );

        try {
            $archivo = new UploadedFile(
                $rutaTmp,
                'reposicion.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true,
            );

            $resp = $this->postJson('/api/asistente/importaciones/previsualizar', [
                'archivo' => $archivo,
            ])->assertOk();

            $resp->assertJsonPath('formato', 'xlsx');
            $resp->assertJsonPath('resumen.total_filas', 4);
            $resp->assertJsonPath('resumen.validas', 2);
            $resp->assertJsonPath('resumen.duplicados', 2); // 1 intra-archivo + 1 lote existente
            $resp->assertJsonPath('resumen.con_errores', 3); // fecha, stock y precio de fila 5

            $token = $resp->json('token_confirmacion');
            $this->postJson('/api/asistente/importaciones/confirmar', [
                'token_confirmacion' => $token,
            ])->assertOk()
                ->assertJsonPath('resultado.lotes_actualizados', 1)
                ->assertJsonPath('resultado.lotes_creados', 1)
                ->assertJsonPath('resultado.unidades_ingresadas', 40);

            $this->assertSame(60, (int) $producto->fresh()->stock);

            // Orden FEFO: AMOX-EXISTENTE (8 meses) sale antes que AMOX-NUEVO (18 meses).
            $lotesFefo = Lote::delProducto($producto->id)->disponible()->ordenFefo()->pluck('codigo_lote')->all();
            $this->assertSame(['AMOX-EXISTENTE', 'AMOX-NUEVO'], $lotesFefo);

            // Token ya consumido -> 422
            $this->postJson('/api/asistente/importaciones/confirmar', [
                'token_confirmacion' => $token,
            ])->assertStatus(422);
        } finally {
            @unlink($rutaTmp);
        }
    }

    public function test_token_caducado_o_de_otro_admin_no_escribe_en_bd(): void
    {
        $admin1 = $this->crearAdministrador(['email' => 'admin1@boticasanjuan.pe', 'dni' => '10000011']);
        $admin2 = $this->crearAdministrador(['email' => 'admin2@boticasanjuan.pe', 'dni' => '10000012']);

        Sanctum::actingAs($admin1);
        $csv = UploadedFile::fake()->createWithContent(
            'lote.csv',
            "producto,concentracion,laboratorio,lote,fecha_vencimiento,stock,precio_venta\nAzitromicina,500mg,Medrock,AZI-01,2028-01-10,12,4.50\n"
        );

        $token = $this->postJson('/api/asistente/importaciones/previsualizar', ['archivo' => $csv])
            ->assertOk()
            ->json('token_confirmacion');

        // Otro administrador no puede confirmar ni quemar el token de admin1.
        Sanctum::actingAs($admin2);
        $this->postJson('/api/asistente/importaciones/confirmar', ['token_confirmacion' => $token])
            ->assertStatus(422);
        $this->assertDatabaseCount('productos', 0);

        // Si caduca el token en caché, admin1 tampoco puede confirmar.
        Cache::forget("asistente_importacion:{$token}");
        Sanctum::actingAs($admin1);
        $this->postJson('/api/asistente/importaciones/confirmar', ['token_confirmacion' => $token])
            ->assertStatus(422);
        $this->assertDatabaseCount('productos', 0);
        $this->assertSame(0, EventoOperacion::count());
    }
}
