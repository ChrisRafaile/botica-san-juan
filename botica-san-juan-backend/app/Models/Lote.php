<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lote de un producto: la unidad real de inventario.
 *
 * Aquí vive el criterio FEFO ("first expired, first out") como un scope, para
 * que exista una sola definición de "qué lote sale primero" en todo el sistema
 * y no cada consulta invente la suya.
 */
class Lote extends Model
{
    protected $table = 'lotes';

    protected $fillable = [
        'producto_id',
        'codigo_lote',
        'fecha_vencimiento',
        'cantidad_inicial',
        'cantidad_actual',
        'costo_unitario',
        'compra_id',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'cantidad_inicial'  => 'integer',
        'cantidad_actual'   => 'integer',
        'costo_unitario'    => 'decimal:4',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    /* ---------------------------------------------------------------------
       Estado del lote
       --------------------------------------------------------------------- */

    public function estaVencido(): bool
    {
        if ($this->fecha_vencimiento === null) {
            return false;
        }

        return $this->fecha_vencimiento->isPast();
    }

    /** Días que faltan para vencer. null si el lote no tiene fecha. */
    public function diasParaVencer(): ?int
    {
        if ($this->fecha_vencimiento === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->fecha_vencimiento, false);
    }

    /**
     * Nivel de urgencia por vencimiento, alineado con los tokens de dominio
     * del design system (ventanas de 30, 60 y 90 días).
     */
    public function nivelVencimiento(): string
    {
        $dias = $this->diasParaVencer();

        if ($dias === null)  return 'sin_fecha';
        if ($dias < 0)       return 'vencido';
        if ($dias <= 30)     return 'critico';
        if ($dias <= 60)     return 'alto';
        if ($dias <= 90)     return 'medio';

        return 'vigente';
    }

    /* ---------------------------------------------------------------------
       Scopes
       --------------------------------------------------------------------- */

    /**
     * Lotes de los que SÍ se puede vender.
     *
     * Excluye tres cosas, y conviene entender por qué cada una:
     *
     *  · Los agotados y retirados, por razones obvias.
     *  · Los vencidos: FEFO aplicado a ciegas elegiría primero justamente el
     *    lote vencido, porque es el de fecha más próxima. Vender un
     *    medicamento vencido no es un error de inventario, es un riesgo
     *    sanitario. Se excluyen siempre, sin excepción configurable.
     *  · Los que vencen dentro del margen de seguridad configurado. Algunas
     *    boticas no despachan nada a menos de N días de vencer, porque el
     *    cliente puede no llegar a consumirlo. Se controla con
     *    `inventario.dias_minimos_venta`; por defecto 0, es decir, sin margen.
     */
    public function scopeDisponible(Builder $query): Builder
    {
        $margenDias = (int) config('inventario.dias_minimos_venta', 0);
        $fechaLimite = now()->startOfDay()->addDays($margenDias);

        return $query
            ->where('estado', 'activo')
            ->where('cantidad_actual', '>', 0)
            ->where(function (Builder $q) use ($fechaLimite) {
                $q->whereNull('fecha_vencimiento')
                  ->orWhere('fecha_vencimiento', '>', $fechaLimite);
            });
    }

    /**
     * Orden FEFO: primero lo que antes vence.
     *
     * Los lotes sin fecha van al final, no al principio. En PostgreSQL los
     * NULL ordenan last de forma natural en ASC, pero se deja explícito para
     * que la intención no dependa del motor: el stock heredado sin fecha debe
     * consumirse después del que sí tiene vencimiento conocido, no antes.
     *
     * El id como último criterio garantiza un orden estable: sin él, dos lotes
     * con la misma fecha podrían alternarse entre consultas y hacer que el
     * reparto por lotes no fuese reproducible.
     */
    public function scopeOrdenFefo(Builder $query): Builder
    {
        return $query
            ->orderByRaw('fecha_vencimiento ASC NULLS LAST')
            ->orderBy('id');
    }

    public function scopeDelProducto(Builder $query, int $productoId): Builder
    {
        return $query->where('producto_id', $productoId);
    }

    /** Lotes que vencen dentro de N días, para el panel de alertas. */
    public function scopeVenceEn(Builder $query, int $dias): Builder
    {
        return $query
            ->where('estado', 'activo')
            ->where('cantidad_actual', '>', 0)
            ->whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [
                now()->startOfDay(),
                now()->startOfDay()->addDays($dias),
            ]);
    }
}
