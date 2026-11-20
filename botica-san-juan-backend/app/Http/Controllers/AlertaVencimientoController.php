<?php

namespace App\Http\Controllers;

use App\Services\AlertaVencimientoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Alertas de vencimiento por lote para el panel administrativo.
 */
class AlertaVencimientoController extends Controller
{
    public function __construct(
        private readonly AlertaVencimientoService $alertas,
    ) {
    }

    /**
     * GET /api/inventario/vencimientos?bucket=<=30
     */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'bucket' => ['nullable', 'string', Rule::in(AlertaVencimientoService::BUCKETS_VALIDOS)],
        ]);

        return response()->json(
            $this->alertas->obtenerAlertas($datos['bucket'] ?? null)
        );
    }
}
