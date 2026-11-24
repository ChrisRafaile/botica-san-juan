<?php

namespace App\Mail;

use App\Models\Usuario;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de bienvenida para cuentas nuevas del portal (registro o alta con Google).
 * Se envía de forma síncrona: en producción no hay worker de cola activo.
 */
class BienvenidaCliente extends Mailable
{
    use SerializesModels;

    public function __construct(public readonly Usuario $usuario)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Te damos la bienvenida · Botica San Juan',
        );
    }

    public function content(): Content
    {
        $completo = trim((string) ($this->usuario->nombre ?? ''));
        $primerNombre = $completo === '' ? 'cliente' : explode(' ', $completo)[0];

        return new Content(
            markdown: 'correos.bienvenida',
            with: [
                'primerNombre' => $primerNombre,
                'urlPortal'    => rtrim((string) config('app.frontend_url'), '/'),
            ],
        );
    }
}
