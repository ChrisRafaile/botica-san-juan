<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sesión de conteo físico por ciclos.
 */
class Conteo extends Model
{
    use HasFactory;

    protected $table = 'conteos';

    protected $fillable = [
        'codigo', 'estado', 'criterio', 'ambito', 'observacion',
        'abierto_por', 'cerrado_por', 'abierto_en', 'cerrado_en',
    ];

    protected function casts(): array
    {
        return [
            'abierto_en' => 'datetime',
            'cerrado_en' => 'datetime',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(ConteoDetalle::class);
    }

    public function abiertoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'abierto_por');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cerrado_por');
    }

    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->where('estado', 'abierto');
    }

    public function estaAbierto(): bool
    {
        return $this->estado === 'abierto';
    }

    /**
     * Siguiente código legible del año en curso.
     *
     * Se numera por año y no de forma global para que el código siga siendo
     * corto de leer y dictar en el mostrador después de varios años de uso.
     */
    public static function siguienteCodigo(): string
    {
        $anio = now()->year;

        $ultimo = static::query()
            ->where('codigo', 'like', "CONT-{$anio}-%")
            ->orderByDesc('codigo')
            ->value('codigo');

        $correlativo = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;

        return sprintf('CONT-%d-%04d', $anio, $correlativo);
    }
}
