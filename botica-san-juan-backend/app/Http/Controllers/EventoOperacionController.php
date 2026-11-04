<?php

namespace App\Http\Controllers;

use App\Models\EventoOperacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bitácora de operación · consulta para el panel.
 * ---------------------------------------------------------------------------
 * POR QUÉ EXISTE ESTO
 *
 * La tabla `eventos_operacion` llevaba semanas recibiendo eventos de los tres
 * niveles y no había forma de verlos. Un registro que nadie puede consultar no
 * es gestión de eventos: es una tabla que crece.
 *
 * SOLO LECTURA, Y SOLO ADMINISTRACIÓN
 *
 * No hay forma de crear, editar ni borrar un evento desde aquí, y es
 * deliberado: una bitácora que se puede editar desde la interfaz deja de
 * servir como evidencia de lo que pasó. Los eventos los escribe el código que
 * los provoca, nunca una persona.
 *
 * La ruta exige sesión y rol de administrador. La bitácora enlaza usuarios con
 * pedidos y con dispositivos de caja: es un mapa de quién hizo qué.
 */
class EventoOperacionController extends Controller
{
    private const POR_PAGINA = 25;

    private const MAXIMO_POR_PAGINA = 100;

    /**
     * Listado con filtros.
     *
     * Los filtros son los que se usan de verdad cuando algo va mal: «enséñame
     * los errores», «enséñame qué pasó con este código», «enséñame el día del
     * incidente». No hay más, a propósito: un formulario con doce campos se
     * usa con dos.
     */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nivel' => ['nullable', Rule::in([
                EventoOperacion::NIVEL_INFO,
                EventoOperacion::NIVEL_AVISO,
                EventoOperacion::NIVEL_ERROR,
            ])],
            'codigo' => ['nullable', 'string', 'max:60'],
            'buscar' => ['nullable', 'string', 'max:120'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'origen' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAXIMO_POR_PAGINA],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $consulta = EventoOperacion::query();

        if (! blank($datos['nivel'] ?? null)) {
            $consulta->where('nivel', $datos['nivel']);
        }

        if (! blank($datos['codigo'] ?? null)) {
            $consulta->where('codigo', $datos['codigo']);
        }

        if (! blank($datos['origen'] ?? null)) {
            $consulta->where('origen', $datos['origen']);
        }

        if (! blank($datos['buscar'] ?? null)) {
            $termino = '%'.str_replace('%', '\%', $datos['buscar']).'%';

            $consulta->where(function ($q) use ($termino) {
                $q->where('mensaje', 'ILIKE', $termino)
                    ->orWhere('codigo', 'ILIKE', $termino);
            });
        }

        if (! blank($datos['desde'] ?? null)) {
            $consulta->where('created_at', '>=', \Carbon\Carbon::parse($datos['desde'])->startOfDay());
        }

        if (! blank($datos['hasta'] ?? null)) {
            /* Hasta el FINAL del día indicado. Comparar contra la fecha a
               secas excluye el propio día que se pidió, que es justo el error
               que hace pensar que no hubo eventos. */
            $consulta->where('created_at', '<=', \Carbon\Carbon::parse($datos['hasta'])->endOfDay());
        }

        $pagina = $consulta
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($datos['per_page'] ?? self::POR_PAGINA))
            ->withQueryString();

        return response()->json([
            'datos' => $pagina->getCollection()->map(fn (EventoOperacion $e) => $this->comoFila($e))->all(),
            'paginacion' => [
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
                'paginas' => $pagina->lastPage(),
            ],
        ]);
    }

    /**
     * Resumen para las pastillas de la cabecera y para el selector de códigos.
     *
     * Va aparte del listado porque responde a otra pregunta. El listado dice
     * «qué pasó»; esto dice «cuánto de cada cosa», que es lo que se mira
     * primero para saber dónde buscar.
     *
     * Los recuentos se calculan sobre la tabla ENTERA y no sobre la página
     * visible: un contador que cambia al pasar de página no sirve para nada.
     */
    public function resumen(): JsonResponse
    {
        $porNivel = EventoOperacion::query()
            ->selectRaw('nivel, count(*) as total')
            ->groupBy('nivel')
            ->pluck('total', 'nivel');

        $codigos = EventoOperacion::query()
            ->selectRaw('codigo, count(*) as total')
            ->groupBy('codigo')
            ->orderByDesc('total')
            ->limit(50)
            ->get()
            ->map(fn ($f) => ['codigo' => $f->codigo, 'total' => (int) $f->total]);

        return response()->json([
            'niveles' => [
                EventoOperacion::NIVEL_INFO => (int) ($porNivel[EventoOperacion::NIVEL_INFO] ?? 0),
                EventoOperacion::NIVEL_AVISO => (int) ($porNivel[EventoOperacion::NIVEL_AVISO] ?? 0),
                EventoOperacion::NIVEL_ERROR => (int) ($porNivel[EventoOperacion::NIVEL_ERROR] ?? 0),
            ],
            'total' => (int) $porNivel->sum(),
            'codigos' => $codigos,
            'ultimo' => EventoOperacion::query()->max('created_at'),
        ]);
    }

    /**
     * La forma de una fila de la bitácora.
     *
     * SOBRE EL CONTEXTO
     *
     * `contexto` es un campo libre en JSON: lo rellena cada sitio que registra
     * un evento, con lo que a ese sitio le pareció útil. Eso significa que no
     * se puede saber de antemano qué lleva dentro, y por eso NO se devuelve
     * tal cual. Se recorta con el mismo depurador que limpia lo que viaja a
     * Sentry, que ya sabe tapar un DNI, un correo o un token incrustado en un
     * texto.
     *
     * Es la misma regla que en el resto del sistema: un campo libre se trata
     * como sospechoso aunque hoy solo lleve identificadores.
     */
    private function comoFila(EventoOperacion $evento): array
    {
        return [
            'id' => $evento->id,
            'nivel' => $evento->nivel,
            'codigo' => $evento->codigo,
            'mensaje' => (string) \App\Support\Observabilidad\Depurador::texto((string) $evento->mensaje),
            'origen' => $evento->origen,
            'dispositivo' => $evento->dispositivo,
            'usuario_id' => $evento->usuario_id,
            'pedido_id' => $evento->pedido_id,
            'contexto' => $this->contextoDepurado($evento->contexto),
            'ocurrio_en' => optional($evento->created_at)->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function contextoDepurado(mixed $contexto): ?array
    {
        if (! is_array($contexto)) {
            return null;
        }

        $limpio = [];

        foreach ($contexto as $clave => $valor) {
            $limpio[$clave] = is_string($valor)
                ? \App\Support\Observabilidad\Depurador::texto($valor)
                : $valor;
        }

        return $limpio;
    }
}
