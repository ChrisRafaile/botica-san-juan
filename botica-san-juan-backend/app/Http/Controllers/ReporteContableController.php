<?php

namespace App\Http\Controllers;

use App\Services\ReporteContableService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Registro de ventas para el contador.
 *
 * Reemplaza el Excel que hoy se llena a mano cada noche.
 */
class ReporteContableController extends Controller
{
    public function __construct(private readonly ReporteContableService $reportes)
    {
    }

    /**
     * GET /api/reportes/registro-ventas?desde=&hasta=
     *
     * Devuelve las filas y un resumen para mostrarlas en pantalla antes de
     * descargar. Que el dueño pueda revisar el mes antes de enviarlo evita el
     * viaje de ida y vuelta con el contador.
     */
    public function index(Request $peticion): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($peticion);

        $resultado = $this->reportes->generar(
            $desde,
            $hasta,
            $peticion->boolean('columnas_heredadas'),
        );

        return response()->json([
            'periodo' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
            ],
            'resumen' => $resultado['resumen'],
            'avisos'  => $resultado['avisos'],
            'data'    => $resultado['filas'],
        ]);
    }

    /**
     * GET /api/reportes/registro-ventas/csv?desde=&hasta=
     *
     * Se envía como flujo y no se arma en memoria: un año de ventas de la
     * botica son unos doce mil comprobantes, y construir esa cadena entera
     * antes de empezar a responder gasta memoria sin necesidad.
     */
    public function csv(Request $peticion): StreamedResponse
    {
        [$desde, $hasta] = $this->periodo($peticion);

        $resultado = $this->reportes->generar(
            $desde,
            $hasta,
            $peticion->boolean('columnas_heredadas'),
        );

        $nombre = sprintf(
            'registro-ventas-%s-al-%s.csv',
            $desde->format('Y-m-d'),
            $hasta->format('Y-m-d'),
        );

        return response()->streamDownload(
            function () use ($resultado) {
                echo $this->reportes->aCsv($resultado['filas']);
            },
            $nombre,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * Resuelve el periodo pedido.
     *
     * Sin parámetros se toma el mes anterior completo, que es cuando de verdad
     * se usa este reporte: el contador lo pide a principios de mes por el mes
     * que acaba de cerrar.
     */
    private function periodo(Request $peticion): array
    {
        $datos = $peticion->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        if (empty($datos['desde'])) {
            $mesAnterior = Carbon::now()->subMonthNoOverflow();

            return [$mesAnterior->copy()->startOfMonth(), $mesAnterior->copy()->endOfMonth()];
        }

        $desde = Carbon::parse($datos['desde']);
        $hasta = isset($datos['hasta'])
            ? Carbon::parse($datos['hasta'])
            : $desde->copy()->endOfMonth();

        return [$desde, $hasta];
    }
}
