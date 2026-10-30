<?php

namespace App\Console\Commands;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;

/**
 * Mapa de servicios para la vista de grafo.
 * ---------------------------------------------------------------------------
 * POR QUE NO BASTA CON openapi.json
 *
 * El documento OpenAPI completo pasa de medio megabyte: lleva el esquema de
 * cada cuerpo, de cada respuesta y de cada modelo. Eso es exactamente lo que
 * necesita un cliente que va a consumir la API, y exactamente lo que sobra
 * cuando lo que se quiere es MIRAR la arquitectura: en un visor de grafos los
 * nodos de esquema entierran a los de servicio y no se distingue nada.
 *
 * Este comando destila el mismo documento a su estructura: qué dominios hay,
 * qué operaciones tiene cada uno, por qué verbo y método se llega, y cuáles
 * exigen token. Son dos vistas del mismo sistema, no dos sistemas.
 *
 * El archivo se regenera; no se edita a mano. Si el grafo muestra un servicio
 * que ya no existe, es que nadie volvió a ejecutar esto.
 */
class ExportarMapaServicios extends Command
{
    protected $signature = 'soa:mapa {--path= : Ruta del archivo a escribir}';

    protected $description = 'Exporta el mapa de servicios (dominios y operaciones) para visualizarlo como grafo.';

    public function handle(Generator $generador): int
    {
        $documento = $generador->generate(Scramble::getGeneratorConfig('default'))->spec();

        $dominios = [];
        $operaciones = 0;
        $protegidas = 0;

        foreach ($documento['paths'] ?? [] as $ruta => $metodos) {
            foreach ($metodos as $verbo => $operacion) {
                if (! is_array($operacion) || ! in_array($verbo, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                /* Sin etiqueta no hay dominio al que colgar la operación; antes
                   de inventarse uno es mejor decir que no lo tiene. */
                $dominio = $operacion['tags'][0] ?? 'sin-clasificar';

                /* `security: []` significa explícitamente «esta es pública».
                   Que la clave falte significa lo contrario: hereda la
                   seguridad global, que es el token Bearer. */
                $publica = array_key_exists('security', $operacion)
                    && $operacion['security'] === [];

                $dominios[$dominio][] = [
                    'operacion' => $operacion['summary'] ?? $operacion['operationId'] ?? $ruta,
                    'metodo' => strtoupper($verbo),
                    'ruta' => '/api'.$ruta,
                    'acceso' => $publica ? 'publico' : 'token Bearer',
                ];

                $operaciones++;
                $publica || $protegidas++;
            }
        }

        ksort($dominios);

        $mapa = [
            'sistema' => $documento['info']['title'] ?? 'API',
            'version' => $documento['info']['version'] ?? null,
            'generado' => now()->toIso8601String(),
            'resumen' => [
                'dominios' => count($dominios),
                'operaciones' => $operaciones,
                'operaciones_con_token' => $protegidas,
                'operaciones_publicas' => $operaciones - $protegidas,
            ],
            'dominios' => $dominios,
        ];

        $destino = $this->option('path') ?: base_path('../docs/mapa-servicios.json');

        file_put_contents(
            $destino,
            json_encode($mapa, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        $this->info(sprintf(
            'Mapa de servicios escrito en %s: %d dominios, %d operaciones (%d con token).',
            $destino,
            count($dominios),
            $operaciones,
            $protegidas,
        ));

        return self::SUCCESS;
    }
}
