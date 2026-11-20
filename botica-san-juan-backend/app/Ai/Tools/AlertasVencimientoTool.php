<?php

namespace App\Ai\Tools;

use App\Models\Usuario;
use App\Services\AlertaVencimientoService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Herramienta interna (solo farmacéutico/admin) que delega en
 * AlertaVencimientoService (Bloque A) para resumir lotes próximos a vencer.
 */
class AlertasVencimientoTool implements Tool
{
    public function __construct(
        private readonly Usuario $usuario,
        private readonly AlertaVencimientoService $alertas,
    ) {
    }

    public function description(): Stringable|string
    {
        return 'Obtiene el resumen de lotes por ventana de vencimiento (vencido, <=30, <=60, <=90, vigente) y el valor en riesgo a 90 días.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'bucket' => $schema->string()->description('Filtro opcional de ventana: vencido, <=30, <=60, <=90 o vigente.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! in_array($this->usuario->rol, ['administrador', 'farmaceutico'], true)) {
            return 'No autorizado: las alertas de vencimiento y valor en riesgo son exclusivas del personal de botica.';
        }

        $bucket = $request['bucket'] ?? null;
        if ($bucket !== null && ! in_array($bucket, AlertaVencimientoService::BUCKETS_VALIDOS, true)) {
            $bucket = null;
        }

        $datos = $this->alertas->obtenerAlertas($bucket);

        return json_encode([
            'resumen' => $datos['resumen'],
            'lotes'   => array_slice($datos['lotes'], 0, 15),
        ], JSON_UNESCAPED_UNICODE);
    }
}
