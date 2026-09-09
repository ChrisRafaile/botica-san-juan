<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'dni',
        'telefono',
        'foto',
        'foto_perfil',
        'foto_portada',
        'rol',
        'mfa_enabled',
        'mfa_secret',
        'mfa_enabled_at',
    ];

    protected $hidden = [
        'password',
        'mfa_secret',
    ];

    protected $casts = [
        'password' => 'hashed',
        'mfa_enabled' => 'boolean',
        'mfa_enabled_at' => 'datetime',
    ];

    public function carritos()
    {
        return $this->hasMany(Carrito::class, 'usuario_id');
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'usuario_id');
    }
}
