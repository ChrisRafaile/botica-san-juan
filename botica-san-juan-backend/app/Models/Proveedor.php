<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'ruc',
        'contacto',
        'telefono',
        'email',
        'direccion',
        'dias_credito',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'dias_credito' => 'integer',
    ];

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'proveedor_id');
    }
}
