<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Pedido;
use App\Services\Pagos\EstadoPago;
use App\Services\Pagos\PagoService;
use App\Services\Pagos\PasarelaPago;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    public function __construct(
        private readonly PagoService $pagos,
        private readonly PasarelaPago $pasarela,
    ) {
    }

    /**
     * Prepara el cobro de un pedido y devuelve al navegador lo justo para
     * desplegar el formulario del proveedor: el token de formulario y la
     * llave publica. La llave privada nunca sale del backend.
     */
    public function iniciar(Request $request, string $pedidoId)
    {
        $pedido = Pedido::findOrFail($pedidoId);

        // Un usuario no puede iniciar el pago de un pedido ajeno.
        if ((int) $pedido->usuario_id !== (int) $request->user()->id && $request->user()->rol !== 'administrador') {
            return response()->json(['message' => 'El pedido no pertenece al usuario autenticado.'], 403);
        }

        $resultado = $this->pagos->iniciar($pedido, (string) $request->user()->email);

        return response()->json([
            'ok' => $resultado['ok'],
            'message' => $resultado['mensaje'],
            'pago' => $resultado['pago']->paraCliente(),
            'checkout' => [
                'form_token' => $resultado['form_token'],
                'public_key' => $resultado['public_key'],
                'modo' => $this->pasarela->modo(),
                // Ubicacion del cliente JavaScript y del tema. Son recursos
                // estaticos publicos, no credenciales: viajan al navegador
                // porque es el navegador quien debe cargarlos.
                'client_url' => rtrim((string) config('services.izipay.client_url'), '/'),
                'client_theme' => (string) config('services.izipay.client_theme'),
            ],
        ], $resultado['ok'] ? 200 : 409);
    }

    /**
     * Estado real del pago. Es lo que consulta la pantalla de procesamiento:
     * el frontend nunca decide por si mismo que una compra fue exitosa.
     */
    public function estado(Request $request, string $referencia)
    {
        $pago = Pago::with('pedido')->where('referencia_pedido', $referencia)->firstOrFail();

        if ((int) $pago->pedido->usuario_id !== (int) $request->user()->id && $request->user()->rol !== 'administrador') {
            return response()->json(['message' => 'El pago no pertenece al usuario autenticado.'], 403);
        }

        return response()->json([
            'pago' => $pago->paraCliente(),
            'pedido_id' => $pago->pedido_id,
            'es_final' => EstadoPago::esFinal($pago->estado),
        ]);
    }

    /**
     * Valida el retorno del formulario del proveedor al navegador.
     *
     * Se verifica con la clave HMAC-SHA-256 de la tienda, distinta de la que
     * firma la notificacion servidor a servidor. Una firma valida aqui permite
     * encaminar la interfaz de inmediato, pero NO marca el pedido como pagado:
     * eso solo lo hace la notificacion, que llega aunque el cliente cierre el
     * navegador.
     */
    public function validarRetorno(Request $request)
    {
        $respuesta = (string) $request->input('kr-answer', '');
        $firma = (string) $request->input('kr-hash', '');

        if ($respuesta === '') {
            return response()->json(['message' => 'Retorno sin contenido.'], 400);
        }

        $resultado = $this->pagos->validarRetornoDelNavegador(
            $respuesta,
            $firma,
            $request->input('kr-hash-algorithm'),
            $request->input('kr-hash-key'),
        );

        if (!$resultado['valida']) {
            return response()->json(['message' => 'La firma del retorno no es valida.'], 401);
        }

        $pago = Pago::with('pedido')->where('referencia_pedido', $resultado['referencia'])->first();

        if ($pago === null) {
            return response()->json(['message' => 'Referencia no reconocida.'], 404);
        }

        if ((int) $pago->pedido->usuario_id !== (int) $request->user()->id && $request->user()->rol !== 'administrador') {
            return response()->json(['message' => 'El pago no pertenece al usuario autenticado.'], 403);
        }

        return response()->json([
            'message' => 'Retorno verificado.',
            // Se devuelve el estado PERSISTIDO, no el que trae el retorno.
            'pago' => $pago->paraCliente(),
            'es_final' => EstadoPago::esFinal($pago->estado),
        ]);
    }

    /**
     * Notificacion servidor a servidor de la pasarela.
     *
     * Publica por necesidad -la invoca el proveedor, no un usuario- pero
     * protegida por tres capas: un segmento secreto en la ruta, la firma
     * HMAC-SHA-256 del mensaje y, en modo real, la reconsulta a la API.
     *
     * Devuelve 200 tambien ante un duplicado: el proveedor debe dejar de
     * reintentar un evento que ya fue atendido.
     */
    public function notificacion(Request $request, string $secreto)
    {
        // Primera capa: el segmento de la ruta debe coincidir con el secreto
        // configurado. Se compara en tiempo constante para no filtrar por
        // temporizacion cuantos caracteres iniciales acerto quien lo intente.
        $esperado = (string) config('services.izipay.webhook_path', '');

        if ($esperado === '' || !hash_equals($esperado, $secreto)) {
            return response()->json(['message' => 'Recurso no encontrado.'], 404);
        }

        $respuesta = (string) $request->input('kr-answer', '');
        $firma = (string) $request->input('kr-hash', '');

        if ($respuesta === '') {
            return response()->json(['message' => 'Notificacion sin contenido.'], 400);
        }

        $resultado = $this->pagos->procesarNotificacion(
            $respuesta,
            $firma,
            $request->input('kr-hash-algorithm'),
            $request->input('kr-hash-key'),
        );

        $codigo = match ($resultado['estado']) {
            'procesado', 'duplicado' => 200,
            'firma_invalida' => 401,
            'malformado' => 400,
            'sin_pago' => 404,
            default => 202,
        };

        return response()->json(['message' => $resultado['mensaje']], $codigo);
    }
}
