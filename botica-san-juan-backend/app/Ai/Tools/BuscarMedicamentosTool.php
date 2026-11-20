<?php

namespace App\Ai\Tools;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\Clinico\ConsultorDeInteracciones;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Herramienta de solo lectura para buscar medicamentos del catálogo público
 * (con unaccent) y verificar stock disponible e interacciones aprobadas.
 */
class BuscarMedicamentosTool implements Tool
{
    public function __construct(
        private readonly Usuario $usuario,
        private readonly ConsultorDeInteracciones $interacciones,
    ) {
    }

    public function description(): Stringable|string
    {
        return 'Busca medicamentos en el catálogo por nombre o principio activo, devuelve precio público y stock disponible (sin lotes ni costos internos), y opcionalmente evalúa interacciones con un segundo medicamento.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'termino'             => $schema->string()->description('Nombre o principio activo del medicamento a buscar (ej. amoxicilina).')->required(),
            'segundo_medicamento' => $schema->string()->description('Nombre opcional de un segundo medicamento para consultar posibles interacciones aprobadas.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! in_array($this->usuario->rol, ['cliente', 'farmaceutico', 'administrador'], true)) {
            return 'No autorizado para consultar el catálogo.';
        }

        $termino = trim((string) ($request['termino'] ?? ''));
        if (mb_strlen($termino) < 2) {
            return 'Ingresa al menos 2 caracteres para buscar medicamentos.';
        }

        $productos = Producto::query()
            ->select(['id', 'nombre', 'concentracion', 'presentacion', 'laboratorio', 'principio_activo', 'precio', 'requiere_receta'])
            ->buscar($termino, ['nombre', 'principio_activo'])
            ->orderBy('nombre')
            ->limit(8)
            ->get();

        if ($productos->isEmpty()) {
            return json_encode(['resultados' => [], 'mensaje' => 'No se encontraron medicamentos con ese término.'], JSON_UNESCAPED_UNICODE);
        }

        $disponibles = Lote::query()
            ->selectRaw('producto_id, SUM(cantidad_actual) AS total')
            ->whereIn('producto_id', $productos->pluck('id'))
            ->disponible()
            ->groupBy('producto_id')
            ->pluck('total', 'producto_id');

        $resultados = $productos->map(fn (Producto $p) => [
            'id'               => $p->id,
            'nombre'           => $p->nombre,
            'concentracion'    => $p->concentracion,
            'presentacion'     => $p->presentacion,
            'laboratorio'      => $p->laboratorio,
            'principio_activo' => $p->principio_activo,
            'precio'           => round((float) $p->precio, 2),
            'requiere_receta'  => (bool) $p->requiere_receta,
            'stock_disponible' => (int) ($disponibles[$p->id] ?? 0),
        ])->values()->all();

        $payload = ['resultados' => $resultados];

        $segundo = trim((string) ($request['segundo_medicamento'] ?? ''));
        if ($segundo !== '' && mb_strlen($segundo) >= 2) {
            $otros = Producto::query()
                ->select(['id'])
                ->buscar($segundo, ['nombre', 'principio_activo'])
                ->limit(3)
                ->get();

            $ids = $productos->take(2)->pluck('id')->merge($otros->pluck('id'))->unique()->values()->all();
            if (count($ids) >= 2) {
                $payload['interacciones'] = $this->interacciones->evaluar($ids);
            }
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
