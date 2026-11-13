<?php

namespace App\Services\Sunat;

use InvalidArgumentException;

/**
 * Importe en letras del comprobante: «SON: CIENTO VEINTE CON 50/100 SOLES».
 * ---------------------------------------------------------------------------
 * POR QUE NO ES UN ADORNO
 *
 * La leyenda con el monto en letras es uno de los datos que la SUNAT exige en
 * la representación impresa (catálogo 52, código 1000). Es además lo que
 * resuelve la duda cuando una cifra impresa se lee mal: el número y las letras
 * tienen que decir lo mismo.
 *
 * POR QUE SE TRABAJA EN CENTIMOS ENTEROS
 *
 * 1000.50 no existe en coma flotante: es 1000.4999999… o 1000.5000001…, y
 * separar «parte entera» y «decimales» con `floor` y una resta acaba, antes o
 * después, imprimiendo 49/100 donde el cliente pagó 50. Se redondea UNA vez a
 * céntimos enteros y de ahí en adelante todo es aritmética exacta.
 *
 * LA GRAMATICA, QUE TIENE DOS TRAMPAS
 *
 *  - «uno» se apocopa delante de «mil» y «millones» (VEINTIÚN MIL, UN MILLÓN)
 *    pero no al final, porque lo que sigue es «CON 00/100», no el sustantivo:
 *    VEINTIUNO CON 00/100 SOLES.
 *  - 100 es CIEN; 101 es CIENTO UNO. Y 1000 es MIL, nunca «UN MIL».
 */
class ImporteEnLetras
{
    /** Tope del importe: lo que cabe en la columna decimal(…,2) con holgura. */
    private const MAXIMO_EN_CENTIMOS = 99_999_999_999;

    private const MONEDAS = [
        'PEN' => 'SOLES',
        'USD' => 'DÓLARES AMERICANOS',
    ];

    private const UNIDADES = [
        '', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE',
        'DIECIOCHO', 'DIECINUEVE', 'VEINTE', 'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS',
        'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE',
    ];

    private const DECENAS = [
        3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA',
        7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA',
    ];

    private const CENTENAS = [
        1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS',
        5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS',
        9 => 'NOVECIENTOS',
    ];

    /**
     * Leyenda completa, tal como va impresa: «SON: MIL CON 50/100 SOLES».
     */
    public function leyenda(float|int|string $importe, string $moneda = 'PEN'): string
    {
        return 'SON: '.$this->convertir($importe, $moneda);
    }

    /**
     * El importe en letras sin el prefijo: «MIL CON 50/100 SOLES».
     */
    public function convertir(float|int|string $importe, string $moneda = 'PEN'): string
    {
        $centimos = (int) round(((float) $importe) * 100);

        if ($centimos < 0) {
            throw new InvalidArgumentException('Un comprobante no puede tener un importe negativo.');
        }

        if ($centimos > self::MAXIMO_EN_CENTIMOS) {
            throw new InvalidArgumentException('El importe excede lo que este sistema puede expresar en letras.');
        }

        $nombreMoneda = self::MONEDAS[strtoupper(trim($moneda))] ?? self::MONEDAS['PEN'];

        return sprintf(
            '%s CON %02d/100 %s',
            $this->entero(intdiv($centimos, 100)),
            $centimos % 100,
            $nombreMoneda,
        );
    }

    /** Un entero de 0 a 999 999 999 en palabras. */
    public function entero(int $numero): string
    {
        if ($numero === 0) {
            return 'CERO';
        }

        $millones = intdiv($numero, 1_000_000);
        $miles    = intdiv($numero, 1_000) % 1_000;
        $resto    = $numero % 1_000;

        $partes = [];

        if ($millones > 0) {
            $partes[] = $millones === 1
                ? 'UN MILLÓN'
                : $this->hastaMil($millones, apocopar: true).' MILLONES';
        }

        if ($miles > 0) {
            $partes[] = $miles === 1
                ? 'MIL'
                : $this->hastaMil($miles, apocopar: true).' MIL';
        }

        if ($resto > 0) {
            $partes[] = $this->hastaMil($resto, apocopar: false);
        }

        return implode(' ', $partes);
    }

    /**
     * De 1 a 999. `$apocopar` convierte el «uno» final en «un» para cuando
     * detrás viene «mil» o «millones».
     */
    private function hastaMil(int $numero, bool $apocopar): string
    {
        if ($numero === 100) {
            return 'CIEN';
        }

        $centena = intdiv($numero, 100);
        $resto   = $numero % 100;

        $partes = [];

        if ($centena > 0) {
            $partes[] = self::CENTENAS[$centena];
        }

        if ($resto > 0) {
            $partes[] = $this->hastaCien($resto, $apocopar);
        }

        return implode(' ', $partes);
    }

    private function hastaCien(int $numero, bool $apocopar): string
    {
        if ($numero < 30) {
            if ($apocopar && $numero === 1) {
                return 'UN';
            }

            /* «Veintiún» lleva tilde al perder la o: es aguda acabada en n. */
            if ($apocopar && $numero === 21) {
                return 'VEINTIÚN';
            }

            return self::UNIDADES[$numero];
        }

        $decena = self::DECENAS[intdiv($numero, 10)];
        $unidad = $numero % 10;

        if ($unidad === 0) {
            return $decena;
        }

        return $decena.' Y '.($apocopar && $unidad === 1 ? 'UN' : self::UNIDADES[$unidad]);
    }
}
