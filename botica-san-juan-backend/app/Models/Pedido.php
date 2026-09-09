<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $table = 'pedidos';

    protected $fillable = [
        'usuario_id',
        'fecha_pedido',
        'total',
        'moneda',
        'estado',
        'estado_pago',
    ];


    protected $casts = [
        'total' => 'decimal:2',
        'fecha_pedido' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function pedidoDetalles()
    {
        return $this->hasMany(PedidoDetalle::class, 'pedido_id');
    }

    public function comprobanteElectronico()
    {
        return $this->hasOne(ComprobanteElectronico::class, 'pedido_id');
    }

    public function pago()
    {
        return $this->hasOne(Pago::class, 'pedido_id');
    }
}
