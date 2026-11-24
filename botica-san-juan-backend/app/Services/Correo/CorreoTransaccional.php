<?php

namespace App\Services\Correo;

use App\Models\EventoOperacion;
use Illuminate\Support\Facades\Log;

/**
 * Enviador único de correos transaccionales.
 *
 * Centraliza el envío síncrono para que ningún fallo del proveedor externo
 * tumbe la operación principal (registro, compra o recuperación), valida el
 * transporte en producción y registra el resultado sin exponer direcciones,
 * tokens ni mensajes de excepción que puedan contener PII.
 */
class CorreoTransaccional
{
    public function enviar(string $tipo, callable $envio, ?int $usuarioId = null, ?int $pedidoId = null): bool
    {
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
