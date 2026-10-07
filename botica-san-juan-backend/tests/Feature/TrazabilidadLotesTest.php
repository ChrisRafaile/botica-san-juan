<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La trazabilidad por lote no se puede destruir en silencio.
 *
 * Antes de esta restricción, borrar un lote ponía a NULL el `lote_id` de todos
 * sus movimientos **sin error**: el movimiento seguía diciendo "salieron 7
 * unidades" pero ya no de dónde. Ante un retiro del mercado, la pregunta "¿a
 * quién le vendimos de este lote?" dejaba de tener respuesta.
 *
 * Estas pruebas fijan el comportamiento nuevo para que nadie lo revierta por
 * comodidad cuando un borrado le dé problemas.
 */
class TrazabilidadLotesTest extends TestCase
{
    use RefreshDatabase;

    private function producto(int $unidades = 10): Producto
    {
        return Producto::create([
            'nombre'        => 'PRODUCTO TRAZA '.uniqid(),
            'concentracion' => '100 mg',
            'presentacion'  => 'Caja',
            'laboratorio'   => 'LAB PRUEBA',
            'tipo'          => 'TABLETA',
            'stock'         => $unidades,
            'precio'        => 10.00,
        ]);
    }

    private function lote(Producto $p, int $unidades = 10): Lote
    {
        return Lote::create([
            'producto_id'       => $p->id,
            'codigo_lote'       => 'L-'.uniqid(),
            'cantidad_inicial'  => $unidades,
            'cantidad_actual'   => $unidades,
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'estado'            => 'activo',
        ]);
    }

    private function movimiento(Producto $p, Lote $l): MovimientoStock
    {
        return MovimientoStock::create([
            'producto_id'     => $p->id,
            'lote_id'         => $l->id,
            'tipo'            => 'venta',
            'cantidad'        => -3,
            'stock_anterior'  => 10,
            'stock_posterior' => 7,
            'referencia_tipo' => 'pedido',
            'referencia_id'   => 1,
        ]);
    }

    public function test_no_se_puede_borrar_un_lote_que_tiene_movimientos(): void
    {
        $producto = $this->producto();
        $lote     = $this->lote($producto);
        $this->movimiento($producto, $lote);

        $this->expectException(QueryException::class);

        $lote->delete();
    }

    public function test_el_movimiento_conserva_su_lote_tras_el_intento_de_borrado(): void
    {
        $producto   = $this->producto();
        $lote       = $this->lote($producto);
        $movimiento = $this->movimiento($producto, $lote);

        /* El borrado va dentro de una transacción ANIDADA, que en PostgreSQL es
           un SAVEPOINT.

           Sin ella la prueba no puede continuar: `RefreshDatabase` ya envuelve
           cada prueba en una transacción, y en PostgreSQL una sentencia que
           falla aborta el bloque entero —cualquier consulta posterior responde
           `SQLSTATE[25P02]` hasta que se haga rollback—. Con el savepoint sólo
           se revierte el intento de borrado y se puede seguir comprobando. */
        try {
            \Illuminate\Support\Facades\DB::transaction(fn () => $lote->delete());
        } catch (QueryException) {
            /* Es el comportamiento esperado; lo que importa es lo de después. */
        }

        /* Esto es lo que antes se perdía: con SET NULL el borrado tenía éxito y
           `lote_id` quedaba en NULL sin que nada lo advirtiera. */
        $this->assertSame(
            $lote->id,
            $movimiento->fresh()->lote_id,
            'el movimiento debe seguir apuntando a su lote'
        );
        $this->assertDatabaseHas('lotes', ['id' => $lote->id]);
    }

    public function test_un_lote_sin_movimientos_si_se_puede_borrar(): void
    {
        /* La restricción no debe convertir los lotes en inmortales: uno creado
           por error, antes de registrar nada, tiene que poder eliminarse. */
        $producto = $this->producto();
        $lote     = $this->lote($producto);

        $lote->delete();

        $this->assertDatabaseMissing('lotes', ['id' => $lote->id]);
    }

    public function test_un_movimiento_puede_seguir_creandose_sin_lote(): void
    {
        /* `nullable` y `nullOnDelete` no son lo mismo. Un ajuste de inventario
           puede no atribuirse a ningún lote: eso debe seguir permitido. Lo que
           se prohibió es que la BASE ponga a NULL un lote_id ya registrado. */
        $producto = $this->producto();

        $movimiento = MovimientoStock::create([
            'producto_id'     => $producto->id,
            'lote_id'         => null,
            'tipo'            => 'ajuste',
            'cantidad'        => 5,
            'stock_anterior'  => 10,
            'stock_posterior' => 15,
            'motivo'          => 'Regularización de stock heredado',
        ]);

        $this->assertNull($movimiento->lote_id);
        $this->assertDatabaseHas('movimientos_stock', ['id' => $movimiento->id]);
    }

    public function test_borrar_un_producto_con_historial_responde_409_y_no_un_error_de_base(): void
    {
        /* `lotes.producto_id` es CASCADE, así que borrar el producto arrastra
           sus lotes y choca con la restricción. Sin la comprobación previa el
           usuario vería un 500 con una violación de clave foránea, que no le
           dice qué hacer. */
        $producto = $this->producto();
        $lote     = $this->lote($producto);
        $this->movimiento($producto, $lote);

        Sanctum::actingAs(Usuario::create([
            'nombre'   => 'Admin Prueba',
            'dni'      => '99999999',
            'email'    => 'admin.traza@prueba.local',
            'password' => Hash::make(str()->random(32)),
            'rol'      => 'administrador',
        ]));

        $respuesta = $this->deleteJson("/api/productos/{$producto->id}");

        $respuesta->assertStatus(409);
        $respuesta->assertJsonPath('movimientos', 1);
        $this->assertDatabaseHas('productos', ['id' => $producto->id]);
    }

    public function test_borrar_un_producto_sin_historial_sigue_funcionando(): void
    {
        /* La guarda no puede convertirse en un bloqueo general: un producto
           creado por error, sin ventas ni movimientos, debe poder eliminarse.
           Esta es la prueba de NO regresión del cambio. */
        $producto = $this->producto();

        Sanctum::actingAs(Usuario::create([
            'nombre'   => 'Admin Prueba 2',
            'dni'      => '99999998',
            'email'    => 'admin.traza2@prueba.local',
            'password' => Hash::make(str()->random(32)),
            'rol'      => 'administrador',
        ]));

        $this->deleteJson("/api/productos/{$producto->id}")->assertOk();

        $this->assertDatabaseMissing('productos', ['id' => $producto->id]);
    }
}
