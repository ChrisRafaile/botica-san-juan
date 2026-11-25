<?php

namespace App\Services\Correo;

use App\Jobs\EnviarCorreoTransaccional;
use App\Models\EventoOperacion;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Enviador único de correos transaccionales.
 *
 * `encolar()` deja el envío en la cola nativa de Laravel; `enviar()` lo hace en
 * el acto y es lo que ejecuta el trabajo. Centraliza el envío para que ningún fallo del proveedor externo
 * tumbe la operación principal (registro, compra o recuperación), valida el
 * transporte en producción y registra el resultado sin exponer direcciones,
 * tokens ni mensajes de excepción que puedan contener PII.
 */
class CorreoTransaccional
{
    /** El último envío se intentó y el proveedor falló (no: se omitió). */
    private bool $falloDelProveedor = false;

    /**
     * Encola un correo y lo intenta en cuanto la respuesta salió.
     *
     * La capa gratuita de Render no da un worker. El trabajo se PERSISTE en la
     * cola, se drena tras la respuesta (VaciadoDeCola) y, si el contenedor
     * muere antes o el proveedor falla, lo recoge el planificador cada minuto.
     *
     * Nunca lanza: una venta o un alta ya registradas no se caen por un correo.
     *
     * @param  array<string, string>  $datos  Sin datos personales ni credenciales.
     */
    public function encolar(string $tipo, int $usuarioId, ?int $pedidoId = null, array $datos = []): void
    {
        $trabajo = new EnviarCorreoTransaccional($tipo, $usuarioId, $pedidoId, $datos);

        try {
            Bus::dispatch($trabajo);
        } catch (\Throwable $e) {
            Log::warning('No se pudo encolar el correo transaccional', [
                'tipo'      => $tipo,
                'excepcion' => $e::class,
            ]);

            /* Con `sync` la excepción ES el envío fallido, ya anotado. Con una
               cola caída, en cambio, el correo todavía no se intentó. */
            if (! $e instanceof CorreoNoEnviado) {
                $this->intentarSinCola($trabajo);
            }

            return;
        }

        if (config('queue.default') !== 'sync') {
            app()->terminating(fn () => app(VaciadoDeCola::class)->vaciar(EnviarCorreoTransaccional::COLA));
        }
    }

    public function falloElProveedor(): bool
    {
        return $this->falloDelProveedor;
    }

    private function intentarSinCola(EnviarCorreoTransaccional $trabajo): void
    {
        try {
            $trabajo->handle($this);
        } catch (\Throwable) {
            // `enviar` ya dejó el evento en la bitácora.
        }
    }

    public function enviar(string $tipo, callable $envio, ?int $usuarioId = null, ?int $pedidoId = null): bool
    {
        $this->falloDelProveedor = false;

        if (app()->isProduction() && ! $this->transporteValidoEnProduccion()) {
            $mailer = config('mail.default');

            Log::error('Transporte de correo inválido en producción', [
                'tipo'       => $tipo,
                'mailer'     => is_string($mailer) ? $mailer : 'desconocido',
                'usuario_id' => $usuarioId,
                'pedido_id'  => $pedidoId,
            ]);

            $this->registrarEventoSeguro(
                fn () => EventoOperacion::error(
                    EventoOperacion::CORREO_TRANSPORTE_INVALIDO,
                    'Transporte de correo inválido en producción.',
                    [
                        'usuario_id' => $usuarioId,
                        'pedido_id'  => $pedidoId,
                        'contexto'   => [
                            'tipo'   => $tipo,
                            'mailer' => is_string($mailer) ? $mailer : 'desconocido',
                        ],
                    ]
                )
            );

            return false;
        }

        try {
            /* `false` explícito: no hubo envío ni fallo (p. ej. el broker de
               contraseñas limitó la petición). No hay nada que anotar. */
            if ($envio() === false) {
                return false;
            }

            $this->registrarEventoSeguro(
                fn () => EventoOperacion::info(
                    EventoOperacion::CORREO_ENVIADO,
                    'Correo transaccional enviado.',
                    [
                        'usuario_id' => $usuarioId,
                        'pedido_id'  => $pedidoId,
                        'contexto'   => ['tipo' => $tipo],
                    ]
                )
            );

            return true;
        } catch (\Throwable $e) {
            $this->falloDelProveedor = true;

            /* No se registra el mensaje de la excepción ni el destinatario:
               clientes como Resend suelen incluir direcciones de correo o
               detalles del payload en el texto del error. */
            Log::error('Fallo al enviar correo transaccional', [
                'tipo'       => $tipo,
                'excepcion'  => $e::class,
                'usuario_id' => $usuarioId,
                'pedido_id'  => $pedidoId,
            ]);

            $this->registrarEventoSeguro(
                fn () => EventoOperacion::error(
                    EventoOperacion::CORREO_FALLIDO,
                    'Fallo al enviar correo transaccional.',
                    [
                        'usuario_id' => $usuarioId,
                        'pedido_id'  => $pedidoId,
                        'contexto'   => [
                            'tipo'      => $tipo,
                            'excepcion' => $e::class,
                        ],
                    ]
                )
            );

            return false;
        }
    }

    private function transporteValidoEnProduccion(): bool
    {
        return config('mail.default') === 'resend'
            && filled(config('services.resend.key'));
    }

    private function registrarEventoSeguro(callable $accion): void
    {
        try {
            $accion();
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar el evento de correo en bitácora', [
                'excepcion' => $e::class,
            ]);
        }
    }
}
