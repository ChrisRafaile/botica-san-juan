<?php

namespace App\Http\Controllers;

use App\Models\MovimientoCaja;
use App\Models\SesionCaja;
use App\Services\CajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Control de caja diario para el mostrador (apertura, movimientos, arqueo,
 * cierre e historial de sesiones).
 */
class CajaController extends Controller
{
    public function __construct(
        private readonly CajaService $caja,
    ) {
    }

    public function actual(Request $request): JsonResponse
    {
        $sesion = $this->caja->sesionAbierta($request->user()->id);

        if ($sesion === null) {
            return response()->json([
                'sesion' => null,
                'arqueo' => null,
            ]);
        }

        return response()->json([
            'sesion' => $this->formatearSesion($sesion),
            'arqueo' => $this->caja->calcularArqueo($sesion),
        ]);
    }

    public function abrir(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'monto_inicial'        => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'observacion_apertura' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $sesion = $this->caja->abrir(
                $request->user()->id,
                (float) $datos['monto_inicial'],
                $datos['observacion_apertura'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Sesión de caja abierta.',
            'sesion'  => $this->formatearSesion($sesion),
            'arqueo'  => $this->caja->calcularArqueo($sesion),
        ], 201);
    }

    public function registrarMovimiento(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo'       => ['required', Rule::in(MovimientoCaja::TIPOS_MANUALES)],
            'monto'      => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'motivo'     => ['required', 'string', 'min:3', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $movimiento = $this->caja->registrarMovimientoManual(
                $request->user()->id,
                $datos['tipo'],
                (float) $datos['monto'],
                $datos['motivo'],
                $datos['referencia'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $sesion = $this->caja->sesionAbierta($request->user()->id);

        return response()->json([
            'message'    => 'Movimiento registrado.',
            'movimiento' => $this->formatearMovimiento($movimiento),
            'arqueo'     => $sesion ? $this->caja->calcularArqueo($sesion) : null,
        ], 201);
    }

    public function cerrar(Request $request, SesionCaja $sesion): JsonResponse
    {
        $datos = $request->validate([
            'monto_contado'      => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'observacion_cierre' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $cerrada = $this->caja->cerrar(
                $sesion->id,
                $request->user()->id,
                (float) $datos['monto_contado'],
                $datos['observacion_cierre'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Sesión de caja cerrada.',
            'sesion'  => $this->formatearSesion($cerrada),
        ]);
    }

    public function historial(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'limite' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->caja->historial(null, (int) ($datos['limite'] ?? 30))
        );
    }

    private function formatearSesion(SesionCaja $s): array
    {
        return [
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
            'movimientos'          => $s->relationLoaded('movimientos')
                ? $s->movimientos->map(fn (MovimientoCaja $m) => $this->formatearMovimiento($m))->values()->all()
                : [],
        ];
    }

    private function formatearMovimiento(MovimientoCaja $m): array
    {
        return [
            'id'         => $m->id,
            'tipo'       => $m->tipo,
            'monto'      => (float) $m->monto,
            'motivo'     => $m->motivo,
            'referencia' => $m->referencia,
            'pedido_id'  => $m->pedido_id,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
