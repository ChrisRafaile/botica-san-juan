<?php

namespace App\Http\Controllers;

use App\Services\Importador\ImportadorInventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Endpoints de importación asistida de inventario desde Excel/CSV.
 *
 * Protegidos exclusivamente para rol `administrador`. Ninguna escritura ocurre
 * en la previsualización; la confirmación exige un token temporal de un solo
 * uso ligado al usuario que subió el archivo.
 */
class AsistenteImportacionController extends Controller
{
    public function __construct(
        private readonly ImportadorInventarioService $importador,
    ) {
    }

    /**
     * POST /api/asistente/importaciones/previsualizar
     */
    public function previsualizar(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:5120'],
        ]);

        $resultado = $this->importador->previsualizar(
            $request->file('archivo'),
            $request->user(),
        );

        return response()->json($resultado);
    }

    /**
     * POST /api/asistente/importaciones/confirmar
     */
    public function confirmar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'token_confirmacion' => ['required', 'string', 'max:100'],
        ]);

        try {
            $resultado = $this->importador->confirmar(
                $request->user(),
                $datos['token_confirmacion'],
                'api',
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Importación confirmada y registrada en la bitácora.',
            'resultado' => $resultado,
        ]);
    }

    /**
     * POST /api/asistente/importaciones/cancelar
     */
    public function cancelar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'token_confirmacion' => ['required', 'string', 'max:100'],
        ]);

        $cancelado = $this->importador->cancelar(
            $request->user(),
            $datos['token_confirmacion'],
        );

        return response()->json([
            'cancelado' => $cancelado,
        ]);
    }
}
