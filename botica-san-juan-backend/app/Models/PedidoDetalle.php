<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoDetalle extends Model
{
    protected $table = 'pedido_detalles';

    protected $fillable = [
        'pedido_id',
        'producto_id',
        'unidad_venta',
        'factor_unidades',
        'cantidad',
        'cantidad_unidades',
        'precio_unitario',
        'precio',
        'subtotal',
    ];

    protected $casts = [
        'unidad_venta' => 'string',
        'factor_unidades' => 'integer',
        'cantidad' => 'integer',
        'cantidad_unidades' => 'integer',
        'precio_unitario' => 'decimal:2',
        'precio' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
