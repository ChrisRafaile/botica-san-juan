<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $table = 'categorias';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'color',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function subcategorias(): HasMany
    {
        return $this->hasMany(Subcategoria::class, 'categoria_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }
}
