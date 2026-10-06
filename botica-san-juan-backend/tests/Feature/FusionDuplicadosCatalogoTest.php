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
 * Lo que de verdad importa comprobar no es que desaparezca una fila, sino dos
 * cosas: que el inventario que queda sea el que está en el anaquel y que la
 * fila que sobrevive sea la que tiene la historia de ventas.
 *
 * Por omisión el stock NO se suma: cuatro copias con el mismo stock son la
 * misma mercancía reimportada, y sumarlas prometería existencias que no hay.
 *
 * Y el superviviente NO es siempre el id menor: si exactamente una fila del
 * grupo tiene actividad (movimientos, ventas o incidencias), esa es la que se
 * ha estado vendiendo y la que se queda, aunque su id sea mayor.
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

    /**
     * Una venta ya registrada contra esa fila del catálogo. Es lo que prueba
     * que la fila está viva.
     */
    private function ventaDe(Producto $producto, ?Lote $lote = null): MovimientoStock
    {
        return MovimientoStock::create([
            'producto_id'     => $producto->id,
            'lote_id'         => $lote?->id,
            'tipo'            => 'venta',
            'cantidad'        => 1,
            'stock_anterior'  => 3,
            'stock_posterior' => 2,
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

        $this->assertNotNull($superviviente, 'Sin actividad en ninguna fila, el superviviente es el id menor.');
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

    /**
     * El caso AMOXICILINA PHARMAGEN de la base real: la fila viva es 835 y el
     * id menor es 72. Quedarse con 72 le devolvería al producto una unidad ya
     * vendida y dejaría la venta colgando de una fila que deja de ser la
     * principal.
     */
    public function test_el_superviviente_es_la_fila_con_actividad_aunque_no_sea_el_id_menor(): void
    {
        $sinVender  = $this->productoIdentico(['stock' => 3, 'precio' => 12.00]);
        $vendida    = $this->productoIdentico(['stock' => 2, 'precio' => 12.00]);
        $ultimaCopia = $this->productoIdentico(['stock' => 3, 'precio' => 13.00]);

        $this->loteDe($sinVender, 3);
        $loteVivo = $this->loteDe($vendida, 2);
        $this->loteDe($ultimaCopia, 3);

        /* La única fila con historia: un movimiento de venta contra ella. */
        $venta = $this->ventaDe($vendida, $loteVivo);

        $this->artisan('catalogo:fusionar-duplicados --aplicar')
            ->expectsOutputToContain('SUPERVIVIENTE ELEGIDO POR ACTIVIDAD')
            ->assertSuccessful();

        $this->assertSame(1, Producto::count());

        $superviviente = Producto::find($vendida->id);

        $this->assertNotNull($superviviente, 'Sobrevive la fila con actividad, no la de id menor.');
        $this->assertNull(Producto::find($sinVender->id), 'El id menor sin actividad se elimina.');
        $this->assertNull(Producto::find($ultimaCopia->id));

        $this->assertSame(2, $superviviente->stock, 'El stock bueno es el de la fila que vendió, no el de la importación.');
        $this->assertSame('13.00', (string) $superviviente->precio, 'El precio sigue siendo el del id mayor.');

        $this->assertSame(
            $vendida->id,
            MovimientoStock::find($venta->id)->producto_id,
            'La venta sigue colgando de la fila que la hizo, que es la que queda.'
        );
        $this->assertSame(
            2,
            (int) Lote::where('producto_id', $vendida->id)->sum('cantidad_actual'),
            'La invariante productos.stock = SUM(lotes) se mantiene.'
        );
    }

    /**
     * Sin actividad en ninguna fila da igual cuál se quede: el id menor es la
     * elección estable, incluso cuando el stock difiere (que con el criterio
     * anterior bastaba para omitir el grupo).
     */
    public function test_sin_actividad_en_ninguna_fila_el_superviviente_es_el_id_menor(): void
    {
        $primero = $this->productoIdentico(['stock' => 6]);
        $segundo = $this->productoIdentico(['stock' => 18]);

        $this->loteDe($primero, 6);
        $this->loteDe($segundo, 18);

        $this->artisan('catalogo:fusionar-duplicados --aplicar')
            ->expectsOutputToContain('No queda ningún grupo ambiguo')
            ->assertSuccessful();

        $superviviente = Producto::find($primero->id);

        $this->assertSame(1, Producto::count(), 'Stock distinto ya no basta para omitir el grupo.');
        $this->assertNotNull($superviviente);
        $this->assertSame(6, $superviviente->stock, 'Se queda el stock del superviviente, sin sumar ni tomar el máximo.');
    }

    /**
     * El único caso que sigue siendo ambiguo: dos filas con historia propia.
     * Elegir una significa decidir de qué fila cuelgan qué ventas, y eso no lo
     * decide un comando.
     */
    public function test_omite_el_grupo_cuando_dos_filas_tienen_actividad(): void
    {
        $primero = $this->productoIdentico(['stock' => 6]);
        $segundo = $this->productoIdentico(['stock' => 18]);

        $loteUno = $this->loteDe($primero, 6);
        $loteDos = $this->loteDe($segundo, 18);

        $this->ventaDe($primero, $loteUno);
        $this->ventaDe($segundo, $loteDos);

        $this->artisan('catalogo:fusionar-duplicados --aplicar')
            ->expectsOutputToContain('GRUPOS AMBIGUOS OMITIDOS')
            ->assertSuccessful();

        $this->assertSame(2, Producto::count(), 'Con dos filas vivas el grupo no se toca.');
        $this->assertSame(6, Producto::find($primero->id)->stock);
        $this->assertSame(18, Producto::find($segundo->id)->stock);
        $this->assertNotNull(Lote::find($loteUno->id));
        $this->assertNotNull(Lote::find($loteDos->id));
    }

    public function test_un_lote_con_movimientos_se_conserva_a_cero_en_vez_de_borrarse(): void
    {
        $primero = $this->productoIdentico(['stock' => 8]);
        $segundo = $this->productoIdentico(['stock' => 8]);

        $this->loteDe($primero, 8);
        $loteCopia = $this->loteDe($segundo, 8);

        /* La venta está registrada contra la primera fila (la que el mostrador
           usa, y por tanto la fila viva y superviviente) pero descontó de un
           lote que quedó colgado de la copia. Es la combinación que hace que un
           lote PERDEDOR tenga trazabilidad: borrarlo pondría `lote_id` a NULL
           sin avisar y la boleta dejaría de saber de qué tanda salió la caja. */
        $movimiento = $this->ventaDe($primero, $loteCopia);

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
