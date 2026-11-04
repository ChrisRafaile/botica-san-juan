<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Receta médica adjunta a un pedido.
 *
 * El modelo no sabe validar recetas, y es importante que no lo parezca: su
 * trabajo es guardar el archivo, su estado y quién lo decidió. Quien dice si
 * una receta vale es una persona con título, en el mostrador.
 */
class Receta extends Model
{
    protected $table = 'recetas';

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_VALIDADA = 'validada';
    public const ESTADO_RECHAZADA = 'rechazada';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_VALIDADA,
        self::ESTADO_RECHAZADA,
    ];

    /** Carpeta dentro del disco privado. Fuera de ella no se borra nada. */
    public const CARPETA = 'recetas';

    /**
     * Tipos que se aceptan, por su tipo REAL, no por la extensión.
     *
     * La foto del móvil y el PDF del consultorio son los dos casos de la vida
     * real. No se acepta HEIC: lo produce el iPhone pero el navegador del
     * mostrador no lo pinta, y una receta que el boticario no puede ver no
     * sirve de nada.
     */
    public const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    protected $fillable = [
        'pedido_id',
        'subida_por_id',
        'archivo',
        'nombre_original',
        'tipo_mime',
        'tamano_bytes',
        'estado',
        'motivo_rechazo',
        'revisada_en',
        'revisada_por_id',
        'version',
    ];

    protected $casts = [
        'revisada_en' => 'datetime',
        'tamano_bytes' => 'integer',
        'version' => 'integer',
    ];

    /**
     * La ruta del archivo NO sale en las respuestas de la API.
     *
     * No es paranoia: publicarla invita a construir la URL a mano y a que
     * alguien pruebe si el disco está servido directamente. El archivo se pide
     * por su endpoint, que comprueba quién lo pide.
     */
    protected $hidden = ['archivo'];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function subidaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'subida_por_id');
    }

    public function revisadaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'revisada_por_id');
    }

    public function esImagen(): bool
    {
        return str_starts_with($this->tipo_mime, 'image/');
    }

    public function estaValidada(): bool
    {
        return $this->estado === self::ESTADO_VALIDADA;
    }

    /** Pendientes de revisar, lo más antiguo primero. */
    public function scopePorRevisar($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE)->oldest();
    }
}
