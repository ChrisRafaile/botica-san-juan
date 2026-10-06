<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Producto;
use App\Services\DesgloseFiscalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cotización del carrito público.
 *
 * Lo que de verdad se comprueba aquí es que **el servidor no se fía del
 * navegador**. El tope de cantidad que pinta la pantalla es una comodidad; si
 * alguien edita el localStorage o llama a la API a mano, el límite tiene que
 * seguir en pie. Por eso varias pruebas mandan a propósito más de lo que hay.
 */
class CarritoCotizarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Compara un importe de la respuesta.
     *
     * No se usa `assertJsonPath` con un float porque JSON no distingue 100 de
     * 100.0: `json_encode(100.00)` escribe `100`, que al decodificar vuelve
     * como entero y rompe la comparación estricta. Eso es una propiedad del
     * formato, no un fallo del endpoint —cualquier cliente JS lee las dos
     * formas igual—, así que lo que se compara es el VALOR, no el tipo.
     */
    private function assertImporte(float $esperado, mixed $recibido, string $que): void
    {
        $this->assertIsNumeric($recibido, "$que debería ser un número");
        $this->assertEqualsWithDelta($esperado, (float) $recibido, 0.001, $que);
    }

    /** Producto con lotes vendibles por la cantidad indicada. */
    private function producto(float $precio, int $unidades, array $extra = []): Producto
    {
        $producto = Producto::create(array_merge([
            'nombre'        => 'PRODUCTO DE PRUEBA '.uniqid(),
            'concentracion' => '500 mg',
            'presentacion'  => 'Caja',
            'laboratorio'   => 'LAB PRUEBA',
            'tipo'          => 'TABLETA',
            'stock'         => $unidades,
            'precio'        => $precio,
        ], $extra));

        if ($unidades > 0) {
            Lote::create([
                'producto_id'       => $producto->id,
                'codigo_lote'       => 'L-'.uniqid(),
                'cantidad_inicial'  => $unidades,
                'cantidad_actual'   => $unidades,
                'fecha_vencimiento' => now()->addYear()->toDateString(),
                'estado'            => 'activo',
            ]);
        }

        return $producto;
    }

    public function test_el_endpoint_es_publico_y_no_exige_sesion(): void
    {
        $producto = $this->producto(10.00, 5);

        $this->postJson('/api/carrito/cotizar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 2]],
        ])->assertOk();
    }

    public function test_recorta_la_cantidad_al_stock_vendible_aunque_el_navegador_pida_mas(): void
    {
        $producto = $this->producto(10.00, 3);

        $respuesta = $this->postJson('/api/carrito/cotizar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 99]],
        ])->assertOk();

        $respuesta->assertJsonPath('lineas.0.cantidad', 3);
        $respuesta->assertJsonPath('lineas.0.stock_disponible', 3);
        $respuesta->assertJsonPath('ajustes.0.motivo', 'stock_insuficiente');

        /* Y el importe se calcula sobre lo servible, no sobre lo pedido. */
        $this->assertImporte(30.00, $respuesta->json('lineas.0.subtotal'), 'subtotal servible');
    }

    public function test_un_lote_vencido_no_cuenta_como_stock_vendible(): void
    {
        $producto = $this->producto(10.00, 0);

        /* Existe en el anaquel —`productos.stock` lo cuenta— pero está vencido:
           no se puede entregar, así que el carrito no puede prometerlo. */
        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'L-VENCIDO',
            'cantidad_inicial'  => 50,
            'cantidad_actual'   => 50,
            'fecha_vencimiento' => now()->subDay()->toDateString(),
            'estado'            => 'activo',
        ]);

        $respuesta = $this->postJson('/api/carrito/cotizar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertOk();

        $respuesta->assertJsonPath('lineas.0.stock_disponible', 0);
        $respuesta->assertJsonPath('lineas.0.cantidad', 0);
        $respuesta->assertJsonPath('ajustes.0.motivo', 'sin_stock');
    }

    public function test_dos_lineas_del_mismo_producto_comparten_el_tope_de_stock(): void
    {
        /* Pasa de verdad: añadir el mismo producto desde dos pestañas. Si cada
           línea se topara por separado, el carrito prometería el doble. */
        $producto = $this->producto(10.00, 4);

        $respuesta = $this->postJson('/api/carrito/cotizar', [
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 3],
                ['producto_id' => $producto->id, 'cantidad' => 3],
            ],
        ])->assertOk();

        $respuesta->assertJsonCount(1, 'lineas');
        $respuesta->assertJsonPath('lineas.0.cantidad', 4);
    }

    public function test_el_precio_lo_pone_el_servidor_y_no_el_cliente(): void
    {
        $producto = $this->producto(25.50, 10);

        $respuesta = $this->postJson('/api/carrito/cotizar', [
            /* El navegador manda un precio inventado; debe ser ignorado. */
            'items' => [['producto_id' => $producto->id, 'cantidad' => 2, 'precio' => 0.01]],
        ])->assertOk();

        $this->assertImporte(25.50, $respuesta->json('lineas.0.precio'), 'precio del servidor');
        $this->assertImporte(51.00, $respuesta->json('lineas.0.subtotal'), 'subtotal');
    }

    public function test_el_igv_se_extrae_del_precio_no_se_suma_encima(): void
    {
        /* S/ 118.00 con IGV incluido al 18 % => base 100.00, IGV 18.00.
           Si el impuesto se sumara, el total sería 139.24. */
        $producto = $this->producto(118.00, 5);

        $respuesta = $this->postJson('/api/carrito/cotizar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertOk();

        $this->assertImporte(100.00, $respuesta->json('desglose.subtotal_gravado'), 'base imponible');
        $this->assertImporte(18.00, $respuesta->json('desglose.igv'), 'IGV extraído');
        $this->assertImporte(118.00, $respuesta->json('desglose.total'), 'total');
    }

    public function test_un_producto_exonerado_no_genera_igv_pero_si_entra_al_total(): void
    {
        $exonerado = $this->producto(50.00, 5, ['tipo_afectacion_igv' => Producto::EXONERADO]);

        $respuesta = $this->postJson('/api/carrito/cotizar', [
            'items' => [['producto_id' => $exonerado->id, 'cantidad' => 1]],
        ])->assertOk();

        $this->assertImporte(0.00, $respuesta->json('desglose.igv'), 'un exonerado no genera IGV');
        $this->assertImporte(50.00, $respuesta->json('desglose.subtotal_exonerado'), 'exonerado');
        $this->assertImporte(50.00, $respuesta->json('desglose.total'), 'total');
    }

    public function test_el_desglose_cuadra_siempre_base_mas_igv_mas_exonerado_es_el_total(): void
    {
        $gravado   = $this->producto(118.00, 5);
        $exonerado = $this->producto(33.33, 5, ['tipo_afectacion_igv' => Producto::EXONERADO]);

        $respuesta = $this->postJson('/api/carrito/cotizar', [
            'items' => [
                ['producto_id' => $gravado->id, 'cantidad' => 3],
                ['producto_id' => $exonerado->id, 'cantidad' => 2],
            ],
        ])->assertOk();

        $d = $respuesta->json('desglose');

        $this->assertEqualsWithDelta(
            $d['total'],
            $d['subtotal_gravado'] + $d['igv'] + $d['subtotal_exonerado'] + $d['subtotal_inafecto'],
            0.001,
            'base + IGV + exonerado + inafecto debe dar exactamente el total'
        );
    }

    public function test_avisa_cuando_alguna_linea_requiere_receta(): void
    {
        $conReceta = $this->producto(20.00, 5, ['requiere_receta' => true]);
        $libre     = $this->producto(20.00, 5, ['requiere_receta' => false]);

        $this->postJson('/api/carrito/cotizar', [
            'items' => [['producto_id' => $libre->id, 'cantidad' => 1]],
        ])->assertOk()->assertJsonPath('receta', false);

        $this->postJson('/api/carrito/cotizar', [
            'items' => [
                ['producto_id' => $libre->id, 'cantidad' => 1],
                ['producto_id' => $conReceta->id, 'cantidad' => 1],
            ],
        ])->assertOk()->assertJsonPath('receta', true);
    }

    public function test_un_producto_borrado_del_catalogo_no_rompe_el_carrito(): void
    {
        $vivo = $this->producto(10.00, 5);

        $respuesta = $this->postJson('/api/carrito/cotizar', [
            'items' => [
                ['producto_id' => $vivo->id, 'cantidad' => 1],
                ['producto_id' => 999999, 'cantidad' => 1],
            ],
        ])->assertOk();

        /* La línea viva sobrevive; la fantasma se informa y se descarta. */
        $respuesta->assertJsonCount(1, 'lineas');
        $respuesta->assertJsonPath('ajustes.0.motivo', 'no_disponible');
    }

    public function test_el_carrito_vacio_devuelve_un_desglose_completo_en_cero(): void
    {
        $this->postJson('/api/carrito/cotizar', ['items' => []])
            ->assertOk()
            ->assertJsonPath('unidades', 0)
            ->assertJsonPath('desglose.total', 0)
            ->assertJsonPath('desglose.igv', 0);
    }

    public function test_rechaza_cantidades_invalidas(): void
    {
        $producto = $this->producto(10.00, 5);

        $this->postJson('/api/carrito/cotizar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 0]],
        ])->assertStatus(422);

        $this->postJson('/api/carrito/cotizar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => -3]],
        ])->assertStatus(422);
    }

    public function test_el_portal_y_el_mostrador_usan_el_mismo_calculo_fiscal(): void
    {
        /* Esta es la prueba que justifica que DesgloseFiscalService exista:
           si alguien vuelve a escribir la aritmética a mano en cualquiera de
           los dos sitios, aquí se nota. */
        $fiscal = app(DesgloseFiscalService::class);
        $propio = $fiscal->repartir(118.00);

        $producto  = $this->producto(118.00, 5);
        $delPortal = $this->postJson('/api/carrito/cotizar', [
            'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->json('desglose');

        $this->assertImporte($propio['subtotal_gravado'], $delPortal['subtotal_gravado'], 'base imponible');
        $this->assertImporte($propio['igv'], $delPortal['igv'], 'IGV');
        $this->assertImporte($propio['total'], $delPortal['total'], 'total');
    }
}
