<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoCaja extends Model
{
    protected $table = 'movimientos_caja';

    public const TIPO_VENTA_EFECTIVO = 'venta_efectivo';
    public const TIPO_INGRESO = 'ingreso';
    public const TIPO_EGRESO = 'egreso';

    public const TIPOS_MANUALES = [
        self::TIPO_INGRESO,
        self::TIPO_EGRESO,
    ];

    protected $fillable = [
        'sesion_caja_id',
        'usuario_id',
        'pedido_id',
        'tipo',
        'monto',
        'motivo',
        'referencia',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(SesionCaja::class, 'sesion_caja_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }
}
