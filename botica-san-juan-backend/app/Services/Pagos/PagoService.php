<?php

namespace App\Services\Pagos;

use App\Models\Pago;
use App\Models\PagoEvento;
use App\Models\Pedido;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orquesta el cobro de un pedido contra la pasarela.
 *
 * Tres invariantes que este servicio sostiene:
 *
 * 1. El importe se toma del pedido, que a su vez lo calculo el servidor a
 *    partir del catalogo. Nada de lo que envie el navegador influye en cuanto
 *    se cobra.
 * 2. Un pedido solo pasa a pagado con una notificacion cuya firma es valida y
 *    -en modo real- confirmada ademas por una reconsulta a la API. El cuerpo
 *    de la notificacion nunca decide por si solo.
 * 3. La misma notificacion aplicada dos veces produce el mismo resultado que
 *    aplicada una vez. La garantia es de la base de datos, no de una
 *    comprobacion previa.
 */
class PagoService
{
    public function __construct(private readonly PasarelaPago $pasarela)
    {
    }

    /**
     * Crea -o recupera- el pago de un pedido y prepara la operacion.
     *
     * Si el pedido ya tiene un pago vivo no se crea otro: eso es lo que impide
     * que un doble clic, una recarga o una segunda pestana generen dos cobros.
     *
     * @return array{pago: Pago, form_token: ?string, public_key: ?string, ok: bool, mensaje: ?string}
     */
    public function iniciar(Pedido $pedido, string $correoCliente): array
    {
        $pago = DB::transaction(function () use ($pedido) {
            $existente = Pago::where('pedido_id', $pedido->id)
                ->lockForUpdate()
                ->first();

            if ($existente !== null) {
                return $existente;
            }

            return Pago::create([
                'pedido_id' => $pedido->id,
                'proveedor' => 'izipay',
                'referencia_pedido' => 'BSJ-' . str_pad((string) $pedido->id, 8, '0', STR_PAD_LEFT) . '-' . Str::lower(Str::random(6)),
                'estado' => EstadoPago::PENDIENTE,
                'monto' => (float) $pedido->total,
                'moneda' => $pedido->moneda ?: (string) config('services.izipay.currency', 'PEN'),
            ]);
        });

        if (EstadoPago::esFinal($pago->estado)) {
            return [
                'pago' => $pago,
                'form_token' => null,
                'public_key' => null,
                'ok' => false,
                'mensaje' => 'El pago de este pedido ya esta resuelto.',
            ];
        }

        $operacion = $this->pasarela->crearOperacion(
            $pago->referencia_pedido,
            (float) $pago->monto,
            $pago->moneda,
            $correoCliente
        );

        if ($operacion['ok']) {
            $pago->estado = EstadoPago::PROCESANDO;
            $pago->save();

            $pedido->estado_pago = EstadoPago::PROCESANDO;
            $pedido->save();
        }

        return [
            'pago' => $pago->fresh(),
            'form_token' => $operacion['form_token'],
            'public_key' => $operacion['public_key'],
            'ok' => (bool) $operacion['ok'],
            'mensaje' => $operacion['mensaje'],
        ];
    }

    /**
     * Procesa una notificacion servidor a servidor.
     *
     * @param  string  $respuestaCruda  contenido de 'kr-answer' tal como llego
     * @param  string  $firma           valor de 'kr-hash'
     * @return array{estado:string, mensaje:string}  estado HTTP semantico para el controlador
     */
    public function procesarNotificacion(
        string $respuestaCruda,
        string $firma,
        ?string $algoritmo = null,
        ?string $claveDeclarada = null,
    ): array {
        // 0. El proveedor declara con que algoritmo y con cual de sus dos
        //    claves firmo. Un mensaje que declare algo distinto de lo previsto
        //    para este canal se rechaza antes de calcular nada.
        if ($algoritmo !== null && !$this->pasarela->algoritmoAceptado($algoritmo)) {
            Log::warning('pagos.ipn.algoritmo_no_soportado', ['algoritmo' => $algoritmo]);

            return ['estado' => 'firma_invalida', 'mensaje' => 'Algoritmo de firma no soportado.'];
        }

        if (!$this->pasarela->claveDeclaradaCoincide($claveDeclarada, 'ipn')) {
            Log::warning('pagos.ipn.clave_declarada_incorrecta');

            return ['estado' => 'firma_invalida', 'mensaje' => 'La clave declarada no corresponde a este canal.'];
        }

        // 1. Autenticidad. Sin firma valida el mensaje no se registra siquiera.
        if (!$this->pasarela->verificarFirma($respuestaCruda, $firma, 'ipn')) {
            Log::warning('pagos.ipn.firma_invalida');

            return ['estado' => 'firma_invalida', 'mensaje' => 'Firma no valida.'];
        }

        $datos = json_decode($respuestaCruda, true);

        if (!is_array($datos)) {
            return ['estado' => 'malformado', 'mensaje' => 'Cuerpo no interpretable.'];
        }

        $referencia = (string) ($datos['orderDetails']['orderId'] ?? $datos['orderId'] ?? '');
        $estadoProveedor = (string) ($datos['orderStatus'] ?? '');
        $eventoId = $this->idDeEvento($datos, $respuestaCruda);

        // 2. Idempotencia estructural: el UNIQUE decide, no una comprobacion.
        try {
            $evento = PagoEvento::create([
                'evento_id' => $eventoId,
                'tipo' => (string) ($datos['eventType'] ?? 'orderStatus'),
                'estado_reportado' => $estadoProveedor,
                'payload' => $this->sanear($datos),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            Log::info('pagos.ipn.duplicado', ['evento_id' => $eventoId]);

            return ['estado' => 'duplicado', 'mensaje' => 'Evento ya procesado.'];
        }

        $pago = Pago::where('referencia_pedido', $referencia)->first();

        if ($pago === null) {
            Log::warning('pagos.ipn.referencia_desconocida', ['evento_id' => $eventoId]);

            return ['estado' => 'sin_pago', 'mensaje' => 'Referencia no reconocida.'];
        }

        $evento->pago_id = $pago->id;
        $evento->save();

        // 3. En modo real la API es la fuente de verdad. Si la reconsulta no
        //    confirma, el pedido no pasa a pagado por mucho que el cuerpo lo diga.
        $estadoInterno = EstadoPago::desdeProveedor($estadoProveedor);

        if ($this->pasarela->modo() === 'api') {
            $confirmacion = $this->pasarela->consultarOperacion($referencia);

            if (!$confirmacion['ok']) {
                Log::warning('pagos.ipn.sin_confirmacion_api', ['evento_id' => $eventoId]);

                return ['estado' => 'sin_confirmar', 'mensaje' => 'No se pudo confirmar contra la pasarela.'];
            }

            $estadoInterno = EstadoPago::desdeProveedor($confirmacion['estado_proveedor'] ?? $estadoProveedor);
        }

        $this->aplicar($pago, $estadoInterno, $datos);

        $evento->procesado_en = now();
        $evento->save();

        Log::info('pagos.ipn.aplicado', [
            'evento_id' => $eventoId,
            'referencia' => $referencia,
            'estado' => $pago->fresh()->estado,
        ]);

        return ['estado' => 'procesado', 'mensaje' => 'Notificacion aplicada.'];
    }

    /**
     * Valida la respuesta que el formulario del proveedor devuelve al navegador.
     *
     * La integracion oficial define DOS puntos de verificacion distintos, con
     * claves distintas: esta respuesta se firma con la clave HMAC-SHA-256 de
     * la tienda, y la notificacion servidor a servidor con la contrasena.
     *
     * Punto importante de diseno: este metodo NO cambia el estado del pago.
     * Una firma valida aqui solo prueba que el mensaje viene del proveedor y
     * no fue alterado; no prueba que el dinero llego. El unico canal que mueve
     * el estado es la notificacion servidor a servidor, que el proveedor emite
     * aunque el cliente cierre el navegador.
     *
     * Sirve para dos cosas: encaminar la interfaz sin esperar al sondeo, y
     * detectar manipulacion del retorno.
     *
     * @return array{valida:bool, referencia:?string, estado_reportado:?string}
     */
    public function validarRetornoDelNavegador(
        string $respuestaCruda,
        string $firma,
        ?string $algoritmo = null,
        ?string $claveDeclarada = null,
    ): array {
        if ($algoritmo !== null && !$this->pasarela->algoritmoAceptado($algoritmo)) {
            Log::warning('pagos.retorno.algoritmo_no_soportado', ['algoritmo' => $algoritmo]);

            return ['valida' => false, 'referencia' => null, 'estado_reportado' => null];
        }

        if (!$this->pasarela->claveDeclaradaCoincide($claveDeclarada, 'navegador')) {
            Log::warning('pagos.retorno.clave_declarada_incorrecta');

            return ['valida' => false, 'referencia' => null, 'estado_reportado' => null];
        }

        if (!$this->pasarela->verificarFirma($respuestaCruda, $firma, 'navegador')) {
            Log::warning('pagos.retorno.firma_invalida');

            return ['valida' => false, 'referencia' => null, 'estado_reportado' => null];
        }

        $datos = json_decode($respuestaCruda, true);

        if (!is_array($datos)) {
            return ['valida' => false, 'referencia' => null, 'estado_reportado' => null];
        }

        return [
            'valida' => true,
            'referencia' => (string) ($datos['orderDetails']['orderId'] ?? $datos['orderId'] ?? '') ?: null,
            'estado_reportado' => (string) ($datos['orderStatus'] ?? '') ?: null,
        ];
    }

    /** Aplica la transicion de estado sobre el pago y su pedido, de forma atomica. */
    private function aplicar(Pago $pago, string $estadoNuevo, array $datos): void
    {
        DB::transaction(function () use ($pago, $estadoNuevo, $datos) {
            $pago->refresh();

            if (!EstadoPago::permiteTransicion($pago->estado, $estadoNuevo)) {
                return;
            }

            $pago->estado = $estadoNuevo;
            $pago->proveedor_pago_id = $datos['transactions'][0]['uuid'] ?? $pago->proveedor_pago_id;
            $pago->metodo_pago = $this->metodo($datos);
            $pago->marca_tarjeta = $datos['transactions'][0]['transactionDetails']['cardDetails']['effectiveBrand'] ?? null;
            // El proveedor entrega el PAN ya enmascarado; se conservan solo los
            // cuatro ultimos digitos, que es lo unico que la columna admite y
            // lo unico que el cliente necesita ver.
            $panEnmascarado = $datos['transactions'][0]['transactionDetails']['cardDetails']['pan'] ?? null;
            if ($panEnmascarado === null) {
                $pago->ultimos4 = null;
            } else {
                $soloDigitos = preg_replace('/\D/', '', (string) $panEnmascarado);
                $pago->ultimos4 = $soloDigitos === '' ? null : substr($soloDigitos, -4);
            }

            if ($estadoNuevo === EstadoPago::PAGADO) {
                $pago->pagado_en = now();
                $pago->codigo_error = null;
                $pago->mensaje_error = null;
            }

            if ($estadoNuevo === EstadoPago::FALLIDO) {
                $pago->codigo_error = (string) ($datos['transactions'][0]['errorCode'] ?? 'RECHAZADO');
                $pago->mensaje_error = 'El pago no pudo completarse.';
            }

            $pago->save();

            $pedido = $pago->pedido;
            if ($pedido !== null) {
                $pedido->estado_pago = $estadoNuevo;
                $pedido->save();
            }
        });
    }

    private function metodo(array $datos): ?string
    {
        $bruto = $datos['transactions'][0]['paymentMethodType']
            ?? $datos['transactions'][0]['paymentMethodToken']
            ?? null;

        return $bruto === null ? null : Str::lower(substr((string) $bruto, 0, 30));
    }

    /**
     * Clave de deduplicacion.
     *
     * Se prefiere el identificador de transaccion del proveedor. Si no viene,
     * se usa un resumen del propio mensaje: dos entregas identicas producen la
     * misma clave, que es exactamente lo que se necesita.
     */
    private function idDeEvento(array $datos, string $crudo): string
    {
        $uuid = $datos['transactions'][0]['uuid'] ?? null;
        $estado = (string) ($datos['orderStatus'] ?? '');

        if (is_string($uuid) && $uuid !== '') {
            return substr($uuid . ':' . $estado, 0, 160);
        }

        return 'sha256:' . hash('sha256', $crudo);
    }

    /**
     * Nunca se persiste ni se registra dato sensible de tarjeta.
     * Del PAN solo sobreviven los ultimos cuatro digitos que el proveedor ya
     * entrega enmascarados.
     */
    private function sanear(array $datos): array
    {
        $prohibidas = ['pan', 'cvv', 'cvc', 'securityCode', 'expiryMonth', 'expiryYear', 'number', 'cardNumber'];

        $limpiar = function (array $nodo) use (&$limpiar, $prohibidas): array {
            $salida = [];
            foreach ($nodo as $clave => $valor) {
                if (in_array((string) $clave, $prohibidas, true)) {
                    continue;
                }
                $salida[$clave] = is_array($valor) ? $limpiar($valor) : $valor;
            }
            return $salida;
        };

        return $limpiar($datos);
    }
}
