<?php

namespace App\Services\Clinico;

use App\Models\PrincipioActivo;
use App\Models\Producto;
use Illuminate\Support\Collection;

/**
 * Reconoce el principio activo a partir del nombre del producto.
 * ---------------------------------------------------------------------------
 * EL PROBLEMA
 *
 * El catálogo tiene 884 productos y uno solo trae el principio activo. Los
 * nombres son de dos clases:
 *
 *   ACETILCISTEINA, ACICLOVIR, PARACETAMOL   el nombre ES el principio activo
 *   AB-MOKS, ACICLAV, ACI BASIC              nombre comercial, no dice nada
 *
 * Este normalizador resuelve la primera clase y NO intenta la segunda.
 *
 * POR QUE NO SE ADIVINA LA SEGUNDA
 *
 * «ACICLAV» se parece a «ACICLOVIR». Un emparejamiento por similitud lo daría
 * por bueno, y son cosas distintas: ACICLAV es amoxicilina con ácido
 * clavulánico. En un catálogo de medicamentos un falso positivo no es un
 * resultado peor, es un aviso equivocado sobre lo que alguien va a tomar.
 *
 * Por eso solo hay coincidencia EXACTA contra la lista curada, por palabras
 * completas. Lo que no casa se queda sin identificar, y el consultor lo dice
 * en voz alta en lugar de disimularlo.
 */
class NormalizadorPrincipioActivo
{
    /** @var Collection<string, int>|null forma normalizada => id */
    private ?Collection $indice = null;

    /**
     * Quita tildes y signos para comparar.
     *
     * «ÁCIDO FÓLICO» y «ACIDO FOLICO» tienen que ser la misma cosa; el
     * catálogo real escribe las dos.
     */
    public static function normalizar(string $texto): string
    {
        $sinTildes = strtr(
            mb_strtoupper(trim($texto)),
            ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N'],
        );

        /* Se conservan solo letras, dígitos y espacios: los guiones y puntos
           del catálogo («AB-MOKS», «N.F.») son ruido para la comparación. */
        $limpio = preg_replace('/[^A-Z0-9 ]+/u', ' ', $sinTildes);

        return trim(preg_replace('/\s+/', ' ', (string) $limpio));
    }

    /** Carga el índice de formas reconocibles una sola vez. */
    private function indice(): Collection
    {
        if ($this->indice !== null) {
            return $this->indice;
        }

        $this->indice = collect();

        foreach (PrincipioActivo::all() as $principio) {
            foreach ($principio->formas() as $forma) {
                $this->indice->put(self::normalizar($forma), $principio->id);
            }
        }

        return $this->indice;
    }

    /**
     * Devuelve el id del principio activo, o null si no se puede afirmar.
     *
     * Se exige que el nombre del producto EMPIECE por la forma reconocida y
     * que lo siguiente sea un límite de palabra. Así «PARACETAMOL 500 MG»
     * casa y «PARACETAMOLINA» no: la segunda es otro producto, y conformarse
     * con que «contenga» la cadena los confundiría.
     */
    public function identificar(string $nombre): ?int
    {
        $limpio = self::normalizar($nombre);

        if ($limpio === '') {
            return null;
        }

        foreach ($this->indice() as $forma => $id) {
            if ($limpio === $forma || str_starts_with($limpio, $forma.' ')) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Rellena `principio_activo_id` en el catálogo.
     *
     * No pisa lo que ya esté puesto: si alguien —el químico farmacéutico o el
     * importador del padrón— lo asignó a mano, su criterio manda sobre el de
     * esta heurística.
     *
     * @return array{revisados:int, identificados:int}
     */
    public function mapearCatalogo(): array
    {
        $revisados = 0;
        $identificados = 0;

        Producto::whereNull('principio_activo_id')
            ->orderBy('id')
            ->chunkById(500, function (Collection $productos) use (&$revisados, &$identificados) {
                foreach ($productos as $producto) {
                    $revisados++;
                    $id = $this->identificar((string) $producto->nombre);

                    if ($id === null) {
                        continue;
                    }

                    $producto->forceFill(['principio_activo_id' => $id])->save();
                    $identificados++;
                }
            });

        return ['revisados' => $revisados, 'identificados' => $identificados];
    }
}
