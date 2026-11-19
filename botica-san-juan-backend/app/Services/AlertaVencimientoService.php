<?php

namespace App\Services;

use App\Models\Lote;
use Illuminate\Support\Collection;

/**
 * Diagnóstico de vencimientos por lote (regla FEFO y semáforo 30/60/90d).
 *
 * Centraliza los buckets de urgencia y el valor en riesgo para que la vista
 * de inventario, el POS y el asistente IA lean exactamente el mismo cálculo.
 */
class AlertaVencimientoService
{
    public const BUCKETS_VALIDOS = ['vencido', '<=30', '<=60', '<=90', 'vigente'];

    /**
     * Devuelve el resumen por bucket, el valor en riesgo a 90 días y los lotes
     * (filtrados opcionalmente por bucket).
     *
     * @return array{
     *   resumen: array{
     *     total: int,
     *     vencido: int,
     *     '<=30': int,
     *     '<=60': int,
     *     '<=90': int,
     *     vigente: int,
     *     valor_en_riesgo_90d: float,
     *     valor_vencido: float
     *   },
     *   lotes: array<int, array<string, mixed>>
     * }
     */
    public function obtenerAlertas(?string $bucket = null): array
    {
        // Se excluyen únicamente los lotes agotados o retirados; los vencidos
        // con stock físico remanente deben seguir visibles para su retiro.
        $lotes = Lote::query()
            ->with('producto:id,nombre,concentracion,presentacion,laboratorio')
            ->where('cantidad_actual', '>', 0)
            ->whereIn('estado', ['activo', 'vencido'])
            ->orderByRaw('fecha_vencimiento ASC NULLS LAST')
            ->orderBy('id')
            ->get();

        $conteos = [
            'total'   => 0,
            'vencido' => 0,
            '<=30'    => 0,
            '<=60'    => 0,
            '<=90'    => 0,
            'vigente' => 0,
        ];
        $valorEnRiesgo90d = 0.0;
        $valorVencido = 0.0;

        $transformados = $lotes->map(function (Lote $lote) use (&$conteos, &$valorEnRiesgo90d, &$valorVencido) {
            $dias = $lote->diasParaVencer();
            $bucketLote = $this->clasificarBucket($dias, $lote->estado);
            $valor = round($lote->cantidad_actual * (float) $lote->costo_unitario, 2);

            $conteos['total']++;
            $conteos[$bucketLote]++;

            if ($bucketLote === 'vencido') {
                $valorVencido += $valor;
            } elseif (in_array($bucketLote, ['<=30', '<=60', '<=90'], true)) {
                $valorEnRiesgo90d += $valor;
            }

            return [
                'id'                => $lote->id,
                'producto_id'       => $lote->producto_id,
                'codigo_lote'       => $lote->codigo_lote,
                'fecha_vencimiento' => $lote->fecha_vencimiento?->toDateString(),
                'dias_para_vencer'  => $dias,
                'bucket'            => $bucketLote,
                'nivel_vencimiento' => $bucketLote === 'vencido' ? 'vencido' : $lote->nivelVencimiento(),
                'cantidad_actual'   => (int) $lote->cantidad_actual,
                'costo_unitario'    => round((float) $lote->costo_unitario, 4),
                'valor_en_riesgo'   => $valor,
                'estado'            => $lote->estado,
                'producto'          => $lote->producto ? [
                    'id'            => $lote->producto->id,
                    'nombre'        => $lote->producto->nombre,
                    'concentracion' => $lote->producto->concentracion,
                    'presentacion'  => $lote->producto->presentacion,
                    'laboratorio'   => $lote->producto->laboratorio,
                ] : null,
            ];
        });

        $filtrados = $this->filtrarPorBucket($transformados, $bucket);

        return [
            'resumen' => [
                ...$conteos,
                'valor_en_riesgo_90d' => round($valorEnRiesgo90d, 2),
                'valor_vencido'       => round($valorVencido, 2),
            ],
            'lotes' => $filtrados->values()->all(),
        ];
    }

    private function clasificarBucket(?int $dias, string $estado): string
    {
        if ($estado === 'vencido' || ($dias !== null && $dias < 0)) {
            return 'vencido';
        }
        if ($dias === null || $dias > 90) {
            return 'vigente';
        }
        if ($dias <= 30) {
            return '<=30';
        }
        if ($dias <= 60) {
            return '<=60';
        }

        return '<=90';
    }

    private function filtrarPorBucket(Collection $lotes, ?string $bucket): Collection
    {
        if ($bucket === null || $bucket === '' || !in_array($bucket, self::BUCKETS_VALIDOS, true)) {
            return $lotes;
        }

        return $lotes->where('bucket', $bucket);
    }
}
