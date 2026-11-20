<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesionCaja extends Model
{
    protected $table = 'sesiones_caja';

    public const ESTADO_ABIERTA = 'abierta';
    public const ESTADO_CERRADA = 'cerrada';

    protected $fillable = [
        'usuario_id',
        'estado',
        'monto_inicial',
        'ventas_efectivo',
        'total_ingresos',
        'total_egresos',
        'monto_esperado',
        'monto_contado',
        'diferencia',
        'observacion_apertura',
        'observacion_cierre',
        'abierta_en',
        'cerrada_en',
    ];

    protected $casts = [
        'monto_inicial'   => 'decimal:2',
        'ventas_efectivo' => 'decimal:2',
        'total_ingresos'  => 'decimal:2',
        'total_egresos'   => 'decimal:2',
        'monto_esperado'  => 'decimal:2',
        'monto_contado'   => 'decimal:2',
        'diferencia'      => 'decimal:2',
        'abierta_en'      => 'datetime',
        'cerrada_en'      => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class, 'sesion_caja_id');
    }
}
