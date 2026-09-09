<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comision extends Model
{
    protected $table = 'comisiones';

    protected $fillable = [
        'comprobante_electronico_id',
        'tipo_agente',
        'agente_nombre',
        'agente_documento',
        'porcentaje',
        'monto',
        'estado',
        'fecha_liquidacion',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:2',
        'monto' => 'decimal:2',
        'fecha_liquidacion' => 'datetime',
    ];

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(ComprobanteElectronico::class, 'comprobante_electronico_id');
    }
}
