<?php

namespace App\Services\Pagos;

/**
 * Estados internos del cobro y su traduccion desde la nomenclatura del
 * proveedor.
 *
 * El resto del sistema nunca ve los nombres de Izipay. Toda la dependencia
 * del vocabulario ajeno queda confinada en este archivo, de modo que cambiar
 * de pasarela signifique reescribir un mapa y no perseguir cadenas de texto
 * por toda la aplicacion.
 *
 * Los valores literales estan tomados de la referencia de la plataforma
 * (ciclo de vida de una transaccion, API REST V4). La plataforma distingue
 * dos campos:
 *
 *   - 'status' / 'orderStatus': estado simplificado. Solo cuatro valores.
 *     'orderStatus' es la consolidacion del 'status' de las transacciones
 *     asociadas a la orden.
 *   - 'detailedStatus': estado detallado, especifico del medio de pago.
 *     Cada valor detallado pertenece a uno de los cuatro simplificados.
 */
final class EstadoPago
{
    public const PENDIENTE = 'pendiente';
    public const PROCESANDO = 'procesando';
    public const PAGADO = 'pagado';
    public const FALLIDO = 'fallido';
    public const CANCELADO = 'cancelado';
    public const REEMBOLSADO = 'reembolsado';

    /** Estados en los que el cobro ya no admite transiciones automaticas. */
    private const FINALES = [self::PAGADO, self::REEMBOLSADO, self::CANCELADO];

    /**
     * Mapa desde 'orderStatus' (o 'status' de una transaccion).
     *
     * Estos cuatro son los unicos valores que la plataforma define para el
     * estado simplificado. No existe un 'REFUNDED' en este campo: una
     * devolucion se materializa como una transaccion nueva de tipo CREDIT,
     * y por eso el estado 'reembolsado' lo fija el propio sistema al
     * confirmar esa operacion, no la traduccion de un estado ajeno.
     *
     * @var array<string, string>
     */
    private const DESDE_ORDER_STATUS = [
        'PAID' => self::PAGADO,
        'UNPAID' => self::FALLIDO,
        'RUNNING' => self::PROCESANDO,
        'ABANDONED' => self::CANCELADO,
    ];

    /**
     * Mapa desde 'detailedStatus'. Se usa solo cuando la reconsulta devuelve
     * el detalle de una transaccion concreta; el estado simplificado sigue
     * siendo la fuente de verdad y este mapa debe coincidir con el.
     *
     * @var array<string, string>
     */
    private const DESDE_DETAILED_STATUS = [
        // status = PAID
        'ACCEPTED' => self::PAGADO,
        'AUTHORISED' => self::PAGADO,
        'CAPTURED' => self::PAGADO,
        'PRE_AUTHORISED' => self::PAGADO,
        // status = RUNNING
        'AUTHORISED_TO_VALIDATE' => self::PROCESANDO,
        'WAITING_AUTHORISATION' => self::PROCESANDO,
        'WAITING_AUTHORISATION_TO_VALIDATE' => self::PROCESANDO,
        'WAITING_FOR_PAYMENT' => self::PROCESANDO,
        'UNDER_VERIFICATION' => self::PROCESANDO,
        // status = UNPAID
        'REFUSED' => self::FALLIDO,
        'ERROR' => self::FALLIDO,
        'CAPTURE_FAILED' => self::FALLIDO,
        'CANCELLED' => self::FALLIDO,
        'EXPIRED' => self::FALLIDO,
    ];

    public static function desdeProveedor(?string $estadoProveedor): string
    {
        $clave = strtoupper(trim((string) $estadoProveedor));

        // Un estado desconocido no se asume exitoso: se deja en proceso para
        // que la reconsulta contra la API lo resuelva. Nunca se infiere PAGADO.
        return self::DESDE_ORDER_STATUS[$clave] ?? self::PROCESANDO;
    }

    public static function desdeEstadoDetallado(?string $estadoDetallado): string
    {
        $clave = strtoupper(trim((string) $estadoDetallado));

        return self::DESDE_DETAILED_STATUS[$clave] ?? self::PROCESANDO;
    }

    /** @return array<int, string> */
    public static function estadosDelProveedor(): array
    {
        return array_keys(self::DESDE_ORDER_STATUS);
    }

    public static function esFinal(string $estado): bool
    {
        return in_array($estado, self::FINALES, true);
    }

    /**
     * Un pago pagado no puede volver a procesando por una notificacion tardia
     * que llegue fuera de orden. Solo el reembolso avanza desde pagado.
     */
    public static function permiteTransicion(string $actual, string $nuevo): bool
    {
        if ($actual === $nuevo) {
            return false;
        }

        if ($actual === self::PAGADO) {
            return $nuevo === self::REEMBOLSADO;
        }

        if (self::esFinal($actual)) {
            return false;
        }

        return true;
    }

    /** @return array<int, string> */
    public static function todos(): array
    {
        return [
            self::PENDIENTE, self::PROCESANDO, self::PAGADO,
            self::FALLIDO, self::CANCELADO, self::REEMBOLSADO,
        ];
    }
}
