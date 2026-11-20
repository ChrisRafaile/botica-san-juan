<?php

namespace App\Http\Controllers;

use App\Services\AsistenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Endpoints del asistente IA de botica.
 *
 * No registra en logs el contenido de las conversaciones ni credenciales.
 */
class AsistenteController extends Controller
{
    public function __construct(
        private readonly AsistenteService $asistente,
    ) {
    }

    /**
     * GET /api/asistente/estado
     */
    public function estado(Request $request): JsonResponse
    {
        $usuario = $request->user();

        return response()->json([
            'habilitado' => $this->asistente->estaDisponible(),
            'rol'        => $usuario?->rol ?? 'cliente',
        ]);
    }

    /**
     * POST /api/asistente/mensajes
     */
    public function responder(Request $request): JsonResponse
    {
        if (! $this->asistente->estaDisponible()) {
            return response()->json([
                'message' => 'El asistente inteligente está deshabilitado porque no hay proveedor de IA configurado.',
            ], 503);
        }

        $maxLongitud = (int) config('asistente.limites.max_longitud', 1000);
        $maxMensajes = (int) config('asistente.limites.max_mensajes', 12);

        $datos = $request->validate([
            'mensaje'            => ['required', 'string', 'min:2', "max:{$maxLongitud}"],
            'historial'          => ['nullable', 'array', "max:{$maxMensajes}"],
            'historial.*.role'   => ['required_with:historial', Rule::in(['user', 'assistant'])],
            'historial.*.content'=> ['required_with:historial', 'string', 'min:1', "max:{$maxLongitud}"],
        ]);

        try {
            $texto = $this->asistente->responder(
                $request->user(),
                $datos['mensaje'],
                $datos['historial'] ?? [],
            );
        } catch (Throwable $e) {
            // Solo la clase: el mensaje de un proveedor puede arrastrar la URL o
            // el contenido de la consulta. Sin esta línea, una clave sin saldo o
            // un modelo retirado se veían idénticos desde fuera.
            Log::warning('El proveedor de IA no respondió', ['excepcion' => $e::class]);

            // 502 y no 503: el cliente web trata 503 como «asistente deshabilitado»
            // y bloqueaba el campo de entrada tras un fallo pasajero del proveedor.
            return response()->json([
                'message' => 'El asistente no pudo responder en este momento. Inténtalo nuevamente en unos segundos.',
            ], 502);
        }

        return response()->json([
            'respuesta' => $texto,
        ]);
    }
}
