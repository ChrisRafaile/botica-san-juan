<?php

namespace App\Http\Controllers;

use App\Models\PedidoPago;
use App\Services\SincronizacionVentasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Recepción de las ventas cobradas sin conexión.
 *
 * El contrato completo está en docs/ARQUITECTURA-OFFLINE.md. Lo que conviene
 * tener presente al leer este archivo es por qué la respuesta es 200 incluso
 * cuando alguna venta del lote no se pudo registrar: el estado pertenece a
 * cada venta, no a la petición. Si el endpoint respondiera 422 por una venta
 * mala de veinte, la caja no tendría forma de saber cuáles de las otras
 * diecinueve entraron, y la única salida segura sería reenviarlas todas a
 * ciegas y confiar en la idempotencia. Funciona, pero deja al vendedor sin
 * saber qué pasó, que es justo lo que queremos evitar.
 */
class SincronizacionController extends Controller
{
    public function __construct(private readonly SincronizacionVentasService $sincronizacion)
    {
    }

    public function ventas(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'dispositivo'                  => ['nullable', 'string', 'max:40'],

            /* El tope de 50 no es estético. Cada venta abre su propia
               transacción y el plan de Render tiene un tiempo de respuesta
               acotado: un lote enorme empieza a competir con la atención en
               mostrador de la otra caja. La cola se envía en tandas. */
            'ventas'                       => ['required', 'array', 'min:1', 'max:50'],

            'ventas.*.uuid_local'          => ['required', 'uuid'],
            'ventas.*.nota_contingencia'   => ['nullable', 'string', 'max:20'],
            'ventas.*.registrada_en'       => ['required', 'date'],
            'ventas.*.observacion'         => ['nullable', 'string', 'max:500'],

            'ventas.*.cliente'                 => ['nullable', 'array'],
            'ventas.*.cliente.nombre'          => ['nullable', 'string', 'max:255'],
            'ventas.*.cliente.documento'       => ['nullable', 'string', 'max:20'],
            'ventas.*.cliente.tipo_documento'  => ['nullable', Rule::in(['dni', 'ruc', 'ce', 'sin_documento'])],

            'ventas.*.items'                   => ['required', 'array', 'min:1'],
            'ventas.*.items.*.producto_id'     => ['required', 'integer'],
            'ventas.*.items.*.unidad_venta'    => ['required', Rule::in(['unidad', 'blister', 'caja'])],
            'ventas.*.items.*.cantidad'        => ['required', 'integer', 'min:1'],

            'ventas.*.pagos'                   => ['nullable', 'array', 'max:4'],
            'ventas.*.pagos.*.medio'           => ['required', Rule::in(PedidoPago::MEDIOS)],
            'ventas.*.pagos.*.monto'           => ['nullable', 'numeric', 'min:0.01'],
            'ventas.*.pagos.*.monto_recibido'  => ['nullable', 'numeric', 'min:0'],
            'ventas.*.pagos.*.referencia'      => ['nullable', 'string', 'max:60'],
        ]);

        /* `producto_id` se valida como entero y no con `exists:productos,id`
           a propósito. Un producto borrado del catálogo después de la venta
           no debe tumbar la validación del lote entero: esa venta concreta
           se rechaza con su motivo y las demás siguen su camino. */

        $resultados = $this->sincronizacion->procesarLote(
            ventas: $datos['ventas'],
            operador: $request->user(),
            dispositivo: $datos['dispositivo'] ?? null,
        );

        return response()->json([
            'procesadas' => count($resultados),
            'resultados' => $resultados,
        ]);
    }
}
