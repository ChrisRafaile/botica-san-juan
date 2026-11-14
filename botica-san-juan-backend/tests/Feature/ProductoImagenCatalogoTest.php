<?php

namespace Tests\Feature;

use App\Console\Commands\PoblarImagenesProductos;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductoImagenCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private function categoria(): Categoria
    {
        return Categoria::firstOrCreate(
            ['slug' => 'antibioticos'],
            ['nombre' => 'Antibioticos']
        );
    }

    private function crearProducto(array $atributos = []): Producto
    {
        return Producto::create(array_merge([
            'nombre' => 'AMOXICILINA',
            'concentracion' => '500 mg',
            'laboratorio' => 'PORTUGAL',
            'presentacion' => 'Caja Envase Blister Cápsulas',
            'tipo' => 'CAPSULA',
            'categoria_id' => $this->categoria()->id,
            'stock' => 60,
            'precio' => 1.80,
            'requiere_receta' => true,
            'imagen' => PoblarImagenesProductos::IMAGEN_RESPALDO_DEFECTO,
        ], $atributos));
    }

    public function test_comando_poblar_imagenes_asigna_fotos_verificadas_y_es_idempotente(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('productos/38530.webp', 'webp-binario-amoxicilina');
        Storage::disk('public')->put('productos/12313-blister.webp', 'webp-binario-apronax');

        $amoxicilina = $this->crearProducto();
        $apronax = $this->crearProducto([
            'nombre' => 'APRONAX',
            'concentracion' => '550 mg',
            'tipo' => 'TABLETA',
            'requiere_receta' => false,
            'precio' => 2.50,
        ]);
        $paracetamol = $this->crearProducto([
            'nombre' => 'PARACETAMOL',
            'concentracion' => '500 mg',
            'tipo' => 'TABLETA',
            'requiere_receta' => false,
            'precio' => 0.50,
        ]);

        $rutaManifiesto = tempnam(sys_get_temp_dir(), 'corr_') . '.json';
        file_put_contents($rutaManifiesto, json_encode([
            'fuente' => 'https://www.boticasperu.pe',
            'correspondencias' => [
                ['producto_id' => $amoxicilina->id, 'imagen' => 'storage/productos/38530.webp'],
                ['producto_id' => $apronax->id, 'imagen' => 'storage/productos/12313-blister.webp'],
            ],
        ], JSON_UNESCAPED_UNICODE));

        try {
            $this->artisan('catalogo:poblar-imagenes', ['--archivo' => $rutaManifiesto])
                ->expectsOutputToContain('Actualizados: 2 | Sin cambios: 0 | Sin coincidencia (respaldo): 1')
                ->assertSuccessful();

            $this->assertSame('storage/productos/38530.webp', $amoxicilina->fresh()->imagen);
            $this->assertSame('storage/productos/12313-blister.webp', $apronax->fresh()->imagen);
            $this->assertSame(
                PoblarImagenesProductos::IMAGEN_RESPALDO_DEFECTO,
                $paracetamol->fresh()->imagen
            );

            // Segunda ejecución: debe ser estrictamente idempotente
            $this->artisan('catalogo:poblar-imagenes', ['--archivo' => $rutaManifiesto])
                ->expectsOutputToContain('Actualizados: 0 | Sin cambios: 2 | Sin coincidencia (respaldo): 1')
                ->assertSuccessful();
        } finally {
            @unlink($rutaManifiesto);
        }
    }

    public function test_comando_omite_correspondencia_si_archivo_no_existe_en_disco(): void
    {
        Storage::fake('public');

        $albendazol = $this->crearProducto([
            'nombre' => 'ALBENDAZOL',
            'concentracion' => '100 mg/5 mL',
            'presentacion' => 'Frasco x 20 mL',
            'tipo' => 'SUSPENSION',
            'requiere_receta' => false,
        ]);

        $rutaManifiesto = tempnam(sys_get_temp_dir(), 'corr_') . '.json';
        file_put_contents($rutaManifiesto, json_encode([
            'correspondencias' => [
                ['producto_id' => $albendazol->id, 'imagen' => 'storage/productos/inexistente.webp'],
            ],
        ]));

        try {
            $this->artisan('catalogo:poblar-imagenes', ['--archivo' => $rutaManifiesto])
                ->expectsOutputToContain('Archivos faltantes en disco: 1')
                ->assertSuccessful();

            $this->assertSame(
                PoblarImagenesProductos::IMAGEN_RESPALDO_DEFECTO,
                $albendazol->fresh()->imagen
            );
        } finally {
            @unlink($rutaManifiesto);
        }
    }

    public function test_comando_con_restauracion_repone_respaldo_en_huerfanas(): void
    {
        Storage::fake('public');

        $huerfano = $this->crearProducto([
            'nombre' => 'LORATADINA',
            'concentracion' => '10 mg',
            'tipo' => 'TABLETA',
            'imagen' => 'storage/productos/archivo-eliminado.webp',
        ]);

        $rutaManifiesto = tempnam(sys_get_temp_dir(), 'corr_') . '.json';
        file_put_contents($rutaManifiesto, json_encode(['correspondencias' => []]));

        try {
            $this->artisan('catalogo:poblar-imagenes', [
                '--archivo' => $rutaManifiesto,
                '--restaurar-sin-coincidencia' => true,
            ])
                ->expectsOutputToContain('Restaurados a respaldo: 1')
                ->assertSuccessful();

            $this->assertSame(
                PoblarImagenesProductos::IMAGEN_RESPALDO_DEFECTO,
                $huerfano->fresh()->imagen
            );
        } finally {
            @unlink($rutaManifiesto);
        }
    }

    public function test_api_productos_y_catalogo_exponen_rutas_reales_y_respaldo(): void
    {
        $conFoto = $this->crearProducto([
            'nombre' => 'AZITROMICINA',
            'concentracion' => '500 mg',
            'tipo' => 'TABLETA',
            'imagen' => 'storage/productos/14764.webp',
        ]);
        $sinFoto = $this->crearProducto([
            'nombre' => 'IBUPROFENO',
            'concentracion' => '400 mg',
            'tipo' => 'TABLETA',
            'imagen' => PoblarImagenesProductos::IMAGEN_RESPALDO_DEFECTO,
        ]);

        $respLista = $this->getJson('/api/productos')->assertOk();
        $elementos = collect($respLista->json('data') ?? $respLista->json());
        $itemConFoto = $elementos->firstWhere('id', $conFoto->id);
        $itemSinFoto = $elementos->firstWhere('id', $sinFoto->id);

        $this->assertNotNull($itemConFoto);
        $this->assertSame('storage/productos/14764.webp', $itemConFoto['imagen']);
        $this->assertNotNull($itemSinFoto);
        $this->assertSame(PoblarImagenesProductos::IMAGEN_RESPALDO_DEFECTO, $itemSinFoto['imagen']);

        $this->getJson("/api/productos/{$conFoto->id}")
            ->assertOk()
            ->assertJsonPath('imagen', 'storage/productos/14764.webp');

        $this->getJson("/api/catalogo/{$conFoto->id}")
            ->assertOk()
            ->assertJsonPath('datos.imagen', 'storage/productos/14764.webp');
    }
}
