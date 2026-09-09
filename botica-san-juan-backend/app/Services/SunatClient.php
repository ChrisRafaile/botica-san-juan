<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SunatClient
{
    public function send(array $documentPayload): array
    {
        $mode = (string) config('services.sunat.mode', 'simulado');

        if ($mode !== 'api') {
            return $this->simulate($documentPayload);
        }

        $baseUrl = rtrim((string) config('services.sunat.base_url', ''), '/');
        $token = (string) config('services.sunat.token', '');
        $timeout = (int) config('services.sunat.timeout', 20);

        if ($baseUrl === '' || $token === '') {
            return [
                'ok' => false,
                'status' => 'rechazada',
                'code' => 'CFG01',
                'message' => 'Integracion SUNAT no configurada (SUNAT_BASE_URL o SUNAT_TOKEN faltante).',
                'ticket' => null,
                'response' => null,
            ];
        }

        $response = Http::timeout(max(5, $timeout))
            ->acceptJson()
            ->withToken($token)
            ->post($baseUrl . '/comprobantes', $documentPayload);

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
