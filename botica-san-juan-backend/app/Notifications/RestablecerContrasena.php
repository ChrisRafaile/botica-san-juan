<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

/**
 * Correo de recuperacion de contrasena.
 * ---------------------------------------------------------------------------
 * EL TOKEN NO SE REGISTRA EN NINGUN SITIO
 *
 * El token que llega aqui es, durante su vigencia, equivalente a la contrasena
 * de la cuenta. No se escribe en el log, no se devuelve en la respuesta de la
 * API y no se guarda en claro: la tabla password_reset_tokens almacena su
 * hash. Solo existe en texto plano dentro de este correo.
 *
 * El enlace lleva tambien el correo porque el verificador de Laravel necesita
 * las dos cosas para comprobar el token. Eso significa que el correo viaja en
 * una URL, de modo que el enlace apunta al portal por POST interno y no se
 * comparte con terceros.
 */
class RestablecerContrasena extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutos = (int) Config::get('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Restablece tu contraseña · Botica San Juan')
            /* Sin nombre en el saludo, a diferencia del correo de
               verificacion. Si el correo llega a la persona equivocada —un
               buzon compartido, una direccion mal teclada en el registro—, el
               saludo no tiene por que confirmarle a quien pertenece la
               cuenta. */
            ->greeting('Hola,')
            ->line('Alguien pidió restablecer la contraseña de la cuenta asociada a este correo.')
            ->action('Elegir una contraseña nueva', $this->enlace($notifiable))
            ->line(sprintf('El enlace vence en %d minutos y sirve una sola vez.', $minutos))
            /* Esta linea importa: si quien recibe el correo NO pidio el
               cambio, tiene que saber que su contrasena actual sigue siendo
               valida y que no hay nada que hacer. Sin esa frase, el correo
               parece avisar de que ya le cambiaron la contrasena. */
            ->line('Si no lo pediste, ignora este mensaje: tu contraseña actual no ha cambiado y nadie puede cambiarla sin este enlace.')
            ->salutation('Botica San Juan');
    }

    private function enlace(object $notifiable): string
    {
        return rtrim((string) Config::get('app.frontend_url'), '/')
            .'/restablecer-contrasena'
            .'?token='.urlencode($this->token)
            .'&correo='.urlencode((string) $notifiable->getEmailForPasswordReset());
    }
}
