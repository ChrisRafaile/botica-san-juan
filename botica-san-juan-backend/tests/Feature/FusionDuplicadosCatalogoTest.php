<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\MovimientoStock;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cubre `catalogo:fusionar-duplicados`.
 *
 * Lo que de verdad importa comprobar no es que desaparezca una fila, sino que
 * el inventario que queda sea el que está en el anaquel. Por omisión eso
 * significa NO sumar: cuatro copias con el mismo stock son la misma mercancía
 * reimportada, y sumarlas prometería existencias que no hay.
 *
 * El índice `productos_identidad_unique` se retira al empezar porque los
 * duplicados que se fusionan son datos históricos: existían ANTES de que la
 * restricción se añadiera. Con el índice puesto no hay forma de reproducir el
 * estado que el comando tiene que arreglar.
 */
class FusionDuplicadosCatalogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('DROP INDEX IF EXISTS productos_identidad_unique');
    }

    private function productoIdentico(array $extra = []): Producto
    {
        return Producto::create(array_merge([
            'nombre'        => 'CLOBETASOL',
            'concentracion' => '0.05 %',
            'presentacion'  => 'Tubo 25 g',
            'laboratorio'   => 'FARMINDUSTRIA',
            'tipo'          => 'CREMA',
            'stock'         => 0,
            'precio'        => 12.00,
        ], $extra));
    }

    /**
     * Todos los lotes heredados se sembraron con `codigo_lote = 'INICIAL'`:
     * es el caso real y el que hace chocar cualquier reapuntado.
     */
    private function loteDe(Producto $producto, int $cantidad): Lote
    {
        return Lote::create([
            'producto_id'      => $producto->id,
            'codigo_lote'      => 'INICIAL',
            'cantidad_inicial' => $cantidad,
            'cantidad_actual'  => $cantidad,
        ]);
    }

    public function test_por_omision_no_suma_el_stock_de_las_copias(): void
    {
        $primero = $this->productoIdentico(['stock' => 8, 'precio' => 12.00]);
        $segundo = $this->productoIdentico(['stock' => 8, 'precio' => 12.50]);

        $this->loteDe($primero, 8);
        $loteCopia = $this->loteDe($segundo, 8);

        $this->artisan('catalogo:fusionar-duplicados --aplicar')->assertSuccessful();

        $this->assertSame(1, Producto::count(), 'Debe quedar una sola fila para la misma mercancía.');
        $this->assertNull(Producto::find($segundo->id), 'El perdedor es el id mayor.');

        $superviviente = Producto::find($primero->id);

        $this->assertNotNull($superviviente, 'El superviviente es el id menor.');
        $this->assertSame(
            1,
            Lote::where('producto_id', $primero->id)->count(),
            'El superviviente conserva solo sus propios lotes.'
        );
        $this->assertNull(
            Lote::find($loteCopia->id),
            'El lote de la copia son las mismas unidades ya contadas: se elimina.'
        );
        $this->assertSame(8, $superviviente->stock, 'El stock es el del anaquel, no la suma de las copias.');
        $this->assertSame('12.50', (string) $superviviente->precio, 'El precio vigente es el del id mayor.');
    }

    public function test_sumar_stock_si_suma_los_lotes_de_las_copias(): void
    {
        $primero = $this->productoIdentico(['stock' => 8]);
        $segundo = $this->productoIdentico(['stock' => 8]);

        $this->loteDe($primero, 8);
        $loteCopia = $this->loteDe($segundo, 8);

        $this->artisan('catalogo:fusionar-duplicados --aplicar --sumar-stock')->assertSuccessful();

        $superviviente = Producto::find($primero->id);

        $this->assertNotNull($superviviente);
        $this->assertSame(
            2,
            Lote::where('producto_id', $primero->id)->count(),
            'Con --sumar-stock los dos lotes quedan en el superviviente.'
        );
        $this->assertSame(16, $superviviente->stock, 'Con --sumar-stock el stock se suma.');

        /* El código se ajusta porque (producto_id, codigo_lote) es UNIQUE y
           los dos lotes se llamaban 'INICIAL'. Los lotes no se fusionan entre
           sí: son dos tandas con su propio vencimiento. */
        $this->assertSame(
            'INICIAL-DUP'.$segundo->id,
            Lote::find($loteCopia->id)->codigo_lote,
            'El lote que llega mantiene su identidad con rastro de dónde venía.'
        );
    }

    public function test_omite_los_grupos_cuyo_stock_difiere_entre_copias(): void
    {
        /* 6 y 18 no es "la misma caja contada dos veces": puede ser una
           reimportación tras una venta o pueden ser dos entregas. Adivinar
           cuesta inventario real, así que el comando no lo toca. */
        $primero = $this->productoIdentico(['stock' => 6]);
        $segundo = $this->productoIdentico(['stock' => 18]);

        $this->loteDe($primero, 6);
        $this->loteDe($segundo, 18);

        $this->artisan('catalogo:fusionar-duplicados --aplicar')
            ->expectsOutputToContain('GRUPOS AMBIGUOS OMITIDOS')
            ->assertSuccessful();

        $this->assertSame(2, Producto::count(), 'Un grupo ambiguo no se fusiona por omisión.');
        $this->assertSame(6, Producto::find($primero->id)->stock);
        $this->assertSame(18, Producto::find($segundo->id)->stock);
    }

    public function test_incluir_ambiguos_fusiona_tomando_el_stock_maximo(): void
    {
        $primero = $this->productoIdentico(['stock' => 6]);
        $segundo = $this->productoIdentico(['stock' => 18]);

        $this->loteDe($primero, 6);
        $this->loteDe($segundo, 18);

        $this->artisan('catalogo:fusionar-duplicados --aplicar --incluir-ambiguos')->assertSuccessful();

        $superviviente = Producto::find($primero->id);

        $this->assertSame(1, Producto::count());
        $this->assertNotNull($superviviente);
        $this->assertSame(18, $superviviente->stock, 'El grupo ambiguo se resuelve con el máximo, no con la suma.');
        $this->assertSame(
            18,
            (int) Lote::where('producto_id', $primero->id)->sum('cantidad_actual'),
            'El stock sigue saliendo de los lotes: la invariante no se rompe.'
        );
    }

    public function test_un_lote_con_movimientos_se_conserva_a_cero_en_vez_de_borrarse(): void
    {
        $primero = $this->productoIdentico(['stock' => 8]);
        $segundo = $this->productoIdentico(['stock' => 8]);

        $this->loteDe($primero, 8);
        $loteCopia = $this->loteDe($segundo, 8);

        /* Una venta ya emitida apunta a este lote. Borrarlo pondría
           `lote_id` a NULL sin avisar y la boleta dejaría de saber de qué
           tanda salió la caja. */
        $movimiento = MovimientoStock::create([
            'producto_id'     => $segundo->id,
            'lote_id'         => $loteCopia->id,
            'tipo'            => 'venta',
            'cantidad'        => 2,
            'stock_anterior'  => 8,
            'stock_posterior' => 6,
        ]);

        $this->artisan('catalogo:fusionar-duplicados --aplicar')->assertSuccessful();

        $sobreviviente = Lote::find($loteCopia->id);

        $this->assertNotNull($sobreviviente, 'Un lote con movimientos no se borra.');
        $this->assertSame($primero->id, $sobreviviente->producto_id, 'Se reapunta al superviviente.');
        $this->assertSame(0, $sobreviviente->cantidad_actual, 'Queda a cero: sus unidades ya estaban contadas.');
        $this->assertSame('agotado', $sobreviviente->estado, 'A cero y agotado para que no vuelva al FEFO.');

        $this->assertSame(
            $loteCopia->id,
            MovimientoStock::find($movimiento->id)->lote_id,
            'La trazabilidad de la venta sigue intacta.'
        );

        /* El stock no se infla: el lote conservado aporta cero. */
        $this->assertSame(8, Producto::find($primero->id)->stock);
    }

    public function test_sin_aplicar_no_escribe_nada(): void
    {
        $primero = $this->productoIdentico(['precio' => 12.00]);
        $segundo = $this->productoIdentico(['precio' => 12.50]);

        $this->artisan('catalogo:fusionar-duplicados')->assertSuccessful();

        $this->assertSame(2, Producto::count(), 'Por omisión el comando simula.');
        $this->assertSame('12.00', (string) Producto::find($primero->id)->precio);
        $this->assertNotNull(Producto::find($segundo->id));
    }

    public function test_no_fusiona_productos_que_solo_comparten_el_nombre(): void
    {
        /* PARACETAMOL aparece 64 veces en el catálogo real y son productos
           distintos. Si el comando los tocara, rompería el catálogo. */
        $this->productoIdentico(['nombre' => 'PARACETAMOL', 'concentracion' => '500 mg']);
        $this->productoIdentico(['nombre' => 'PARACETAMOL', 'concentracion' => '1 g']);

        $this->artisan('catalogo:fusionar-duplicados --aplicar')->assertSuccessful();

        $this->assertSame(2, Producto::count());
    }
}
