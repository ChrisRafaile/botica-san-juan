<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que se pidió y no se pudo entregar.
 *
 * Además de dejar constancia para regularizar inventario, estas filas son la
 * demanda que la botica no llegó a atender: el dato que hoy se pierde cuando
 * el cliente se va con las manos vacías.
 */
class IncidenciaVenta extends Model
{
    protected $table = 'incidencias_venta';

    protected $fillable = [
        'pedido_id', 'pedido_detalle_id', 'producto_id', 'tipo',
        'cantidad_solicitada', 'cantidad_atendida', 'usuario_id', 'observacion',
    ];

    protected $casts = [
        'cantidad_solicitada' => 'integer',
        'cantidad_atendida'   => 'integer',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** Unidades que faltaron por entregar. */
    public function faltante(): int
    {
        return $this->cantidad_solicitada - $this->cantidad_atendida;
    }

    public function scopeDesde(Builder $query, $fecha): Builder
    {
        return $query->where('created_at', '>=', $fecha);
    }
}
