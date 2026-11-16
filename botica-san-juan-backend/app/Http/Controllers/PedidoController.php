<?php

namespace App\Http\Controllers;

use App\Exceptions\PedidoNoCancelableException;
use App\Exceptions\VentaSinStockException;
use App\Models\Pedido;
use App\Services\PedidoService;
use App\Services\ResumenClienteService;
use App\Services\ResumenPedidosService;
use App\Services\VentaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * Detalle de un pedido perteneciente al cliente autenticado.
     *
     * Permite a la pantalla de checkout y pago cargar los detalles, el estado
     * del pago y la referencia del comprobante sin abrir el recurso de gestion.
     *
     * Incluye el campo `expira_en` (timestamp ISO 8601 o null), que indica la fecha y
     * hora límite de expiración de la reserva de stock para pedidos pagados con tarjeta.
     */
    public function miPedido(Request $request, string $id)
    {
        $pedido = Pedido::with([
            'pedidoDetalles.producto',
            'comprobanteElectronico:id,pedido_id,tipo_comprobante,serie,numero,estado_sunat,total,fecha_emision',
            'pago',
            'receta',
        ])->findOrFail($id);

        $solicitante = $request->user();

        if ((int) $pedido->usuario_id !== (int) $solicitante->id && $solicitante->rol !== 'administrador') {
            return response()->json([
                'message' => 'El pedido no pertenece al usuario autenticado.',
            ], 403);
        }

        $this->anotarReceta($pedido);

        return response()->json($pedido);
    }

    /**
     * Pedidos de un usuario específico.
     *
     * Consulta el historial de pedidos del cliente autenticado (o cualquiera si es administrador).
     * Cada pedido incluye el detalle de productos, comprobante, estado de receta y el campo
     * `expira_en` (timestamp ISO 8601 o null) con el límite de expiración de la reserva de stock.
     */
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
     * Confirma una venta del portal cliente y reserva stock.
     *
     * Crea el pedido con su detalle y reserva el stock de productos mediante asignación FEFO
     * dentro de una única transacción.
     *
     * Cabecera `Idempotency-Key` (opcional, string de 8 a 120 caracteres alfanuméricos o `_ . : -`):
     * Protege contra órdenes duplicadas por reintentos de red.
     * - `400 Bad Request`: Formato de clave inválido.
     * - `422 Unprocessable Content`: Conflicto de idempotencia (`codigo: "idempotencia_conflicto"`) si la misma clave se reutiliza con un cuerpo diferente.
     * - `409 Conflict`: Petición concurrente en curso (con cabecera `Retry-After: 1`).
     * - Reproducción: Si la clave ya fue procesada, reproduce la respuesta original acompañada de la cabecera `Idempotency-Replayed: true`.
     *
     * Respuestas `409 Conflict` de negocio:
     * - `codigo: "stock_insuficiente"`: Cuando no hay existencias vendibles suficientes para uno o más productos.
     * - `codigo: "contencion"`: En caso de alta contención o interbloqueo transaccional sobre las filas de inventario (incluye cabecera `Retry-After: 1`).
     *
     * Respuesta `201 Created`:
     * Incluye el objeto `pedido`, `comprobante`, `seguimiento` y el campo `expira_en` (string ISO 8601 o null).
     * `expira_en` fija el TTL de reserva (15 minutos por omisión) únicamente si el cliente tiene sesión y seleccionó `metodo_pago: "tarjeta"`.
     */
    public function confirmar(Request $request, PedidoService $pedidos)
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
        $tipoComprobante = $request->input('tipo_comprobante');
        $modalidadEntrega = $request->input('modalidad_entrega');

        $reglasDocumento = match ($tipoComprobante) {
            'factura' => ['required', 'string', 'regex:/^\d{11}$/'],
            'boleta'  => [$faltaDocumento ? 'required' : 'nullable', 'string', 'regex:/^\d{8}$/'],
            default   => [$faltaDocumento ? 'required' : 'nullable', 'string', 'regex:/^(\d{8}|\d{11})$/'],
        };

        $validado = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000'],
            'modalidad_entrega' => ['nullable', 'string', Rule::in(['recojo', 'entrega'])],
            'tipo_comprobante' => ['nullable', 'string', Rule::in(['boleta', 'factura'])],
            /**
             * Método de pago seleccionado: 'tarjeta' (pasarela online con reserva temporal de stock) o 'contra_entrega' (pago al recibir o recoger).
             */
            'metodo_pago' => ['nullable', 'string', Rule::in(['tarjeta', 'contra_entrega'])],
            'direccion' => [
                $modalidadEntrega === 'entrega' ? 'required' : 'nullable',
                'string',
                $modalidadEntrega === 'entrega' ? 'min:5' : 'min:0',
                'max:255',
            ],

            /* Checkout rápido: sin cuenta, pero no sin datos. Estos tres son lo
               mínimo para poder entregar el pedido y avisar cuando esté listo.
               Se exigen SOLO al invitado; quien inició sesión ya los tiene. */
            'cliente_nombre'   => [$esInvitado ? 'required' : 'nullable', 'string', 'max:255'],
            'cliente_documento' => $reglasDocumento,
            'cliente_telefono' => [$esInvitado ? 'required' : 'nullable', 'string', 'max:30'],
        ], [
            'cliente_nombre.required'    => 'Necesitamos un nombre para preparar el pedido.',
            'cliente_documento.required' => $tipoComprobante === 'factura'
                ? 'La factura electrónica exige un RUC válido de 11 dígitos.'
                : 'Necesitamos tu DNI o RUC para emitir el comprobante.',
            'cliente_documento.regex'    => match ($tipoComprobante) {
                'factura' => 'El RUC debe tener exactamente 11 dígitos numéricos.',
                'boleta'  => 'El DNI debe tener exactamente 8 dígitos numéricos.',
                default   => 'El documento debe ser un DNI de 8 dígitos o un RUC de 11.',
            },
            'cliente_telefono.required'  => 'Necesitamos un teléfono para avisarte cuando esté listo.',
            'direccion.required'         => 'Ingresa la dirección completa para el envío a domicilio.',
            'direccion.min'              => 'La dirección debe ser más específica (al menos 5 caracteres).',
        ]);

        /* Los datos del cliente: los del formulario si vinieron, y si no los de
           la cuenta. Se copian al pedido aunque haya sesión porque el
           comprobante debe decir a quién se le vendió el día que se emitió,
           aunque mañana esa persona cambie su nombre en el perfil. */
        $documento = $validado['cliente_documento'] ?? $cliente?->dni;
        $observacion = match ($modalidadEntrega) {
            'entrega' => trim((string) ($validado['direccion'] ?? '')),
            'recojo'  => 'Recojo en botica',
            default   => $validado['direccion'] ?? null,
        };

        $datosCliente = [
            'cliente_nombre'         => $validado['cliente_nombre'] ?? $cliente?->nombre,
            'cliente_documento'      => $documento,
            'cliente_tipo_documento' => $this->tipoDeDocumento($documento),
            'cliente_telefono'       => $validado['cliente_telefono'] ?? $cliente?->telefono,
            'observacion'            => $observacion ?: null,
        ];

        try {
            [$pedido, $comprobante] = $pedidos->confirmarDelPortal(
                items: $validado['items'],
                datosCliente: $datosCliente,
                cliente: $cliente,
                metodoPago: $validado['metodo_pago'] ?? null,
            );
        } catch (VentaSinStockException $e) {
            return response()->json([
                'message'   => $e->getMessage(),
                'sin_stock' => true,
                'codigo'    => 'stock_insuficiente',
            ], 409);
        } catch (\Illuminate\Database\QueryException $e) {
            if (in_array($e->getCode(), ['40P01', '55P03'], true) || str_contains($e->getMessage(), 'deadlock') || str_contains($e->getMessage(), 'lock timeout')) {
                return response()->json([
                    'message' => 'Alta contención en el inventario. Por favor reintente.',
                    'codigo'  => 'contencion',
                ], 409)->header('Retry-After', '1');
            }
            throw $e;
        }

        return response()->json([
            'message' => 'Pedido registrado. Queda pendiente de preparacion.',
            'pedido' => $pedido,
            'expira_en' => $pedido->expira_en?->toIso8601String(),
            'modalidad_entrega' => $modalidadEntrega ?? ($observacion && $observacion !== 'Recojo en botica' ? 'entrega' : 'recojo'),
            'comprobante' => [
                'id'            => $comprobante->id,
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

    /**
     * Cancela un pedido web abierto y restituye el stock reservado a sus lotes.
     *
     * Permite al cliente autenticado (o administrador) cancelar un pedido que no ha sido pagado ni completado.
     * Restituye inmediatamente las cantidades a los lotes de origen y anula el comprobante asociado si estaba pendiente.
     *
     * Cabecera `Idempotency-Key` (opcional, string de 8 a 120 caracteres alfanuméricos o `_ . : -`):
     * Evita ejecuciones duplicadas de cancelación.
     * - `400 Bad Request`: Clave de idempotencia con formato no permitido.
     * - `422 Unprocessable Content`: Conflicto de idempotencia (`codigo: "idempotencia_conflicto"`).
     * - `409 Conflict`: Operación en curso concurrente (`Retry-After: 1`).
     * - Reproducción: Respuesta almacenada con cabecera `Idempotency-Replayed: true`.
     *
     * Respuestas `409 Conflict` (PedidoNoCancelableException):
     * Devuelve `{ "message": string, "motivo": string }` cuando el pedido no admite cancelación según las reglas de negocio:
     * - `motivo: "pago_confirmado"`: El pedido ya registra un pago aprobado en la pasarela.
     * - `motivo: "ya_anulado"`: El pedido ya se encuentra cancelado o anulado.
     * - `motivo: "comprobante_aceptado"`: El comprobante electrónico ya fue informado y aceptado por SUNAT (exige nota de crédito).
     * - `motivo: "venta_mostrador"`: Es una venta de mostrador (POS), las cuales nacen completadas y no admiten este flujo.
     * - `motivo: "estado_invalido"`: El estado actual del pedido no pertenece a los estados abiertos cancelables.
     * - `motivo: "pago_invalido"`: El estado de pago en la pasarela no permite cancelar la operación.
     */
    public function cancelar(Request $request, string $id, PedidoService $pedidoService)
    {
        $pedido = Pedido::findOrFail($id);
        $solicitante = $request->user();

        if ((int) $pedido->usuario_id !== (int) $solicitante->id && $solicitante->rol !== 'administrador') {
            return response()->json([
                'message' => 'No estás autorizado para cancelar este pedido.',
            ], 403);
        }

        $motivo = $solicitante->rol === 'administrador' && (int) $pedido->usuario_id !== (int) $solicitante->id
            ? 'administrador'
            : 'cliente';

        try {
            $resultado = $pedidoService->cancelar($pedido, $motivo, (int) $solicitante->id);
        } catch (PedidoNoCancelableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'motivo'  => $e->getMotivo(),
            ], 409);
        }

        $pedidoAnulado = $resultado['pedido'];
        $comprobante = $resultado['comprobante'];

        return response()->json([
            'message' => 'Pedido cancelado correctamente.',
            'pedido'  => [
                'id'               => $pedidoAnulado->id,
                'estado'           => $pedidoAnulado->estado,
                'estado_pago'      => $pedidoAnulado->estado_pago,
                'anulado_en'       => $pedidoAnulado->anulado_en?->toIso8601String(),
                'motivo_anulacion' => $pedidoAnulado->motivo_anulacion,
            ],
            'comprobante' => $comprobante ? [
                'identificador' => $comprobante->serie . '-' . str_pad((string) $comprobante->numero, 8, '0', STR_PAD_LEFT),
                'estado_sunat'  => $comprobante->estado_sunat,
            ] : null,
            'stock_restituido' => $resultado['stock_restituido'],
        ], 200);
    }
}
