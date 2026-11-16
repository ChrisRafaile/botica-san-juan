<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaveIdempotencia extends Model
{
    protected $table = 'claves_idempotencia';

    protected $fillable = [
        'clave',
        'alcance',
        'actor',
        'huella_peticion',
        'estado',
        'codigo_respuesta',
        'cuerpo_respuesta',
        'expira_en',
    ];

    protected $casts = [
        'codigo_respuesta' => 'integer',
        'expira_en' => 'datetime',
    ];
}
