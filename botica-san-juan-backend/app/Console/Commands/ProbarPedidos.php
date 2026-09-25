<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use App\Services\ResumenPedidosService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Comprueba las reglas de la pantalla de pedidos contra la base real.
 *
 *   php artisan pedidos:probar
 *
 * NACE DE UN FALLO CONCRETO
 * ---------------------------------------------------------------------------
 * La pantalla de pedidos filtraba por 'procesando' y 'entregado' —estados que
 * nunca existieron en la base— mientras que 'completado', el estado con el que
 * nace toda venta de mostrador, no tenia categoria. Nadie lo detecto porque la
 * columna `estado` era un varchar libre y ninguna capa validaba su contenido:
 * el frontend y el backend hablaban idiomas distintos sin que nada protestara.
 *
 * La primera comprobacion de este comando existe para que eso no pueda
 * repetirse en silencio: si aparece en la base un estado que el modelo no
 * declara, la prueba falla.
 *
 * Todo ocurre dentro de una transaccion que se revierte: los pedidos reales
 * quedan intactos.
 */
class ProbarPedidos extends Command
{
    protected $signature = 'pedidos:probar';
    protected $description = 'Prueba las reglas de estados y el resumen de pedidos (revierte los cambios)';

    private int $paso = 0;

    public function handle(ResumenPedidosService $resumen): int
    {
        $this->newLine();
        $this->info('Prueba de las reglas de pedidos');
        $this->line(str_repeat('-', 62));

        DB::beginTransaction();

        try {
            $this->vocabularioDeEstadosEsCerrado();
            $this->mostradorNaceCompletado();
            $this->resumenCoincideConLaBase($resumen);
            $this->resumenNoCuentaMostradorComoTrabajoPendiente($resumen);
            $this->ventaCerradaNoSePuedeBorrar();

            $this->newLine();
            $this->line(str_repeat('-', 62));
            $this->info('RESULTADO: todas las reglas de pedidos se cumplen.');
            $this->newLine();
            $this->comment('Transaccion revertida: los pedidos reales quedaron intactos.');

            DB::rollBack();

            return self::SUCCESS;
        } catch (Throwable $e) {
            DB::rollBack();

            $this->newLine();
            $this->line(str_repeat('-', 62));
            $this->error('FALLO: ' . $e->getMessage());
            $this->newLine();

            return self::FAILURE;
        }
    }

    /** Ningun estado guardado puede quedar fuera del vocabulario del modelo. */
    private function vocabularioDeEstadosEsCerrado(): void
    {
        $this->encabezado('El vocabulario de estados esta cerrado');

        $enLaBase = Pedido::query()
            ->select('estado')
            ->distinct()
            ->pluck('estado')
            ->filter()
            ->values()
            ->all();

        $this->line('   Estados presentes: ' . implode(', ', $enLaBase));
        $this->line('   Estados validos:   ' . implode(', ', Pedido::ESTADOS));

        $huerfanos = array_diff($enLaBase, Pedido::ESTADOS);

        if ($huerfanos !== []) {
            throw new RuntimeException(
                'Hay estados en la base que el modelo no declara: ' . implode(', ', $huerfanos)
                . '. O se añaden a Pedido::ESTADOS o se corrigen los registros.'
            );
        }

        $this->exito('Sin estados huerfanos.');
    }

    /**
     * La venta de mostrador nace cerrada.
     *
     * Es la regla que la pantalla anterior ignoraba: metia las ventas del POS
     * en una cola de trabajo, como si hubiera algo que hacer con ellas.
     */
    private function mostradorNaceCompletado(): void
    {
        $this->encabezado('La venta de mostrador nace completada');

        $abiertas = Pedido::query()->deMostrador()->abiertos()->count();
        $total    = Pedido::query()->deMostrador()->count();

        $this->line("   Ventas de mostrador: {$total}");
        $this->line("   En estado abierto:   {$abiertas}");

        if ($abiertas > 0) {
            throw new RuntimeException(
                "Hay {$abiertas} ventas de mostrador en estado abierto. Una venta "
                . 'de mostrador ya se cobro y se entrego: no puede estar pendiente.'
            );
        }

        $this->exito('Ninguna venta de mostrador espera atencion.');
    }

    /**
     * El resumen no puede contradecir a la base.
     *
     * Se recalcula con consultas independientes en vez de reutilizar el
     * servicio: si ambos lados usaran el mismo codigo, la prueba pasaria
     * aunque ese codigo estuviera mal.
     */
    private function resumenCoincideConLaBase(ResumenPedidosService $resumen): void
    {
        $this->encabezado('El resumen coincide con la base');

        $datos = $resumen->resumen();

        $esperados = [
            'pendientes'  => Pedido::ESTADO_PENDIENTE,
            'confirmados' => Pedido::ESTADO_CONFIRMADO,
        ];

        foreach ($esperados as $clave => $estado) {
            $pedidos = (int) DB::table('pedidos')
                ->where('origen', Pedido::ORIGEN_WEB)
                ->where('estado', $estado)
                ->count();

            $importe = round((float) DB::table('pedidos')
                ->where('origen', Pedido::ORIGEN_WEB)
                ->where('estado', $estado)
                ->sum('total'), 2);

            $delServicio = $datos['portal'][$clave];

            $this->line(sprintf(
                '   %-12s servicio: %d (S/ %s)  ·  base: %d (S/ %s)',
                $clave,
                $delServicio['pedidos'],
                number_format($delServicio['importe'], 2),
                $pedidos,
                number_format($importe, 2),
            ));

            if ($delServicio['pedidos'] !== $pedidos) {
                throw new RuntimeException(
                    "El resumen dice {$delServicio['pedidos']} {$clave} y la base tiene {$pedidos}."
                );
            }

            /* Comparacion con tolerancia de medio centimo: `total` es decimal
               en la base y float al salir del servicio. */
            if (abs($delServicio['importe'] - $importe) > 0.005) {
                throw new RuntimeException(
                    "El importe de {$clave} no cuadra: {$delServicio['importe']} contra {$importe}."
                );
            }
        }

        /* Los huecos de detalle se recuentan con NOT EXISTS, que es otra forma
           de preguntar lo mismo que `whereDoesntHave` en el servicio. */
        $huecosReales = (int) DB::table('pedidos as p')
            ->where('p.origen', Pedido::ORIGEN_WEB)
            ->whereIn('p.estado', Pedido::ESTADOS_ABIERTOS)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('pedido_detalles as d')
                    ->whereColumn('d.pedido_id', 'p.id');
            })
            ->count();

        $this->line(sprintf(
            '   %-12s servicio: %d  ·  base: %d',
            'sin lineas',
            $datos['portal']['abiertos_sin_lineas'],
            $huecosReales,
        ));

        if ($datos['portal']['abiertos_sin_lineas'] !== $huecosReales) {
            throw new RuntimeException(
                "Los encargos sin lineas no cuadran: {$datos['portal']['abiertos_sin_lineas']} contra {$huecosReales}."
            );
        }

        $totalReal = (int) DB::table('pedidos')->count();
        if ($datos['total_registros'] !== $totalReal) {
            throw new RuntimeException(
                "El total de registros no cuadra: {$datos['total_registros']} contra {$totalReal}."
            );
        }

        $this->exito('Las cifras de cabecera reflejan el catalogo completo.');
    }

    /**
     * La cola de trabajo es solo del portal.
     *
     * Se inserta una venta de mostrador reciente y se comprueba que NO aparece
     * entre los pendientes: es la confusion exacta que tenia la pantalla.
     */
    private function resumenNoCuentaMostradorComoTrabajoPendiente(ResumenPedidosService $resumen): void
    {
        $this->encabezado('Una venta de mostrador no entra en la cola de trabajo');

        $antes = $resumen->resumen();

        $usuarioId = DB::table('usuarios')->value('id');

        $id = DB::table('pedidos')->insertGetId([
            'usuario_id'   => $usuarioId,
            'origen'       => Pedido::ORIGEN_POS,
            'estado'       => Pedido::ESTADO_COMPLETADO,
            'estado_pago'  => 'pagado',
            'total'        => 123.45,
            'fecha'        => now(),
            'fecha_pedido' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $despues = $resumen->resumen();

        $this->line("   Venta de prueba insertada: id {$id}, S/ 123.45");
        $this->line(sprintf(
            '   Pendientes: %d -> %d   ·   Mostrador hoy: %d -> %d',
            $antes['portal']['pendientes']['pedidos'],
            $despues['portal']['pendientes']['pedidos'],
            $antes['mostrador']['hoy']['pedidos'],
            $despues['mostrador']['hoy']['pedidos'],
        ));

        if ($despues['portal']['pendientes']['pedidos'] !== $antes['portal']['pendientes']['pedidos']) {
            throw new RuntimeException(
                'Una venta de mostrador altero el contador de pendientes del portal.'
            );
        }

        if ($despues['mostrador']['hoy']['pedidos'] !== $antes['mostrador']['hoy']['pedidos'] + 1) {
            throw new RuntimeException(
                'La venta de mostrador no aparecio en el contador del dia.'
            );
        }

        $this->exito('Cada venta cuenta donde le corresponde.');
    }

    /**
     * Una venta cerrada no se borra.
     *
     * El boton de la papelera llamaba a DELETE /pedidos/{id} sin ninguna
     * comprobacion: bastaba un clic para que desapareciera una venta que ya
     * movio stock y ya figura en el registro del contador.
     */
    private function ventaCerradaNoSePuedeBorrar(): void
    {
        $this->encabezado('Una venta completada no se puede eliminar');

        $venta = Pedido::query()
            ->deMostrador()
            ->where('estado', Pedido::ESTADO_COMPLETADO)
            ->first();

        if (!$venta) {
            $this->warn('   No hay ventas de mostrador completadas: paso omitido.');

            return;
        }

        $respuesta = app(\App\Http\Controllers\PedidoController::class)->destroy((string) $venta->id);
        $codigo    = $respuesta->getStatusCode();

        $this->line("   DELETE /pedidos/{$venta->id} respondio {$codigo}");

        if ($codigo !== 422) {
            throw new RuntimeException(
                "El controlador permitio eliminar la venta #{$venta->id} (respondio {$codigo})."
            );
        }

        if (!Pedido::query()->whereKey($venta->id)->exists()) {
            throw new RuntimeException("La venta #{$venta->id} desaparecio de la base.");
        }

        $this->exito('La venta sigue en la base y el borrado fue rechazado.');
    }

    private function encabezado(string $texto): void
    {
        $this->newLine();
        $this->line(sprintf('%d. %s', ++$this->paso, $texto));
    }

    private function exito(string $texto): void
    {
        $this->line("   OK: {$texto}");
    }
}
