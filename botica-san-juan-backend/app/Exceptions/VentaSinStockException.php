<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando una venta no puede entregar ni una sola unidad.
 *
 * Es distinta de la entrega parcial: ahí sí hay algo que cobrar y la venta se
 * registra con su incidencia. Aquí no hay nada, y registrar el pedido dejaría
 * un comprobante en cero que ensucia el historial y el reporte del contador.
 *
 * Al heredar de RuntimeException dentro de una transacción, lanzarla revierte
 * todo lo escrito: pedido, movimientos e incidencias.
 */
class VentaSinStockException extends RuntimeException
{
}
