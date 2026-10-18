<?php

namespace Tests\Feature;

use App\Models\ComprobanteElectronico;
use App\Models\Pedido;
use App\Models\SerieComprobante;
use App\Services\ComprobanteService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Correlativo transaccional de comprobantes (hallazgo C-03, Fase A del plan
 * docs/planes/bloque3-correlativo-y-ubl.md).
 *
 * Qué NO prueban estos casos: dos conexiones compitiendo de verdad. Con
 * `RefreshDatabase` todo el caso corre dentro de una única transacción y una
 * sola conexión, así que el bloqueo `FOR UPDATE` entre cajas no se puede
 * reproducir aquí. Lo que sí se fija es el contrato que ese bloqueo sostiene:
 * numeración contigua desde una fila de serie que siempre existe, idempotencia
 * por pedido garantizada por el motor, y recuperación ante un contador
 * desfasado, que es el síntoma observable de haber perdido una carrera.
 */
class CorrelativoComprobanteTest extends TestCase
{
    use RefreshDatabase;

    private int $secuencia = 0;

    private function pedido(float $total = 100.00, ?string $documento = '12345678', ?string $tipoDocumento = 'dni'): Pedido
    {
        $this->secuencia++;

        $usuario = $this->crearCliente([
            'dni'   => str_pad((string) (40000000 + $this->secuencia), 8, '0', STR_PAD_LEFT),
            'email' => "correlativo{$this->secuencia}@boticasanjuan.pe",
        ]);

        return Pedido::create([
            'usuario_id'             => $usuario->id,
            'fecha_pedido'           => now(),
            'total'                  => $total,
            'estado'                 => 'confirmado',
            'cliente_nombre'         => "Cliente {$this->secuencia}",
            'cliente_documento'      => $documento,
            'cliente_tipo_documento' => $tipoDocumento,
        ]);
    }

    private function servicio(): ComprobanteService
    {
        return app(ComprobanteService::class);
    }

    private function fila(string $serie): SerieComprobante
    {
        return SerieComprobante::where('ambiente', SerieComprobante::AMBIENTE_SIMULADO)
            ->where('serie', $serie)
            ->firstOrFail();
    }

    // ------------------------------------------------------------- correlativo

    public function test_el_correlativo_es_consecutivo_y_sin_huecos(): void
    {
        $numeros = [];
        for ($i = 0; $i < 5; $i++) {
            $numeros[] = $this->servicio()->emitir($this->pedido())->numero;
        }

        $this->assertSame([1, 2, 3, 4, 5], $numeros);
        $this->assertSame(5, $this->fila('B001')->ultimo_numero);
    }

    public function test_la_migracion_deja_sembradas_las_series_en_uso(): void
    {
        $this->assertSame(0, $this->fila('B001')->ultimo_numero);
        $this->assertSame(0, $this->fila('F001')->ultimo_numero);
        $this->assertSame('boleta', $this->fila('B001')->tipo_comprobante);
        $this->assertSame('factura', $this->fila('F001')->tipo_comprobante);
    }

    public function test_las_series_de_boleta_y_factura_son_independientes(): void
    {
        $b1 = $this->servicio()->emitir($this->pedido());
        $f1 = $this->servicio()->emitir($this->pedido(500, '20123456789', 'ruc'));
        $b2 = $this->servicio()->emitir($this->pedido());
        $f2 = $this->servicio()->emitir($this->pedido(500, '20123456789', 'ruc'));

        $this->assertSame(['B001', 1], [$b1->serie, $b1->numero]);
        $this->assertSame(['F001', 1], [$f1->serie, $f1->numero]);
        $this->assertSame(['B001', 2], [$b2->serie, $b2->numero]);
        $this->assertSame(['F001', 2], [$f2->serie, $f2->numero]);
        $this->assertSame('factura', $f2->tipo_comprobante);
    }

    // ------------------------------------------------------------ idempotencia

    public function test_emitir_dos_veces_el_mismo_pedido_devuelve_el_mismo_comprobante(): void
    {
        $pedido = $this->pedido();

        $primero = $this->servicio()->emitir($pedido);
        $segundo = $this->servicio()->emitir($pedido);

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, ComprobanteElectronico::where('pedido_id', $pedido->id)->count());
        /* El contador no avanza por la reemision: no se quema un numero. */
        $this->assertSame(1, $this->fila('B001')->ultimo_numero);
    }

    public function test_el_motor_rechaza_un_segundo_comprobante_del_mismo_pedido(): void
    {
        $pedido = $this->pedido();
        $this->servicio()->emitir($pedido);

        $rechazado = false;
        try {
            /* Savepoint propio: en PostgreSQL un error aborta la transaccion en
               curso, y la de RefreshDatabase debe seguir utilizable. */
            DB::transaction(fn () => ComprobanteElectronico::create([
                'pedido_id'        => $pedido->id,
                'tipo_comprobante' => 'boleta',
                'serie'            => 'B001',
                'numero'           => 999,
                'cliente_nombre'   => 'Duplicado',
                'total'            => 100,
                'estado_sunat'     => 'pendiente',
                'fecha_emision'    => now(),
            ]));
        } catch (QueryException $e) {
            $rechazado = str_contains($e->getMessage(), 'comprobantes_electronicos_pedido_id_unique_activo');
        }

        $this->assertTrue($rechazado, 'el UNIQUE parcial de pedido_id debe impedir el segundo comprobante');
        $this->assertSame(1, ComprobanteElectronico::where('pedido_id', $pedido->id)->count());
    }

    // ------------------------------------------------------------- el choque

    public function test_un_contador_desfasado_no_produce_duplicados_ni_error(): void
    {
        /* Estado al que llega el sistema si perdio una carrera o alguien
           inserto a mano: ya existe B001-7 pero el contador dice 2. */
        $this->servicio()->emitir($this->pedido());
        $viejo = $this->pedido();
        ComprobanteElectronico::create([
            'pedido_id'        => $viejo->id,
            'tipo_comprobante' => 'boleta',
            'serie'            => 'B001',
            'numero'           => 7,
            'cliente_nombre'   => 'Insertado a mano',
            'total'            => 10,
            'estado_sunat'     => 'pendiente',
            'fecha_emision'    => now(),
        ]);
        $this->fila('B001')->update(['ultimo_numero' => 2]);

        $nuevo = $this->servicio()->emitir($this->pedido());

        $this->assertSame(8, $nuevo->numero);
        $this->assertSame(8, $this->fila('B001')->ultimo_numero);
        $this->assertSame(3, ComprobanteElectronico::where('serie', 'B001')->count());
    }

    public function test_una_serie_inexistente_se_crea_desde_el_maximo_emitido(): void
    {
        $this->servicio()->emitir($this->pedido());
        $this->servicio()->emitir($this->pedido());
        SerieComprobante::where('serie', 'B001')->delete();

        $nuevo = $this->servicio()->emitir($this->pedido());

        $this->assertSame(3, $nuevo->numero);
        $this->assertSame(3, $this->fila('B001')->ultimo_numero);
    }

    public function test_el_bloqueo_de_serie_se_pide_con_for_update(): void
    {
        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            $consultas[] = $q->sql;
        });

        $this->servicio()->emitir($this->pedido());

        $bloqueo = array_filter($consultas, fn ($sql) => str_contains($sql, 'series_comprobante') && str_contains($sql, 'for update'));
        $this->assertNotEmpty($bloqueo, 'la fila de la serie debe leerse con FOR UPDATE dentro de la transaccion');
    }

    // ------------------------------------------- ruta de administracion (C-03)

    public function test_generar_desde_pedidos_usa_el_mismo_correlativo_y_es_idempotente(): void
    {
        $admin = $this->crearAdministrador();
        $token = $admin->createToken('prueba')->plainTextToken;

        $this->pedido(100);
        $this->pedido(800);
        $this->pedido(50);

        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/facturacion/generar-desde-pedidos');
        $r->assertOk()->assertJson(['message' => 'Comprobantes generados correctamente.', 'created' => 3]);

        $this->assertSame([1, 2], ComprobanteElectronico::where('serie', 'B001')->orderBy('numero')->pluck('numero')->all());
        $this->assertSame([1], ComprobanteElectronico::where('serie', 'F001')->pluck('numero')->all());
        $this->assertSame(2, $this->fila('B001')->ultimo_numero);

        /* Segunda pasada: no quedan pedidos sin comprobante. */
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/facturacion/generar-desde-pedidos')
            ->assertOk()->assertJson(['created' => 0]);

        /* Y la ruta del portal sigue la numeracion donde la dejo esta. */
        $this->assertSame(3, $this->servicio()->emitir($this->pedido())->numero);
    }

    // --------------------------------------------------------------- migracion

    private function migracion(): object
    {
        return require database_path('migrations/2026_10_18_100000_create_series_comprobante_table.php');
    }

    public function test_la_migracion_siembra_el_maximo_por_serie_sin_perder_numeros(): void
    {
        $migracion = $this->migracion();
        $migracion->down();

        foreach ([['B001', 'boleta', 5], ['B001', 'boleta', 9], ['F001', 'factura', 2], ['B002', 'boleta', 4]] as [$serie, $tipo, $numero]) {
            ComprobanteElectronico::create([
                'pedido_id'        => $this->pedido()->id,
                'tipo_comprobante' => $tipo,
                'serie'            => $serie,
                'numero'           => $numero,
                'cliente_nombre'   => 'Historico',
                'total'            => 10,
                'estado_sunat'     => 'pendiente',
                'fecha_emision'    => now(),
            ]);
        }

        $migracion->up();

        $this->assertSame(9, $this->fila('B001')->ultimo_numero);
        $this->assertSame(2, $this->fila('F001')->ultimo_numero);
        $this->assertSame(4, $this->fila('B002')->ultimo_numero);
        $this->assertSame(10, $this->servicio()->emitir($this->pedido())->numero);
    }

    public function test_la_migracion_aborta_con_mensaje_claro_si_hay_pedidos_duplicados(): void
    {
        $migracion = $this->migracion();
        $migracion->down();

        $pedido = $this->pedido();
        foreach ([1, 2] as $numero) {
            ComprobanteElectronico::create([
                'pedido_id'        => $pedido->id,
                'tipo_comprobante' => 'boleta',
                'serie'            => 'B001',
                'numero'           => $numero,
                'cliente_nombre'   => 'Duplicado historico',
                'total'            => 10,
                'estado_sunat'     => 'pendiente',
                'fecha_emision'    => now(),
            ]);
        }

        try {
            $migracion->up();
            $this->fail('la migracion debio abortar por pedidos duplicados');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("pedido {$pedido->id} (2 comprobantes)", $e->getMessage());
            $this->assertStringContainsString('no borra datos', $e->getMessage());
        }

        $this->assertSame(2, ComprobanteElectronico::where('pedido_id', $pedido->id)->count());
    }
}
