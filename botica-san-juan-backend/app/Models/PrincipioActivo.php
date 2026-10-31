<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Principio activo: la cara clínica de un producto.
 *
 * El nombre canónico se guarda normalizado —mayúsculas, sin tildes— porque es
 * la forma con la que se compara contra los nombres del catálogo. Si se
 * guardara «como se escribe bonito», cada comparación tendría que normalizar
 * en caliente y el índice no serviría.
 */
class PrincipioActivo extends Model
{
    protected $table = 'principios_activos';

    protected $fillable = ['nombre', 'sinonimos'];

    protected $casts = ['sinonimos' => 'array'];

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'principio_activo_id');
    }

    /** Todas las formas por las que se puede reconocer. */
    public function formas(): array
    {
        return array_values(array_unique([
            $this->nombre,
            ...array_map(static fn ($s) => mb_strtoupper((string) $s), $this->sinonimos ?? []),
        ]));
    }
}
