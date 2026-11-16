<?php

namespace Tests\Feature;

use App\Models\ComprobanteElectronico;
use App\Models\Lote;
use App\Models\MovimientoStock;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Prueba de contención concurrente real contra PostgreSQL y procesos PHP reales.
 *
 * NO usa RefreshDatabase porque RefreshDatabase envuelve la prueba en una transacción
 * de una sola conexión que los servidores externos no verían.
 *
 * Levanta 8 servidores PHP reales (php -S) escuchando cada uno en su propio puerto,
 * asegurando aislamiento de procesos y concurrencia verdadera.
 */
class ContencionConcurrenteTest extends TestCase
{
    private const NUMERO_SERVIDORES = 8;
    private const PUERTO_BASE = 8820;

    /** @var Process[] */
    private array $procesos = [];

    /** @var string[] */
    private array $urls = [];

    /** @var int[] */
    private array $productoIds = [];

    /** @var array */
    private array $seriesOriginales = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seriesOriginales = DB::table('series_comprobante')->get()->toArray();
        $this->limpiarDatos();
        $this->iniciarServidores();
    }

    protected function tearDown(): void
    {
        $this->detenerServidores();
        $this->limpiarDatos();

        parent::tearDown();
    }

    private function iniciarServidores(): void
    {
        $serverScript = file_exists(base_path('server.php'))
            ? base_path('server.php')
            : base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');

        $publicPath = public_path();

        $env = array_merge($_ENV, $_SERVER, [
            'APP_ENV'      => 'testing',
            'DB_DATABASE'  => 'botica_san_juan_test',
            'CACHE_STORE'  => 'array',
        ]);

        for ($i = 0; $i < self::NUMERO_SERVIDORES; $i++) {
            $puerto = self::PUERTO_BASE + $i;
            $proceso = new Process(
                [PHP_BINARY, '-S', "127.0.0.1:{$puerto}", $serverScript],
                $publicPath,
                $env
            );
            $proceso->start();

            $this->procesos[] = $proceso;
            $this->urls[] = "http://127.0.0.1:{$puerto}";
        }

        // Esperar a que todos los servidores respondan en /api/salud usando pool concurrente
        $inicio = microtime(true);
        $todosListos = false;

        while (microtime(true) - $inicio < 25.0) {
            $listos = 0;
            try {
                $respuestas = Http::pool(function (Pool $pool) {
                    foreach ($this->urls as $url) {
                        $pool->timeout(0.8)->get("{$url}/api/salud");
                    }
                });

                foreach ($respuestas as $res) {
                    if ($res instanceof \Illuminate\Http\Client\Response && $res->successful()) {
                        $listos++;
                    }
                }
            } catch (\Throwable) {
            }

            if ($listos === self::NUMERO_SERVIDORES) {
                $todosListos = true;
                break;
            }

            usleep(100000); // 100ms
        }

        if (! $todosListos) {
            $this->detenerServidores();
            $this->fail('No todos los servidores PHP pudieron iniciarse a tiempo para la prueba de concurrencia.');
        }
    }

    private function detenerServidores(): void
    {
        foreach ($this->procesos as $p) {
            if ($p->isRunning()) {
                $p->stop();
            }
        }
        $this->procesos = [];
        $this->urls = [];
    }

    private function crearProducto(string $nombre, int $unidades, float $precio = 10.00): Producto
    {
        $producto = Producto::create([
            'nombre'        => $nombre . ' ' . uniqid(),
            'concentracion' => '500 mg',
            'presentacion'  => 'Caja',
            'laboratorio'   => 'Laboratorio San Juan',
            'tipo'          => 'TABLETA',
            'stock'         => $unidades,
            'precio'        => $precio,
        ]);

        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'LOTE-' . uniqid(),
            'cantidad_inicial'  => $unidades,
            'cantidad_actual'   => $unidades,
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'estado'            => 'activo',
        ]);

        $this->productoIds[] = $producto->id;

        return $producto;
    }

    private function limpiarDatos(): void
    {
        if (! empty($this->productoIds)) {
            $pedidoIds = DB::table('pedido_detalles')
                ->whereIn('producto_id', $this->productoIds)
                ->pluck('pedido_id')
                ->all();

            if (! empty($pedidoIds)) {
                DB::table('comprobantes_electronicos')->whereIn('pedido_id', $pedidoIds)->delete();
                DB::table('pagos_eventos')->whereExists(function ($q) use ($pedidoIds) {
                    $q->select(DB::raw(1))
                        ->from('pagos')
                        ->whereColumn('pagos.id', 'pagos_eventos.pago_id')
                        ->whereIn('pagos.pedido_id', $pedidoIds);
                })->delete();
                DB::table('pagos')->whereIn('pedido_id', $pedidoIds)->delete();
                DB::table('pedido_detalle_lotes')->whereExists(function ($q) use ($pedidoIds) {
                    $q->select(DB::raw(1))
                        ->from('pedido_detalles')
                        ->whereColumn('pedido_detalles.id', 'pedido_detalle_lotes.pedido_detalle_id')
                        ->whereIn('pedido_detalles.pedido_id', $pedidoIds);
                })->delete();
                DB::table('pedido_detalles')->whereIn('pedido_id', $pedidoIds)->delete();
                DB::table('eventos_operacion')->whereIn('pedido_id', $pedidoIds)->delete();
                DB::table('pedidos')->whereIn('id', $pedidoIds)->delete();
            }

            DB::table('movimientos_stock')->whereIn('producto_id', $this->productoIds)->delete();
            DB::table('lotes')->whereIn('producto_id', $this->productoIds)->delete();
            DB::table('productos')->whereIn('id', $this->productoIds)->delete();

            $this->productoIds = [];
        }

        DB::table('claves_idempotencia')->truncate();

        if (! empty($this->seriesOriginales)) {
            foreach ($this->seriesOriginales as $s) {
                DB::table('series_comprobante')
                    ->where('id', $s->id)
                    ->update(['ultimo_numero' => $s->ultimo_numero]);
            }
        }
    }

    public function test_ocho_compradores_por_la_ultima_unidad_produce_exactamente_un_exito_y_siete_409(): void
    {
        $amoxicilina = $this->crearProducto('Amoxicilina 500 mg', 1);

        $payloads = [];
        for ($i = 0; $i < self::NUMERO_SERVIDORES; $i++) {
            $dni = str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT);
            $payloads[$i] = [
                'items'             => [['producto_id' => $amoxicilina->id, 'cantidad' => 1]],
                'metodo_pago'       => 'contra_entrega',
                'cliente_nombre'    => "Comprador {$i}",
                'cliente_documento' => $dni,
                'cliente_telefono'  => "98700000{$i}",
            ];
        }

        // Disparar las 8 peticiones a la vez mediante pool HTTP, una a cada servidor PHP
        $respuestas = Http::pool(function (Pool $pool) use ($payloads) {
            foreach ($payloads as $i => $payload) {
                $pool->as("req_{$i}")
                    ->timeout(12)
                    ->post("{$this->urls[$i]}/api/pedidos/confirmar", $payload);
            }
        });

        $exitos = 0;
        $conflictos = 0;
        $errores500 = 0;

        foreach ($respuestas as $resp) {
            if ($resp instanceof \Throwable) {
                $this->fail("Petición HTTP falló por excepción: " . $resp->getMessage());
            }

            $status = $resp->status();
            if ($status === 201) {
                $exitos++;
            } elseif ($status === 409) {
                $conflictos++;
                $this->assertTrue((bool) $resp->json('sin_stock'));
            } elseif ($status >= 500) {
                $errores500++;
            }
        }

        $this->assertSame(0, $errores500, 'Ninguna petición concurrente debe arrojar error 500');
        $this->assertSame(1, $exitos, 'Exactamente una petición debe ganar la última unidad disponible');
        $this->assertSame(self::NUMERO_SERVIDORES - 1, $conflictos, 'Las restantes peticiones deben recibir 409 sin_stock');

        $lote = Lote::where('producto_id', $amoxicilina->id)->first();
        $this->assertSame(0, $lote->cantidad_actual);
        $this->assertSame(0, (int) $amoxicilina->fresh()->stock);

        // Movimientos deben sumar exactamente -1
        $sumaMovimientos = (int) MovimientoStock::where('producto_id', $amoxicilina->id)->sum('cantidad');
        $this->assertSame(-1, $sumaMovimientos);
    }

    public function test_ocho_compradores_para_cinco_unidades_produce_cinco_exitos_y_stock_cero(): void
    {
        $paracetamol = $this->crearProducto('Paracetamol 500 mg', 5);

        $payloads = [];
        for ($i = 0; $i < self::NUMERO_SERVIDORES; $i++) {
            $dni = str_pad((string) (20000000 + $i), 8, '0', STR_PAD_LEFT);
            $payloads[$i] = [
                'items'             => [['producto_id' => $paracetamol->id, 'cantidad' => 1]],
                'metodo_pago'       => 'contra_entrega',
                'cliente_nombre'    => "Comprador {$i}",
                'cliente_documento' => $dni,
                'cliente_telefono'  => "98800000{$i}",
            ];
        }

        $respuestas = Http::pool(function (Pool $pool) use ($payloads) {
            foreach ($payloads as $i => $payload) {
                $pool->as("req_{$i}")
                    ->timeout(12)
                    ->post("{$this->urls[$i]}/api/pedidos/confirmar", $payload);
            }
        });

        $exitos = 0;
        $conflictos = 0;
        $errores500 = 0;

        foreach ($respuestas as $resp) {
            if ($resp instanceof \Throwable) {
                $this->fail("Petición HTTP falló por excepción: " . $resp->getMessage());
            }

            $status = $resp->status();
            if ($status === 201) {
                $exitos++;
            } elseif ($status === 409) {
                $conflictos++;
                $this->assertTrue((bool) $resp->json('sin_stock'));
            } elseif ($status >= 500) {
                $errores500++;
            }
        }

        $this->assertSame(0, $errores500);
        $this->assertSame(5, $exitos, 'Exactamente 5 peticiones deben tener éxito');
        $this->assertSame(3, $conflictos, 'Exactamente 3 peticiones deben recibir 409');

        $lote = Lote::where('producto_id', $paracetamol->id)->first();
        $this->assertSame(0, $lote->cantidad_actual);
        $this->assertSame(0, (int) $paracetamol->fresh()->stock);

        $sumaMovimientos = (int) MovimientoStock::where('producto_id', $paracetamol->id)->sum('cantidad');
        $this->assertSame(-5, $sumaMovimientos);
    }

    public function test_dos_productos_pedidos_en_orden_cruzado_no_generan_interbloqueo_sin_resolver(): void
    {
        $productoA = $this->crearProducto('Amoxicilina 500 mg', 10);
        $productoB = $this->crearProducto('Ibuprofeno 400 mg', 10);

        $payloads = [];
        for ($i = 0; $i < self::NUMERO_SERVIDORES; $i++) {
            $dni = str_pad((string) (30000000 + $i), 8, '0', STR_PAD_LEFT);

            // Mitad en orden A, B y la otra mitad en orden inverso B, A
            $items = ($i % 2 === 0)
                ? [
                    ['producto_id' => $productoA->id, 'cantidad' => 1],
                    ['producto_id' => $productoB->id, 'cantidad' => 1],
                ]
                : [
                    ['producto_id' => $productoB->id, 'cantidad' => 1],
                    ['producto_id' => $productoA->id, 'cantidad' => 1],
                ];

            $payloads[$i] = [
                'items'             => $items,
                'metodo_pago'       => 'contra_entrega',
                'cliente_nombre'    => "Comprador Cruzado {$i}",
                'cliente_documento' => $dni,
                'cliente_telefono'  => "98900000{$i}",
            ];
        }

        $respuestas = Http::pool(function (Pool $pool) use ($payloads) {
            foreach ($payloads as $i => $payload) {
                $pool->as("req_{$i}")
                    ->timeout(12)
                    ->post("{$this->urls[$i]}/api/pedidos/confirmar", $payload);
            }
        });

        $exitos = 0;
        $errores500 = 0;

        foreach ($respuestas as $resp) {
            if ($resp instanceof \Throwable) {
                $this->fail("Petición HTTP falló por excepción: " . $resp->getMessage());
            }

            if ($resp->status() === 201) {
                $exitos++;
            } elseif ($resp->status() >= 500) {
                $errores500++;
            }
        }

        $this->assertSame(0, $errores500, 'Ningún interbloqueo debe terminar en 500');
        $this->assertSame(self::NUMERO_SERVIDORES, $exitos, 'Todas las peticiones deben completarse exitosamente');

        $stockFinalA = (int) $productoA->fresh()->stock;
        $stockFinalB = (int) $productoB->fresh()->stock;

        $this->assertSame(10 - $exitos, $stockFinalA);
        $this->assertSame(10 - $exitos, $stockFinalB);
    }
}
