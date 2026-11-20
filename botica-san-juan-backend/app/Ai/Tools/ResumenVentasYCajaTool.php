<?php

namespace App\Ai\Tools;

use App\Models\Pedido;
use App\Models\Usuario;
use App\Services\CajaService;
use App\Services\ResumenTableroService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Herramienta interna (solo farmacéutico/admin) que resume ventas del día/semana
 * y el estado actual de la caja del usuario autenticado (Bloques B y C).
 */
class ResumenVentasYCajaTool implements Tool
{
    public function __construct(
        private readonly Usuario $usuario,
        private readonly ResumenTableroService $tablero,
        private readonly CajaService $caja,
    ) {
    }

    public function description(): Stringable|string
    {
        return 'Consulta el resumen de ventas de hoy, comparativa de los últimos 7 días vs semana anterior, métodos de pago y estado de la sesión de caja abierta del usuario.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! in_array($this->usuario->rol, ['administrador', 'farmaceutico'], true)) {
            return 'No autorizado: los reportes de ventas y caja son exclusivos del personal de botica.';
        }

        $hoy = Carbon::today();
        $ventasHoy = Pedido::query()
            ->whereDate('fecha', $hoy)
            ->where('estado', '!=', 'anulado')
            ->selectRaw('COALESCE(SUM(total), 0) AS importe, COUNT(*) AS comprobantes')
            ->first();

        $sesionCaja = $this->caja->sesionAbierta($this->usuario->id);

        return json_encode([
            'ventas_hoy' => [
                'importe'      => round((float) ($ventasHoy->importe ?? 0), 2),
                'comprobantes' => (int) ($ventasHoy->comprobantes ?? 0),
            ],
            'comparativa_semanal' => $this->tablero->comparativaSemanal($hoy),
            'metodos_pago'        => $this->tablero->distribucionMetodosPago(30),
            'caja_actual'         => $sesionCaja ? [
                'estado' => $sesionCaja->estado,
                'arqueo' => $this->caja->calcularArqueo($sesionCaja),
            ] : null,
        ], JSON_UNESCAPED_UNICODE);
    }
}
