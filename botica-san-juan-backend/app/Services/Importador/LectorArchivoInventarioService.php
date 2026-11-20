<?php

namespace App\Services\Importador;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lector seguro de archivos de inventario (.xlsx y .csv).
 *
 * Valida la firma real del archivo (MIME y estructura interna) sin confiar en la
 * extensión declarada, normaliza encabezados con tildes/mayúsculas y devuelve
 * filas asociativas listas para validación de negocio.
 */
class LectorArchivoInventarioService
{
    public const TAMANO_MAXIMO_BYTES = 5 * 1024 * 1024; // 5 MB

    /** @var array<string, list<string>> */
    private const ALIAS_COLUMNAS = [
        'producto' => ['producto', 'nombre', 'nombre_producto', 'medicamento', 'descripcion'],
        'concentracion' => ['concentracion', 'dosis'],
        'laboratorio' => ['laboratorio', 'marca', 'fabricante', 'laboratorio_fabricante'],
        'lote' => ['lote', 'codigo_lote', 'nro_lote', 'numero_lote'],
        'fecha_vencimiento' => ['fecha_vencimiento', 'vencimiento', 'fecha_caducidad', 'caducidad', 'fv'],
        'stock' => ['stock', 'cantidad', 'cantidad_inicial', 'unidades'],
        'precio_venta' => ['precio_venta', 'precio', 'pvp', 'precio_unitario'],
        'presentacion' => ['presentacion', 'forma_farmaceutica'],
        'tipo' => ['tipo', 'categoria_producto'],
        'costo_unitario' => ['costo_unitario', 'costo', 'precio_compra'],
    ];

    /**
     * Lee un archivo subido (.xlsx o .csv) y devuelve sus encabezados canónicos y filas.
     *
     * @return array{formato: 'xlsx'|'csv', columnas: list<string>, filas: list<array{fila: int, datos: array<string, string>}>}
     */
    public function leer(UploadedFile $archivo): array
    {
        $ruta = $archivo->getRealPath();
        if (! $ruta || ! is_file($ruta)) {
            throw ValidationException::withMessages([
                'archivo' => ['No se pudo acceder al archivo subido.'],
            ]);
        }

        $tamano = filesize($ruta);
        if ($tamano === false || $tamano <= 0) {
            throw ValidationException::withMessages([
                'archivo' => ['El archivo está vacío.'],
            ]);
        }

        if ($tamano > self::TAMANO_MAXIMO_BYTES) {
            throw ValidationException::withMessages([
                'archivo' => ['El archivo supera el tamaño máximo permitido de 5 MB.'],
            ]);
        }

        $formato = $this->detectarFormatoReal($ruta);

        $matriz = $formato === 'xlsx'
            ? $this->leerXlsx($ruta)
            : $this->leerCsv($ruta);

        if (count($matriz) < 2) {
            throw ValidationException::withMessages([
                'archivo' => ['El archivo debe incluir una fila de encabezados y al menos una fila de datos.'],
            ]);
        }

        $encabezadosCrudos = array_shift($matriz);
        $mapaIndices = $this->mapearEncabezados($encabezadosCrudos);

        $filas = [];
        foreach ($matriz as $indice => $celdas) {
            $numeroFila = $indice + 2; // Fila 1 = encabezados
            $datos = [];
            $tieneContenido = false;

            foreach ($mapaIndices as $indiceColumna => $claveCanonica) {
                $valor = trim((string) ($celdas[$indiceColumna] ?? ''));
                if ($valor !== '') {
                    $tieneContenido = true;
                }
                $datos[$claveCanonica] = $valor;
            }

            if (! $tieneContenido) {
                continue;
            }

            $filas[] = [
                'fila' => $numeroFila,
                'datos' => $datos,
            ];
        }

        return [
            'formato' => $formato,
            'columnas' => array_values(array_unique(array_values($mapaIndices))),
            'filas' => $filas,
        ];
    }

    /**
     * Detecta el formato real inspeccionando magic bytes, finfo y estructura ZIP/texto.
     *
     * @return 'xlsx'|'csv'
     */
    private function detectarFormatoReal(string $ruta): string
    {
        $cabecera = file_get_contents($ruta, false, null, 0, 8) ?: '';
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($ruta);

        // Los archivos .xlsx son contenedores ZIP (PK\x03\x04) con xl/workbook.xml.
        if (str_starts_with($cabecera, "PK\x03\x04")) {
            $mimesZip = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/x-zip-compressed',
                'application/octet-stream',
            ];

            if (! in_array($mime, $mimesZip, true)) {
                throw ValidationException::withMessages([
                    'archivo' => ['El archivo comprimido no corresponde a un libro Excel (.xlsx) válido.'],
                ]);
            }

            $zip = new ZipArchive();
            if ($zip->open($ruta) !== true) {
                throw ValidationException::withMessages([
                    'archivo' => ['No se pudo abrir el archivo .xlsx.'],
                ]);
            }

            $esXlsxValido = $zip->locateName('[Content_Types].xml') !== false
                && $zip->locateName('xl/workbook.xml') !== false;
            $zip->close();

            if (! $esXlsxValido) {
                throw ValidationException::withMessages([
                    'archivo' => ['El archivo ZIP no contiene una estructura Excel (.xlsx) válida.'],
                ]);
            }

            return 'xlsx';
        }

        // CSV: debe ser texto plano/CSV sin bytes nulos ni firmas binarias.
        $mimesTexto = [
            'text/plain',
            'text/csv',
            'application/csv',
            'text/x-csv',
            'application/vnd.ms-excel',
        ];

        if (! in_array($mime, $mimesTexto, true)) {
            throw ValidationException::withMessages([
                'archivo' => ["Formato no permitido ({$mime}). Sube un archivo .xlsx o .csv válido."],
            ]);
        }

        $muestra = file_get_contents($ruta, false, null, 0, 4096) ?: '';
        if (str_contains($muestra, "\0")) {
            throw ValidationException::withMessages([
                'archivo' => ['El archivo contiene datos binarios y no es un CSV válido.'],
            ]);
        }

        return 'csv';
    }

    /**
     * @return list<list<string>>
     */
    private function leerCsv(string $ruta): array
    {
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            return [];
        }

        // Eliminar BOM UTF-8 si está presente.
        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        }

        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1');
        }

        $lineas = preg_split("/\r\n|\n|\r/", $contenido) ?: [];
        $primeraLinea = '';
        foreach ($lineas as $linea) {
            if (trim($linea) !== '') {
                $primeraLinea = $linea;
                break;
            }
        }

        $delimitador = $this->detectarDelimitadorCsv($primeraLinea);
        $filas = [];

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return [];
        }
        fwrite($stream, $contenido);
        rewind($stream);

        while (($celdas = fgetcsv($stream, 0, $delimitador, '"', '\\')) !== false) {
            if ($celdas === [null]) {
                continue;
            }
            $filas[] = array_map(fn ($v) => (string) ($v ?? ''), $celdas);
        }

        fclose($stream);

        return $filas;
    }

    private function detectarDelimitadorCsv(string $linea): string
    {
        $candidatos = [',' => substr_count($linea, ','), ';' => substr_count($linea, ';'), "\t" => substr_count($linea, "\t")];
        arsort($candidatos);
        $mejor = array_key_first($candidatos);

        return ($candidatos[$mejor] ?? 0) > 0 ? (string) $mejor : ',';
    }

    /**
     * Parsea la primera hoja de un libro .xlsx usando ZipArchive y SimpleXML.
     *
     * @return list<list<string>>
     */
    private function leerXlsx(string $ruta): array
    {
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw ValidationException::withMessages([
                'archivo' => ['No se pudo abrir el archivo .xlsx.'],
            ]);
        }

        try {
            $sharedStrings = $this->leerSharedStrings($zip);
            $rutaHoja = $this->resolverRutaPrimeraHoja($zip);
            $xmlHoja = $zip->getFromName($rutaHoja);

            if ($xmlHoja === false) {
                throw ValidationException::withMessages([
                    'archivo' => ['El libro Excel no contiene ninguna hoja legible.'],
                ]);
            }

            $hoja = @simplexml_load_string($xmlHoja);
            if (! $hoja instanceof SimpleXMLElement || ! isset($hoja->sheetData->row)) {
                return [];
            }

            $filas = [];
            foreach ($hoja->sheetData->row as $row) {
                $celdasFila = [];
                $maxCol = -1;

                foreach ($row->c as $celda) {
                    $ref = (string) ($celda['r'] ?? '');
                    $colIdx = $ref !== '' ? $this->referenciaAColumna($ref) : ($maxCol + 1);
                    $maxCol = max($maxCol, $colIdx);
                    $celdasFila[$colIdx] = $this->extraerValorCelda($celda, $sharedStrings);
                }

                if ($maxCol < 0) {
                    continue;
                }

                $filaNormalizada = [];
                for ($i = 0; $i <= $maxCol; $i++) {
                    $filaNormalizada[] = $celdasFila[$i] ?? '';
                }
                $filas[] = $filaNormalizada;
            }

            return $filas;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function leerSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $doc = @simplexml_load_string($xml);
        if (! $doc instanceof SimpleXMLElement) {
            return [];
        }

        $cadenas = [];
        foreach ($doc->si as $si) {
            if (isset($si->t)) {
                $cadenas[] = (string) $si->t;
                continue;
            }

            $texto = '';
            if (isset($si->r)) {
                foreach ($si->r as $run) {
                    $texto .= (string) ($run->t ?? '');
                }
            }
            $cadenas[] = $texto;
        }

        return $cadenas;
    }

    private function resolverRutaPrimeraHoja(ZipArchive $zip): string
    {
        if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
            return 'xl/worksheets/sheet1.xml';
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = (string) $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $nombre)) {
                return $nombre;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function extraerValorCelda(SimpleXMLElement $celda, array $sharedStrings): string
    {
        $tipo = (string) ($celda['t'] ?? '');

        if ($tipo === 's') {
            $idx = (int) ($celda->v ?? -1);
            return $sharedStrings[$idx] ?? '';
        }

        if ($tipo === 'inlineStr') {
            if (isset($celda->is->t)) {
                return (string) $celda->is->t;
            }
            $texto = '';
            if (isset($celda->is->r)) {
                foreach ($celda->is->r as $run) {
                    $texto .= (string) ($run->t ?? '');
                }
            }
            return $texto;
        }

        if ($tipo === 'b') {
            return ((string) ($celda->v ?? '0')) === '1' ? '1' : '0';
        }

        return (string) ($celda->v ?? '');
    }

    private function referenciaAColumna(string $ref): int
    {
        if (! preg_match('/^([A-Z]+)/i', $ref, $coincidencia)) {
            return 0;
        }

        $letras = strtoupper($coincidencia[1]);
        $indice = 0;
        $longitud = strlen($letras);
        for ($i = 0; $i < $longitud; $i++) {
            $indice = $indice * 26 + (ord($letras[$i]) - ord('A') + 1);
        }

        return $indice - 1;
    }

    /**
     * @param  list<string>  $encabezadosCrudos
     * @return array<int, string>
     */
    private function mapearEncabezados(array $encabezadosCrudos): array
    {
        $mapa = [];

        foreach ($encabezadosCrudos as $idx => $encabezado) {
            $normalizado = $this->normalizarEncabezado($encabezado);
            if ($normalizado === '') {
                continue;
            }

            $canonico = $normalizado;
            foreach (self::ALIAS_COLUMNAS as $clave => $aliases) {
                if (in_array($normalizado, $aliases, true)) {
                    $canonico = $clave;
                    break;
                }
            }

            $mapa[$idx] = $canonico;
        }

        return $mapa;
    }

    public function normalizarEncabezado(string $texto): string
    {
        $limpio = trim($texto);
        $limpio = preg_replace('/^\xEF\xBB\xBF/', '', $limpio) ?? $limpio;
        $limpio = mb_strtolower($limpio, 'UTF-8');

        $reemplazos = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ];
        $limpio = strtr($limpio, $reemplazos);
        $limpio = preg_replace('/[\s\-]+/', '_', $limpio) ?? $limpio;
        $limpio = preg_replace('/[^a-z0-9_]/', '', $limpio) ?? $limpio;

        return trim($limpio, '_');
    }

    /**
     * Genera un archivo .xlsx mínimo y válido en disco (útil para evidencias y pruebas).
     *
     * @param  list<string>  $encabezados
     * @param  list<list<string|int|float>>  $filas
     */
    public static function generarXlsx(string $rutaDestino, array $encabezados, array $filas): void
    {
        $directorio = dirname($rutaDestino);
        if (! is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }

        $todas = array_merge([$encabezados], $filas);
        $filasXml = '';

        foreach ($todas as $numFila => $celdas) {
            $r = $numFila + 1;
            $celdasXml = '';
            foreach (array_values($celdas) as $numCol => $valor) {
                $colLetra = self::indiceALetra($numCol);
                $ref = "{$colLetra}{$r}";
                if (is_int($valor) || is_float($valor)) {
                    $celdasXml .= "<c r=\"{$ref}\"><v>{$valor}</v></c>";
                } else {
                    $escapado = htmlspecialchars((string) $valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $celdasXml .= "<c r=\"{$ref}\" t=\"inlineStr\"><is><t>{$escapado}</t></is></c>";
                }
            }
            $filasXml .= "<row r=\"{$r}\">{$celdasXml}</row>";
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $filasXml . '</sheetData>'
            . '</worksheet>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Inventario" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';

        $zip = new ZipArchive();
        if ($zip->open($rutaDestino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("No se pudo crear el archivo Excel en {$rutaDestino}");
        }

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();
    }

    private static function indiceALetra(int $indice): string
    {
        $letra = '';
        $n = $indice;
        while ($n >= 0) {
            $letra = chr(65 + ($n % 26)) . $letra;
            $n = intdiv($n, 26) - 1;
        }

        return $letra;
    }
}
