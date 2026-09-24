<?php

namespace App\Services;

use App\Models\Conteo;
use App\Models\ConteoDetalle;
use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Conteo físico por ciclos.
 * ---------------------------------------------------------------------------
 * Por qué por ciclos y no un inventario general: parar la botica un día entero
 * para contar 3361 productos es caro y se hace una vez al año, con lo cual el
 * inventario pasa once meses desactualizado. Contando 20 o 30 productos
 * diarios, elegidos por criterio, todo el catálogo queda revisado varias veces
 * al año sin cerrar la puerta ni un minuto.
 *
 * El segundo propósito, tan importante como el primero: hoy las 17101 unidades
 * migraron a un único lote "INICIAL" sin fecha de vencimiento. El FEFO está
 * construido pero no tiene con qué ordenar. Cada conteo captura el lote y la
 * fecha reales del anaquel, así que el inventario se va volviendo utilizable a
 * medida que se cuenta, sin necesidad de una carga inicial masiva.
 */
class ConteoService
{
    public function __construct(private readonly InventarioService $inventario)
    {
    }

    /* ======================================================================
       Abrir una sesión
       ====================================================================== */

    /**
     * Abre una sesión y propone los productos a contar.
     *
     * @param  string  $criterio  rotacion | sin_lote | vencimiento | categoria | manual
     * @param  array<int>  $productosManuales  Sólo para el criterio `manual`.
     */
    public function abrir(
        string $criterio = 'rotacion',
        ?string $ambito = null,
        int $cantidad = 25,
        ?int $usuarioId = null,
        array $productosManuales = [],
    ): Conteo {
        /* Dos sesiones abiertas a la vez llevan a contar el mismo producto dos
           veces y a aplicar ajustes contradictorios. Mejor obligar a cerrar. */
        if (Conteo::abiertos()->exists()) {
            throw new RuntimeException('Ya hay un conteo abierto. Ciérralo o anúlalo antes de abrir otro.');
        }

        $cantidad = max(1, min(200, $cantidad));

        return DB::transaction(function () use ($criterio, $ambito, $cantidad, $usuarioId, $productosManuales) {
            $conteo = Conteo::create([
                'codigo'      => Conteo::siguienteCodigo(),
                'estado'      => 'abierto',
                'criterio'    => $criterio,
                'ambito'      => $ambito,
                'abierto_por' => $usuarioId,
                'abierto_en'  => now(),
            ]);

            $productos = $this->proponerProductos($criterio, $ambito, $cantidad, $productosManuales);

            if ($productos->isEmpty()) {
                throw new RuntimeException('El criterio elegido no devolvió productos que contar.');
            }

            foreach ($productos as $producto) {
                ConteoDetalle::create([
                    'conteo_id'     => $conteo->id,
                    'producto_id'   => $producto->id,
                    /* Foto del stock en este instante: es la referencia contra
                       la que se comparará lo contado. */
                    'stock_sistema' => (int) $producto->stock,
                ]);
            }

            return $conteo->fresh('detalles');
        });
    }

    /**
     * Elige qué productos tocan hoy.
     *
     * @return Collection<int, Producto>
     */
    private function proponerProductos(
        string $criterio,
        ?string $ambito,
        int $cantidad,
        array $productosManuales,
    ): Collection {
        /* La tabla de productos no tiene bandera de "activo": todo lo que está
           registrado es vendible. Si más adelante se añade baja lógica, el
           filtro va aquí y no repartido por los cinco criterios. */
        $base = Producto::query();

        /* Nunca se repite un producto contado en los últimos 30 días: de otro
           modo el criterio de rotación devolvería siempre los mismos y el resto
           del catálogo no se revisaría jamás. */
        $contadosHacePoco = ConteoDetalle::query()
            ->whereNotNull('cantidad_contada')
            ->where('contado_en', '>=', now()->subDays(30))
            ->pluck('producto_id');

        return match ($criterio) {
            /* Lo que más se mueve es donde antes se descuadra el stock. */
            'rotacion' => $base
                ->whereNotIn('id', $contadosHacePoco)
                ->withCount(['movimientos as ventas_recientes' => fn ($q) => $q
                    ->where('tipo', 'venta')
                    ->where('created_at', '>=', now()->subDays(60))])
                ->orderByDesc('ventas_recientes')
                ->orderBy('id')
                ->limit($cantidad)
                ->get(),

            /* El criterio que más rinde al principio: productos cuyo stock
               sigue entero en el lote "INICIAL" sin fecha de vencimiento. Cada
               uno que se cuenta le devuelve al FEFO un dato que hoy no tiene. */
            'sin_lote' => $base
                ->whereNotIn('id', $contadosHacePoco)
                ->where('stock', '>', 0)
                ->whereDoesntHave('lotes', fn ($q) => $q->whereNotNull('fecha_vencimiento'))
                ->orderByDesc('stock')
                ->limit($cantidad)
                ->get(),

            /* Lo que vence pronto conviene verificarlo antes de que sea tarde
               para devolverlo al proveedor. */
            'vencimiento' => $base
                ->whereHas('lotes', fn ($q) => $q
                    ->where('cantidad_actual', '>', 0)
                    ->whereNotNull('fecha_vencimiento')
                    ->whereBetween('fecha_vencimiento', [now(), now()->addDays((int) ($ambito ?: 90))]))
                ->limit($cantidad)
                ->get(),

            'categoria' => $base
                ->whereNotIn('id', $contadosHacePoco)
                ->when($ambito, fn ($q) => $q->where('categoria_id', $ambito))
                ->orderBy('nombre')
                ->limit($cantidad)
                ->get(),

            'manual' => $base->whereIn('id', $productosManuales)->get(),

            default => throw new RuntimeException("Criterio de conteo no válido: {$criterio}"),
        };
    }

    /* ======================================================================
       Registrar lo contado
       ====================================================================== */

    /**
     * Anota lo que se contó de un producto. No toca el stock todavía.
     *
     * @param  array<int, array{codigo_lote:string, fecha_vencimiento:?string, cantidad:int}>  $lotes
     */
    public function registrarConteo(
        ConteoDetalle $detalle,
        int $cantidadContada,
        array $lotes = [],
        ?string $observacion = null,
        ?int $usuarioId = null,
    ): ConteoDetalle {
        if (! $detalle->conteo->estaAbierto()) {
            throw new RuntimeException('El conteo ya está cerrado: no admite más registros.');
        }

        if ($cantidadContada < 0) {
            throw new RuntimeException('La cantidad contada no puede ser negativa.');
        }

        $lotes = $this->normalizarLotes($lotes);

        /* Si se dicta el desglose por lotes, tiene que sumar lo contado. Un
           desglose que no cuadra con el total es un error de tecleo, y dejarlo
           pasar significaría escribir en el inventario una cifra que nadie
           verificó. */
        if ($lotes !== []) {
            $suma = array_sum(array_column($lotes, 'cantidad'));

            if ($suma !== $cantidadContada) {
                throw new RuntimeException(
                    "El desglose por lotes suma {$suma} y se contaron {$cantidadContada} unidades."
                );
            }
        }

        $detalle->update([
            'cantidad_contada' => $cantidadContada,
            'lotes_contados'   => $lotes ?: null,
            'observacion'      => $observacion,
            'contado_por'      => $usuarioId,
            'contado_en'       => now(),
        ]);

        return $detalle->fresh();
    }

    /**
     * Limpia y valida el desglose dictado.
     *
     * @return array<int, array{codigo_lote:string, fecha_vencimiento:?string, cantidad:int}>
     */
    private function normalizarLotes(array $lotes): array
    {
        $salida = [];

        foreach ($lotes as $lote) {
            $cantidad = (int) ($lote['cantidad'] ?? 0);

            if ($cantidad <= 0) {
                continue;
            }

            $codigo = trim((string) ($lote['codigo_lote'] ?? ''));

            if ($codigo === '') {
                throw new RuntimeException('Cada lote contado necesita su código.');
            }

            $salida[] = [
                'codigo_lote'       => mb_strtoupper($codigo),
                'fecha_vencimiento' => $lote['fecha_vencimiento'] ?: null,
                'cantidad'          => $cantidad,
            ];
        }

        return $salida;
    }

    /* ======================================================================
       Cerrar y aplicar
       ====================================================================== */

    /**
     * Cierra la sesión y aplica los ajustes al inventario.
     *
     * Todo ocurre en una transacción: o se aplica el conteo entero o no se
     * aplica nada. Un cierre a medias dejaría parte del catálogo ajustado y
     * parte no, sin manera de saber dónde se cortó.
     *
     * El resumen separa dos cosas que es fácil confundir: cuántos productos
     * cambiaron de CANTIDAD y en cuántos se capturó el DESGLOSE POR LOTES. Un
     * conteo puede no mover ni una unidad y aun así ser el más valioso del mes,
     * si convirtió stock anónimo en lotes con fecha de vencimiento.
     *
     * @return array{ajustados:int, sin_diferencia:int, no_contados:int, unidades_netas:int, lotes_capturados:int}
     */
    public function cerrar(Conteo $conteo, ?int $usuarioId = null): array
    {
        if (! $conteo->estaAbierto()) {
            throw new RuntimeException('Este conteo ya fue cerrado.');
        }

        return DB::transaction(function () use ($conteo, $usuarioId) {
            $resumen = [
                'ajustados'        => 0,
                'sin_diferencia'   => 0,
                'no_contados'      => 0,
                'unidades_netas'   => 0,
                'lotes_capturados' => 0,
            ];

            $detalles = $conteo->detalles()->with('producto')->get();

            foreach ($detalles as $detalle) {
                if (! $detalle->fueContado()) {
                    $resumen['no_contados']++;
                    continue;
                }

                $producto = Producto::whereKey($detalle->producto_id)->lockForUpdate()->first();

                if ($producto === null) {
                    continue;
                }

                $motivo = "Conteo físico {$conteo->codigo}";

                if ($detalle->lotes_contados) {
                    $aplicado = $this->aplicarConDesglose($producto, $detalle->lotes_contados, $motivo, $usuarioId, $conteo->id);

                    /* Sólo cuenta como captura si al menos un lote trajo fecha:
                       un código de lote sin vencimiento no le sirve al FEFO. */
                    if (array_filter(array_column($detalle->lotes_contados, 'fecha_vencimiento'))) {
                        $resumen['lotes_capturados']++;
                    }
                } else {
                    $aplicado = $this->aplicarSoloTotal($producto, $detalle->cantidad_contada, $motivo, $usuarioId, $conteo->id);
                }

                $detalle->update(['diferencia_aplicada' => $aplicado]);

                if ($aplicado === 0) {
                    $resumen['sin_diferencia']++;
                } else {
                    $resumen['ajustados']++;
                    $resumen['unidades_netas'] += $aplicado;
                }
            }

            $conteo->update([
                'estado'      => 'cerrado',
                'cerrado_por' => $usuarioId,
                'cerrado_en'  => now(),
            ]);

            return $resumen;
        });
    }

    /**
     * Aplica un conteo que vino con desglose por lotes.
     *
     * El anaquel manda: lo dictado pasa a ser la verdad. Los lotes que el
     * sistema tenía y el conteo no menciona se ponen a cero — no se "respetan
     * por si acaso", porque eso es justamente lo que hace que el inventario se
     * vaya llenando de lotes fantasma que nadie encuentra en el estante.
     */
    private function aplicarConDesglose(
        Producto $producto,
        array $lotesContados,
        string $motivo,
        ?int $usuarioId,
        int $conteoId,
    ): int {
        $stockAntes = (int) $producto->stock;

        $existentes = Lote::query()
            ->where('producto_id', $producto->id)
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (Lote $l) => mb_strtoupper($l->codigo_lote));

        $vistos = [];

        foreach ($lotesContados as $contado) {
            $codigo = $contado['codigo_lote'];
            $vistos[] = $codigo;

            $lote = $existentes->get($codigo);

            if ($lote === null) {
                /* Lote que el sistema no conocía: entra con su fecha real. */
                $this->inventario->ingresar(
                    producto: $producto,
                    unidades: $contado['cantidad'],
                    codigoLote: $codigo,
                    fechaVencimiento: $contado['fecha_vencimiento'],
                    usuarioId: $usuarioId,
                    tipoMovimiento: 'ajuste',
                    motivo: "{$motivo} · lote encontrado en el anaquel",
                    referenciaTipo: 'conteo',
                    referenciaId: $conteoId,
                );

                continue;
            }

            /* La fecha de vencimiento se actualiza aunque la cantidad coincida:
               capturarla es el objetivo principal del conteo. */
            if ($contado['fecha_vencimiento'] !== null
                && (string) $lote->fecha_vencimiento !== (string) $contado['fecha_vencimiento']) {
                $lote->fecha_vencimiento = $contado['fecha_vencimiento'];
                $lote->save();
            }

            if ((int) $lote->cantidad_actual !== $contado['cantidad']) {
                $this->inventario->ajustar($producto, $lote, $contado['cantidad'], $motivo, $usuarioId);
            }
        }

        /* Lo que el sistema creía tener y no apareció en el anaquel. */
        foreach ($existentes as $codigo => $lote) {
            if (in_array($codigo, $vistos, true) || (int) $lote->cantidad_actual === 0) {
                continue;
            }

            $this->inventario->ajustar($producto, $lote, 0, "{$motivo} · lote no encontrado", $usuarioId);
        }

        return (int) $producto->fresh()->stock - $stockAntes;
    }

    /**
     * Aplica un conteo del que sólo se sabe el total.
     *
     * Si falta stock se descuenta en orden FEFO — lo que antes vence es lo que
     * más probablemente se vendió, se dañó o caducó. Si sobra, se suma al lote
     * que FEFO consumiría al final, para no adelantar artificialmente la
     * caducidad del producto.
     */
    private function aplicarSoloTotal(
        Producto $producto,
        int $contado,
        string $motivo,
        ?int $usuarioId,
        int $conteoId,
    ): int {
        $stockAntes = (int) $producto->stock;
        $diferencia = $contado - $stockAntes;

        if ($diferencia === 0) {
            return 0;
        }

        $lotes = Lote::query()
            ->where('producto_id', $producto->id)
            ->where('cantidad_actual', '>', 0)
            ->ordenFefo()
            ->lockForUpdate()
            ->get();

        if ($diferencia < 0) {
            $porQuitar = -$diferencia;

            foreach ($lotes as $lote) {
                if ($porQuitar <= 0) {
                    break;
                }

                $quita = min($porQuitar, (int) $lote->cantidad_actual);

                $this->inventario->ajustar(
                    $producto, $lote, (int) $lote->cantidad_actual - $quita, $motivo, $usuarioId
                );

                $porQuitar -= $quita;
            }

            return (int) $producto->fresh()->stock - $stockAntes;
        }

        $destino = $lotes->last();

        if ($destino === null) {
            /* No quedaba ningún lote: se crea uno identificado con el conteo,
               sin fecha. Queda marcado como candidato a capturar vencimiento. */
            $this->inventario->ingresar(
                producto: $producto,
                unidades: $diferencia,
                codigoLote: str_replace('Conteo físico ', 'CONTEO-', $motivo),
                usuarioId: $usuarioId,
                tipoMovimiento: 'ajuste',
                motivo: "{$motivo} · sobrante sin lote identificado",
                referenciaTipo: 'conteo',
                referenciaId: $conteoId,
            );
        } else {
            $this->inventario->ajustar(
                $producto, $destino, (int) $destino->cantidad_actual + $diferencia, $motivo, $usuarioId
            );
        }

        return (int) $producto->fresh()->stock - $stockAntes;
    }

    /** Descarta una sesión sin tocar el inventario. */
    public function anular(Conteo $conteo, ?int $usuarioId = null): Conteo
    {
        if (! $conteo->estaAbierto()) {
            throw new RuntimeException('Sólo se puede anular un conteo abierto.');
        }

        $conteo->update([
            'estado'      => 'anulado',
            'cerrado_por' => $usuarioId,
            'cerrado_en'  => now(),
        ]);

        return $conteo->fresh();
    }
}
