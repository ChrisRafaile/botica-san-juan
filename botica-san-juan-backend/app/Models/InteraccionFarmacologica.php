<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regla de interacción entre dos principios activos.
 *
 * DOS INVARIANTES QUE SOSTIENEN TODO LO DEMAS
 *
 * 1. El par se guarda con el id MENOR primero. Sin esa convención la misma
 *    interacción entraría dos veces —una por cada orden— y el índice único
 *    no la detendría.
 * 2. Una regla sin `aprobada_en` NO se muestra a ningún cliente. La aprueba
 *    un químico farmacéutico, y hasta entonces existe solo para revisión.
 */
class InteraccionFarmacologica extends Model
{
    protected $table = 'interacciones_farmacologicas';

    protected $fillable = [
        'principio_menor_id', 'principio_mayor_id',
        'severidad', 'efecto', 'recomendacion', 'fuente',
        'aprobada_en', 'aprobada_por_id',
    ];

    protected $casts = ['aprobada_en' => 'datetime'];

    public const SEVERIDADES = ['leve', 'moderada', 'grave'];

    public function principioMenor(): BelongsTo
    {
        return $this->belongsTo(PrincipioActivo::class, 'principio_menor_id');
    }

    public function principioMayor(): BelongsTo
    {
        return $this->belongsTo(PrincipioActivo::class, 'principio_mayor_id');
    }

    /** Solo lo aprobado llega al público. */
    public function scopeAprobadas(Builder $consulta): Builder
    {
        return $consulta->whereNotNull('aprobada_en');
    }

    /**
     * Ordena un par de identificadores según la convención de la tabla.
     *
     * @return array{0:int, 1:int}
     */
    public static function ordenarPar(int $a, int $b): array
    {
        return $a <= $b ? [$a, $b] : [$b, $a];
    }
}
