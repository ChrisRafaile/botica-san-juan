<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Excepción lanzada cuando una solicitud de cancelación de pedido no puede
 * completarse debido a reglas de negocio o estados no reversibles.
 */
class PedidoNoCancelableException extends RuntimeException
{
    public function __construct(
        private readonly string $motivo,
        string $mensaje = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($mensaje ?: "El pedido no se puede cancelar ({$motivo}).", $code, $previous);
    }

    public function getMotivo(): string
    {
        return $this->motivo;
    }
}
