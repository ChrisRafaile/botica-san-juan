<?php

namespace App\Http\Controllers;

use App\Models\DigemidCatalogo;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DigemidCatalogoController extends Controller
{
    public function index(Request $request)
    {
        $query = DigemidCatalogo::query()->orderBy('nombre_producto');

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($search) {
                $inner->where('codigo_digemid', 'like', "%{$search}%")
                    ->orWhere('nombre_producto', 'like', "%{$search}%")
                    ->orWhere('principio_activo', 'like', "%{$search}%")
                    ->orWhere('laboratorio_fabricante', 'like', "%{$search}%");
            });
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->filled('requiere_receta')) {
            $query->where('requiere_receta', $request->boolean('requiere_receta'));
        }

        $shouldPaginate = $request->has('page') || $request->has('per_page') || $request->boolean('paginate');
        if ($shouldPaginate) {
            $perPage = (int) $request->input('per_page', 15);
            return $query->paginate(max(1, min($perPage, 100)));
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo_digemid' => 'required|string|max:64|unique:digemid_catalogos,codigo_digemid',
            'nombre_producto' => 'required|string|max:255',
            'principio_activo' => 'nullable|string|max:255',
            'laboratorio_fabricante' => 'nullable|string|max:255',
            'requiere_receta' => 'nullable|boolean',
            'precio_maximo_regulado' => 'nullable|numeric|min:0',
            'activo' => 'nullable|boolean',
        ]);

        $record = DigemidCatalogo::create([
            'codigo_digemid' => trim($validated['codigo_digemid']),
            'nombre_producto' => trim($validated['nombre_producto']),
            'principio_activo' => $validated['principio_activo'] ?? null,
            'laboratorio_fabricante' => $validated['laboratorio_fabricante'] ?? null,
            'requiere_receta' => $validated['requiere_receta'] ?? false,
            'precio_maximo_regulado' => $validated['precio_maximo_regulado'] ?? null,
            'activo' => $validated['activo'] ?? true,
        ]);

        return response()->json($record, 201);
    }

    public function show(string $id)
    {
        return DigemidCatalogo::findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $record = DigemidCatalogo::findOrFail($id);

        $validated = $request->validate([
            'codigo_digemid' => 'sometimes|required|string|max:64|unique:digemid_catalogos,codigo_digemid,' . $record->id,
            'nombre_producto' => 'sometimes|required|string|max:255',
            'principio_activo' => 'nullable|string|max:255',
            'laboratorio_fabricante' => 'nullable|string|max:255',
            'requiere_receta' => 'nullable|boolean',
            'precio_maximo_regulado' => 'nullable|numeric|min:0',
            'activo' => 'nullable|boolean',
        ]);

        foreach (['codigo_digemid', 'nombre_producto', 'principio_activo', 'laboratorio_fabricante', 'requiere_receta', 'precio_maximo_regulado', 'activo'] as $field) {
            if (array_key_exists($field, $validated)) {
                $record->{$field} = $validated[$field];
            }
        }

        $record->save();

        return response()->json($record);
    }

    public function destroy(string $id)
    {
        $record = DigemidCatalogo::findOrFail($id);
        $record->delete();

        return response()->json(['message' => 'Registro DIGEMID eliminado correctamente']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
            'overwrite' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        if (!$file) {
            throw ValidationException::withMessages(['file' => ['Archivo no recibido.']]);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $rows = [];

        if ($extension === 'csv') {
            $rows = $this->parseCsvFile($file->getRealPath());
        } elseif ($extension === 'xlsx') {
            $rows = $this->parseXlsxFile($file->getRealPath());
        } else {
            throw ValidationException::withMessages([
                'file' => ['Formato no soportado. Usa CSV o XLSX.'],
            ]);
        }

        if (count($rows) === 0) {
            throw ValidationException::withMessages([
                'file' => ['No se encontraron filas validas para importar.'],
            ]);
        }

        $overwrite = $request->boolean('overwrite');
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $codigo = trim((string) ($row['codigo_digemid'] ?? ''));
                $nombre = trim((string) ($row['nombre_producto'] ?? ''));

                if ($codigo === '' || $nombre === '') {
                    $skipped++;
                    $errors[] = [
                        'row' => $index + 2,
                        'error' => 'Fila omitida: codigo_digemid y nombre_producto son obligatorios.',
                    ];
                    continue;
                }

                $payload = [
                    'nombre_producto' => $nombre,
                    'principio_activo' => $row['principio_activo'] ?: null,
                    'laboratorio_fabricante' => $row['laboratorio_fabricante'] ?: null,
                    'requiere_receta' => $this->toBool($row['requiere_receta'] ?? false),
                    'precio_maximo_regulado' => $this->toNullableDecimal($row['precio_maximo_regulado'] ?? null),
                    'activo' => $this->toBool($row['activo'] ?? true),
                ];

                $existing = DigemidCatalogo::where('codigo_digemid', $codigo)->first();
                if ($existing) {
                    if ($overwrite) {
                        $existing->update($payload);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                DigemidCatalogo::create(array_merge(['codigo_digemid' => $codigo], $payload));
                $created++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'message' => 'Importacion DIGEMID finalizada.',
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    public function alertasCumplimiento()
    {
        $missingCode = Producto::query()
            ->where(function ($q) {
                $q->whereNull('codigo_digemid')->orWhere('codigo_digemid', '');
            })
            ->select('id', 'nombre', 'precio', 'codigo_digemid')
            ->limit(100)
            ->get();

        $codeNotFound = Producto::query()
            ->leftJoin('digemid_catalogos as d', 'productos.codigo_digemid', '=', 'd.codigo_digemid')
            ->whereNotNull('productos.codigo_digemid')
            ->where('productos.codigo_digemid', '!=', '')
            ->whereNull('d.id')
            ->select('productos.id', 'productos.nombre', 'productos.precio', 'productos.codigo_digemid')
            ->limit(100)
            ->get();

        $priceExceeded = Producto::query()
            ->join('digemid_catalogos as d', 'productos.codigo_digemid', '=', 'd.codigo_digemid')
            ->whereNotNull('d.precio_maximo_regulado')
            ->whereColumn('productos.precio', '>', 'd.precio_maximo_regulado')
            ->select('productos.id', 'productos.nombre', 'productos.precio', 'productos.codigo_digemid', 'd.precio_maximo_regulado')
            ->limit(100)
            ->get();

        $missingRegulatoryFields = Producto::query()
            ->whereNotNull('codigo_digemid')
            ->where('codigo_digemid', '!=', '')
            ->where(function ($q) {
                $q->whereNull('principio_activo')
                    ->orWhere('principio_activo', '')
                    ->orWhereNull('laboratorio_fabricante')
                    ->orWhere('laboratorio_fabricante', '');
            })
            ->select('id', 'nombre', 'codigo_digemid', 'principio_activo', 'laboratorio_fabricante')
            ->limit(100)
            ->get();

        return response()->json([
            'summary' => [
                'missing_code' => $missingCode->count(),
                'code_not_found' => $codeNotFound->count(),
                'price_exceeded' => $priceExceeded->count(),
                'missing_fields' => $missingRegulatoryFields->count(),
            ],
            'alerts' => [
                'missing_code' => $missingCode,
                'code_not_found' => $codeNotFound,
                'price_exceeded' => $priceExceeded,
                'missing_fields' => $missingRegulatoryFields,
            ],
        ]);
    }

    private function parseCsvFile(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (!$handle) {
            return $rows;
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return $rows;
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $parsed = [];
            foreach ($headers as $idx => $header) {
                $parsed[$header] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
            }

            $rows[] = $parsed;
        }

        fclose($handle);
        return $rows;
    }

    private function parseXlsxFile(string $path): array
    {
        $rows = [];

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return $rows;
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $sx = simplexml_load_string($sharedStringsXml);
            if ($sx) {
                foreach ($sx->si as $si) {
                    $parts = [];
                    if (isset($si->t)) {
                        $parts[] = (string) $si->t;
                    } else {
                        foreach ($si->r as $r) {
                            $parts[] = (string) $r->t;
                        }
                    }
                    $sharedStrings[] = implode('', $parts);
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            return $rows;
        }

        $sheet = simplexml_load_string($sheetXml);
        if (!$sheet || !isset($sheet->sheetData->row)) {
            return $rows;
        }

        $grid = [];
        foreach ($sheet->sheetData->row as $rowNode) {
            $rowArray = [];
            foreach ($rowNode->c as $cell) {
                $ref = (string) $cell['r'];
                $col = preg_replace('/\d+/', '', $ref);
                $type = (string) $cell['t'];
                $value = '';

                if ($type === 's') {
                    $idx = (int) ($cell->v ?? -1);
                    $value = ($idx >= 0 && isset($sharedStrings[$idx])) ? $sharedStrings[$idx] : '';
                } else {
                    $value = (string) ($cell->v ?? '');
                }

                $rowArray[$col] = trim($value);
            }
            $grid[] = $rowArray;
        }

        if (count($grid) === 0) {
            return $rows;
        }

        $headerRow = $grid[0];
        $headerCols = array_keys($headerRow);
        $headers = [];
        foreach ($headerCols as $col) {
            $headers[$col] = $this->normalizeHeader((string) ($headerRow[$col] ?? ''));
        }

        for ($i = 1; $i < count($grid); $i++) {
            $parsed = [];
            $hasContent = false;
            foreach ($headers as $col => $header) {
                $value = (string) ($grid[$i][$col] ?? '');
                if ($value !== '') {
                    $hasContent = true;
                }
                $parsed[$header] = $value;
            }

            if ($hasContent) {
                $rows[] = $parsed;
            }
        }

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = str_replace([' ', '-', '.'], '_', $normalized);

        $aliases = [
            'codigo' => 'codigo_digemid',
            'codigo_digemid' => 'codigo_digemid',
            'cod_digemid' => 'codigo_digemid',
            'nombre' => 'nombre_producto',
            'producto' => 'nombre_producto',
            'nombre_producto' => 'nombre_producto',
            'principio_activo' => 'principio_activo',
            'lab_fabricante' => 'laboratorio_fabricante',
            'laboratorio' => 'laboratorio_fabricante',
            'laboratorio_fabricante' => 'laboratorio_fabricante',
            'requiere_receta' => 'requiere_receta',
            'precio_maximo' => 'precio_maximo_regulado',
            'precio_maximo_regulado' => 'precio_maximo_regulado',
            'precio_regulado' => 'precio_maximo_regulado',
            'activo' => 'activo',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $stringValue = strtolower(trim((string) $value));
        return in_array($stringValue, ['1', 'true', 'si', 'sí', 'yes', 'y'], true);
    }

    private function toNullableDecimal(mixed $value): ?float
    {
        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $stringValue);
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}
