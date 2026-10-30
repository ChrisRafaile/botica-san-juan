<?php

namespace App\Services\Sunat;

use App\Models\ComprobanteElectronico;
use App\Models\EventoOperacion;
use App\Services\NombreArchivoSunat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Envío de un comprobante a la SUNAT, de principio a fin.
 * ---------------------------------------------------------------------------
 * QUE RESUELVE
 *
 * Encadena los pasos que hasta ahora no existían: empaquetar, enviar, recibir
 * la constancia, guardarla con su nombre normalizado y dejar el comprobante
 * actualizado con lo que dijo.
 *
 * DOS MODOS, UN SOLO CAMINO
 *
 * En modo simulado el envío no sale del proceso, pero **recorre exactamente
 * las mismas capas**: se comprime de verdad, se codifica de verdad, se arma
 * el sobre de verdad y la respuesta es un CDR con su estructura formal, que
 * se guarda como archivo igual que el real.
 *
 * Esto no es un capricho de purista. Una simulación que devuelve un JSON
 * inventado deja sin probar justo lo que falla el primer día: el nombre del
 * archivo dentro del ZIP, el Base64, la extracción del `applicationResponse`.
 * Al simular con la envoltura completa, pasar a producción cambia una sola
 * cosa —a dónde se manda— en lugar de estrenar media tubería.
 *
 * LO QUE SIGUE SIN ESTAR
 *
 * La firma digital. Exige un certificado tributario del negocio, que es un
 * trámite y no código. Mientras no exista, el modo real puede probarse
 * contra el entorno beta, donde el empaquetado se valida aunque la firma no
 * sea definitiva. Ver docs/FACTURACION-SUNAT.md.
 */
class FacturadorSunat
{
    public function __construct(
        private readonly NombreArchivoSunat $nombres,
        private readonly EmpaquetadoSunat $empaquetado,
        private readonly ConstanciaRecepcion $constancias,
        private readonly SimuladorSunat $simulador,
    ) {
    }

    /**
     * Envía el comprobante y deja guardada su constancia.
     *
     * @return array{estado:string, codigo:string, descripcion:string, cdr:string|null}
     */
    public function enviar(ComprobanteElectronico $comprobante, string $xmlFirmado): array
    {
        $nombreBase = $this->nombres->base($comprobante);
        $zip = $this->empaquetado->comprimir($xmlFirmado, $nombreBase);

        /* El ZIP enviado también se guarda. Sin él, ante una discrepancia
           solo quedaría la palabra del sistema contra la del servicio. */
        Storage::disk('local')->put("facturacion/enviados/{$nombreBase}.zip", $zip);

        try {
            $respuesta = $this->despachar($comprobante, $nombreBase, $zip);
            $zipCdr = $this->empaquetado->cdrDesdeRespuesta($respuesta);
            $lectura = $this->constancias->guardar($comprobante, $zipCdr);
        } catch (Throwable $e) {
            /* Un fallo aquí NO es un rechazo: es que no sabemos qué pasó. La
               diferencia importa, porque un rechazo se corrige y un fallo de
               transporte se reintenta. Confundirlos lleva a reenviar lo que
               ya está aceptado o a dar por perdido lo que solo tardó. */
            $comprobante->forceFill([
                'estado_sunat' => 'pendiente',
                'mensaje_sunat' => mb_substr('No se pudo completar el envío: '.$e->getMessage(), 0, 250),
                'fecha_envio_sunat' => now(),
            ])->save();

            EventoOperacion::error(
                'SUNAT_ENVIO_FALLIDO',
                "No se pudo enviar el comprobante {$nombreBase}: {$e->getMessage()}",
                ['contexto' => ['comprobante' => $nombreBase, 'excepcion' => $e::class]],
            );

            return [
                'estado' => 'pendiente',
                'codigo' => '',
                'descripcion' => $e->getMessage(),
                'cdr' => null,
            ];
        }

        $comprobante->forceFill([
            'estado_sunat' => $lectura['estado'],
            'codigo_respuesta_sunat' => $lectura['codigo'],
            'mensaje_sunat' => mb_substr($lectura['descripcion'], 0, 250),
            'fecha_envio_sunat' => now(),
            'cdr_path' => $lectura['ruta'],
            'cdr_recibido_en' => now(),
            'cdr_hash' => $lectura['hash'],
            /* La columna deja de guardar un volcado suelto y pasa a guardar
               la lectura formal de la constancia, que es lo que una auditoría
               externa necesita consultar sin abrir archivos. */
            'sunat_response' => [
                'cdr' => [
                    'archivo' => basename($lectura['ruta']),
                    'codigo_respuesta' => $lectura['codigo'],
                    'descripcion' => $lectura['descripcion'],
                    'estado' => $lectura['estado'],
                    'notas' => $lectura['notas'],
                    'sha256' => $lectura['hash'],
                    'recibido_en' => now()->toIso8601String(),
                ],
                'modo' => $this->modo(),
            ],
        ])->save();

        EventoOperacion::info(
            'SUNAT_CDR_RECIBIDO',
            "Constancia {$lectura['codigo']} guardada para {$nombreBase}: {$lectura['estado']}.",
            ['contexto' => [
                'comprobante' => $nombreBase,
                'codigo' => $lectura['codigo'],
                'estado' => $lectura['estado'],
                'modo' => $this->modo(),
            ]],
        );

        return [
            'estado' => $lectura['estado'],
            'codigo' => $lectura['codigo'],
            'descripcion' => $lectura['descripcion'],
            'cdr' => basename($lectura['ruta']),
        ];
    }

    /** Devuelve el sobre SOAP de respuesta, venga de donde venga. */
    private function despachar(ComprobanteElectronico $comprobante, string $nombreBase, string $zip): string
    {
        if ($this->modo() !== 'api') {
            return $this->simulador->responder($comprobante, $nombreBase);
        }

        $credenciales = CredencialesSunat::desdeConfiguracion();
        $sobre = $this->empaquetado->sobreSoap($nombreBase.'.zip', $zip, $credenciales);

        $respuesta = Http::timeout((int) config('services.sunat.timeout', 20))
            ->withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => '',
            ])
            ->send('POST', (string) config('services.sunat.endpoint'), ['body' => $sobre]);

        if (! $respuesta->successful()) {
            throw new \RuntimeException("El servicio respondió HTTP {$respuesta->status()}.");
        }

        return $respuesta->body();
    }

    private function modo(): string
    {
        return (string) config('services.sunat.mode', 'simulado');
    }
}
