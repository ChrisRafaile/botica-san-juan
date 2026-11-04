<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El contrato público del catálogo.
 * ---------------------------------------------------------------------------
 * PARA QUÉ SIRVEN ESTAS PRUEBAS
 *
 * No comprueban que el catálogo «funcione». Comprueban que su FORMA no cambie,
 * porque hay proyectos ajenos consumiéndola y un cambio nuestro les rompe el
 * suyo sin que nos enteremos.
 *
 * De ahí que varias afirmen la AUSENCIA de campos. Añadir `stock` a esta
 * respuesta sería un descuido de una línea —y publicaría cuánto queda de cada
 * medicamento—, así que la ausencia se verifica igual que la presencia.
 */
class CatalogoPublicoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La MISMA categoría en todas las llamadas.
     *
     * Con `create()` a secas, el segundo producto de una prueba reventaba por
     * el índice único del slug: cada llamada intentaba crear «analgesicos»
     * otra vez.
     */
    private function categoria(): Categoria
    {
        return Categoria::firstOrCreate(
            ['slug' => 'analgesicos'],
            ['nombre' => 'Analgesicos'],
        );
    }

    private function producto(array $extra = []): Producto
    {
        return Producto::create(array_merge([
            'nombre' => 'Paracetamol',
            'concentracion' => '500 mg',
            'laboratorio' => 'Laboratorio Prueba',
            'presentacion' => 'Tableta',
            'tipo' => 'medicamento',
            'categoria_id' => $this->categoria()->id,
            'stock' => 40,
            'precio' => 1.50,
            'requiere_receta' => false,
        ], $extra));
    }

    // ------------------------------------------------- acceso

    public function test_el_catalogo_se_consume_sin_token(): void
    {
        /* Es el requisito entero del bloque: otro proyecto tiene que poder
           llamar sin credenciales, como se consume PokeAPI. */
        $this->producto();

        $this->getJson('/api/catalogo')->assertOk();
    }

    public function test_el_detalle_tambien(): void
    {
        $producto = $this->producto();

        $this->getJson("/api/catalogo/{$producto->id}")->assertOk();
    }

    public function test_los_destacados_tambien(): void
    {
        $this->producto();

        $this->getJson('/api/catalogo/destacados')->assertOk();
    }

    // ------------------------------------------------- la forma

    public function test_la_respuesta_tiene_exactamente_los_campos_del_contrato(): void
    {
        $this->producto();

        $respuesta = $this->getJson('/api/catalogo')->assertOk();

        $primero = $respuesta->json('datos.0');

        /* Se compara el conjunto COMPLETO de claves, no que estén las que
           interesan. Así, añadir un campo obliga a pasar por aquí y decidirlo
           en lugar de que se cuele. */
        $this->assertSame([
            'id',
            'nombre',
            'concentracion',
            'presentacion',
            'laboratorio',
            'principio_activo',
            'categoria',
            'categoria_slug',
            'precio',
            'moneda',
            'requiere_receta',
            'disponible',
            'imagen',
        ], array_keys($primero));
    }

    public function test_el_listado_y_el_detalle_tienen_la_misma_forma(): void
    {
        /* Si divergieran, un consumidor que lee el listado y luego pide el
           detalle encontraría campos distintos para el mismo producto. */
        $producto = $this->producto();

        $delListado = $this->getJson('/api/catalogo')->json('datos.0');
        $delDetalle = $this->getJson("/api/catalogo/{$producto->id}")->json('datos');

        $this->assertSame(array_keys($delListado), array_keys($delDetalle));
    }

    public function test_el_stock_no_sale_nunca(): void
    {
        /* Cuánto queda de un medicamento es información comercial y, agregada
           en el tiempo, dice qué se está vendiendo en el barrio. Sale si HAY
           o no hay, que es lo único que un catálogo necesita responder. */
        $this->producto(['stock' => 37]);

        $cuerpo = $this->getJson('/api/catalogo')->getContent();

        $this->assertStringNotContainsString('"stock"', $cuerpo);
        $this->assertStringNotContainsString('37', $cuerpo);
        $this->assertTrue($this->getJson('/api/catalogo')->json('datos.0.disponible'));
    }

    public function test_sin_existencias_el_producto_aparece_como_no_disponible(): void
    {
        $this->producto(['stock' => 0]);

        $this->assertFalse($this->getJson('/api/catalogo')->json('datos.0.disponible'));
    }

    public function test_la_paginacion_va_aparte_de_los_datos(): void
    {
        $this->producto();

        $this->getJson('/api/catalogo')
            ->assertOk()
            ->assertJsonStructure([
                'datos',
                'paginacion' => ['pagina', 'por_pagina', 'total', 'paginas'],
            ]);
    }

    // ------------------------------------------------- filtros

    public function test_se_puede_buscar_por_nombre(): void
    {
        $this->producto(['nombre' => 'Paracetamol']);
        $this->producto(['nombre' => 'Ibuprofeno']);

        $respuesta = $this->getJson('/api/catalogo?buscar=ibupro')->assertOk();

        $this->assertSame(1, $respuesta->json('paginacion.total'));
        $this->assertSame('Ibuprofeno', $respuesta->json('datos.0.nombre'));
    }

    public function test_se_puede_filtrar_por_el_slug_de_la_categoria(): void
    {
        /* Por el slug y no solo por el id: un consumidor externo descubre el
           slug leyendo la respuesta, mientras que el id le obligaría a
           conocer nuestra base de datos. */
        $this->producto();

        $this->getJson('/api/catalogo?categoria=analgesicos')
            ->assertOk()
            ->assertJsonPath('paginacion.total', 1);

        $this->getJson('/api/catalogo?categoria=no-existe')
            ->assertOk()
            ->assertJsonPath('paginacion.total', 0);
    }

    public function test_el_tamano_de_pagina_tiene_tope(): void
    {
        /* Sin tope, `?per_page=100000` es una descarga de la tabla entera en
           una sola petición. */
        $this->producto();

        $this->getJson('/api/catalogo?per_page=5000')->assertStatus(422);
    }

    // ------------------------------------------------- destacados

    public function test_los_destacados_excluyen_lo_que_exige_receta(): void
    {
        /* Es una vitrina pública. Una lista de medicamentos controlados en la
           portada de una API abierta no es buena idea aunque los datos sean
           públicos. */
        $this->producto(['nombre' => 'Libre', 'requiere_receta' => false]);
        $this->producto(['nombre' => 'Controlado', 'requiere_receta' => true]);

        $nombres = array_column($this->getJson('/api/catalogo/destacados')->json('datos'), 'nombre');

        $this->assertContains('Libre', $nombres);
        $this->assertNotContains('Controlado', $nombres);
    }

    public function test_los_destacados_excluyen_lo_que_no_hay(): void
    {
        $this->producto(['nombre' => 'Hay', 'stock' => 10]);
        $this->producto(['nombre' => 'Agotado', 'stock' => 0]);

        $nombres = array_column($this->getJson('/api/catalogo/destacados')->json('datos'), 'nombre');

        $this->assertContains('Hay', $nombres);
        $this->assertNotContains('Agotado', $nombres);
    }

    public function test_los_destacados_declaran_su_criterio(): void
    {
        /* No hay columna «destacado» ni nadie los marca a mano. Un criterio
           opaco sería peor que no tener el endpoint, así que la respuesta
           dice con qué regla se eligieron. */
        $this->producto();

        $this->getJson('/api/catalogo/destacados')
            ->assertOk()
            ->assertJsonStructure(['datos', 'criterio']);
    }

    // ------------------------------------------------- errores

    public function test_un_identificador_que_no_existe_da_404(): void
    {
        $this->getJson('/api/catalogo/999999')->assertStatus(404);
    }

    public function test_una_palabra_en_lugar_de_un_id_no_revienta(): void
    {
        /* ESTE ES EL FALLO QUE HABIA EN PRODUCCION.
           /api/productos/destacados caía en productos/{producto}, PostgreSQL
           intentaba convertir «destacados» en entero y la respuesta era un
           500 en una ruta pública. Con el parámetro restringido a dígitos,
           la ruta simplemente no casa y devuelve 404. */
        $this->getJson('/api/productos/destacados')->assertStatus(404);
        $this->getJson('/api/catalogo/loquesea')->assertStatus(404);
    }

    // ------------------------------------------------- CORS

    public function test_un_origen_cualquiera_puede_leer_el_catalogo(): void
    {
        /* Es el punto que hace que esto sirva de algo: el frontend de otro
           proyecto, en su propio dominio, tiene que poder hacer fetch sin que
           el navegador bloquee la respuesta. */
        $this->producto();

        $this->getJson('/api/catalogo', ['Origin' => 'https://proyecto-de-un-companero.vercel.app'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_el_carril_abierto_no_lleva_credenciales(): void
    {
        /* '*' y credenciales son incompatibles por norma del navegador, y
           ademas estas rutas no usan cookie de sesion. Anunciar las dos cosas
           haria que el navegador rechazara la respuesta. */
        $this->producto();

        $respuesta = $this->getJson('/api/catalogo', ['Origin' => 'https://otro-dominio.test']);

        /* El middleware anuncia la cabecera con valor «false» en vez de
           omitirla. Para el navegador es lo mismo: solo «true» habilita las
           credenciales. Lo que hay que afirmar es eso —que NO vale «true»— y
           no que la cabecera falte, que es un detalle de implementacion. */
        $this->assertNotSame('true', $respuesta->headers->get('Access-Control-Allow-Credentials'));
    }
}
