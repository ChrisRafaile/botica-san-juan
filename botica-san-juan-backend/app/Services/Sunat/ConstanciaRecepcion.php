<?php

namespace App\Services\Sunat;

use App\Models\ComprobanteElectronico;
use App\Services\NombreArchivoSunat;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Constancia de recepción (CDR): guardado, lectura y lo que dice.
 * ---------------------------------------------------------------------------
 * EL CDR NO SE BORRA
 *
 * Es la prueba de que un comprobante llegó. La normativa obliga a
 * conservarlo, y en la práctica es lo único que se puede enseñar cuando
 * alguien pregunta si una boleta se declaró. Por eso se guarda como archivo
 * en disco, con su nombre normalizado, y no solo como una columna JSON: una
 * fila se actualiza y se pierde el original; un archivo con nombre propio,
 * no.
 *
 * La columna `sunat_response` sigue existiendo, pero cambia de papel: ahora
 * guarda la lectura del CDR —código, descripción, notas y hash— mientras que
 * el documento íntegro vive en disco. Conviene tener las dos cosas: la
 * columna para consultar sin abrir archivos, el archivo para demostrar.
 *
 * NOMBRE: `R-` SOBRE EL MISMO NOMBRE BASE
 *
 * `R-20123456789-01-F001-00000123.zip`. Ese prefijo permite emparejar
 * comprobante y constancia mirando el directorio, sin consultar la base. No
 * es una convención inventada aquí: es la que usa el propio servicio.
 *
 * CODIGOS DE RESPUESTA
 *
 * El `ResponseCode` del CDR es el que manda, y se lee así:
 *   0           aceptado
 *   0100-0999   rechazado; el comprobante NO existe para la SUNAT
 *   2000-3999   rechazado por contenido
 *   4000+       observado: aceptado, pero con reparos que conviene corregir
 *
 * Un comprobante observado está aceptado. Tratarlo como error llevaría a
 * reenviarlo, y reenviar algo ya aceptado es duplicarlo.
 */
class ConstanciaRecepcion
{
    private const CARPETA = 'facturacion/cdr';

    public function __construct(
        private readonly NombreArchivoSunat $nombres,
        private readonly EmpaquetadoSunat $empaquetado,
    ) {
    }

    /**
     * Guarda el ZIP del CDR y devuelve su lectura.
     *
     * @return array{ruta:string, codigo:string, descripcion:string, estado:string, notas:array<int,string>, hash:string}
     */
    public function guardar(ComprobanteElectronico $comprobante, string $zipBinario): array
    {
        $nombre = $this->nombres->cdr($comprobante);
        $ruta = self::CARPETA.'/'.$nombre;

        Storage::disk('local')->put($ruta, $zipBinario);

        $lectura = $this->leerXml($this->empaquetado->extraerXml($zipBinario));

        return [
            'ruta' => $ruta,
            'hash' => hash('sha256', $zipBinario),
            ...$lectura,
        ];
    }

    /** Devuelve el ZIP guardado, o null si todavía no hay constancia. */
    public function contenido(ComprobanteElectronico $comprobante): ?string
    {
        if (blank($comprobante->cdr_path)) {
            return null;
        }

        $disco = Storage::disk('local');

        return $disco->exists($comprobante->cdr_path)
            ? $disco->get($comprobante->cdr_path)
            : null;
    }

    public function nombreDescarga(ComprobanteElectronico $comprobante): string
    {
        return $this->nombres->cdr($comprobante);
    }

    /**
     * Lee el XML de la constancia.
     *
     * @return array{codigo:string, descripcion:string, estado:string, notas:array<int,string>}
     */
    public function leerXml(string $xml): array
    {
        $codigo = $this->primerValor($xml, 'ResponseCode');

        if ($codigo === null) {
            throw new RuntimeException('El CDR no trae ResponseCode: no se puede saber si fue aceptado.');
        }

        return [
            'codigo' => $codigo,
            'descripcion' => $this->primerValor($xml, 'Description') ?? '',
            'estado' => $this->estadoDesdeCodigo($codigo),
            'notas' => $this->todosLosValores($xml, 'Note'),
        ];
    }

    /**
     * Traduce el código a uno de los tres estados que el negocio distingue.
     *
     * @return 'aceptada'|'observada'|'rechazada'
     */
    public function estadoDesdeCodigo(string $codigo): string
    {
        $numero = (int) $codigo;

        if ($numero === 0) {
            return 'aceptada';
        }

        /* Observado es aceptado con reparos. Confundirlo con un rechazo
           llevaría a reenviar un comprobante que la SUNAT ya tiene. */
        if ($numero >= 4000) {
            return 'observada';
        }

        return 'rechazada';
    }

    private function primerValor(string $xml, string $etiqueta): ?string
    {
        $patron = sprintf('/<(?:\w+:)?%s>(.*?)<\/(?:\w+:)?%s>/s', preg_quote($etiqueta, '/'), preg_quote($etiqueta, '/'));

        return preg_match($patron, $xml, $c) === 1 ? trim($c[1]) : null;
    }

    /** @return array<int, string> */
    private function todosLosValores(string $xml, string $etiqueta): array
    {
        $patron = sprintf('/<(?:\w+:)?%s>(.*?)<\/(?:\w+:)?%s>/s', preg_quote($etiqueta, '/'), preg_quote($etiqueta, '/'));
        preg_match_all($patron, $xml, $c);

        return array_values(array_map('trim', $c[1] ?? []));
    }
}
