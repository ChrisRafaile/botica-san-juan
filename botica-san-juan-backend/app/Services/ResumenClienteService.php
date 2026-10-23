<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Cifras de cabecera del portal del cliente.
 * ---------------------------------------------------------------------------
 * POR QUE LAS CALCULA LA BASE Y NO EL NAVEGADOR
 *
 * Es la quinta vez que este proyecto se encuentra con el mismo error, y por
 * eso conviene escribirlo: una vista que recibe la lista de pedidos y cuenta
 * sobre ella esta contando lo que le cupo en la pagina, no lo que hay. Con
 * ocho pedidos nadie lo nota; con doscientos, el portal le dice al cliente un
 * numero falso con toda naturalidad. El caso mas visible fue la pantalla de
 * productos mostrando «2 en stock» sobre un catalogo entero.
 *
 * La regla del proyecto: si una cifra habla del total, la calcula SQL.
 *
 * ---------------------------------------------------------------------------
 * LO QUE ESTE SERVICIO NO DEVUELVE, Y POR QUE
 *
 * El carrito. En el portal vive en `localStorage` y solo guarda producto y
 * cantidad; el servidor no tiene forma de saber que hay dentro hasta que se
 * pide una cotizacion. Devolver aqui un «carrito activo» obligaria a inventar
 * una cifra o a resucitar la tabla `carrito`, que se abandono precisamente
 * porque exigia sesion para empezar a comprar. Esa tarjeta la alimenta el
 * almacen del navegador, que es quien sabe la verdad.
 */
class ResumenClienteService
{
    /**
     * @return array{
     *     pedidos: array{abiertos: int, completados: int, anulados: int, total: int},
     *     gasto: array{total: float, ultimos_30_dias: float},
     *     ultimo_pedido: array|null,
     *     comprobantes: int
     * }
     */
    public function resumen(Usuario $usuario): array
    {
        /* Una sola consulta con agregados condicionales en vez de cuatro
           `count()` seguidos: la base recorre la tabla una vez. */
        $fila = Pedido::query()
            ->where('usuario_id', $usuario->id)
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where estado in (?, ?)) as abiertos', Pedido::ESTADOS_ABIERTOS)
            ->selectRaw('count(*) filter (where estado = ?) as completados', [Pedido::ESTADO_COMPLETADO])
            ->selectRaw('count(*) filter (where estado = ?) as anulados', [Pedido::ESTADO_ANULADO])
            /* El gasto NO incluye los anulados: un pedido anulado no se pago,
               y sumarlo le diria al cliente que gasto un dinero que sigue en
               su bolsillo. */
            ->selectRaw('coalesce(sum(total) filter (where estado <> ?), 0) as gastado', [Pedido::ESTADO_ANULADO])
            ->selectRaw(
                'coalesce(sum(total) filter (where estado <> ? and fecha >= ?), 0) as gastado_reciente',
                [Pedido::ESTADO_ANULADO, now()->subDays(30)]
            )
            ->first();

        $ultimo = Pedido::query()
            ->where('usuario_id', $usuario->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first(['id', 'fecha', 'estado', 'total']);

        $comprobantes = ComprobanteElectronico::query()
            ->whereHas('pedido', fn ($q) => $q->where('usuario_id', $usuario->id))
            ->count();

        return [
            'pedidos' => [
                'abiertos'    => (int) $fila->abiertos,
                'completados' => (int) $fila->completados,
                'anulados'    => (int) $fila->anulados,
                'total'       => (int) $fila->total,
            ],
            'gasto' => [
                'total'           => round((float) $fila->gastado, 2),
                'ultimos_30_dias' => round((float) $fila->gastado_reciente, 2),
            ],
            'ultimo_pedido' => $ultimo === null ? null : [
                'id'     => $ultimo->id,
                'fecha'  => $ultimo->fecha,
                'estado' => $ultimo->estado,
                'total'  => (float) $ultimo->total,
            ],
            'comprobantes' => $comprobantes,
        ];
    }
}
