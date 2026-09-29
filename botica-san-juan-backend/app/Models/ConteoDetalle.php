<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un producto dentro de una sesión de conteo.
 */
class ConteoDetalle extends Model
{
    use HasFactory;

    protected $table = 'conteo_detalles';

    protected $fillable = [
        'conteo_id', 'producto_id', 'stock_sistema', 'cantidad_contada',
        'lotes_contados', 'observacion', 'contado_por', 'contado_en',
        'diferencia_aplicada',
    ];

    protected function casts(): array
    {
        return [
            'lotes_contados'   => 'array',
            'contado_en'       => 'datetime',
            'stock_sistema'    => 'integer',
            'cantidad_contada' => 'integer',
        ];
    }

    public function conteo(): BelongsTo
    {
        return $this->belongsTo(Conteo::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function contadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'contado_por');
    }

    /** Si ya se contó. Ojo: contar 0 es contar. */
    public function fueContado(): bool
    {
        return $this->cantidad_contada !== null;
    }

    /**
     * Diferencia entre lo contado y lo que creía el sistema.
     * Positiva: sobra en el anaquel. Negativa: falta.
     */
    public function diferencia(): ?int
    {
        return $this->fueContado()
            ? $this->cantidad_contada - $this->stock_sistema
            : null;
    }
}
