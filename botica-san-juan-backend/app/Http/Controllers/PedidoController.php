<?php

namespace App\Http\Controllers;

use App\Exceptions\VentaSinStockException;
use App\Models\Pedido;
use App\Services\ResumenClienteService;
use App\Services\ResumenPedidosService;
use App\Services\VentaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PedidoController extends Controller
{
    /**
     * Cifras de cabecera de la pantalla de pedidos.
     *
     * Van aparte del listado porque hablan del catálogo entero y el listado
     * viene paginado. Contar sobre la página y presentarlo como total es el
     * error que ya se corrigió en tablero y en productos.
     */
    public function resumen(ResumenPedidosService $resumen)
    {
        return response()->json(['data' => $resumen->resumen()]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $request = request();

        /* `receta` se carga aquí, en una sola consulta para toda la página.
           La pantalla necesita pintar el distintivo en cada fila: pedirlo por
           pedido serían veinte peticiones para dibujar una lista. */
        $query = Pedido::with(['usuario', 'pedidoDetalles.producto', 'receta'])
            ->orderByDesc('fecha_pedido');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('estado', $request->input('status'));
        }

        /* El pedido del portal y la venta de mostrador conviven en esta tabla
           pero son dos trabajos distintos, así que la pantalla los pide por
           separado en vez de mezclarlos en una sola lista. */
        if (in_array($request->input('origen'), [Pedido::ORIGEN_WEB, Pedido::ORIGEN_POS], true)) {
            $query->where('origen', $request->input('origen'));
        }

        if ($request->filled('date')) {
            $query->whereDate('fecha_pedido', $request->input('date'));
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($search) {
                $inner->where('id', 'like', "%{$search}%")
                    ->orWhereHas('usuario', function ($usuarioQuery) use ($search) {
                        $usuarioQuery->where('nombre', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('pedidoDetalles.producto', function ($productoQuery) use ($search) {
                        $productoQuery->where('nombre', 'like', "%{$search}%");
                    });
            });
        }

        $shouldPaginate = $request->has('page') || $request->has('per_page') || $request->boolean('paginate');

        if ($shouldPaginate) {
            $perPage = (int) $request->input('per_page', 10);
            $pagina = $query->paginate(max(1, min($perPage, 100)));

            $pagina->getCollection()->each($this->anotarReceta(...));

            return $pagina;
        }

        return $query->get()->each($this->anotarReceta(...));
    }

    /**
     * Añade al pedido si necesita receta y en qué estado está la suya.
     *
     * Se calcula sobre las relaciones YA cargadas, sin tocar la base otra
     * vez. Llamar a `requiereReceta()` del modelo aquí dispararía una
     * consulta por fila, que es el patrón que ya costó caro al contar
     * pedidos en el navegador en lugar de en SQL.
     */
    private function anotarReceta(Pedido $pedido): void
    {
        $pedido->setAttribute(
            'requiere_receta',
            $pedido->pedidoDetalles->contains(fn ($linea) => (bool) $linea->producto?->requiere_receta),
        );

        $pedido->setAttribute('receta_estado', $pedido->receta?->estado);
    }

    /**
     * Get orders for a specific user.
     */
    /**
     * Pedidos de un usuario.
     *
     * FALLO CORREGIDO: la ruta devolvia los pedidos de CUALQUIER usuario con
     * solo cambiar el identificador de la direccion. Un cliente autenticado
     * podia recorrer el historial de compras de los demas —nombre, documento,
     * productos y montos— sin mas que ir sumando uno al id.
     *
     * Ahora cada quien solo ve lo suyo; el administrador si puede consultar el
     * de cualquiera, porque atiende en mostrador y necesita hacerlo.
     */
    /**
     * Resumen del cliente que pregunta, agregado en SQL.
     *
     * No recibe `usuario_id`: lo deduce del token, por la misma razon que los
     * comprobantes. Un endpoint que acepta de quien son las cifras que
     * devuelve depende de que alguien se acuerde de comprobarlo.
     */
    public function miResumen(Request $request, ResumenClienteService $resumen)
    {
        return response()->json(['data' => $resumen->resumen($request->user())]);
    }

    public function getByUser(Request $request, $usuarioId)
    {
        $solicitante = $request->user();

        if ((int) $solicitante->id !== (int) $usuarioId && $solicitante->rol !== 'administrador') {
            return response()->json([
                'message' => 'No puedes consultar los pedidos de otro usuario.',
            ], 403);
        }

        $pedidos = Pedido::with(['pedidoDetalles.producto', 'receta'])
            ->where('usuario_id', $usuarioId)
            ->get();

        /* Se añaden dos datos que el portal necesita para decidir si enseña
           el bloque de receta, y se calculan AQUÍ y no en la pantalla.

           La alternativa era que el portal pidiera el estado de la receta de
           cada pedido por separado: con veinte pedidos en el historial son
           veinte peticiones para pintar una lista. El mismo error que ya se
           corrigió contando pedidos en el navegador en vez de en la base.

           `requiere_receta` sale de los productos ya cargados, sin consulta
           extra: `requiereReceta()` del modelo iría a la base una vez por
           pedido. */
        return $pedidos->each($this->anotarReceta(...));
    }

    /**
     * Confirma una venta: crea el pedido con su detalle y descuenta el stock
     * dentro de una unica transaccion.
     *
     * El consumidor declara unicamente que producto y que cantidad quiere. El
     * precio y el total los determina el servidor a partir del catalogo
     * vigente, de modo que un cliente manipulado no puede fijar el importe.
     */
    public function confirmar(Request $request, VentaService $ventas)
    {
        /* Quién es el cliente: la sesión si la hay, nadie si no.
           `auth('sanctum')` resuelve el token cuando viene en la cabecera y
           devuelve null cuando no, sin exigirlo. Por eso esta ruta ya no vive
           dentro del grupo que obliga a estar autenticado: un visitante tiene
           que poder encargar. */
        $cliente = auth('sanctum')->user();
        $esInvitado = $cliente === null;

        /* Quien entró con Google no tiene DNI, y el comprobante lo exige.
           ----------------------------------------------------------------
           Google acredita un correo; no sabe el documento de nadie. Una
           cuenta creada por ahí nace sin DNI, de modo que `$cliente?->dni`
           sería nulo y el pedido saldría sin documento: una boleta sin
           adquirente.

           En lugar de inventar un número o de añadir una pantalla nueva, se
           trata a esa cuenta igual que a un invitado PARA ESTE CAMPO: se le
           pide el documento aquí, que es exactamente donde hace falta y
           donde el formulario ya sabe pedirlo. */
        $faltaDocumento = $esInvitado || $cliente->necesitaCompletarDni();

        $validado = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000'],
            'direccion' => ['nullable', 'string', 'max:255'],

            /* Checkout rápido: sin cuenta, pero no sin datos. Estos tres son lo
               mínimo para poder entregar el pedido y avisar cuando esté listo.
               Se exigen SOLO al invitado; quien inició sesión ya los tiene. */
            'cliente_nombre'   => [$esInvitado ? 'required' : 'nullable', 'string', 'max:255'],
            'cliente_documento' => [
                $faltaDocumento ? 'required' : 'nullable',
                'string',
                /* DNI (8) o RUC (11). El tipo de comprobante se decide con esto:
                   RUC → factura, DNI → boleta. */
                'regex:/^(\d{8}|\d{11})$/',
            ],
            'cliente_telefono' => [$esInvitado ? 'required' : 'nullable', 'string', 'max:30'],
        ], [
            'cliente_nombre.required'    => 'Necesitamos un nombre para preparar el pedido.',
            'cliente_documento.required' => 'Necesitamos tu DNI o RUC para emitir el comprobante.',
            'cliente_documento.regex'    => 'El documento debe ser un DNI de 8 dígitos o un RUC de 11.',
            'cliente_telefono.required'  => 'Necesitamos un teléfono para avisarte cuando esté listo.',
        ]);

        /* Este endpoint llamaba a VentaService::confirmar(), un metodo que
           dejo de existir cuando el servicio se unifico para el punto de
           venta. Desde entonces el checkout del portal respondia 500: la
           suite de pruebas lo senalaba, pero nadie ejecutaba la suite.

           Se pasa el origen explicitamente. Sin ese argumento el pedido
           nacería como venta de mostrador —completado y pagado— y un encargo
           web sin cobrar entraria en la caja del dia. */
        /* Los datos del cliente: los del formulario si vinieron, y si no los de
           la cuenta. Se copian al pedido aunque haya sesión porque el
           comprobante debe decir a quién se le vendió el día que se emitió,
           aunque mañana esa persona cambie su nombre en el perfil. */
        $documento = $validado['cliente_documento'] ?? $cliente?->dni;

        $datosCliente = [
            'cliente_nombre'    => $validado['cliente_nombre'] ?? $cliente?->nombre,
            'cliente_documento' => $documento,
            'cliente_tipo_documento' => $this->tipoDeDocumento($documento),
            'cliente_telefono'  => $validado['cliente_telefono'] ?? $cliente?->telefono,
            'observacion'       => $validado['direccion'] ?? null,
        ];

        try {
            $pedido = $ventas->registrar(
                items: $validado['items'],
                datosCliente: $datosCliente,
                usuarioClienteId: $cliente?->id,
                origen: Pedido::ORIGEN_WEB,
            );
        } catch (VentaSinStockException $e) {
            /* Falta de stock no es un fallo del sistema: es informacion que el
               cliente necesita para ajustar su pedido. Sin este bloque salia
               como 500 y el portal mostraba "error del servidor" cuando lo
               unico que pasaba es que no habia suficientes unidades. */
            return response()->json([
                'message'   => $e->getMessage(),
                'sin_stock' => true,
            ], 422);
        }

        /* El comprobante se emite en el acto, con el pedido ya cerrado y sus
           importes calculados. Es idempotente: un doble clic o un reintento no
           produce un segundo comprobante del mismo pedido, que sería duplicidad
           tributaria. */
        $comprobante = app(\App\Services\ComprobanteService::class)->emitir($pedido);

        return response()->json([
            'message' => 'Pedido registrado. Queda pendiente de preparacion.',
            'pedido' => $pedido,
            'comprobante' => [
                'tipo'          => $comprobante->tipo_comprobante,
                'identificador' => $comprobante->serie.'-'.str_pad((string) $comprobante->numero, 8, '0', STR_PAD_LEFT),
                'estado_sunat'  => $comprobante->estado_sunat,
            ],
            /* Un invitado no tiene panel donde consultar su pedido, así que el
               número es lo único con lo que puede preguntar por él en el
               mostrador. Por eso se devuelve siempre y la pantalla lo muestra. */
            'seguimiento' => [
                'pedido_id'  => $pedido->id,
                'invitado'   => $esInvitado,
            ],
        ], 201);
    }

    /**
     * Tipo de documento a partir de su longitud.
     *
     * 11 dígitos es RUC y obliga a factura; 8 es DNI y va con boleta. Cualquier
     * otra cosa se registra como venta sin documento identificado, que es lo que
     * de verdad es, en vez de inventar un tipo.
     */
    private function tipoDeDocumento(?string $documento): string
    {
        $limpio = preg_replace('/\D/', '', (string) $documento);

        return match (strlen((string) $limpio)) {
            11 => 'ruc',
            8  => 'dni',
            default => 'sin_documento',
        };
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|exists:usuarios,id',
            'fecha_pedido' => 'required|date',
            'total' => 'required|numeric|min:0',
            'estado' => 'required|string|max:50',
        ]);

        $pedido = Pedido::create($request->all());

        return response()->json($pedido, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $pedido = Pedido::with(['usuario', 'pedidoDetalles.producto'])->findOrFail($id);
        return response()->json($pedido);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $pedido = Pedido::findOrFail($id);

        $datos = $request->validate([
            'usuario_id' => 'sometimes|required|exists:usuarios,id',
            'fecha_pedido' => 'sometimes|required|date',
            'total' => 'sometimes|required|numeric|min:0',
            /* Antes era `string|max:50`, y por eso el frontend podía guardar
               'procesando' o 'entregado' sin que nada protestara: estados que
               no significan nada para el resto del sistema. */
            'estado' => ['sometimes', 'required', Rule::in(Pedido::ESTADOS)],
        ]);

        /* Un pedido con medicamentos de venta bajo receta no se completa sin
           una receta validada.
           ----------------------------------------------------------------
           Esta comprobación está aquí, en el servidor, y no solo en el botón
           de la pantalla. La pantalla puede deshabilitar el botón —y lo
           hace—, pero esta misma ruta la puede llamar cualquiera con sesión
           de administrador desde fuera de la aplicación. Dispensar sin receta
           lo que la ley exige con receta no es un error de interfaz.

           Se bloquea el paso a 'completado', que es el estado en el que el
           producto sale del mostrador. 'confirmado' sí se permite: preparar
           el pedido mientras se revisa la receta es justamente el flujo
           normal de la botica. */
        $pasaACompletado = ($datos['estado'] ?? null) === Pedido::ESTADO_COMPLETADO
            && $pedido->estado !== Pedido::ESTADO_COMPLETADO;

        if ($pasaACompletado && $pedido->requiereReceta()) {
            $receta = $pedido->receta;

            if ($receta === null || ! $receta->estaValidada()) {
                return response()->json([
                    'message' => $receta === null
                        ? 'Este pedido lleva productos de venta bajo receta y no tiene receta adjunta.'
                        : 'La receta de este pedido todavía no está validada.',
                    'errors' => ['estado' => ['Falta la receta validada para poder entregar el pedido.']],
                ], 422);
            }
        }

        /* `$request->all()` dejaba escribir cualquier columna del `fillable`,
           incluidos los importes fiscales de una venta ya emitida. Solo se
           guarda lo que se validó. */
        $pedido->update($datos);

        return response()->json($pedido);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $pedido = Pedido::findOrFail($id);

        /* Una venta cerrada no se borra: se anula.
           ----------------------------------------------------------------
           El boton de la papelera en la pantalla de pedidos llamaba aqui sin
           ninguna comprobacion. Bastaba un clic para que desapareciera una
           venta de mostrador que ya movio stock, ya cobro y —si tiene
           comprobante— ya figura en el registro de ventas del contador. La
           fila se iba de la base y el sistema quedaba descuadrado contra la
           caja y contra SUNAT, sin rastro de que algo hubiera existido.

           Borrar esta permitido solo mientras el pedido siga siendo una
           intencion: un encargo del portal que nadie atendio todavia. */
        if ($pedido->estado === Pedido::ESTADO_COMPLETADO) {
            return response()->json([
                'message' => 'Una venta completada no se elimina: debe anularse para que quede el rastro.',
                'motivo'  => 'venta_cerrada',
            ], 422);
        }

        if ($pedido->comprobanteElectronico()->exists()) {
            return response()->json([
                'message' => 'El pedido tiene un comprobante emitido y no puede eliminarse.',
                'motivo'  => 'comprobante_emitido',
            ], 422);
        }

        $pedido->delete();

        return response()->json(['message' => 'Pedido deleted successfully']);
    }
}
