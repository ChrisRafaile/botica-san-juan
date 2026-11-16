<?php

namespace App\Http\Middleware;

use App\Models\ClaveIdempotencia;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class Idempotencia
{
    /**
     * Maneja la verificación y aplicación de idempotencia sobre peticiones HTTP.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clave = $request->header('Idempotency-Key');

        // Si no viene cabecera, pasa de largo para mantener compatibilidad
        if ($clave === null || trim($clave) === '') {
            return $next($request);
        }

        $clave = trim($clave);

        // Validación de formato: 8-120 caracteres permitidos [A-Za-z0-9_.:-]
        if (preg_match('/^[A-Za-z0-9_.:-]{8,120}$/', $clave) !== 1) {
            return response()->json([
                'message' => 'La clave de idempotencia debe contener entre 8 y 120 caracteres alfanuméricos o los signos _ . : -',
            ], 400);
        }

        // Actor: u:{id} si autenticado (Sanctum), o ip:{ip} si es anónimo
        $usuario = auth('sanctum')->user() ?? $request->user();
        $actor = $usuario ? "u:{$usuario->id}" : 'ip:' . $request->ip();

        // Alcance: método HTTP + plantilla de la ruta
        $ruta = $request->route();
        $plantillaRuta = $ruta ? $ruta->uri() : $request->path();
        $alcance = $request->method() . ' ' . $plantillaRuta;

        // Huella: sha256(método + plantilla + json canónico con claves ordenadas)
        $huella = hash('sha256', $request->method() . '|' . $plantillaRuta . '|' . $this->canonizarJson($request->all()));

        // Intentar registrar la clave como 'en_curso' dentro de un SAVEPOINT propio.
        // En PostgreSQL una violación de unicidad abortaría la transacción entera si no usa SAVEPOINT.
        $registro = null;

        try {
            $registro = DB::transaction(fn () => ClaveIdempotencia::create([
                'clave'            => $clave,
                'alcance'          => $alcance,
                'actor'            => $actor,
                'huella_peticion'  => $huella,
                'estado'           => 'en_curso',
                'expira_en'        => now()->addHours(24),
            ]));
        } catch (UniqueConstraintViolationException) {
            // Ya existía la clave para este (clave, alcance, actor)
            $existente = ClaveIdempotencia::where('clave', $clave)
                ->where('alcance', $alcance)
                ->where('actor', $actor)
                ->first();

            if ($existente === null) {
                // Caso extremo de concurrencia: si otro hilo abortó, pasa a reintentar
                return response()->json(['message' => 'Conflicto temporal. Por favor reintente.'], 409)
                    ->header('Retry-After', '1');
            }

            // Huella distinta: conflicto de parámetros
            if ($existente->huella_peticion !== $huella) {
                return response()->json([
                    'message' => 'Conflicto de idempotencia: la clave fue enviada previamente con otro contenido de petición.',
                    'codigo'  => 'idempotencia_conflicto',
                ], 422);
            }

            // Petición aún en curso
            if ($existente->estado === 'en_curso') {
                return response()->json([
                    'message' => 'Petición en curso. Por favor espere.',
                ], 409)->header('Retry-After', '1');
            }

            // Petición completada: reproducir respuesta original sin llamar al controlador
            $cuerpo = $existente->cuerpo_respuesta;
            $datos = json_decode((string) $cuerpo, true);

            $respuestaReplay = $datos !== null
                ? response()->json($datos, $existente->codigo_respuesta ?: 200)
                : response((string) $cuerpo, $existente->codigo_respuesta ?: 200, ['Content-Type' => 'application/json']);

            return $respuestaReplay->header('Idempotency-Replayed', 'true');
        }

        // Ejecutar el controlador
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Si hubo excepción, no hubo respuesta 2xx exitosa: borrar clave para reintento limpio
            $registro?->delete();
            throw $e;
        }

        // Solo se persisten respuestas exitosas 2xx.
        // Cualquier error (4xx, 5xx) borra la clave porque no dejó efectos transaccionales
        if ($response->isSuccessful()) {
            $registro->update([
                'estado'           => 'completada',
                'codigo_respuesta' => $response->getStatusCode(),
                'cuerpo_respuesta' => $response->getContent(),
            ]);
        } else {
            $registro->delete();
        }

        return $response;
    }

    /**
     * Ordena recursivamente las claves de un array para generar un JSON canónico determinista.
     */
    private function canonizarJson(mixed $data): string
    {
        if (! is_array($data)) {
            return (string) $data;
        }

        return (string) json_encode($this->ordenarRecursivo($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function ordenarRecursivo(array $arr): array
    {
        if (! array_is_list($arr)) {
            ksort($arr);
        }

        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $arr[$k] = $this->ordenarRecursivo($v);
            }
        }

        return $arr;
    }
}
