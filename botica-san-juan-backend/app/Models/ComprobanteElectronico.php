<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComprobanteElectronico extends Model
{
    protected $table = 'comprobantes_electronicos';

    protected $fillable = [
        'pedido_id',
        'tipo_comprobante',
        'serie',
        'numero',
        'cliente_nombre',
        'cliente_documento',
        'total',
        'monto_comision',
        'estado_comision',
        'estado_sunat',
        'codigo_respuesta_sunat',
        'sunat_ticket',
        'sunat_payload',
        'sunat_response',
        'mensaje_sunat',
        'fecha_emision',
        'fecha_envio_sunat',
        'hash_documento',
        'xml_path',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'monto_comision' => 'decimal:2',
        'sunat_payload' => 'array',
        'sunat_response' => 'array',
        'fecha_emision' => 'datetime',
        'fecha_envio_sunat' => 'datetime',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function comisiones(): HasMany
    {
        return $this->hasMany(Comision::class, 'comprobante_electronico_id');
    }
}
