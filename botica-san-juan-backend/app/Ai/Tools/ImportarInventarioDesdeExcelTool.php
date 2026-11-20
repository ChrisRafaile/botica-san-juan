<?php

namespace App\Ai\Tools;

use App\Models\Usuario;
use App\Services\Importador\ImportadorInventarioService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

/**
 * Herramienta de escritura segura (exclusiva para administrador) que confirma
 * una importación previamente previsualizada usando su token temporal de un
 * solo uso.
 */
class ImportarInventarioDesdeExcelTool implements Tool
{
    public function __construct(
        private readonly Usuario $usuario,
        private readonly ImportadorInventarioService $importador,
    ) {
    }

    public function description(): Stringable|string
    {
        return 'Confirma e importa productos y lotes desde una previsualización Excel/CSV previamente validada. Requiere el token_confirmacion de un solo uso y solo puede ser ejecutada por un administrador tras confirmación explícita.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'token_confirmacion' => $schema->string()
                ->description('Token temporal de confirmación generado al previsualizar el archivo Excel/CSV.')
                ->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if ($this->usuario->rol !== 'administrador') {
            return 'No autorizado: la importación de inventario es exclusiva del administrador.';
        }

        $token = trim((string) ($request['token_confirmacion'] ?? ''));
        if ($token === '') {
            return 'Se requiere un token_confirmacion válido para ejecutar la importación.';
        }

        try {
            $resultado = $this->importador->confirmar($this->usuario, $token, 'tool_ia');
        } catch (RuntimeException $e) {
            return 'No se pudo completar la importación: ' . $e->getMessage();
        }

        return json_encode([
            'estado' => 'confirmada',
            'resultado' => $resultado,
        ], JSON_UNESCAPED_UNICODE);
    }
}
