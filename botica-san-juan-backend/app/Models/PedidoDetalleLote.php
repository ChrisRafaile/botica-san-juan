<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qué lote, y cuánto de él, sirvió una línea de venta.
 *
 * El código de lote y el vencimiento se guardan copiados, no sólo por la
 * relación: si el lote se corrige o se retira más adelante, el comprobante
 * debe seguir reflejando lo que se entregó ese día.
 */
class PedidoDetalleLote extends Model
{
    protected $table = 'pedido_detalle_lotes';

    protected $fillable = [
        'pedido_detalle_id', 'lote_id', 'cantidad_unidades',
        'codigo_lote', 'fecha_vencimiento',
    ];

    protected $casts = [
        'cantidad_unidades' => 'integer',
        'fecha_vencimiento' => 'date',
    ];

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(PedidoDetalle::class, 'pedido_detalle_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }
}
