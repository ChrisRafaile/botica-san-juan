<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Models\Producto;
use App\Services\DesgloseFiscalService;
use Illuminate\Http\Request;

/**
 * Cotización del carrito del portal público.
 *
 * POR QUÉ EL CARRITO SE COTIZA EN EL SERVIDOR
 *
 * El carrito del portal vive en el navegador (localStorage), para que un
 * visitante pueda armar su pedido sin crear una cuenta y no lo pierda al
 * recargar. Pero en el navegador se guarda **únicamente qué producto y cuánto**:
 * ni el precio, ni el stock, ni si necesita receta.
 *
 * Esos tres datos se piden aquí cada vez que se abre el carrito, por tres
 * razones distintas:
 *
 *  1. El precio de hace tres días no es el precio de hoy. Un carrito que guarda
 *     el precio enseña una cifra que al cobrar no coincide, y el cliente tiene
 *     razón al reclamar.
 *
 *  2. El stock cambia mientras el carrito está abierto. Alguien pudo comprar en
 *     mostrador las dos últimas cajas. Prometer existencias que no están en el
 *     anaquel es el fallo que más caro sale en una botica.
 *
 *  3. Lo que se guarda en el navegador lo puede editar cualquiera. Si el precio
 *     viajara en el localStorage, cambiarlo a 0.01 sería cuestión de abrir la
 *     consola. El tope de cantidad del selector es una cortesía para el usuario;
 *     **el límite de verdad se aplica aquí**, y vuelve a aplicarse aunque el
 *     navegador mande 9 999.
 *
 * ES CONSULTA, NO RESERVA
 *
 * Cotizar no aparta mercancía ni toca lotes. El stock se descuenta cuando la
 * venta se registra, con FEFO y asignación por lote, en VentaService. Dicho de
 * otro modo: dos personas pueden cotizar a la vez las mismas tres cajas; sólo
 * quien compre primero se las lleva. Reservar al cotizar bloquearía inventario
 * por carritos abandonados, que es la mayoría de ellos.
 */
class CarritoPublicoController extends Controller
{
    /** Techo por línea. Un pedido de portal no es una compra mayorista. */
    private const MAX_POR_LINEA = 99;

    /**
     * POST /api/carrito/cotizar   (público)
     *
     * Entrada:  { "items": [ { "producto_id": 12, "cantidad": 3 }, ... ] }
     *
     * Salida: cada línea con su precio y stock ACTUALES, la cantidad realmente
     * servible, el aviso de receta, y el desglose fiscal del conjunto.
     */
    public function cotizar(Request $request, DesgloseFiscalService $fiscal)
    {
        $datos = $request->validate([
            'items'               => ['present', 'array', 'max:50'],
            'items.*.producto_id' => ['required', 'integer', 'min:1'],
            'items.*.cantidad'    => ['required', 'integer', 'min:1'],
        ]);

        /* Se agrupa por producto antes de consultar: si el navegador manda dos
           líneas del mismo producto —pasa al añadir desde dos pestañas— deben
           contar como una sola contra el stock, no como dos topes separados. */
        $pedidas = [];
        foreach ($datos['items'] as $linea) {
            $id = (int) $linea['producto_id'];
            $pedidas[$id] = ($pedidas[$id] ?? 0) + (int) $linea['cantidad'];
        }

        if ($pedidas === []) {
            return response()->json($this->vacio($fiscal));
        }

        $ids = array_keys($pedidas);

        $productos = Producto::query()
            ->with(['categoria:id,nombre'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        /* Stock vendible de todas las líneas en UNA consulta agrupada, no una
           por producto. `disponible()` excluye lo vencido y lo no activo:
           `productos.stock` incluye lo vencido porque físicamente está en el
           anaquel, pero no se puede vender. */
        $disponibles = Lote::query()
            ->selectRaw('producto_id, SUM(cantidad_actual) AS total')
            ->whereIn('producto_id', $ids)
            ->disponible()
            ->groupBy('producto_id')
            ->pluck('total', 'producto_id');

        $lineas       = [];
        $ajustes      = [];
        $baseGravada  = 0.0;
        $exonerada    = 0.0;
        $inafecta     = 0.0;

        foreach ($pedidas as $productoId => $solicitada) {
            $producto = $productos->get($productoId);

            /* Producto que ya no existe en el catálogo: puede haber sido
               fusionado como duplicado o dado de baja. Se informa y se descarta,
               en vez de fallar entero y dejar al cliente sin carrito. */
            if (!$producto) {
                $ajustes[] = [
                    'producto_id' => $productoId,
                    'motivo'      => 'no_disponible',
                    'mensaje'     => 'Este producto ya no está en el catálogo y se retiró del carrito.',
                ];
                continue;
            }

            $stockDisponible = (int) ($disponibles[$productoId] ?? 0);
            $servible        = max(0, min($solicitada, $stockDisponible, self::MAX_POR_LINEA));

            if ($servible < $solicitada) {
                $ajustes[] = [
                    'producto_id' => $productoId,
                    'nombre'      => $producto->nombre,
                    'motivo'      => $stockDisponible === 0 ? 'sin_stock' : 'stock_insuficiente',
                    'solicitada'  => $solicitada,
                    'servible'    => $servible,
                    'mensaje'     => $stockDisponible === 0
                        ? sprintf('%s se quedó sin stock disponible.', $producto->nombre)
                        : sprintf(
                            'De %s quedan %d unidad%s; se ajustó la cantidad.',
                            $producto->nombre,
                            $stockDisponible,
                            $stockDisponible === 1 ? '' : 'es'
                        ),
                ];
            }

            $precio   = round((float) $producto->precio, 2);
            $subtotal = round($precio * $servible, 2);

            $afectacion = $fiscal->afectacionDe($producto);
            match ($afectacion) {
                Producto::EXONERADO => $exonerada += $subtotal,
                Producto::INAFECTO  => $inafecta += $subtotal,
                default             => $baseGravada += $subtotal,
            };

            $lineas[] = [
                'producto_id'      => $producto->id,
                'nombre'           => $producto->nombre,
                'concentracion'    => $producto->concentracion,
                'presentacion'     => $producto->presentacion,
                'laboratorio'      => $producto->laboratorio,
                'tipo'             => $producto->tipo,
                'imagen'           => $producto->imagen,
                'precio'           => $precio,
                'cantidad'         => $servible,
                'subtotal'         => $subtotal,
                'stock_disponible' => $stockDisponible,
                'requiere_receta'  => (bool) $producto->requiere_receta,
                'afectacion_igv'   => $afectacion,
            ];
        }

        $desglose = $fiscal->repartir($baseGravada, $exonerada, $inafecta);

        return response()->json([
            'lineas'   => $lineas,
            /* Los ajustes son la parte que la pantalla DEBE mostrar: cambiar
               una cantidad en silencio es peor que no poder servirla. */
            'ajustes'  => $ajustes,
            'unidades' => array_sum(array_column($lineas, 'cantidad')),
            'receta'   => collect($lineas)->contains(fn ($l) => $l['requiere_receta'] && $l['cantidad'] > 0),
            'desglose' => $desglose,
        ]);
    }

    /** Respuesta de carrito vacío, con el desglose en cero pero completo. */
    private function vacio(DesgloseFiscalService $fiscal): array
    {
        return [
            'lineas'   => [],
            'ajustes'  => [],
            'unidades' => 0,
            'receta'   => false,
            'desglose' => $fiscal->repartir(0.0),
        ];
    }
}
