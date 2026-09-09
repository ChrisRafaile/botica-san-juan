<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pago extends Model
{
    protected $table = 'pagos';

    protected $fillable = [
        'pedido_id',
        'proveedor',
        'proveedor_pago_id',
        'referencia_pedido',
        'estado',
        'metodo_pago',
        'marca_tarjeta',
        'ultimos4',
        'monto',
        'moneda',
        'pagado_en',
        'codigo_error',
        'mensaje_error',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'pagado_en' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(PagoEvento::class, 'pago_id');
    }

    /** Representacion publica: nunca expone identificadores internos del proveedor. */
    public function paraCliente(): array
    {
        return [
            'referencia' => $this->referencia_pedido,
            'estado' => $this->estado,
            'metodo_pago' => $this->metodo_pago,
            'marca_tarjeta' => $this->marca_tarjeta,
            'ultimos4' => $this->ultimos4,
            'monto' => (float) $this->monto,
            'moneda' => $this->moneda,
            'pagado_en' => optional($this->pagado_en)->toDateTimeString(),
            'mensaje_error' => $this->mensaje_error,
        ];
    }
}
