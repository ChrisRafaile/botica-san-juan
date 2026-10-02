<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contacto extends Model
{
    protected $table = 'contacto';

    /* La tabla exige telefono, motivo y fecha (NOT NULL) pero no estaban
       aqui: cualquier alta fallaba con error de base, no de validacion. */
    protected $fillable = [
        'nombre',
        'email',
        'telefono',
        'motivo',
        'mensaje',
        'fecha',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];
}
