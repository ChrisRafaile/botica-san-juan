<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Evento de operación del sistema.
 *
 * Se escribe con los ayudantes `info()`, `advertencia()` y `error()` en vez de
 * con `create()` suelto: así el nivel nunca llega escrito a mano con una
 * mayúscula de más, y quien lee el código de llamada ve de un vistazo la
 * gravedad de lo que se está registrando.
 */
class EventoOperacion extends Model
{
    public const UPDATED_AT = null;

    public const NIVEL_INFO    = 'info';
    public const NIVEL_AVISO   = 'warning';
    public const NIVEL_ERROR   = 'error';

    /* Códigos estables. Son los que se cuentan en los informes de operación;
       si alguno se renombra, los conteos históricos dejan de cuadrar. */
    public const SYNC_CREADA      = 'SYNC_VENTA_CREADA';
    public const SYNC_DUPLICADA   = 'SYNC_VENTA_DUPLICADA';
    public const SYNC_RECHAZADA   = 'SYNC_VENTA_RECHAZADA';
    public const SYNC_INCIDENCIA  = 'SYNC_STOCK_INSUFICIENTE';
    public const SYNC_LOTE        = 'SYNC_LOTE_PROCESADO';

    protected $table = 'eventos_operacion';

    protected $fillable = [
        'nivel', 'codigo', 'mensaje', 'origen',
        'dispositivo', 'uuid_local', 'usuario_id', 'pedido_id', 'contexto',
    ];

    protected $casts = [
        'contexto'   => 'array',
        'created_at' => 'datetime',
    ];

    public static function info(string $codigo, string $mensaje, array $datos = []): self
    {
        return self::registrar(self::NIVEL_INFO, $codigo, $mensaje, $datos);
    }

    public static function advertencia(string $codigo, string $mensaje, array $datos = []): self
    {
        return self::registrar(self::NIVEL_AVISO, $codigo, $mensaje, $datos);
    }

    public static function error(string $codigo, string $mensaje, array $datos = []): self
    {
        return self::registrar(self::NIVEL_ERROR, $codigo, $mensaje, $datos);
    }

    /**
     * @param  array{origen?:string, dispositivo?:string|null, uuid_local?:string|null, usuario_id?:int|null, pedido_id?:int|null, contexto?:array}  $datos
     */
    private static function registrar(string $nivel, string $codigo, string $mensaje, array $datos): self
    {
        return self::create([
            'nivel'       => $nivel,
            'codigo'      => $codigo,
            /* Se recorta en vez de reventar: perder el final de un mensaje
               largo es molesto, pero que una excepción al escribir la
               bitácora tumbe la operación que se estaba registrando sería
               absurdo. */
            'mensaje'     => mb_substr($mensaje, 0, 255),
            'origen'      => $datos['origen'] ?? 'api',
            'dispositivo' => $datos['dispositivo'] ?? null,
            'uuid_local'  => $datos['uuid_local'] ?? null,
            'usuario_id'  => $datos['usuario_id'] ?? null,
            'pedido_id'   => $datos['pedido_id'] ?? null,
            'contexto'    => $datos['contexto'] ?? null,
        ]);
    }
}
