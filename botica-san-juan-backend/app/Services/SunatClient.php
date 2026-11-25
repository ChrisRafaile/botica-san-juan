<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Envío de comprobantes al microservicio REST (OSE / Greenter).
 *
 *   simulado    resuelve localmente, sin red.
 *   beta        microservicio apuntando al entorno de pruebas.
 *   produccion  microservicio con efecto tributario; exige APP_ENV=production.
 *   api         modo histórico: mismo contrato REST, sin validar el entorno.
 *
 * SUNAT_BASE_URL es SIEMPRE la del microservicio. Los servicios de SUNAT hablan
 * SOAP y exigen firma; apuntar aquí a sunat.gob.pe mandaría JSON y un token
 * propio a un tercero que no los entiende, así que se rechaza.
 */
class SunatClient
{
    public const MODOS = ['simulado', 'beta', 'produccion', 'api'];

    public function send(array $documentPayload): array
    {
        $mode = strtolower(trim((string) config('services.sunat.mode', 'simulado')));

        /* Antes, un modo mal escrito caía al simulador y daba por aceptado un
           comprobante que nunca salió. */
        if (! in_array($mode, self::MODOS, true)) {
            return $this->errorDeConfiguracion('CFG02', 'SUNAT_MODE no reconocido. Valores validos: '.implode(', ', self::MODOS).'.');
        }

        if ($mode === 'simulado') {
            return $this->simulate($documentPayload);
        }

        $baseUrl = rtrim((string) config('services.sunat.base_url', ''), '/');
        $token = (string) config('services.sunat.token', '');
        $timeout = (int) config('services.sunat.timeout', 20);

        if ($baseUrl === '' || $token === '') {
            return $this->errorDeConfiguracion('CFG01', 'Integracion SUNAT no configurada (SUNAT_BASE_URL o SUNAT_TOKEN faltante).');
        }

        $peticion = Http::timeout(max(5, $timeout))->acceptJson()->withToken($token);

        if ($mode !== 'api') {
            $error = $this->validarEntorno($mode, $baseUrl);

            if ($error !== null) {
                return $error;
            }

            /* El estado forzado es una herramienta del simulador. */
            unset($documentPayload['__force_status']);
            $peticion = $peticion->withHeaders(['X-Sunat-Entorno' => $mode]);
        }

        $response = $peticion->post($baseUrl . '/comprobantes', $documentPayload);

        if (!$response->ok()) {
            return [
                'ok' => false,
                'status' => 'rechazada',
                'code' => 'HTTP' . $response->status(),
                'message' => 'SUNAT endpoint respondio error.',
                'ticket' => null,
                'response' => $response->json(),
            ];
        }

        $body = $response->json();
        return [
            'ok' => (bool) ($body['ok'] ?? false),
            'status' => (string) ($body['status'] ?? 'pendiente'),
            'code' => (string) ($body['code'] ?? ''),
            'message' => (string) ($body['message'] ?? ''),
            'ticket' => $body['ticket'] ?? null,
            'response' => $body,
        ];
    }

    private function validarEntorno(string $mode, string $baseUrl): ?array
    {
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $esquema = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        $esLocal = in_array($host, ['localhost', '127.0.0.1'], true);

        if ($host === 'sunat.gob.pe' || str_ends_with($host, '.sunat.gob.pe')) {
            return $this->errorDeConfiguracion('CFG03', 'SUNAT_BASE_URL debe apuntar al microservicio OSE, no a un servicio web de SUNAT.');
        }

        if ($host === '' || ($esquema !== 'https' && ! ($mode === 'beta' && $esLocal && $esquema === 'http'))) {
            return $this->errorDeConfiguracion('CFG04', 'SUNAT_BASE_URL debe ser una URL HTTPS valida (HTTP solo en beta contra localhost).');
        }

        if ($mode === 'produccion' && ! app()->isProduction()) {
            return $this->errorDeConfiguracion('CFG05', 'SUNAT_MODE=produccion solo se admite con APP_ENV=production.');
        }

        return null;
    }

    private function errorDeConfiguracion(string $codigo, string $mensaje): array
    {
        return [
            'ok' => false,
            'status' => 'rechazada',
            'code' => $codigo,
            'message' => $mensaje,
            'ticket' => null,
            'response' => null,
        ];
    }

    private function simulate(array $documentPayload): array
    {
        $forcedStatus = strtolower((string) ($documentPayload['__force_status'] ?? ''));
        if (in_array($forcedStatus, ['aceptada', 'rechazada'], true)) {
            $accepted = $forcedStatus === 'aceptada';
        } else {
            $accepted = random_int(1, 100) <= 88;
        }

        $series = (string) ($documentPayload['serie'] ?? 'B001');
        $number = (string) ($documentPayload['numero'] ?? '0');

        return [
            'ok' => $accepted,
            'status' => $accepted ? 'aceptada' : 'rechazada',
            'code' => $accepted ? '0' : 'SUN001',
            'message' => $accepted
                ? 'Aceptado por SUNAT (modo simulado).'
                : 'Rechazado por SUNAT (modo simulado).',
            'ticket' => sprintf('TCK-%s-%s-%s', $series, $number, now()->format('YmdHis')),
            'response' => [
                'mock' => true,
                'forced' => in_array($forcedStatus, ['aceptada', 'rechazada'], true),
                'at' => now()->toDateTimeString(),
            ],
        ];
    }
}
