<?php

namespace App\Ai\Tools;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Herramienta interna (solo farmacéutico/admin) para consultar lotes FEFO,
 * fechas de vencimiento y costos unitarios de un producto.
 */
class ConsultarStockYLotesFefoTool implements Tool
{
    public function __construct(
        private readonly Usuario $usuario,
    ) {
    }

    public function description(): Stringable|string
    {
        return 'Consulta el stock real y el desglose de lotes ordenados por FEFO (código de lote, fecha de vencimiento, días para vencer, cantidad actual y costo unitario). Solo para personal autorizado.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'termino' => $schema->string()->description('Nombre o principio activo del medicamento.')->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! in_array($this->usuario->rol, ['administrador', 'farmaceutico'], true)) {
            return 'No autorizado: el detalle interno de lotes y costos es exclusivo del personal de botica.';
        }

        $termino = trim((string) ($request['termino'] ?? ''));
        if (mb_strlen($termino) < 2) {
            return 'Ingresa al menos 2 caracteres para consultar lotes.';
        }

        $productos = Producto::query()
            ->select(['id', 'nombre', 'concentracion', 'presentacion', 'stock_minimo'])
            ->buscar($termino, ['nombre', 'principio_activo'])
            ->orderBy('nombre')
            ->limit(5)
            ->get();

        if ($productos->isEmpty()) {
            return json_encode(['productos' => [], 'mensaje' => 'No se encontraron productos.'], JSON_UNESCAPED_UNICODE);
        }

        $lotesPorProducto = Lote::query()
            ->whereIn('producto_id', $productos->pluck('id'))
            ->where('cantidad_actual', '>', 0)
            ->whereIn('estado', ['activo', 'vencido'])
            ->ordenFefo()
            ->get()
            ->groupBy('producto_id');

        $salida = $productos->map(function (Producto $p) use ($lotesPorProducto) {
            $lotes = ($lotesPorProducto->get($p->id) ?? collect())->map(fn (Lote $l) => [
                'codigo_lote'       => $l->codigo_lote,
                'fecha_vencimiento' => $l->fecha_vencimiento?->toDateString(),
                'dias_para_vencer'  => $l->diasParaVencer(),
                'nivel_vencimiento' => $l->nivelVencimiento(),
                'cantidad_actual'   => (int) $l->cantidad_actual,
                'costo_unitario'    => round((float) $l->costo_unitario, 4),
                'estado'            => $l->estado,
            ])->values()->all();

            return [
                'id'           => $p->id,
                'nombre'       => $p->nombre,
                'concentracion'=> $p->concentracion,
                'stock_minimo' => (int) ($p->stock_minimo ?? 5),
                'lotes_fefo'   => $lotes,
            ];
        })->values()->all();

        return json_encode(['productos' => $salida], JSON_UNESCAPED_UNICODE);
    }
}
