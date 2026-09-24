<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $table = 'pedidos';

    protected $fillable = [
        'usuario_id',
        'origen',
        'vendedor_id',
        'cliente_nombre',
        'cliente_documento',
        'cliente_tipo_documento',
        'fecha',
        'fecha_pedido',
        'total',
        'subtotal_gravado',
        'subtotal_exonerado',
        'igv',
        'tasa_igv',
        'moneda',
        'estado',
        'estado_pago',
        'observacion',
        'medio_pago',
        'subtotal_inafecto',
    ];


    protected $casts = [
        'total'              => 'decimal:2',
        'subtotal_gravado'   => 'decimal:2',
        'subtotal_exonerado' => 'decimal:2',
        'igv'                => 'decimal:2',
        'tasa_igv'           => 'decimal:4',
        'fecha'              => 'datetime',
        'fecha_pedido'       => 'datetime',
    ];

    /** Venta hecha en mostrador, no por el portal web. */
    public function esVentaMostrador(): bool
    {
        return $this->origen === 'pos';
    }

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

    /** Alias corto usado por el servicio de venta. */
    public function detalles()
    {
        return $this->hasMany(PedidoDetalle::class, 'pedido_id');
    }

    /** Quien atendio la venta en mostrador. */
    public function vendedor()
    {
        return $this->belongsTo(Usuario::class, 'vendedor_id');
    }

    /** Faltantes registrados durante esta venta. */
    public function incidencias()
    {
        return $this->hasMany(IncidenciaVenta::class, 'pedido_id');
    }

    /** Cobro en mostrador: un registro por medio de pago usado. */
    public function pagos()
    {
        return $this->hasMany(PedidoPago::class, 'pedido_id');
    }

    /**
     * Efectivo que entró a la gaveta por esta venta.
     *
     * Es el importe cobrado, no el recibido: el vuelto vuelve a salir. Esta es
     * la cifra que debe cuadrar con el conteo físico de la caja al cerrar.
     */
    public function efectivoEnGaveta(): float
    {
        return (float) $this->pagos()->enGaveta()->sum('monto');
    }
}
