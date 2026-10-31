<?php

namespace App\Services\Clinico;

use App\Models\InteraccionFarmacologica;
use App\Models\Producto;
use Illuminate\Support\Collection;

/**
 * Consultor de interacciones sobre el carrito.
 * ---------------------------------------------------------------------------
 * LA REGLA QUE GOBIERNA TODO LO DEMAS
 *
 * Este servicio puede decir «ojo con estos dos». NUNCA puede decir «esto es
 * seguro».
 *
 * El motivo no es prudencia retórica: es que la ausencia de una regla en la
 * tabla significa «no lo sé», no «no hay interacción». Con 25 principios
 * activos sembrados, 15 % del catálogo mapeado y una tabla de reglas todavía
 * sin aprobar, la mayor parte de las combinaciones posibles caen fuera de lo
 * que el sistema conoce. Un «sin interacciones» en esas condiciones sería
 * una afirmación falsa sobre medicamentos.
 *
 * Por eso la respuesta siempre lleva tres cifras —revisados, no identificados
 * y avisos— y la pantalla está obligada a enseñar las tres.
 *
 * SOLO SE MUESTRA LO APROBADO
 *
 * Las reglas que no ha firmado un químico farmacéutico no salen de aquí.
 * Existen para que haya algo que revisar, no algo que mostrar.
 */
class ConsultorDeInteracciones
{
    /**
     * @param  array<int, int>  $productoIds
     * @return array{
     *     revisados: array<int, array{producto_id:int, nombre:string, principio:string}>,
     *     no_identificados: array<int, array{producto_id:int, nombre:string}>,
     *     avisos: array<int, array<string, mixed>>,
     *     concluyente: bool,
     *     derivar: bool,
     *     mensaje: string
     * }
     */
    public function evaluar(array $productoIds): array
    {
        $productos = Producto::with('principioActivo')
            ->whereIn('id', array_unique($productoIds))
            ->get();

        [$conPerfil, $sinPerfil] = $productos->partition(
            fn (Producto $p) => $p->principio_activo_id !== null,
        );

        $avisos = $this->buscarAvisos($conPerfil);

        /* Concluyente solo si TODO lo del carrito se pudo identificar. Basta
           un producto de marca sin mapear para que el resultado deje de
           describir el carrito completo. */
        $concluyente = $sinPerfil->isEmpty() && $conPerfil->isNotEmpty();

        return [
            'revisados' => $conPerfil->map(fn (Producto $p) => [
                'producto_id' => $p->id,
                'nombre' => $p->nombre,
                'principio' => $p->principioActivo?->nombre ?? '',
            ])->values()->all(),

            'no_identificados' => $sinPerfil->map(fn (Producto $p) => [
                'producto_id' => $p->id,
                'nombre' => $p->nombre,
            ])->values()->all(),

            'avisos' => $avisos,
            'concluyente' => $concluyente,

            /* Se deriva siempre que haya algo que un profesional deba mirar:
               un aviso, o un producto que no se pudo revisar. */
            'derivar' => $avisos !== [] || $sinPerfil->isNotEmpty(),

            'mensaje' => $this->redactarMensaje($conPerfil->count(), $sinPerfil->count(), count($avisos)),
        ];
    }

    /**
     * @param  Collection<int, Producto>  $productos
     * @return array<int, array<string, mixed>>
     */
    private function buscarAvisos(Collection $productos): array
    {
        $porPrincipio = $productos->groupBy('principio_activo_id');
        $ids = $porPrincipio->keys()->map(fn ($k) => (int) $k)->all();

        if (count($ids) < 2) {
            return [];
        }

        $reglas = InteraccionFarmacologica::aprobadas()
            ->with(['principioMenor', 'principioMayor'])
            ->whereIn('principio_menor_id', $ids)
            ->whereIn('principio_mayor_id', $ids)
            ->get();

        $avisos = [];

        foreach ($reglas as $regla) {
            $avisos[] = [
                'severidad' => $regla->severidad,
                'principios' => [
                    $regla->principioMenor?->nombre,
                    $regla->principioMayor?->nombre,
                ],
                'productos' => [
                    ...$porPrincipio->get($regla->principio_menor_id, collect())->pluck('nombre')->all(),
                    ...$porPrincipio->get($regla->principio_mayor_id, collect())->pluck('nombre')->all(),
                ],
                'efecto' => $regla->efecto,
                'recomendacion' => $regla->recomendacion,
            ];
        }

        /* Lo grave primero: si solo se lee el primer aviso, que sea el que
           más importa. */
        $orden = array_flip(['grave', 'moderada', 'leve']);
        usort($avisos, fn ($a, $b) => ($orden[$a['severidad']] ?? 9) <=> ($orden[$b['severidad']] ?? 9));

        return $avisos;
    }

    /**
     * Redacta el resumen.
     *
     * Ninguna de estas frases afirma que algo sea seguro. La mejor de todas
     * dice que no se encontraron interacciones REGISTRADAS, que es distinto
     * y es lo único que el sistema puede sostener.
     */
    private function redactarMensaje(int $revisados, int $sinIdentificar, int $avisos): string
    {
        if ($revisados === 0 && $sinIdentificar === 0) {
            return 'No hay productos que revisar.';
        }

        if ($revisados === 0) {
            return 'No se pudo identificar el principio activo de ningún producto del carrito. '
                .'Consulta al químico farmacéutico antes de combinarlos.';
        }

        $partes = [];

        $partes[] = $avisos > 0
            ? sprintf('Se encontr%s %d posible%s interacci%s entre los productos revisados.',
                $avisos === 1 ? 'ó' : 'aron', $avisos, $avisos === 1 ? '' : 's', $avisos === 1 ? 'ón' : 'ones')
            : 'No se encontraron interacciones registradas entre los productos revisados.';

        if ($sinIdentificar > 0) {
            $partes[] = sprintf(
                'Quedan %d producto%s cuyo principio activo no está mapeado: sobre %s el sistema no puede pronunciarse.',
                $sinIdentificar,
                $sinIdentificar === 1 ? '' : 's',
                $sinIdentificar === 1 ? 'ese' : 'esos',
            );
        }

        return implode(' ', $partes);
    }
}
