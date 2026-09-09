<?php

namespace App\Jobs;

use App\Models\ComprobanteElectronico;
use App\Services\SunatClient;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Envio asincrono del comprobante electronico al operador de servicios
 * electronicos.
 *
 * El envio sincrono ata la respuesta al usuario a la latencia de un proveedor
 * externo sobre el que la botica no tiene control: si el OSE tarda treinta
 * segundos, el cajero queda bloqueado y la peticion puede agotar su tiempo de
 * espera con la venta ya registrada. Encolar el envio desacopla ambos ritmos.
 *
 * ShouldBeUnique impide que dos ejecuciones simultaneas del mismo comprobante
 * lleguen al proveedor: la clave de unicidad es el identificador del documento.
 */
class EnviarComprobanteASunat implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /** Reintentos con espera creciente ante indisponibilidad del proveedor. */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    /** Ventana durante la cual no se admite otra instancia del mismo documento. */
    public int $uniqueFor = 900;

    public function __construct(
        public readonly string $comprobanteId,
        public readonly ?string $forzarEstado = null,
    ) {
    }

    public function uniqueId(): string
    {
        return 'comprobante:' . $this->comprobanteId;
    }

    public function handle(SunatClient $sunatClient): void
    {
        $doc = ComprobanteElectronico::find($this->comprobanteId);

        if ($doc === null) {
            Log::warning('sunat.envio.comprobante_inexistente', ['id' => $this->comprobanteId]);
            return;
        }

        // Idempotencia: un comprobante ya aceptado no se reenvia aunque el
        // trabajo se ejecute de nuevo por un reintento de la cola.
        if ($doc->estado_sunat === 'aceptada') {
            Log::info('sunat.envio.omitido_ya_aceptado', ['id' => $doc->id]);
            return;
        }

        $payload = [
            'tipo_comprobante' => $doc->tipo_comprobante,
            'serie' => $doc->serie,
            'numero' => $doc->numero,
            'cliente_nombre' => $doc->cliente_nombre,
            'cliente_documento' => $doc->cliente_documento,
            'total' => (float) $doc->total,
            'fecha_emision' => optional($doc->fecha_emision)->toDateTimeString(),
        ];

        if (in_array($this->forzarEstado, ['aceptada', 'rechazada'], true)) {
            $payload['__force_status'] = $this->forzarEstado;
        }

        $resultado = $sunatClient->send($payload);
        $aceptado = (bool) ($resultado['ok'] ?? false);

        $doc->estado_sunat = $aceptado ? 'aceptada' : 'rechazada';
        $doc->codigo_respuesta_sunat = (string) ($resultado['code'] ?? ($aceptado ? '0' : '1032'));
        $doc->sunat_ticket = $resultado['ticket'] ?? null;
        $doc->sunat_payload = $payload;
        $doc->sunat_response = $resultado['response'] ?? null;
        $doc->mensaje_sunat = (string) ($resultado['message'] ?? '');
        $doc->fecha_envio_sunat = Carbon::now();
        $doc->hash_documento = hash('sha256', $doc->serie . '-' . $doc->numero . '-' . $doc->total . '-' . now()->timestamp);
        $doc->save();

        // La bitacora nunca registra la carga util completa: solo el resultado.
        Log::info('sunat.envio.resuelto', [
            'comprobante_id' => $doc->id,
            'estado' => $doc->estado_sunat,
            'codigo' => $doc->codigo_respuesta_sunat,
            'ticket' => $doc->sunat_ticket,
        ]);
    }
}
