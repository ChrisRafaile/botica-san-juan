<?php

namespace App\Http\Controllers;

use App\Models\ComprobanteElectronico;
use App\Services\ComprobanteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Comprobantes del cliente que ha iniciado sesion.
 * ---------------------------------------------------------------------------
 * POR QUE UN CONTROLADOR APARTE Y NO MAS METODOS EN FacturacionController
 *
 * Aquel controlador existe para la gestion: emitir, reenviar a SUNAT,
 * registrar comisiones, exportar. Casi todo lo suyo es de administrador. Un
 * cliente solo necesita dos cosas —ver cuales son sus comprobantes y abrir uno
 * suyo— y mezclarlas alli obligaria a recordar, metodo a metodo, cual lleva
 * control de propiedad y cual no. Precisamente ese olvido es lo que dejo
 * abiertas las descargas de XML y PDF.
 *
 * Aqui el alcance es explicito desde el nombre del archivo: todo lo que entra
 * responde solo por el duenyo.
 */
class ComprobanteClienteController extends Controller
{
    public function __construct(private ComprobanteService $comprobantes)
    {
    }

    /**
     * Lista los comprobantes de la cuenta que pregunta.
     *
     * No recibe `usuario_id` por parametro A PROPOSITO. Un endpoint que acepta
     * de quien son los datos que devuelve depende de que alguien se acuerde de
     * comprobar que coinciden con quien pregunta; uno que los deduce del token
     * no puede equivocarse en eso.
     */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $comprobantes = ComprobanteElectronico::query()
            ->whereHas('pedido', fn ($q) => $q->where('usuario_id', $usuario->id))
            ->with('pedido:id,fecha,estado,total,origen')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ComprobanteElectronico $c) => $this->resumen($c));

        return response()->json([
            'data'  => $comprobantes,
            'total' => $comprobantes->count(),
        ]);
    }

    /**
     * Devuelve un comprobante con el detalle necesario para imprimirlo.
     *
     * La autorizacion no se escribe a mano: la decide la politica, que es la
     * misma que protege la descarga del XML y del PDF. Una sola regla, en un
     * solo sitio.
     */
    public function show(Request $request, ComprobanteElectronico $comprobante): JsonResponse
    {
        /* `Gate::authorize` y no `$this->authorize`: el controlador base de
           Laravel 12 ya no incorpora el trait AuthorizesRequests, de modo que
           aquel metodo no existe. Lanza 403 por si solo si la politica dice
           que no. */
        Gate::authorize('view', $comprobante);

        $comprobante->load('pedido.pedidoDetalles.producto');

        return response()->json([
            'data' => $this->comprobantes->datosParaImpresion($comprobante),
        ]);
    }

    /**
     * Resumen para el listado.
     *
     * Se eligen los campos uno a uno en lugar de devolver el modelo entero:
     * la tabla guarda el payload y la respuesta de SUNAT, que no le sirven de
     * nada al cliente y pueden contener datos internos del negocio.
     */
    private function resumen(ComprobanteElectronico $c): array
    {
        return [
            'id'            => $c->id,
            'tipo'          => $c->tipo_comprobante,
            'serie'         => $c->serie,
            'numero'        => $c->numero,
            'correlativo'   => sprintf('%s-%08d', $c->serie, $c->numero),
            'total'         => (float) $c->total,
            'fecha_emision' => $c->fecha_emision,
            'estado_sunat'  => $c->estado_sunat,
            'pedido' => $c->pedido === null ? null : [
                'id'     => $c->pedido->id,
                'fecha'  => $c->pedido->fecha,
                'estado' => $c->pedido->estado,
                'origen' => $c->pedido->origen,
            ],
        ];
    }
}
