<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un medio de pago aplicado a una venta de mostrador.
 */
class PedidoPago extends Model
{
    use HasFactory;

    protected $table = 'pedido_pagos';

    protected $fillable = [
        'pedido_id', 'medio', 'monto', 'monto_recibido', 'vuelto', 'referencia',
    ];

    protected function casts(): array
    {
        return [
            'monto'          => 'decimal:2',
            'monto_recibido' => 'decimal:2',
            'vuelto'         => 'decimal:2',
        ];
    }

    /**
     * Medios aceptados en mostrador.
     *
     * Yape y Plin van separados a propósito, aunque ambos sean billeteras: son
     * cuentas distintas y se concilian por separado. Agruparlos como "billetera
     * digital" obligaría a abrir la app para saber cuál fue.
     */
    public const MEDIOS = ['efectivo', 'tarjeta', 'yape', 'plin', 'transferencia'];

    /**
     * Medios que entran físicamente a la gaveta.
     *
     * Es la distinción que hace posible el arqueo: al cerrar el día, la gaveta
     * debe contener el efectivo y nada más.
     */
    public const EN_GAVETA = ['efectivo'];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function scopeEnGaveta(Builder $query): Builder
    {
        return $query->whereIn('medio', self::EN_GAVETA);
    }

    public function esEfectivo(): bool
    {
        return $this->medio === 'efectivo';
    }

    /** Etiqueta para pantalla y comprobante. */
    public function etiqueta(): string
    {
        return match ($this->medio) {
            'efectivo'      => 'Efectivo',
            'tarjeta'       => 'Tarjeta',
            'yape'          => 'Yape',
            'plin'          => 'Plin',
            'transferencia' => 'Transferencia',
            default         => ucfirst($this->medio),
        };
    }
}
