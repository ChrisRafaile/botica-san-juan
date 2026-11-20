<?php

namespace App\Services;

use App\Ai\Agents\AsistenteBotica;
use App\Models\Usuario;
use RuntimeException;

/**
 * Orquesta la disponibilidad, los proveedores gratuitos con respaldo ordenado y
 * la invocación segura del agente AsistenteBotica.
 *
 * Si no existe ninguna clave de proveedor configurada, evita cargar/invocar el
 * SDK de IA y permite responder 503 de forma controlada.
 */
class AsistenteService
{
    public function estaDisponible(): bool
    {
        if (! config('asistente.habilitado', true)) {
            return false;
        }

        return $this->proveedoresConfigurados() !== [];
    }

    /**
     * Devuelve el mapa [proveedor => modelo] ordenado con el primario primero y
     * el fallback después, incluyendo solo proveedores con clave no vacía.
     *
     * @return array<string, string>
     */
    public function proveedoresConfigurados(): array
    {
        $primario = (string) config('asistente.proveedor_primario', 'gemini');
        $fallback = (array) config('asistente.fallback', ['groq', 'gemini', 'openrouter']);
        $orden = array_values(array_unique(array_merge([$primario], $fallback)));

        $definiciones = (array) config('asistente.proveedores', []);
        $activos = [];

        foreach ($orden as $nombre) {
            if (! isset($definiciones[$nombre])) {
                continue;
            }
            $clave = config("ai.providers.{$nombre}.key");
            if (is_string($clave) && trim($clave) !== '') {
                $activos[$nombre] = (string) $definiciones[$nombre]['modelo'];
            }
        }

        return $activos;
    }

    /**
     * Envía el mensaje del usuario al agente usando los proveedores configurados.
     *
     * @param  array<int, array{role: string, content: string}>  $historial
     */
    public function responder(Usuario $usuario, string $mensaje, array $historial = []): string
    {
        $proveedores = $this->proveedoresConfigurados();

        if (! config('asistente.habilitado', true) || $proveedores === []) {
            throw new RuntimeException('El asistente inteligente no está disponible porque no hay proveedor de IA configurado.');
        }

        $maxMensajes = max(1, (int) config('asistente.limites.max_mensajes', 12));
        $maxLongitud = max(50, (int) config('asistente.limites.max_longitud', 1000));
        $timeout = max(5, (int) config('asistente.limites.timeout', 25));

        $historialAcotado = array_slice($historial, -($maxMensajes - 1));
        $historialSeguro = array_map(fn (array $m) => [
            'role'    => $m['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => mb_substr(trim((string) $m['content']), 0, $maxLongitud),
        ], $historialAcotado);

        $agente = new AsistenteBotica($usuario, $historialSeguro);

        $respuesta = $agente->prompt(
            prompt: mb_substr(trim($mensaje), 0, $maxLongitud),
            provider: $proveedores,
            timeout: $timeout,
        );

        return (string) $respuesta;
    }
}
