<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $table = 'pedidos';

    /* ------------------------------------------------------------------
     * ESTADOS
     * ------------------------------------------------------------------
     * Hasta ahora la columna `estado` era un string libre: la migración solo
     * declara varchar(30) y el controlador validaba `string|max:50`. El
     * resultado fue que el frontend filtraba por 'procesando' y 'entregado',
     * estados que NUNCA existieron en la base, mientras que 'completado' —el
     * estado con el que nace toda venta de mostrador— no tenía categoría en
     * la pantalla. Siete de cada diez ventas del sistema quedaban invisibles.
     *
     * Estas constantes fijan el vocabulario. Dos ciclos de vida distintos
     * comparten el mismo estado final:
     *
     *   web:  pendiente ──► confirmado ──► completado
     *                   └──────────────► anulado
     *   pos:  completado (nace aquí; la venta de mostrador ya ocurrió)
     *
     * 'anulado' no tiene filas todavía, pero la pantalla ya ofrecía cancelar,
     * así que se nombra aquí en vez de dejar que cada capa invente la suya
     * ('cancelado' en el frontend, 'anulado' en el resto del sistema).
     */
    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_CONFIRMADO = 'confirmado';
    public const ESTADO_COMPLETADO = 'completado';
    public const ESTADO_ANULADO    = 'anulado';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_CONFIRMADO,
        self::ESTADO_COMPLETADO,
        self::ESTADO_ANULADO,
    ];

    /** Estados en los que el pedido todavía espera una acción del personal. */
    public const ESTADOS_ABIERTOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_CONFIRMADO,
    ];

    public const ORIGEN_POS = 'pos';
    public const ORIGEN_WEB = 'web';

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
        return $this->origen === self::ORIGEN_POS;
    }

    /**
     * Pedidos que todavía esperan algo del personal.
     *
     * Deliberadamente excluye las ventas de mostrador: nacen completadas
     * porque el cliente ya se llevó el producto y pagó. Meterlas en la cola
     * de trabajo sería pedirle al boticario que "atienda" algo que ya atendió.
     */
    public function scopeAbiertos($query)
    {
        return $query->whereIn('estado', self::ESTADOS_ABIERTOS);
    }

    public function scopeDelPortal($query)
    {
        return $query->where('origen', self::ORIGEN_WEB);
    }

    public function scopeDeMostrador($query)
    {
        return $query->where('origen', self::ORIGEN_POS);
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
