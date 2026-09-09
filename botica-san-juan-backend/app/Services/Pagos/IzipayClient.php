<?php

namespace App\Services\Pagos;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Agente de servicio hacia Izipay (plataforma Lyra / micuentaweb).
 *
 * Opera en dos modos, igual que SunatClient:
 *
 *   simulado : resuelve la operacion localmente y firma sus propias respuestas
 *              con las mismas claves configuradas, de modo que el flujo
 *              completo -incluida la verificacion de firma- sea ejercitable
 *              sin credenciales del proveedor.
 *   api      : invoca los endpoints reales de la API REST V4.
 *
 * La verificacion de firma sigue el mecanismo documentado por la plataforma:
 * HMAC-SHA-256 en hexadecimal sobre el contenido literal de 'kr-answer',
 * comparado contra 'kr-hash'. La plataforma usa dos claves distintas segun el
 * origen del mensaje y lo declara en 'kr-hash-key': 'password' para la
 * notificacion servidor a servidor y la clave HMAC-SHA-256 para el retorno
 * del navegador. Esa distincion se respeta y se verifica aqui.
 */
class IzipayClient implements PasarelaPago
{
    private const RUTA_CREAR_PAGO = '/api-payment/V4/Charge/CreatePayment';

    /**
     * Consulta de las transacciones asociadas a una referencia de pedido.
     * La plataforma recomienda no exceder una llamada por minuto y espera a
     * la respuesta antes de la siguiente. Devuelve como maximo 30 resultados.
     */
    private const RUTA_CONSULTAR_ORDEN = '/api-payment/V4/Order/Get';

    /** Unico algoritmo de firma que la plataforma declara. */
    private const ALGORITMO_FIRMA = 'sha256_hmac';

    public function modo(): string
    {
        return (string) config('services.izipay.mode', 'simulado');
    }

    public function llavePublica(): ?string
    {
        $llave = (string) config('services.izipay.public_key', '');

        if ($llave !== '') {
            return $llave;
        }

        return $this->modo() === 'simulado' ? 'llave-publica-simulada' : null;
    }

    public function crearOperacion(string $referencia, float $monto, string $moneda, string $correoCliente): array
    {
        if ($this->modo() !== 'api') {
            return [
                'ok' => true,
                'form_token' => 'simulado:' . base64_encode($referencia . '|' . $monto),
                'public_key' => $this->llavePublica(),
                'referencia' => $referencia,
                'mensaje' => 'Operacion creada en modo simulado.',
            ];
        }

        $usuario = (string) config('services.izipay.username', '');
        $clave = (string) config('services.izipay.password', '');

        if ($usuario === '' || $clave === '') {
            return $this->fallo($referencia, 'Integracion Izipay no configurada (IZIPAY_USERNAME o IZIPAY_PASSWORD faltante).');
        }

        $cuerpoPeticion = [
            // La plataforma expresa el importe como entero en la unidad
            // minima de la moneda. El sol peruano tiene dos decimales, de
            // modo que el factor es cien: 149.00 PEN se envia como 14900.
            'amount' => $this->aUnidadMinima($monto, $moneda),
            'currency' => $moneda,
            'orderId' => $referencia,
            'formAction' => 'PAYMENT',
            'customer' => ['email' => $correoCliente],
        ];

        // La URL de notificacion lleva el secreto en la ruta, de modo que se
        // fija por operacion en lugar de depender de la configuracion del
        // panel del comercio. El campo admite 255 caracteres.
        $urlNotificacion = (string) config('services.izipay.ipn_url', '');

        if ($urlNotificacion !== '' && strlen($urlNotificacion) <= 255) {
            $cuerpoPeticion['ipnTargetUrl'] = $urlNotificacion;
        }

        try {
            $respuesta = Http::timeout(max(5, (int) config('services.izipay.timeout', 20)))
                ->withBasicAuth($usuario, $clave)
                ->acceptJson()
                ->post($this->url(self::RUTA_CREAR_PAGO), $cuerpoPeticion);
        } catch (\Throwable $e) {
            return $this->fallo($referencia, 'No se pudo contactar a la pasarela.');
        }

        if (!$respuesta->ok()) {
            return $this->fallo($referencia, 'La pasarela respondio HTTP ' . $respuesta->status() . '.');
        }

        $cuerpo = $respuesta->json();

        return [
            'ok' => ($cuerpo['status'] ?? '') === 'SUCCESS',
            'form_token' => $cuerpo['answer']['formToken'] ?? null,
            'public_key' => $this->llavePublica(),
            'referencia' => $referencia,
            'mensaje' => $cuerpo['answer']['errorMessage'] ?? null,
        ];
    }

    /**
     * HMAC-SHA-256 sobre la respuesta cruda, comparado en tiempo constante.
     *
     * hash_equals evita que la comparacion filtre informacion por el tiempo
     * que tarda en fallar.
     */
    public function verificarFirma(string $respuesta, string $firma, string $contexto): bool
    {
        $clave = $this->claveDeFirma($contexto);

        if ($clave === '' || $firma === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $respuesta, $clave), $firma);
    }

    /**
     * La plataforma declara el algoritmo en 'kr-hash-algorithm' y la clave
     * empleada en 'kr-hash-key'. Rechazar un valor inesperado impide que una
     * notificacion firmada con la clave del navegador -que viaja al cliente y
     * por tanto es conocida- se acepte en el canal servidor a servidor.
     */
    public function algoritmoAceptado(?string $algoritmo): bool
    {
        return strtolower(trim((string) $algoritmo)) === self::ALGORITMO_FIRMA;
    }

    public function claveDeclaradaCoincide(?string $claveDeclarada, string $contexto): bool
    {
        $declarada = strtolower(trim((string) $claveDeclarada));

        // Cuando la plataforma no declara el campo no se bloquea el mensaje:
        // la firma sigue siendo la prueba. Si lo declara, debe coincidir.
        if ($declarada === '') {
            return true;
        }

        return $contexto === 'ipn'
            ? $declarada === 'password'
            : $declarada !== 'password';
    }

    /**
     * Firma un contenido con la misma clave que despues lo verificara.
     * Solo se usa para construir notificaciones en modo simulado y en pruebas.
     */
    public function firmar(string $respuesta, string $contexto): string
    {
        return hash_hmac('sha256', $respuesta, $this->claveDeFirma($contexto));
    }

    /**
     * Reconsulta el desenlace real contra la API.
     *
     * Order/Get devuelve las transacciones asociadas a una referencia de
     * pedido. Se toma la ultima transaccion de debito y de ella el estado
     * simplificado; si no lo trae, el detallado. Cualquier ausencia deja el
     * resultado como no concluyente, y el servicio nunca marca un pedido como
     * pagado sin una confirmacion afirmativa.
     */
    public function consultarOperacion(string $referencia): array
    {
        if ($this->modo() !== 'api') {
            // En modo simulado el desenlace lo determina la notificacion que
            // se inyecta en la prueba, no una llamada de red.
            return $this->consultaVacia('Consulta omitida en modo simulado.', true);
        }

        $usuario = (string) config('services.izipay.username', '');
        $clave = (string) config('services.izipay.password', '');

        if ($usuario === '' || $clave === '') {
            return $this->consultaVacia('Integracion Izipay no configurada.');
        }

        try {
            $respuesta = Http::timeout(max(5, (int) config('services.izipay.timeout', 20)))
                ->withBasicAuth($usuario, $clave)
                ->acceptJson()
                ->post($this->url(self::RUTA_CONSULTAR_ORDEN), [
                    'orderId' => $referencia,
                    'operationType' => 'DEBIT',
                ]);
        } catch (\Throwable $e) {
            Log::warning('izipay.consulta.sin_conexion', ['referencia' => $referencia]);

            return $this->consultaVacia('No se pudo contactar a la pasarela.');
        }

        if (!$respuesta->ok()) {
            return $this->consultaVacia('La pasarela respondio HTTP ' . $respuesta->status() . '.');
        }

        $cuerpo = $respuesta->json();

        if (($cuerpo['status'] ?? '') !== 'SUCCESS') {
            return $this->consultaVacia($cuerpo['answer']['errorMessage'] ?? 'La consulta no fue exitosa.');
        }

        $transaccion = $this->ultimaTransaccion($cuerpo['answer'] ?? []);

        if ($transaccion === null) {
            return $this->consultaVacia('La orden no tiene transacciones asociadas.');
        }

        $estado = $transaccion['status'] ?? null;
        $tarjeta = $transaccion['transactionDetails']['cardDetails'] ?? [];

        return [
            'ok' => $estado !== null || isset($transaccion['detailedStatus']),
            'estado_proveedor' => $estado,
            'estado_detallado' => $transaccion['detailedStatus'] ?? null,
            'pago_id' => $transaccion['uuid'] ?? null,
            'metodo' => $transaccion['paymentMethodType'] ?? null,
            'marca' => $tarjeta['effectiveBrand'] ?? null,
            'ultimos4' => $this->ultimosCuatro($tarjeta['pan'] ?? null),
            'monto' => isset($transaccion['amount'])
                ? $this->desdeUnidadMinima((int) $transaccion['amount'], (string) ($transaccion['currency'] ?? 'PEN'))
                : null,
            'mensaje' => null,
        ];
    }

    /**
     * Importe entero en la unidad minima de la moneda.
     *
     * Casi todas las monedas que la plataforma admite tienen dos decimales,
     * entre ellas el sol peruano. Las excepciones se declaran de forma
     * explicita para que un cambio de moneda no introduzca un error de factor.
     */
    public function aUnidadMinima(float $monto, string $moneda): int
    {
        return (int) round($monto * (10 ** $this->decimalesDe($moneda)));
    }

    public function desdeUnidadMinima(int $importe, string $moneda): float
    {
        return $importe / (10 ** $this->decimalesDe($moneda));
    }

    private function decimalesDe(string $moneda): int
    {
        $sinDecimales = ['JPY', 'KRW', 'KHR', 'XOF', 'XPF'];
        $tresDecimales = ['KWD', 'TND'];
        $unDecimal = ['CNY'];

        $codigo = strtoupper(trim($moneda));

        if (in_array($codigo, $sinDecimales, true)) {
            return 0;
        }

        if (in_array($codigo, $unDecimal, true)) {
            return 1;
        }

        if (in_array($codigo, $tresDecimales, true)) {
            return 3;
        }

        return 2;
    }

    /** @param array<string, mixed> $answer */
    private function ultimaTransaccion(array $answer): ?array
    {
        $transacciones = $answer['transactions'] ?? (array_is_list($answer) ? $answer : []);

        if (!is_array($transacciones) || $transacciones === []) {
            return null;
        }

        $debitos = array_values(array_filter(
            $transacciones,
            fn ($t) => is_array($t) && ($t['operationType'] ?? 'DEBIT') === 'DEBIT'
        ));

        $candidatas = $debitos !== [] ? $debitos : array_values(array_filter($transacciones, 'is_array'));

        if ($candidatas === []) {
            return null;
        }

        // Una orden con reintentos acumula transacciones. Si alguna llego a
        // PAID, ese es el desenlace de la orden; si no, vale la ultima.
        foreach ($candidatas as $t) {
            if (($t['status'] ?? null) === 'PAID') {
                return $t;
            }
        }

        return end($candidatas) ?: null;
    }

    private function ultimosCuatro(?string $pan): ?string
    {
        if ($pan === null) {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $pan) ?? '';

        return strlen($digitos) >= 4 ? substr($digitos, -4) : null;
    }

    private function url(string $ruta): string
    {
        return rtrim((string) config('services.izipay.base_url'), '/') . $ruta;
    }

    private function claveDeFirma(string $contexto): string
    {
        // El navegador devuelve el hash firmado con la clave HMAC-SHA-256;
        // la notificacion servidor a servidor, con la contrasena de la tienda.
        $clave = $contexto === 'ipn'
            ? (string) config('services.izipay.password', '')
            : (string) config('services.izipay.sha256_key', '');

        if ($clave !== '') {
            return $clave;
        }

        // En modo simulado se usa una clave derivada de APP_KEY para que la
        // verificacion siga siendo real aunque no haya credenciales.
        return $this->modo() === 'simulado'
            ? hash('sha256', 'izipay-simulado|' . $contexto . '|' . config('app.key'))
            : '';
    }

    /** @return array<string, mixed> */
    private function consultaVacia(string $mensaje, bool $ok = false): array
    {
        return [
            'ok' => $ok,
            'estado_proveedor' => null,
            'estado_detallado' => null,
            'pago_id' => null,
            'metodo' => null,
            'marca' => null,
            'ultimos4' => null,
            'monto' => null,
            'mensaje' => $mensaje,
        ];
    }

    /** @return array{ok:bool, form_token:null, public_key:?string, referencia:string, mensaje:string} */
    private function fallo(string $referencia, string $mensaje): array
    {
        return [
            'ok' => false,
            'form_token' => null,
            'public_key' => $this->llavePublica(),
            'referencia' => $referencia,
            'mensaje' => $mensaje,
        ];
    }
}
