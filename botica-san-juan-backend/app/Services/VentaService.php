<?php

namespace App\Services;

use App\Exceptions\VentaSinStockException;
use App\Models\IncidenciaVenta;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoDetalleLote;
use App\Models\PedidoPago;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Registro de una venta de mostrador.
 *
 * Todo ocurre dentro de una sola transacción: o se guardan la venta, el
 * descuento de stock, el desglose por lotes y las incidencias, o no se guarda
 * nada. No puede existir una venta cuyo stock no se descontó, ni un stock
 * descontado sin venta que lo justifique.
 *
 * REGLAS DE NEGOCIO IMPLEMENTADAS
 *
 *  1. Venta parcial permitida. Si se piden 10 y hay 7, se venden 7 y se
 *     registra la incidencia. Nunca se genera stock negativo ni se bloquea la
 *     venta: quien decide si acepta las 7 es el cliente, no el software.
 *
 *  2. FEFO con reparto entre lotes. Si el lote más próximo a vencer tiene 3 y
 *     se piden 5, salen 3 de ese y 2 del siguiente. La boleta muestra una sola
 *     línea de 5 unidades; el desglose queda registrado para trazabilidad.
 *
 *  3. Precio por forma de venta. Unidad, blíster y caja tienen precio propio
 *     con descuento; el importe de la línea se calcula con el precio de la
 *     forma elegida, no multiplicando el unitario.
 *
 *  4. Desglose fiscal. Los productos exonerados de IGV se separan de los
 *     gravados, porque de eso dependen tanto la boleta como el reporte al
 *     contador.
 */
class VentaService
{
    public function __construct(private readonly InventarioService $inventario)
    {
    }

    /**
     * Comprueba la disponibilidad de un carrito antes de cobrar.
     *
     * El punto de venta llama a esto mientras se arma la venta, para poder
     * avisar "pediste 10, solo hay 7" antes de que el cliente pague. No
     * modifica nada.
     *
     * @param  array<int, array{producto_id:int, unidad_venta:string, cantidad:int}>  $items
     */
    public function verificarDisponibilidad(array $items): array
    {
        $resultado = [];

        foreach ($items as $item) {
            $producto = Producto::find($item['producto_id']);

            if ($producto === null) {
                $resultado[] = [
                    'producto_id' => $item['producto_id'],
                    'error'       => 'Producto no encontrado',
                ];
                continue;
            }

            $factor            = $this->inventario->factorUnidades($producto, $item['unidad_venta']);
            $unidadesPedidas   = $factor * max(0, (int) $item['cantidad']);
            $simulacion        = $this->inventario->simularFefo($producto->id, $unidadesPedidas);

            $resultado[] = [
                'producto_id'         => $producto->id,
                'nombre'              => $producto->nombre,
                'unidad_venta'        => $item['unidad_venta'],
                'cantidad_solicitada' => (int) $item['cantidad'],
                'unidades_pedidas'    => $unidadesPedidas,
                'unidades_disponibles'=> $simulacion['atendido'],
                'faltante'            => $simulacion['faltante'],
                'suficiente'          => $simulacion['faltante'] === 0,
                /* Cuántas unidades de venta completas se pueden entregar.
                   Si pidió 2 cajas y solo alcanza para 1, aquí sale 1. */
                'cantidad_atendible'  => $factor > 0 ? intdiv($simulacion['atendido'], $factor) : 0,
                'lotes'               => $simulacion['asignaciones'],
            ];
        }

        return $resultado;
    }

    /**
     * Registra la venta.
     *
     * Los pagos se validan y guardan DESPUÉS de calcular el total, nunca antes:
     * el importe que se cobra es el que resulta del stock realmente entregado,
     * no el que traía la petición. Si el cliente pagó por una cantidad que al
     * final no se pudo entregar, el cobro tiene que cuadrar con lo entregado.
     *
     * @param  array<int, array{producto_id:int, unidad_venta:string, cantidad:int}>  $items
     * @param  array{cliente_nombre?:string, cliente_documento?:string, cliente_tipo_documento?:string, observacion?:string}  $datosCliente
     * @param  array<int, array{medio:string, monto?:float, monto_recibido?:float, referencia?:string}>  $pagos
     */
    public function registrar(
        array $items,
        array $datosCliente = [],
        ?int $vendedorId = null,
        ?int $usuarioClienteId = null,
        array $pagos = [],
    ): Pedido {
        if ($items === []) {
            throw new RuntimeException('No se puede registrar una venta sin productos.');
        }

        return DB::transaction(function () use ($items, $datosCliente, $vendedorId, $usuarioClienteId, $pagos) {

            $tasaIgv = (float) config('inventario.tasa_igv', 0.18);

            $pedido = Pedido::create([
                'usuario_id'             => $usuarioClienteId,
                'origen'                 => 'pos',
                'vendedor_id'            => $vendedorId,
                'cliente_nombre'         => $datosCliente['cliente_nombre'] ?? null,
                'cliente_documento'      => $datosCliente['cliente_documento'] ?? null,
                'cliente_tipo_documento' => $datosCliente['cliente_tipo_documento'] ?? 'sin_documento',
                'fecha'                  => now(),
                'fecha_pedido'           => now(),
                'estado'                 => 'completado',
                'estado_pago'            => 'pagado',
                'moneda'                 => 'PEN',
                'observacion'            => $datosCliente['observacion'] ?? null,
                'total'                  => 0,
                'subtotal_gravado'       => 0,
                'subtotal_exonerado'     => 0,
                'igv'                    => 0,
                'tasa_igv'               => $tasaIgv,
            ]);

            $baseGravada   = 0.0;
            $baseExonerada = 0.0;
            $baseInafecta  = 0.0;
            $lineasCreadas = 0;

            foreach ($items as $item) {
                $producto = Producto::whereKey($item['producto_id'])->first();

                if ($producto === null) {
                    throw new RuntimeException("Producto {$item['producto_id']} no existe.");
                }

                $unidadVenta = $item['unidad_venta'];
                $cantidadPedida = max(0, (int) $item['cantidad']);

                if ($cantidadPedida === 0) {
                    continue;
                }

                $factor          = $this->inventario->factorUnidades($producto, $unidadVenta);
                $unidadesPedidas = $factor * $cantidadPedida;

                /* Descuenta lo que haya, siguiendo FEFO. Devuelve cuánto pudo
                   entregar y cuánto faltó. */
                $reparto = $this->inventario->descontar(
                    producto: $producto,
                    unidades: $unidadesPedidas,
                    referenciaTipo: 'pedido',
                    referenciaId: $pedido->id,
                    usuarioId: $vendedorId,
                    tipoMovimiento: 'venta',
                );

                $unidadesEntregadas = $reparto['atendido'];

                /* Si no se pudo entregar ni una unidad, no se crea la línea:
                   una línea de cero confunde en la boleta. La incidencia sí se
                   registra, porque la demanda existió. */
                if ($unidadesEntregadas === 0) {
                    $this->registrarIncidencia(
                        $pedido->id, null, $producto->id, 'stock_insuficiente',
                        $unidadesPedidas, 0, $vendedorId,
                        'Sin stock disponible al momento de la venta.'
                    );
                    continue;
                }

                /* La cantidad facturada se expresa en la forma de venta
                   elegida. Al entregar parcialmente puede quedar una fracción
                   (por ejemplo, 7 unidades de una caja de 10): en ese caso la
                   línea se factura en unidades sueltas, que es lo que
                   realmente se entrega y cobra. */
                $unidadVentaFinal = $unidadVenta;
                $factorFinal      = $factor;
                $cantidadFinal    = intdiv($unidadesEntregadas, $factor);

                if ($unidadesEntregadas % $factor !== 0) {
                    $unidadVentaFinal = 'unidad';
                    $factorFinal      = 1;
                    $cantidadFinal    = $unidadesEntregadas;
                }

                $precioUnidadVenta = $this->inventario->precioUnidadVenta($producto, $unidadVentaFinal);
                $subtotal          = round($precioUnidadVenta * $cantidadFinal, 2);

                /* La afectación es del PRODUCTO, no de la presentación: la
                   misma amoxicilina tributa igual suelta, en blíster o en caja.
                   Se copia a la línea para dejarla congelada: si mañana cambia
                   la lista de exonerados, esta boleta debe seguir diciendo lo
                   que dijo el día que se emitió. */
                $afectacion = $producto->tipo_afectacion_igv ?? Producto::GRAVADO;

                $detalle = PedidoDetalle::create([
                    'pedido_id'           => $pedido->id,
                    'producto_id'         => $producto->id,
                    'unidad_venta'        => $unidadVentaFinal,
                    'factor_unidades'     => $factorFinal,
                    'cantidad'            => $cantidadFinal,
                    'cantidad_unidades'   => $unidadesEntregadas,
                    'precio_unitario'     => $precioUnidadVenta,
                    'precio'              => $precioUnidadVenta,
                    'subtotal'            => $subtotal,
                    'tipo_afectacion_igv' => $afectacion,
                ]);

                $lineasCreadas++;

                /* Desglose por lote: lo que hace posible la trazabilidad. */
                foreach ($reparto['asignaciones'] as $asignacion) {
                    PedidoDetalleLote::create([
                        'pedido_detalle_id' => $detalle->id,
                        'lote_id'           => $asignacion['lote_id'],
                        'cantidad_unidades' => $asignacion['cantidad'],
                        'codigo_lote'       => $asignacion['codigo_lote'],
                        'fecha_vencimiento' => $asignacion['fecha_vencimiento'],
                    ]);
                }

                if ($reparto['faltante'] > 0) {
                    $this->registrarIncidencia(
                        $pedido->id, $detalle->id, $producto->id, 'stock_insuficiente',
                        $unidadesPedidas, $unidadesEntregadas, $vendedorId,
                        sprintf(
                            'Se solicitaron %d unidades y se entregaron %d. Faltaron %d.',
                            $unidadesPedidas, $unidadesEntregadas, $reparto['faltante']
                        )
                    );
                }

                /* Reparto fiscal. Exonerado e inafecto se separan porque el
                   comprobante electrónico los declara en casillas distintas:
                   el exonerado está dentro del ámbito del impuesto pero
                   dispensado por ley; el inafecto queda fuera del ámbito. */
                match ($afectacion) {
                    Producto::EXONERADO => $baseExonerada += $subtotal,
                    Producto::INAFECTO  => $baseInafecta += $subtotal,
                    default             => $baseGravada += $subtotal,
                };
            }

            /* Si no se pudo entregar NADA, no hay venta que registrar. Sin esta
               guarda quedaba un pedido fantasma: total 0, sin líneas y con una
               sola incidencia. La excepción revierte la transacción completa,
               así que tampoco quedan movimientos ni el pedido vacío. */
            if ($lineasCreadas === 0) {
                throw new VentaSinStockException(
                    'No hay stock disponible para ninguno de los productos solicitados.'
                );
            }

            /* Los precios de venta ya incluyen IGV, como es habitual en
               mostrador. Se separa la base imponible del impuesto para el
               comprobante y para el contador. */
            $baseGravadaSinIgv = $tasaIgv > 0 ? round($baseGravada / (1 + $tasaIgv), 2) : $baseGravada;
            $igv               = round($baseGravada - $baseGravadaSinIgv, 2);

            /* Lo exonerado y lo inafecto entran al total tal cual: no llevan
               impuesto que extraer, su precio ya es el importe final. */
            $total = round($baseGravada + $baseExonerada + $baseInafecta, 2);

            $medioResumen = $this->registrarPagos($pedido, $pagos, $total);

            $pedido->update([
                'subtotal_gravado'   => $baseGravadaSinIgv,
                'subtotal_exonerado' => round($baseExonerada, 2),
                'subtotal_inafecto'  => round($baseInafecta, 2),
                'igv'                => $igv,
                'total'              => $total,
                'medio_pago'         => $medioResumen,
            ]);

            return $pedido->fresh(['detalles.lotesAsignados', 'incidencias', 'pagos']);
        });
    }

    /**
     * Valida y guarda el cobro. Devuelve el resumen para `pedidos.medio_pago`.
     *
     * Reglas, y el porqué de cada una:
     *
     * - Sin datos de pago se asume efectivo exacto. Es lo que ocurre en el
     *   mostrador la mayoría de las veces y lo que hacía el sistema anterior;
     *   obligar a declararlo volvería más lenta la venta típica.
     *
     * - Los montos deben sumar EXACTAMENTE el total. Ni de más ni de menos: un
     *   cobro que no cuadra con la venta es un descuadre de caja esperando a
     *   aparecer al cierre del día, y para entonces ya nadie recuerda cuál fue.
     *
     * - El vuelto sale sólo del efectivo. De una tarjeta o un Yape no se da
     *   vuelto: si el cliente transfirió de más, eso se devuelve por el mismo
     *   medio, no sacando billetes de la gaveta.
     *
     * - Un solo pago en efectivo por venta. Dos entregas de billetes son una
     *   sola entrega; separarlas sólo complica el arqueo.
     */
    private function registrarPagos(Pedido $pedido, array $pagos, float $total): string
    {
        /* Venta de S/ 0.00: no hay nada que cobrar ni medio que declarar. */
        if ($total <= 0) {
            return 'efectivo';
        }

        if ($pagos === []) {
            $pagos = [['medio' => 'efectivo', 'monto' => $total, 'monto_recibido' => $total]];
        }

        $suma = 0.0;
        $mediosVistos = [];
        $lineas = [];

        foreach ($pagos as $pago) {
            $medio = strtolower(trim((string) ($pago['medio'] ?? '')));

            if (! in_array($medio, PedidoPago::MEDIOS, true)) {
                throw new RuntimeException("Medio de pago no válido: {$medio}");
            }

            if ($medio === 'efectivo' && in_array('efectivo', $mediosVistos, true)) {
                throw new RuntimeException('Sólo se admite un pago en efectivo por venta.');
            }

            $mediosVistos[] = $medio;

            /* Con un solo medio el monto se deduce del total: el vendedor no
               debería teclear una cifra que el sistema ya conoce. Con pago
               dividido sí hay que declararlo — ahí el reparto es una decisión
               del cliente que el sistema no puede adivinar. */
            if (isset($pago['monto'])) {
                $monto = round((float) $pago['monto'], 2);
            } elseif (count($pagos) === 1) {
                $monto = round($total, 2);
            } else {
                throw new RuntimeException(
                    "Con pago dividido, cada medio debe indicar su importe (falta el de {$medio})."
                );
            }

            if ($monto <= 0) {
                throw new RuntimeException('Cada medio de pago debe cubrir un importe mayor que cero.');
            }

            $recibido = null;
            $vuelto   = null;

            if ($medio === 'efectivo') {
                $recibido = isset($pago['monto_recibido'])
                    ? round((float) $pago['monto_recibido'], 2)
                    : $monto;

                if ($recibido + 0.005 < $monto) {
                    throw new RuntimeException(
                        sprintf('El efectivo recibido (S/ %.2f) no cubre S/ %.2f.', $recibido, $monto)
                    );
                }

                $vuelto = round($recibido - $monto, 2);
            }

            $suma += $monto;

            $lineas[] = [
                'pedido_id'      => $pedido->id,
                'medio'          => $medio,
                'monto'          => $monto,
                'monto_recibido' => $recibido,
                'vuelto'         => $vuelto,
                'referencia'     => isset($pago['referencia'])
                    ? (trim((string) $pago['referencia']) ?: null)
                    : null,
            ];
        }

        /* Tolerancia de medio céntimo: es error de redondeo al sumar decimales,
           no una diferencia real de caja. */
        if (abs(round($suma, 2) - round($total, 2)) > 0.005) {
            throw new RuntimeException(
                sprintf('Los pagos suman S/ %.2f y la venta es de S/ %.2f.', $suma, $total)
            );
        }

        foreach ($lineas as $linea) {
            PedidoPago::create($linea);
        }

        $unicos = array_values(array_unique($mediosVistos));

        return count($unicos) === 1 ? $unicos[0] : 'mixto';
    }

    private function registrarIncidencia(
        ?int $pedidoId,
        ?int $detalleId,
        int $productoId,
        string $tipo,
        int $solicitada,
        int $atendida,
        ?int $usuarioId,
        string $observacion,
    ): void {
        IncidenciaVenta::create([
            'pedido_id'           => $pedidoId,
            'pedido_detalle_id'   => $detalleId,
            'producto_id'         => $productoId,
            'tipo'                => $tipo,
            'cantidad_solicitada' => $solicitada,
            'cantidad_atendida'   => $atendida,
            'usuario_id'          => $usuarioId,
            'observacion'         => $observacion,
        ]);
    }
}
