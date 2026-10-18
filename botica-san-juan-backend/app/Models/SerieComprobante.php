<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Contador de correlativo de una serie de comprobantes (B001, F001...).
 *
 * Existe para poder bloquear una fila que SIEMPRE esta ahi. Bloquear "la fila
 * mas alta de comprobantes" no sirve para el primer comprobante y obliga a
 * reintentar tras un choque contra UNIQUE(serie, numero).
 */
class SerieComprobante extends Model
{
    public const AMBIENTE_SIMULADO = 'simulado';

    protected $table = 'series_comprobante';

    protected $fillable = [
        'ambiente',
        'serie',
        'tipo_comprobante',
        'ultimo_numero',
    ];

    protected $casts = [
        'ultimo_numero' => 'integer',
    ];

    /**
     * Devuelve la fila de la serie bloqueada (`FOR UPDATE`) hasta que termine
     * la transaccion en curso. DEBE llamarse dentro de `DB::transaction`.
     *
     * Si la serie no existe todavia se crea, y es seguro bajo concurrencia:
     * `INSERT ... ON CONFLICT DO NOTHING` no falla ni aborta la transaccion si
     * otra caja la creo a la vez, y despues ambas bloquean la misma fila. Al
     * crearla se parte del maximo ya emitido en esa serie, no de cero.
     */
    public static function bloquear(string $serie, string $tipoComprobante, string $ambiente = self::AMBIENTE_SIMULADO): self
    {
        $existente = static::consulta($serie, $ambiente)->lockForUpdate()->first();
        if ($existente) {
            return $existente;
        }

        $maximo = (int) DB::table('comprobantes_electronicos')->where('serie', $serie)->max('numero');

        static::query()->insertOrIgnore([
            'ambiente'         => $ambiente,
            'serie'            => $serie,
            'tipo_comprobante' => $tipoComprobante,
            'ultimo_numero'    => $maximo,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return static::consulta($serie, $ambiente)->lockForUpdate()->firstOrFail();
    }

    private static function consulta(string $serie, string $ambiente)
    {
        return static::query()->where('ambiente', $ambiente)->where('serie', $serie);
    }
}
