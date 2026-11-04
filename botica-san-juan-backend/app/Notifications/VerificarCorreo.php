<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Correo de verificacion de cuenta.
 * ---------------------------------------------------------------------------
 * COMO SE CONSTRUYE EL ENLACE, Y POR QUE ASI
 *
 * El enlace apunta a la API, firmado. Es la API la que puede validar la firma
 * —la clave esta ahi y tiene que seguir ahi—, y al pulsarlo la API redirige al
 * portal con el resultado.
 *
 * El primer intento fue al reves: el correo llevaba al portal con el enlace
 * firmado como parametro, y el portal lo invocaba. Era una peticion entre
 * origenes distintos para algo que no necesita serlo, y en la prueba la
 * bloqueo el navegador. Navegar es mas simple que llamar.
 *
 * El enlace CADUCA. Un enlace de verificacion eterno en una bandeja de entrada
 * es una credencial permanente: quien tenga acceso al correo dos anos despues
 * sigue pudiendo activar la cuenta.
 */
class VerificarCorreo extends Notification
{
    use Queueable;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutos = Config::get('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Confirma tu correo · Botica San Juan')
            ->greeting('Hola '.$this->nombre($notifiable).',')
            ->line('Creaste una cuenta en el portal de Botica San Juan. Para terminar, confirma que este correo es tuyo.')
            ->action('Confirmar mi correo', $this->enlace($notifiable))
            ->line(sprintf('El enlace vence en %d minutos. Si vence, puedes pedir otro desde tu perfil.', $minutos))
            ->line('Confirmar tu correo nos permite avisarte del estado de tus pedidos y recuperar tu cuenta si olvidas la contraseña.')
            /* Quien no pidio esto no tiene que hacer nada. Pedirle que
               "ignore el mensaje" es mas honesto que no decir nada: el correo
               pudo acabar ahi por un tecleo ajeno. */
            ->line('Si no creaste ninguna cuenta, no hace falta que hagas nada: sin confirmar, la cuenta no se activa.')
            ->salutation('Botica San Juan');
    }

    /**
     * Enlace firmado de la API. Al abrirlo, redirige al portal.
     */
    private function enlace(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'verificacion.verificar',
            now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    private function nombre(object $notifiable): string
    {
        $completo = trim((string) ($notifiable->nombre ?? ''));

        if ($completo === '') {
            return 'cliente';
        }

        /* Solo el primer nombre. El nombre completo en un saludo suena a
           carta de cobranza. */
        return explode(' ', $completo)[0];
    }
}
