<?php

namespace App\Services\Importador;

use App\Models\EventoOperacion;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\InventarioService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Orquesta la previsualización y confirmación segura de importaciones de
 * inventario desde Excel/CSV.
 *
 * Ninguna fila se escribe en base de datos durante la previsualización: los
 * cambios solo ocurren al confirmar un token temporal de un solo uso ligado al
 * administrador que subió el archivo.
 */
class ImportadorInventarioService
{
    public const TTL_MINUTOS = 15;

    private const PREFIJO_CACHE = 'asistente_importacion:';

    /** @var list<string> */
    private const COLUMNAS_REQUERIDAS = [
        'producto',
        'concentracion',
        'laboratorio',
        'lote',
        'fecha_vencimiento',
        'stock',
        'precio_venta',
    ];

    public function __construct(
        private readonly LectorArchivoInventarioService $lector,
        private readonly InventarioService $inventarioService,
    ) {
    }

    /**
     * Parsea y valida un archivo sin escribir en la base de datos.
     *
     * @return array<string, mixed>
     */
    public function previsualizar(UploadedFile $archivo, Usuario $usuario): array
    {
        if ($usuario->rol !== 'administrador') {
            throw new RuntimeException('Solo un administrador puede previsualizar importaciones.');
        }

        $leido = $this->lector->leer($archivo);
        $faltantes = array_values(array_diff(self::COLUMNAS_REQUERIDAS, $leido['columnas']));

        if ($faltantes !== []) {
            throw ValidationException::withMessages([
                'archivo' => ['Faltan columnas obligatorias en el encabezado: ' . implode(', ', $faltantes) . '.'],
            ]);
        }

        $filasValidas = [];
        $duplicados = [];
        $advertencias = [];
        $errores = [];
        $clavesVistasEnArchivo = [];

        foreach ($leido['filas'] as $item) {
            $numFila = $item['fila'];
            $datos = $item['datos'];

            $resultado = $this->normalizarYValidarFila($numFila, $datos);
            if ($resultado['errores'] !== []) {
                foreach ($resultado['errores'] as $mensajeError) {
                    $errores[] = [
                        'fila' => $numFila,
                        'mensaje' => $mensajeError,
                    ];
                }
                continue;
            }

            $normalizada = $resultado['fila'];
            $claveArchivo = implode('|', [
                mb_strtolower($normalizada['producto'], 'UTF-8'),
                mb_strtolower($normalizada['concentracion'], 'UTF-8'),
                mb_strtolower($normalizada['laboratorio'], 'UTF-8'),
                mb_strtolower($normalizada['lote'], 'UTF-8'),
            ]);

            if (isset($clavesVistasEnArchivo[$claveArchivo])) {
                $duplicados[] = [
                    'fila' => $numFila,
                    'tipo' => 'duplicado_en_archivo',
                    'producto' => $normalizada['producto'],
                    'lote' => $normalizada['lote'],
                    'mensaje' => "Fila duplicada dentro del archivo (ya apareció en la fila {$clavesVistasEnArchivo[$claveArchivo]}).",
                ];
                continue;
            }
            $clavesVistasEnArchivo[$claveArchivo] = $numFila;

            $productoExistente = $this->buscarProductoExistente(
                $normalizada['producto'],
                $normalizada['concentracion'],
                $normalizada['laboratorio'],
            );

            $accion = 'crear_producto_y_lote';
            $productoId = null;

            if ($productoExistente !== null) {
                $productoId = $productoExistente->id;
                $loteExistente = Lote::query()
                    ->where('producto_id', $productoExistente->id)
                    ->whereRaw('lower(trim(codigo_lote)) = ?', [mb_strtolower($normalizada['lote'], 'UTF-8')])
                    ->first();

                if ($loteExistente !== null) {
                    $accion = 'actualizar_lote';
                    $duplicados[] = [
                        'fila' => $numFila,
                        'tipo' => 'lote_existente',
                        'producto' => $normalizada['producto'],
                        'lote' => $normalizada['lote'],
                        'mensaje' => "El lote {$normalizada['lote']} ya existe para este producto (stock actual: {$loteExistente->cantidad_actual}); al confirmar se sumarán {$normalizada['stock']} unidades.",
                    ];
                    $advertencias[] = [
                        'fila' => $numFila,
                        'mensaje' => "Lote existente {$normalizada['lote']}: se sumarán {$normalizada['stock']} unidades.",
                    ];
                } else {
                    $accion = 'agregar_lote';
                    $advertencias[] = [
                        'fila' => $numFila,
                        'mensaje' => "El producto ya existe en catálogo; se creará el nuevo lote {$normalizada['lote']}.",
                    ];
                }
            }

            $diasParaVencer = (int) now()->startOfDay()->diffInDays(
                Carbon::parse($normalizada['fecha_vencimiento']),
                false
            );
            if ($diasParaVencer <= 30) {
                $advertencias[] = [
                    'fila' => $numFila,
                    'mensaje' => "Lote próximo a vencer en {$diasParaVencer} día(s) ({$normalizada['fecha_vencimiento']}).",
                ];
            }

            $normalizada['accion'] = $accion;
            $normalizada['producto_existente_id'] = $productoId;
            $filasValidas[] = $normalizada;
        }

        $token = null;
        if ($filasValidas !== []) {
            $token = Str::random(40);
            Cache::put(
                self::claveCache($token),
                [
                    'usuario_id' => $usuario->id,
                    'archivo' => mb_substr(basename($archivo->getClientOriginalName()), 0, 120),
                    'formato' => $leido['formato'],
                    'filas' => $filasValidas,
                    'creado_en' => now()->toIso8601String(),
                ],
                now()->addMinutes(self::TTL_MINUTOS),
            );
        }

        return [
            'formato' => $leido['formato'],
            'token_confirmacion' => $token,
            'expira_en_minutos' => $token !== null ? self::TTL_MINUTOS : null,
            'resumen' => [
                'total_filas' => count($leido['filas']),
                'validas' => count($filasValidas),
                'con_errores' => count($errores),
                'duplicados' => count($duplicados),
                'advertencias' => count($advertencias),
            ],
            'filas_validas' => $filasValidas,
            'duplicados' => $duplicados,
            'advertencias' => $advertencias,
            'errores' => $errores,
        ];
    }

    /**
     * Ejecuta la importación dentro de una transacción única consumiendo el token.
     *
     * @return array<string, mixed>
     */
    public function confirmar(Usuario $usuario, string $token, string $via = 'api'): array
    {
        if ($usuario->rol !== 'administrador') {
            throw new RuntimeException('No autorizado: solo un administrador puede confirmar importaciones.');
        }

        $tokenLimpio = trim($token);
        if ($tokenLimpio === '') {
            throw new RuntimeException('El token de confirmación es obligatorio.');
        }

        $clave = self::claveCache($tokenLimpio);
        $carga = Cache::get($clave);

        if (! is_array($carga)) {
            throw new RuntimeException('El token de confirmación es inválido, ha caducado o ya fue utilizado.');
        }

        if ((int) ($carga['usuario_id'] ?? 0) !== (int) $usuario->id) {
            throw new RuntimeException('El token de confirmación pertenece a otro administrador.');
        }

        // Consumo atómico de un solo uso tras verificar pertenencia.
        $consumido = Cache::pull($clave);
        if (! is_array($consumido)) {
            throw new RuntimeException('El token de confirmación ya fue utilizado.');
        }

        $filas = $consumido['filas'] ?? [];
        if (! is_array($filas) || $filas === []) {
            throw new RuntimeException('La previsualización no contiene filas válidas para importar.');
        }

        return DB::transaction(function () use ($usuario, $consumido, $filas, $via) {
            $productosCreados = 0;
            $productosActualizados = 0;
            $lotesCreados = 0;
            $lotesActualizados = 0;
            $unidadesIngresadas = 0;

            foreach ($filas as $fila) {
                $producto = $this->buscarProductoExistente(
                    $fila['producto'],
                    $fila['concentracion'],
                    $fila['laboratorio'],
                );

                if ($producto === null) {
                    $producto = Producto::create([
                        'nombre' => $fila['producto'],
                        'concentracion' => $fila['concentracion'],
                        'laboratorio' => $fila['laboratorio'],
                        'presentacion' => $fila['presentacion'] ?? 'Unidad',
                        'tipo' => $fila['tipo'] ?? 'Medicamento',
                        'stock' => 0,
                        'precio' => $fila['precio_venta'],
                        'afecto_igv' => true,
                        'tipo_afectacion_igv' => Producto::GRAVADO,
                    ]);
                    $productosCreados++;
                } else {
                    if ((float) $producto->precio !== (float) $fila['precio_venta']) {
                        $producto->precio = $fila['precio_venta'];
                        $producto->save();
                    }
                    $productosActualizados++;
                }

                $lotePrevio = Lote::query()
                    ->where('producto_id', $producto->id)
                    ->whereRaw('lower(trim(codigo_lote)) = ?', [mb_strtolower($fila['lote'], 'UTF-8')])
                    ->exists();

                $this->inventarioService->ingresar(
                    producto: $producto,
                    unidades: (int) $fila['stock'],
                    codigoLote: $fila['lote'],
                    fechaVencimiento: $fila['fecha_vencimiento'],
                    costoUnitario: $fila['costo_unitario'] ?? null,
                    compraId: null,
                    usuarioId: $usuario->id,
                    tipoMovimiento: 'compra',
                    motivo: 'Importación asistida de inventario (' . ($consumido['archivo'] ?? 'archivo') . ')',
                    referenciaTipo: 'importacion_ia',
                    referenciaId: null,
                );

                if ($lotePrevio) {
                    $lotesActualizados++;
                } else {
                    $lotesCreados++;
                }

                $unidadesIngresadas += (int) $fila['stock'];
            }

            $evento = EventoOperacion::info(
                'IMPORTACION_INVENTARIO',
                sprintf(
                    'Importación de inventario confirmada: %d fila(s), %d producto(s) nuevo(s), %d lote(s) procesado(s), %d unidades.',
                    count($filas),
                    $productosCreados,
                    $lotesCreados + $lotesActualizados,
                    $unidadesIngresadas,
                ),
                [
                    'origen' => 'asistente_ia',
                    'usuario_id' => $usuario->id,
                    'contexto' => [
                        'via' => $via,
                        'archivo' => $consumido['archivo'] ?? null,
                        'formato' => $consumido['formato'] ?? null,
                        'filas_procesadas' => count($filas),
                        'productos_creados' => $productosCreados,
                        'productos_actualizados' => $productosActualizados,
                        'lotes_creados' => $lotesCreados,
                        'lotes_actualizados' => $lotesActualizados,
                        'unidades_ingresadas' => $unidadesIngresadas,
                    ],
                ],
            );

            return [
                'filas_procesadas' => count($filas),
                'productos_creados' => $productosCreados,
                'productos_actualizados' => $productosActualizados,
                'lotes_creados' => $lotesCreados,
                'lotes_actualizados' => $lotesActualizados,
                'unidades_ingresadas' => $unidadesIngresadas,
                'evento_id' => $evento->id,
            ];
        });
    }

    /**
     * Descarta un token pendiente cuando el administrador cancela en el widget.
     */
    public function cancelar(Usuario $usuario, string $token): bool
    {
        if ($usuario->rol !== 'administrador') {
            return false;
        }

        $clave = self::claveCache(trim($token));
        $carga = Cache::get($clave);

        if (! is_array($carga) || (int) ($carga['usuario_id'] ?? 0) !== (int) $usuario->id) {
            return false;
        }

        return Cache::forget($clave);
    }

    /**
     * @param  array<string, string>  $datos
     * @return array{fila: array<string, mixed>, errores: list<string>}
     */
    private function normalizarYValidarFila(int $numFila, array $datos): array
    {
        $errores = [];

        $producto = trim((string) ($datos['producto'] ?? ''));
        $concentracion = trim((string) ($datos['concentracion'] ?? ''));
        $laboratorio = trim((string) ($datos['laboratorio'] ?? ''));
        $lote = trim((string) ($datos['lote'] ?? ''));
        $fechaCruda = trim((string) ($datos['fecha_vencimiento'] ?? ''));
        $stockCrudo = trim((string) ($datos['stock'] ?? ''));
        $precioCrudo = trim((string) ($datos['precio_venta'] ?? ''));

        if ($producto === '') {
            $errores[] = 'Falta el nombre del producto.';
        }
        if ($concentracion === '') {
            $errores[] = 'Falta la concentración.';
        }
        if ($laboratorio === '') {
            $errores[] = 'Falta el laboratorio.';
        }
        if ($lote === '') {
            $errores[] = 'Falta el código de lote.';
        } elseif (mb_strlen($lote) > 60) {
            $errores[] = 'El código de lote supera los 60 caracteres.';
        }

        $fechaIso = $this->normalizarFecha($fechaCruda);
        if ($fechaIso === null) {
            $errores[] = "Fecha de vencimiento inválida ('{$fechaCruda}'). Usa dd/mm/aaaa o AAAA-MM-DD.";
        } elseif (Carbon::parse($fechaIso)->lt(now()->startOfDay())) {
            $errores[] = "La fecha de vencimiento ({$fechaIso}) ya está vencida.";
        }

        $stock = $this->normalizarEntero($stockCrudo);
        if ($stock === null || $stock <= 0) {
            $errores[] = "Stock inválido ('{$stockCrudo}'). Debe ser un entero mayor a 0.";
        }

        $precioVenta = $this->normalizarDecimal($precioCrudo);
        if ($precioVenta === null || $precioVenta <= 0) {
            $errores[] = "Precio de venta inválido ('{$precioCrudo}'). Debe ser mayor a 0.";
        }

        $costoUnitario = null;
        $costoCrudo = trim((string) ($datos['costo_unitario'] ?? ''));
        if ($costoCrudo !== '') {
            $costoUnitario = $this->normalizarDecimal($costoCrudo);
            if ($costoUnitario === null || $costoUnitario < 0) {
                $errores[] = "Costo unitario inválido ('{$costoCrudo}').";
            }
        }

        $presentacion = trim((string) ($datos['presentacion'] ?? ''));
        $tipo = trim((string) ($datos['tipo'] ?? ''));

        return [
            'errores' => $errores,
            'fila' => [
                'fila' => $numFila,
                'producto' => $producto,
                'concentracion' => $concentracion,
                'laboratorio' => $laboratorio,
                'lote' => $lote,
                'fecha_vencimiento' => $fechaIso,
                'stock' => $stock,
                'precio_venta' => $precioVenta !== null ? round($precioVenta, 2) : null,
                'presentacion' => $presentacion !== '' ? $presentacion : 'Unidad',
                'tipo' => $tipo !== '' ? $tipo : 'Medicamento',
                'costo_unitario' => $costoUnitario !== null ? round($costoUnitario, 4) : null,
            ],
        ];
    }

    private function normalizarFecha(string $valor): ?string
    {
        if ($valor === '') {
            return null;
        }

        // Serial numérico de Excel (ej. 46614).
        if (is_numeric($valor)) {
            $dias = (int) floor((float) $valor);
            if ($dias > 20000 && $dias < 100000) {
                return Carbon::create(1899, 12, 30)->addDays($dias)->toDateString();
            }
        }

        // Formato dd/mm/aaaa o dd-mm-aaaa.
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $valor, $m)) {
            $dia = (int) $m[1];
            $mes = (int) $m[2];
            $anio = (int) $m[3];
            if (checkdate($mes, $dia, $anio)) {
                return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            }
            return null;
        }

        // Formato ISO aaaa-mm-dd o aaaa/mm/dd.
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $valor, $m)) {
            $anio = (int) $m[1];
            $mes = (int) $m[2];
            $dia = (int) $m[3];
            if (checkdate($mes, $dia, $anio)) {
                return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            }
            return null;
        }

        return null;
    }

    private function normalizarEntero(string $valor): ?int
    {
        if ($valor === '') {
            return null;
        }

        $limpio = preg_replace('/\s+/', '', $valor) ?? $valor;
        if (! preg_match('/^-?\d+$/', $limpio)) {
            return null;
        }

        return (int) $limpio;
    }

    private function normalizarDecimal(string $valor): ?float
    {
        if ($valor === '') {
            return null;
        }

        $limpio = str_ireplace(['S/', 'PEN', ' '], '', $valor);
        $limpio = trim($limpio);

        if ($limpio === '') {
            return null;
        }

        // Si tiene coma y punto, el último separador es el decimal.
        if (str_contains($limpio, ',') && str_contains($limpio, '.')) {
            if (strrpos($limpio, ',') > strrpos($limpio, '.')) {
                $limpio = str_replace('.', '', $limpio);
                $limpio = str_replace(',', '.', $limpio);
            } else {
                $limpio = str_replace(',', '', $limpio);
            }
        } elseif (str_contains($limpio, ',')) {
            $limpio = str_replace(',', '.', $limpio);
        }

        if (! is_numeric($limpio)) {
            return null;
        }

        return (float) $limpio;
    }

    private function buscarProductoExistente(string $nombre, string $concentracion, string $laboratorio): ?Producto
    {
        return Producto::query()
            ->whereRaw('lower(trim(nombre)) = ?', [mb_strtolower(trim($nombre), 'UTF-8')])
            ->whereRaw('lower(trim(concentracion)) = ?', [mb_strtolower(trim($concentracion), 'UTF-8')])
            ->whereRaw('lower(trim(laboratorio)) = ?', [mb_strtolower(trim($laboratorio), 'UTF-8')])
            ->first();
    }

    private static function claveCache(string $token): string
    {
        return self::PREFIJO_CACHE . $token;
    }
}
