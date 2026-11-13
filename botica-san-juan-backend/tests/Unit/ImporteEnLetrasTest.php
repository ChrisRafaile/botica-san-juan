<?php

namespace Tests\Unit;

use App\Services\Sunat\ImporteEnLetras;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * El importe en letras tiene que decir lo mismo que el número.
 *
 * Los casos no son arbitrarios: cada uno es un sitio donde una conversión
 * escrita deprisa se equivoca —el «cien» que no es «ciento», el «mil» que no
 * es «un mil», el «uno» que se apocopa o no según lo que venga detrás, y los
 * céntimos que la coma flotante se come—.
 */
class ImporteEnLetrasTest extends TestCase
{
    public static function importes(): array
    {
        return [
            'un sol'                          => [1.00, 'UNO CON 00/100 SOLES'],
            'cien, no ciento'                 => [100.00, 'CIEN CON 00/100 SOLES'],
            'mil, no un mil, con céntimos'    => [1000.50, 'MIL CON 50/100 SOLES'],
            'tres amoxicilinas a 12.50'       => [37.50, 'TREINTA Y SIETE CON 50/100 SOLES'],
            'venta de cero'                   => [0.00, 'CERO CON 00/100 SOLES'],
            'solo céntimos, con cero delante' => [0.05, 'CERO CON 05/100 SOLES'],
            'ciento uno'                      => [101.01, 'CIENTO UNO CON 01/100 SOLES'],
            'tildes de los veinti-'           => [26.00, 'VEINTISÉIS CON 00/100 SOLES'],
            'dieciséis'                       => [16.16, 'DIECISÉIS CON 16/100 SOLES'],
            'veintiuno sin apocopar'          => [21.00, 'VEINTIUNO CON 00/100 SOLES'],
            'veintiún mil, apocopado'         => [21000.00, 'VEINTIÚN MIL CON 00/100 SOLES'],
            'treinta y un mil'                => [31000.00, 'TREINTA Y UN MIL CON 00/100 SOLES'],
            'mil uno'                         => [1001.00, 'MIL UNO CON 00/100 SOLES'],
            'umbral de la boleta sin DNI'     => [700.00, 'SETECIENTOS CON 00/100 SOLES'],
            'un millón'                       => [1000000.00, 'UN MILLÓN CON 00/100 SOLES'],
            'millones, miles y céntimos'      => [2500000.99, 'DOS MILLONES QUINIENTOS MIL CON 99/100 SOLES'],
            'todo nueves'                     => [
                999999.99,
                'NOVECIENTOS NOVENTA Y NUEVE MIL NOVECIENTOS NOVENTA Y NUEVE CON 99/100 SOLES',
            ],
            /* 0.29 * 100 da 28.999999999999996: truncar imprimiría 28/100. */
            'céntimos que el float no guarda' => [0.29, 'CERO CON 29/100 SOLES'],
            /* El redondeo arrastra al entero: no puede salir «100/100». */
            'redondeo que cambia el entero'   => [19.999, 'VEINTE CON 00/100 SOLES'],
            /* Así llega el total desde la base: como cadena decimal. */
            'total leído de la base'          => ['1000.50', 'MIL CON 50/100 SOLES'],
        ];
    }

    #[DataProvider('importes')]
    public function test_convierte_el_importe(float|string $importe, string $esperado): void
    {
        $this->assertSame($esperado, (new ImporteEnLetras)->convertir($importe));
    }

    public function test_la_leyenda_impresa_empieza_por_son(): void
    {
        $this->assertSame(
            'SON: CIENTO VEINTICINCO CON 00/100 SOLES',
            (new ImporteEnLetras)->leyenda(125.00)
        );
    }

    public function test_nombra_la_moneda_del_comprobante(): void
    {
        $letras = new ImporteEnLetras;

        $this->assertSame('DIEZ CON 00/100 DÓLARES AMERICANOS', $letras->convertir(10, 'USD'));
        $this->assertSame('DIEZ CON 00/100 SOLES', $letras->convertir(10, 'pen'));
    }

    public function test_rechaza_un_importe_negativo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ImporteEnLetras)->convertir(-0.01);
    }
}
