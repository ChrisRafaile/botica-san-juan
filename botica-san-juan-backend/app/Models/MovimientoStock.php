<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asiento del libro de inventario. Toda variación de stock escribe uno.
 *
 * Nunca se edita ni se borra: si un movimiento fue erróneo se registra otro
 * que lo compensa. Un libro que se puede reescribir no sirve como auditoría.
 */
class MovimientoStock extends Model
{
    protected $table = 'movimientos_stock';

    protected $fillable = [
        'producto_id', 'lote_id', 'tipo', 'cantidad',
        'stock_anterior', 'stock_posterior',
        'referencia_tipo', 'referencia_id', 'usuario_id', 'motivo',
    ];

    protected $casts = [
        'cantidad'        => 'integer',
        'stock_anterior'  => 'integer',
        'stock_posterior' => 'integer',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** true si el movimiento suma stock. */
    public function esEntrada(): bool
    {
        return $this->cantidad > 0;
    }
}
