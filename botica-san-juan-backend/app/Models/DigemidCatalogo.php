<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DigemidCatalogo extends Model
{
    protected $table = 'digemid_catalogos';

    protected $fillable = [
        'codigo_digemid',
        'nombre_producto',
        'principio_activo',
        'laboratorio_fabricante',
        'requiere_receta',
        'precio_maximo_regulado',
        'activo',
    ];

    protected $casts = [
        'requiere_receta' => 'boolean',
        'activo' => 'boolean',
        'precio_maximo_regulado' => 'decimal:2',
    ];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
