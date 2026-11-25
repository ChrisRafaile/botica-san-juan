<?php

namespace App\Jobs;

use App\Mail\BienvenidaCliente;
use App\Mail\CompraRegistrada;
use App\Models\Pedido;
use App\Models\Usuario;
use App\Services\Correo\CorreoNoEnviado;
use App\Services\Correo\CorreoTransaccional;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Envío encolable de bienvenida, confirmación de compra y recuperación.
 *
 * La carga útil son identificadores: ni correos ni tokens quedan en claro en la
 * tabla `jobs`. El token de recuperación se genera al ejecutar.
 */
class EnviarCorreoTransaccional implements ShouldQueue
{
    use Queueable;

    public const COLA = 'correos';

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    /** @param array<string, string> $datos */
    public function __construct(
        public readonly string $tipo,
        public readonly int $usuarioId,
        public readonly ?int $pedidoId = null,
        public readonly array $datos = [],
    ) {
        $this->onQueue(self::COLA);
    }

    public function handle(CorreoTransaccional $correos): void
    {
        $usuario = Usuario::find($this->usuarioId);

        if ($usuario === null || blank($usuario->email)) {
            return;
        }

        $correos->enviar($this->tipo, fn () => $this->despachar($usuario), $this->usuarioId, $this->pedidoId);

        if ($correos->falloElProveedor()) {
            throw new CorreoNoEnviado("No se pudo enviar el correo ({$this->tipo}).");
        }
    }

    private function despachar(Usuario $usuario): mixed
    {
        return match ($this->tipo) {
            'bienvenida', 'bienvenida_google' => Mail::to($usuario->email)->send(new BienvenidaCliente($usuario)),
            'compra_registrada' => Mail::to($usuario->email)->send(new CompraRegistrada(
                pedido: Pedido::with('pedidoDetalles.producto')->findOrFail($this->pedidoId),
                identificadorComprobante: $this->datos['identificador'] ?? '',
                modalidadEntrega: $this->datos['modalidad'] ?? 'recojo',
            )),
            'recuperacion_contrasena' => $this->recuperar($usuario),
            default => throw new \InvalidArgumentException("Tipo de correo desconocido: {$this->tipo}"),
        };
    }

    /**
     * Solo RESET_LINK_SENT es un envío: con RESET_THROTTLED no salió ningún
     * correo y anotarlo como enviado falsearía la bitácora.
     */
    private function recuperar(Usuario $usuario): bool
    {
        /* Un intento fallido ya dejó un token reciente, y el límite del broker
           haría que el reintento se omitiera sin enviar nada. */
        if (($this->job?->attempts() ?? 1) > 1) {
            Password::broker()->deleteToken($usuario);
        }

        return Password::broker()->sendResetLink(['email' => $usuario->email]) === Password::RESET_LINK_SENT;
    }
}
