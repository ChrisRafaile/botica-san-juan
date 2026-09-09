<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoEvento extends Model
{
    protected $table = 'pagos_eventos';

    protected $fillable = [
        'evento_id',
        'pago_id',
        'tipo',
        'estado_reportado',
        'payload',
        'procesado_en',
    ];

    protected $casts = [
        'payload' => 'array',
        'procesado_en' => 'datetime',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }
}
