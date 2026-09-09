<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subcategoria extends Model
{
    protected $table = 'subcategorias';

    protected $fillable = [
        'categoria_id',
        'nombre',
        'slug',
        'descripcion',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'subcategoria_id');
    }
}
