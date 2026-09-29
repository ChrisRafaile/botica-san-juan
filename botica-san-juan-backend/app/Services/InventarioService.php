<?php

namespace App\Services;

use App\Models\Lote;
use App\Models\MovimientoStock;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Operaciones de inventario: conversión de unidades, reparto FEFO y registro
 * de movimientos.
 *
 * REGLA INNEGOCIABLE DE ESTA CLASE
 * Nadie toca `lotes.cantidad_actual` ni `productos.stock` fuera de aquí, y
 * todo cambio queda acompañado de su movimiento en la misma transacción. El
 * día que alguien actualice el stock por su cuenta, el libro deja de cuadrar y
 * la auditoría no vale nada.
 *
 * SOBRE LA CONCURRENCIA
 * El código anterior leía el producto y lo volvía a guardar (GET y luego PUT).
 * Con dos cajas atendiendo a la vez, la segunda escritura pisaba a la primera
 * y una venta desaparecía del inventario sin dejar rastro. Aquí los lotes se
 * bloquean con `lockForUpdate()` dentro de la transacción: la segunda venta
 * espera a que la primera termine y lee el stock ya actualizado.
 */
class InventarioService
{
    /* =====================================================================
       CONVERSIÓN DE UNIDADES
       ===================================================================== */

    /**
     * Cuántas unidades base representa una cantidad expresada en cierta forma
     * de venta.
     *
     * Vender 2 cajas de 10 blísteres de 10 unidades descuenta 200 unidades del
     * inventario, no 2. El stock siempre se lleva en unidad base; la forma de
     * venta sólo afecta al precio y a la presentación.
     */
    public function factorUnidades(Producto $producto, string $unidadVenta): int
    {
        $porBlister = max(1, (int) ($producto->unidades_por_blister ?? 1));
        $porCaja    = max(1, (int) ($producto->blisters_por_caja ?? 1));

        return match ($unidadVenta) {
            'unidad'  => 1,
            'blister' => $porBlister,
            'caja'    => $porBlister * $porCaja,
            default   => throw new RuntimeException("Unidad de venta no valida: {$unidadVenta}"),
        };
    }

    /**
     * Precio de una unidad de venta.
     *
     * Cada forma tiene precio propio con descuento: la caja NO es el precio
     * unitario multiplicado. Si el precio de esa forma no está configurado, se
     * cae al equivalente proporcional en lugar de fallar, pero es una señal de
     * que falta configurar el producto.
     */
    public function precioUnidadVenta(Producto $producto, string $unidadVenta): float
    {
        $precioUnidad = (float) $producto->precio;

        return match ($unidadVenta) {
            'unidad'  => $precioUnidad,
            'blister' => $producto->precio_blister !== null
                ? (float) $producto->precio_blister
                : $precioUnidad * $this->factorUnidades($producto, 'blister'),
            'caja'    => $producto->precio_caja !== null
                ? (float) $producto->precio_caja
                : $precioUnidad * $this->factorUnidades($producto, 'caja'),
            default   => throw new RuntimeException("Unidad de venta no valida: {$unidadVenta}"),
        };
    }

    /* =====================================================================
       DISPONIBILIDAD
       ===================================================================== */

    /**
     * Unidades base realmente vendibles de un producto.
     *
     * No es `productos.stock`: excluye lo vencido, lo retirado y lo que está
     * dentro del margen de seguridad. Un producto puede tener 40 unidades de
     * stock y 0 disponibles si todo su inventario está vencido.
     */
    public function disponible(int $productoId): int
    {
        return (int) Lote::query()
            ->delProducto($productoId)
            ->disponible()
            ->sum('cantidad_actual');
    }

    /**
     * Simula el reparto FEFO sin tocar nada.
     *
     * Sirve para que el punto de venta avise antes de cobrar: "pediste 10,
     * solo hay 7". Devuelve qué lotes se usarían y cuánto falta.
     *
     * @return array{asignaciones: array<int, array{lote_id:int, codigo_lote:string, fecha_vencimiento:?string, cantidad:int}>, atendido:int, faltante:int}
     */
    public function simularFefo(int $productoId, int $unidadesSolicitadas): array
    {
        $lotes = Lote::query()
            ->delProducto($productoId)
            ->disponible()
            ->ordenFefo()
            ->get();

        return $this->repartir($lotes, $unidadesSolicitadas);
    }

    /**
     * Reparte una cantidad entre lotes ya ordenados por FEFO.
     *
     * Se aparta en su propio método —sin acceso a base de datos— para poder
     * razonarlo y probarlo en aislamiento: es la regla de negocio más delicada
     * del sistema.
     */
    private function repartir($lotes, int $unidadesSolicitadas): array
    {
        $pendiente    = max(0, $unidadesSolicitadas);
        $asignaciones = [];

        foreach ($lotes as $lote) {
            if ($pendiente <= 0) {
                break;
            }

            $tomar = min($pendiente, (int) $lote->cantidad_actual);

            if ($tomar <= 0) {
                continue;
            }

            $asignaciones[] = [
                'lote_id'           => $lote->id,
                'codigo_lote'       => $lote->codigo_lote,
                'fecha_vencimiento' => $lote->fecha_vencimiento?->toDateString(),
                'cantidad'          => $tomar,
            ];

            $pendiente -= $tomar;
        }

        $atendido = $unidadesSolicitadas - $pendiente;

        return [
            'asignaciones' => $asignaciones,
            'atendido'     => $atendido,
            'faltante'     => $pendiente,
        ];
    }

    /* =====================================================================
       DESCUENTO DE STOCK
       ===================================================================== */

    /**
     * Descuenta unidades siguiendo FEFO y registra los movimientos.
     *
     * DEBE invocarse dentro de una transacción ya abierta: forma parte de una
     * venta, y si la venta falla el stock tiene que revertirse con ella.
     *
     * No lanza excepción cuando no hay stock suficiente. Devuelve lo que sí
     * pudo descontar y cuánto faltó, porque la regla acordada con el negocio
     * es que la venta parcial se permite: el vendedor entrega lo que hay y el
     * sistema deja constancia de lo que faltó.
     *
     * @return array{asignaciones: array, atendido:int, faltante:int}
     */
    public function descontar(
        Producto $producto,
        int $unidades,
        string $referenciaTipo,
        ?int $referenciaId,
        ?int $usuarioId = null,
        string $tipoMovimiento = 'venta',
    ): array {
        if (! DB::transactionLevel()) {
            throw new RuntimeException(
                'descontar() debe ejecutarse dentro de una transaccion: el stock '
                . 'y la venta tienen que confirmarse o revertirse juntos.'
            );
        }

        /* lockForUpdate bloquea estas filas hasta el commit. Otra venta del
           mismo producto espera aquí y luego lee las cantidades ya
           actualizadas, en lugar de partir de un valor obsoleto. */
        $lotes = Lote::query()
            ->delProducto($producto->id)
            ->disponible()
            ->ordenFefo()
            ->lockForUpdate()
            ->get();

        $reparto = $this->repartir($lotes, $unidades);

        $stockAnterior = (int) $producto->stock;
        $descontado    = 0;

        foreach ($reparto['asignaciones'] as $asignacion) {
            $lote = $lotes->firstWhere('id', $asignacion['lote_id']);
            $cantidad = $asignacion['cantidad'];

            $lote->cantidad_actual -= $cantidad;

            /* El lote agotado cambia de estado: deja de aparecer en las
               consultas de disponibilidad sin necesidad de filtrar por
               cantidad en cada una. */
            if ($lote->cantidad_actual === 0) {
                $lote->estado = 'agotado';
            }

            $lote->save();

            MovimientoStock::create([
                'producto_id'     => $producto->id,
                'lote_id'         => $lote->id,
                'tipo'            => $tipoMovimiento,
                'cantidad'        => -$cantidad,
                'stock_anterior'  => $stockAnterior - $descontado,
                'stock_posterior' => $stockAnterior - $descontado - $cantidad,
                'referencia_tipo' => $referenciaTipo,
                'referencia_id'   => $referenciaId,
                'usuario_id'      => $usuarioId,
            ]);

            $descontado += $cantidad;
        }

        if ($descontado > 0) {
            /* Actualización atómica de la copia denormalizada: se usa una
               resta en SQL en lugar de leer y escribir, para que no dependa
               del valor que teníamos en memoria. */
            Producto::whereKey($producto->id)->update([
                'stock' => DB::raw("GREATEST(stock - {$descontado}, 0)"),
            ]);

            $producto->refresh();
        }

        return $reparto;
    }

    /**
     * Ingresa stock creando o reforzando un lote.
     *
     * Se usa en compras y en devoluciones. Si ya existe un lote con el mismo
     * código para ese producto, suma sobre él en lugar de duplicarlo.
     */
    public function ingresar(
        Producto $producto,
        int $unidades,
        string $codigoLote,
        ?string $fechaVencimiento = null,
        ?float $costoUnitario = null,
        ?int $compraId = null,
        ?int $usuarioId = null,
        string $tipoMovimiento = 'compra',
        /* Por qué entra este stock. Con `compra` se sobreentiende, pero un
           ingreso nacido de un conteo o de una devolución necesita explicarse:
           sin esto, el libro de movimientos muestra unidades que aparecen de la
           nada y nadie puede reconstruir de dónde salieron. */
        ?string $motivo = null,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
    ): Lote {
        if (! DB::transactionLevel()) {
            throw new RuntimeException('ingresar() debe ejecutarse dentro de una transaccion.');
        }

        if ($unidades <= 0) {
            throw new RuntimeException('La cantidad a ingresar debe ser mayor que cero.');
        }

        $lote = Lote::query()
            ->where('producto_id', $producto->id)
            ->where('codigo_lote', $codigoLote)
            ->lockForUpdate()
            ->first();

        $stockAnterior = (int) $producto->stock;

        if ($lote === null) {
            $lote = Lote::create([
                'producto_id'       => $producto->id,
                'codigo_lote'       => $codigoLote,
                'fecha_vencimiento' => $fechaVencimiento,
                'cantidad_inicial'  => $unidades,
                'cantidad_actual'   => $unidades,
                'costo_unitario'    => $costoUnitario,
                'compra_id'         => $compraId,
                'estado'            => 'activo',
            ]);
        } else {
            $lote->cantidad_inicial += $unidades;
            $lote->cantidad_actual  += $unidades;
            $lote->estado = 'activo';

            if ($fechaVencimiento !== null) {
                $lote->fecha_vencimiento = $fechaVencimiento;
            }
            if ($costoUnitario !== null) {
                $lote->costo_unitario = $costoUnitario;
            }

            $lote->save();
        }

        MovimientoStock::create([
            'producto_id'     => $producto->id,
            'lote_id'         => $lote->id,
            'tipo'            => $tipoMovimiento,
            'cantidad'        => $unidades,
            'stock_anterior'  => $stockAnterior,
            'stock_posterior' => $stockAnterior + $unidades,
            'motivo'          => $motivo,
            'referencia_tipo' => $referenciaTipo ?? ($compraId !== null ? 'compra' : null),
            'referencia_id'   => $referenciaId ?? $compraId,
            'usuario_id'      => $usuarioId,
        ]);

        Producto::whereKey($producto->id)->update([
            'stock' => DB::raw("stock + {$unidades}"),
        ]);

        $producto->refresh();

        return $lote;
    }

    /**
     * Ajuste manual tras conteo físico.
     *
     * El motivo es obligatorio: un ajuste sin explicación es indistinguible de
     * un descuadre, y es justo el registro que hace falta cuando aparece una
     * diferencia de inventario.
     */
    public function ajustar(
        Producto $producto,
        Lote $lote,
        int $nuevaCantidad,
        string $motivo,
        ?int $usuarioId = null,
    ): MovimientoStock {
        if (! DB::transactionLevel()) {
            throw new RuntimeException('ajustar() debe ejecutarse dentro de una transaccion.');
        }

        if (trim($motivo) === '') {
            throw new RuntimeException('Todo ajuste de inventario requiere un motivo.');
        }

        if ($nuevaCantidad < 0) {
            throw new RuntimeException('La cantidad ajustada no puede ser negativa.');
        }

        $diferencia    = $nuevaCantidad - (int) $lote->cantidad_actual;
        $stockAnterior = (int) $producto->stock;

        $lote->cantidad_actual = $nuevaCantidad;
        $lote->estado = $nuevaCantidad === 0 ? 'agotado' : 'activo';
        $lote->save();

        $movimiento = MovimientoStock::create([
            'producto_id'     => $producto->id,
            'lote_id'         => $lote->id,
            'tipo'            => 'ajuste',
            'cantidad'        => $diferencia,
            'stock_anterior'  => $stockAnterior,
            'stock_posterior' => max(0, $stockAnterior + $diferencia),
            'referencia_tipo' => 'ajuste_manual',
            'referencia_id'   => null,
            'usuario_id'      => $usuarioId,
            'motivo'          => $motivo,
        ]);

        Producto::whereKey($producto->id)->update([
            'stock' => DB::raw("GREATEST(stock + ({$diferencia}), 0)"),
        ]);

        return $movimiento;
    }

    /**
     * Marca como vencidos los lotes que pasaron su fecha.
     *
     * Pensado para ejecutarse a diario. Aunque el scope `disponible()` ya los
     * excluye de la venta, dejarlos marcados permite valorar la pérdida y
     * saber qué hay que retirar físicamente del anaquel.
     */
    public function marcarVencidos(?int $usuarioId = null): int
    {
        return DB::transaction(function () use ($usuarioId) {
            $lotes = Lote::query()
                ->where('estado', 'activo')
                ->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '<', now()->startOfDay())
                ->lockForUpdate()
                ->get();

            foreach ($lotes as $lote) {
                $producto      = Producto::find($lote->producto_id);
                $stockAnterior = (int) ($producto->stock ?? 0);
                $cantidad      = (int) $lote->cantidad_actual;

                $lote->estado = 'vencido';
                $lote->save();

                if ($cantidad > 0) {
                    MovimientoStock::create([
                        'producto_id'     => $lote->producto_id,
                        'lote_id'         => $lote->id,
                        'tipo'            => 'vencimiento',
                        'cantidad'        => -$cantidad,
                        'stock_anterior'  => $stockAnterior,
                        'stock_posterior' => max(0, $stockAnterior - $cantidad),
                        'referencia_tipo' => 'proceso_vencimiento',
                        'usuario_id'      => $usuarioId,
                        'motivo'          => 'Lote vencido el ' . $lote->fecha_vencimiento->toDateString(),
                    ]);

                    Producto::whereKey($lote->producto_id)->update([
                        'stock' => DB::raw("GREATEST(stock - {$cantidad}, 0)"),
                    ]);
                }
            }

            return $lotes->count();
        });
    }
}
