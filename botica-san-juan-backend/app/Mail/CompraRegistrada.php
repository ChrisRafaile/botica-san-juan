<?php

namespace App\Mail;

use App\Models\Pedido;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmación de compra registrada en el portal para clientes con sesión.
 * Omite datos sensibles (DNI, teléfono y dirección) por privacidad.
 */
class CompraRegistrada extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly Pedido $pedido,
        public readonly string $identificadorComprobante,
        public readonly string $modalidadEntrega,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Pedido #%d registrado · Botica San Juan', $this->pedido->id),
        );
    }

    public function content(): Content
    {
        $items = $this->pedido->pedidoDetalles->map(fn ($detalle) => [
            'nombre'   => (string) ($detalle->producto?->nombre ?? 'Producto'),
            'cantidad' => (int) $detalle->cantidad,
            'subtotal' => number_format((float) $detalle->subtotal, 2, '.', ''),
        ])->all();

        $modalidadTexto = $this->modalidadEntrega === 'entrega'
            ? 'Entrega a domicilio'
            : 'Recojo en botica';

        return new Content(
            markdown: 'correos.compra-registrada',
            with: [
                'numeroPedido'             => $this->pedido->id,
                'identificadorComprobante' => $this->identificadorComprobante,
                'items'                    => $items,
                'total'                    => number_format((float) $this->pedido->total, 2, '.', ''),
                'modalidad'                => $modalidadTexto,
                'estado'                   => (string) $this->pedido->estado,
                'urlPedidos'               => rtrim((string) config('app.frontend_url'), '/').'/client/pedidos',
            ],
        );
    }
}
