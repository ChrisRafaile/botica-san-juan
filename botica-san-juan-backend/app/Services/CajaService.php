<?php

namespace App\Services;

use App\Models\MovimientoCaja;
use App\Models\Pedido;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Control de caja por turno de cajero.
 *
 * Serializa apertura y cierre mediante bloqueos pesimistas (lockForUpdate)
 * sobre el usuario y la sesión para impedir dobles aperturas o cierres
 * concurrentes sin depender únicamente del índice único en PostgreSQL.
 */
class CajaService
{
    public function sesionAbierta(int $usuarioId): ?SesionCaja
    {
        return SesionCaja::query()
            ->with(['usuario:id,nombre,email', 'movimientos' => fn ($q) => $q->latest('id')])
            ->where('usuario_id', $usuarioId)
            ->where('estado', SesionCaja::ESTADO_ABIERTA)
            ->first();
    }

    public function abrir(int $usuarioId, float $montoInicial, ?string $observacion = null): SesionCaja
    {
        if ($montoInicial < 0) {
            throw new RuntimeException('El monto inicial no puede ser negativo.');
        }

        return DB::transaction(function () use ($usuarioId, $montoInicial, $observacion) {
            // Bloquea la fila del cajero para serializar peticiones simultáneas
            // incluso cuando aún no existe ninguna sesión abierta en sesiones_caja.
            Usuario::query()->where('id', $usuarioId)->lockForUpdate()->firstOrFail();

            $existente = SesionCaja::query()
                ->where('usuario_id', $usuarioId)
                ->where('estado', SesionCaja::ESTADO_ABIERTA)
                ->lockForUpdate()
                ->first();

            if ($existente !== null) {
                throw new RuntimeException('Ya tienes una sesión de caja abierta.');
            }

            return SesionCaja::create([
                'usuario_id'           => $usuarioId,
                'estado'               => SesionCaja::ESTADO_ABIERTA,
                'monto_inicial'        => round($montoInicial, 2),
                'observacion_apertura' => $observacion ? trim($observacion) : null,
                'abierta_en'           => now(),
            ])->load(['usuario:id,nombre,email', 'movimientos']);
        });
    }

    public function registrarMovimientoManual(
        int $usuarioId,
        string $tipo,
        float $monto,
        string $motivo,
        ?string $referencia = null,
    ): MovimientoCaja {
        if (! in_array($tipo, MovimientoCaja::TIPOS_MANUALES, true)) {
            throw new RuntimeException('Tipo de movimiento de caja no válido.');
        }
        if ($monto <= 0) {
            throw new RuntimeException('El monto del movimiento debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($usuarioId, $tipo, $monto, $motivo, $referencia) {
            $sesion = SesionCaja::query()
                ->where('usuario_id', $usuarioId)
                ->where('estado', SesionCaja::ESTADO_ABIERTA)
                ->lockForUpdate()
                ->first();

            if ($sesion === null) {
                throw new RuntimeException('No hay una sesión de caja abierta para registrar el movimiento.');
            }

            $movimiento = MovimientoCaja::create([
                'sesion_caja_id' => $sesion->id,
                'usuario_id'     => $usuarioId,
                'tipo'           => $tipo,
                'monto'          => round($monto, 2),
                'motivo'         => trim($motivo),
                'referencia'     => $referencia ? trim($referencia) : null,
            ]);

            $this->sincronizarTotalesSesion($sesion);

            return $movimiento;
        });
    }

    /**
     * Vincula el efectivo cobrado en una venta POS a la sesión abierta del
     * vendedor. Si no tiene caja abierta, retorna null sin interrumpir la venta.
     */
    public function vincularVentaEfectivo(int $usuarioId, Pedido $pedido): ?MovimientoCaja
    {
        $pedido->loadMissing('pagos');

        $montoEfectivo = round(
            (float) $pedido->pagos->where('medio', 'efectivo')->sum('monto'),
            2
        );

        if ($montoEfectivo <= 0) {
            return null;
        }

        return DB::transaction(function () use ($usuarioId, $pedido, $montoEfectivo) {
            $sesion = SesionCaja::query()
                ->where('usuario_id', $usuarioId)
                ->where('estado', SesionCaja::ESTADO_ABIERTA)
                ->lockForUpdate()
                ->first();

            if ($sesion === null) {
                return null;
            }

            $existente = MovimientoCaja::query()
                ->where('sesion_caja_id', $sesion->id)
                ->where('pedido_id', $pedido->id)
                ->first();

            if ($existente !== null) {
                return $existente;
            }

            $movimiento = MovimientoCaja::create([
                'sesion_caja_id' => $sesion->id,
                'usuario_id'     => $usuarioId,
                'pedido_id'      => $pedido->id,
                'tipo'           => MovimientoCaja::TIPO_VENTA_EFECTIVO,
                'monto'          => $montoEfectivo,
                'motivo'         => "Venta mostrador #{$pedido->id}",
                'referencia'     => $pedido->numero_orden ?? (string) $pedido->id,
            ]);

            $this->sincronizarTotalesSesion($sesion);

            return $movimiento;
        });
    }

    /**
     * @return array{
     *   monto_inicial: float,
     *   ventas_efectivo: float,
     *   ingresos: float,
     *   egresos: float,
     *   monto_esperado: float,
     *   movimientos_count: int
     * }
     */
    public function calcularArqueo(SesionCaja $sesion): array
    {
        $sumas = MovimientoCaja::query()
            ->where('sesion_caja_id', $sesion->id)
            ->selectRaw("
                COUNT(*) AS total_movs,
                COALESCE(SUM(CASE WHEN tipo = 'venta_efectivo' THEN monto ELSE 0 END), 0) AS ventas_efectivo,
                COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) AS ingresos,
                COALESCE(SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END), 0) AS egresos
            ")
            ->first();

        $inicial = round((float) $sesion->monto_inicial, 2);
        $ventasEfectivo = round((float) ($sumas->ventas_efectivo ?? 0), 2);
        $ingresos = round((float) ($sumas->ingresos ?? 0), 2);
        $egresos = round((float) ($sumas->egresos ?? 0), 2);
        $esperado = round($inicial + $ventasEfectivo + $ingresos - $egresos, 2);

        return [
            'monto_inicial'     => $inicial,
            'ventas_efectivo'   => $ventasEfectivo,
            'ingresos'          => $ingresos,
            'egresos'           => $egresos,
            'monto_esperado'    => $esperado,
            'movimientos_count' => (int) ($sumas->total_movs ?? 0),
        ];
    }

    public function cerrar(
        int $sesionId,
        int $usuarioId,
        float $montoContado,
        ?string $observacion = null,
    ): SesionCaja {
        if ($montoContado < 0) {
            throw new RuntimeException('El monto contado no puede ser negativo.');
        }

        return DB::transaction(function () use ($sesionId, $usuarioId, $montoContado, $observacion) {
            $sesion = SesionCaja::query()
                ->where('id', $sesionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sesion->usuario_id !== $usuarioId) {
                throw new RuntimeException('Solo el cajero propietario de la sesión puede cerrarla.');
            }

            if ($sesion->estado === SesionCaja::ESTADO_CERRADA) {
                throw new RuntimeException('La sesión de caja ya fue cerrada.');
            }

            $arqueo = $this->calcularArqueo($sesion);
            $contado = round($montoContado, 2);
            $diferencia = round($contado - $arqueo['monto_esperado'], 2);

            $sesion->update([
                'estado'             => SesionCaja::ESTADO_CERRADA,
                'ventas_efectivo'    => $arqueo['ventas_efectivo'],
                'total_ingresos'     => $arqueo['ingresos'],
                'total_egresos'      => $arqueo['egresos'],
                'monto_esperado'     => $arqueo['monto_esperado'],
                'monto_contado'      => $contado,
                'diferencia'         => $diferencia,
                'observacion_cierre' => $observacion ? trim($observacion) : null,
                'cerrada_en'         => now(),
            ]);

            return $sesion->fresh(['usuario:id,nombre,email', 'movimientos']);
        });
    }

    public function historial(?int $usuarioId = null, int $limite = 30): array
    {
        $query = SesionCaja::query()
            ->with('usuario:id,nombre,email')
            ->orderByDesc('abierta_en')
            ->orderByDesc('id');

        if ($usuarioId !== null) {
            $query->where('usuario_id', $usuarioId);
        }

        $sesiones = $query->limit($limite)->get();

        $cerradas = $sesiones->where('estado', SesionCaja::ESTADO_CERRADA);

        return [
            'kpis' => [
                'total_sesiones'        => $sesiones->count(),
                'total_ventas_efectivo' => round((float) $sesiones->sum('ventas_efectivo'), 2),
                'diferencia_acumulada'  => round((float) $cerradas->sum('diferencia'), 2),
            ],
            'sesiones' => $sesiones->map(fn (SesionCaja $s) => [
                'id'                   => $s->id,
                'usuario_id'           => $s->usuario_id,
                'cajero'               => $s->usuario?->nombre,
                'estado'               => $s->estado,
                'monto_inicial'        => (float) $s->monto_inicial,
                'ventas_efectivo'      => (float) $s->ventas_efectivo,
                'total_ingresos'       => (float) $s->total_ingresos,
                'total_egresos'        => (float) $s->total_egresos,
                'monto_esperado'       => $s->monto_esperado !== null ? (float) $s->monto_esperado : null,
                'monto_contado'        => $s->monto_contado !== null ? (float) $s->monto_contado : null,
                'diferencia'           => $s->diferencia !== null ? (float) $s->diferencia : null,
                'observacion_apertura' => $s->observacion_apertura,
                'observacion_cierre'   => $s->observacion_cierre,
                'abierta_en'           => $s->abierta_en?->toIso8601String(),
                'cerrada_en'           => $s->cerrada_en?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function sincronizarTotalesSesion(SesionCaja $sesion): void
    {
        $arqueo = $this->calcularArqueo($sesion);
        $sesion->update([
            'ventas_efectivo' => $arqueo['ventas_efectivo'],
            'total_ingresos'  => $arqueo['ingresos'],
            'total_egresos'   => $arqueo['egresos'],
        ]);
    }
}
