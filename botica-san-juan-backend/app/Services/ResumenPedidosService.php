<?php

namespace App\Services;

use App\Models\Pedido;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cifras de cabecera de la pantalla de pedidos.
 * ---------------------------------------------------------------------------
 * POR QUÉ ES UN SERVICIO Y NO UN `computed` EN LA VISTA
 *
 * Es la cuarta vez que aparece el mismo error en este proyecto: la vista
 * contaba sobre `orders.value`, que es UNA PÁGINA de diez registros, y
 * presentaba el resultado como si fuera el total del negocio. Con 42 pedidos
 * el error es discreto; con 400 el tablero miente sin avisar.
 *
 * La regla que se sigue aquí: si una cifra habla del catálogo entero, la
 * calcula la base de datos, no el navegador.
 *
 * ---------------------------------------------------------------------------
 * POR QUÉ SEPARA PORTAL DE MOSTRADOR
 *
 * Son dos ciclos de vida distintos que la pantalla anterior mezclaba:
 *
 *   - El pedido web es TRABAJO PENDIENTE. Alguien encargó algo y espera.
 *   - La venta de mostrador es HISTORIAL. Ya ocurrió, ya se pagó, ya se
 *     entregó. No hay nada que hacer con ella salvo consultarla.
 *
 * Contarlas juntas produce un número que no responde a ninguna pregunta real.
 */
class ResumenPedidosService
{
    /**
     * Cola de trabajo del portal web y actividad de mostrador del día.
     *
     * @return array{
     *     portal: array{pendientes: array, confirmados: array, mas_antiguo_dias: int|null},
     *     mostrador: array{hoy: array},
     *     total_registros: int
     * }
     */
    public function resumen(): array
    {
        /* Una sola consulta agrupada en vez de cuatro COUNT: los importes y
           los conteos salen del mismo recorrido de la tabla. */
        $porEstado = Pedido::query()
            ->delPortal()
            ->selectRaw('estado, COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS importe')
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        $tramo = function (string $estado) use ($porEstado): array {
            $fila = $porEstado->get($estado);

            return [
                'pedidos' => (int) ($fila->pedidos ?? 0),
                'importe' => round((float) ($fila->importe ?? 0), 2),
            ];
        };

        return [
            'portal' => [
                'pendientes'       => $tramo(Pedido::ESTADO_PENDIENTE),
                'confirmados'      => $tramo(Pedido::ESTADO_CONFIRMADO),
                'mas_antiguo_dias' => $this->diasDelPedidoAbiertoMasAntiguo(),
                'abiertos_sin_lineas' => $this->abiertosSinLineas(),
            ],
            'mostrador'       => ['hoy' => $this->mostradorHoy()],
            'total_registros' => (int) Pedido::query()->count(),
        ];
    }

    /**
     * Antigüedad del pedido web más viejo que sigue sin cerrarse.
     *
     * Es la cifra que convierte una lista en una alerta. "32 pendientes" no
     * dice nada; "32 pendientes, el más antiguo de hace 18 días" dice que la
     * cola dejó de atenderse. Devuelve null cuando no hay pedidos abiertos.
     */
    private function diasDelPedidoAbiertoMasAntiguo(): ?int
    {
        $fecha = Pedido::query()
            ->delPortal()
            ->abiertos()
            ->min(DB::raw('COALESCE(fecha_pedido, fecha)'));

        if (!$fecha) {
            return null;
        }

        return Carbon::parse($fecha)->startOfDay()->diffInDays(Carbon::today());
    }

    /**
     * Pedidos abiertos que no tienen ni una línea de detalle.
     *
     * Un encargo sin líneas no es un encargo: es una cabecera que quedó
     * huérfana. Importa porque el importe que muestra la cabecera se calcula
     * sobre `pedidos.total`, así que esos registros suman dinero a un total
     * que no corresponde a ningún producto. Mientras el número sea alto, la
     * cifra de la cola no describe trabajo real y la pantalla lo advierte en
     * vez de presentarla como si lo fuera.
     */
    private function abiertosSinLineas(): int
    {
        return (int) Pedido::query()
            ->delPortal()
            ->abiertos()
            ->whereDoesntHave('pedidoDetalles')
            ->count();
    }

    /**
     * Ventas de mostrador registradas hoy.
     *
     * Se apoya en COALESCE porque `fecha_pedido` se añadió en una migración
     * posterior a `fecha` y los registros antiguos solo tienen la segunda.
     */
    private function mostradorHoy(): array
    {
        $fila = Pedido::query()
            ->deMostrador()
            ->where('estado', Pedido::ESTADO_COMPLETADO)
            ->whereRaw('DATE(COALESCE(fecha_pedido, fecha)) = ?', [Carbon::today()->toDateString()])
            ->selectRaw('COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS importe')
            ->first();

        return [
            'pedidos' => (int) ($fila->pedidos ?? 0),
            'importe' => round((float) ($fila->importe ?? 0), 2),
        ];
    }
}
