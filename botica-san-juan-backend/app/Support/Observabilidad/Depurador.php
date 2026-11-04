<?php

namespace App\Support\Observabilidad;

/**
 * Quita de un reporte de error todo lo que no debería salir del servidor.
 * ---------------------------------------------------------------------------
 * POR QUÉ ESTO EXISTE Y NO SE CONFÍA EN LAS OPCIONES DEL SDK
 *
 * Sentry trae `send_default_pii => false`, y está puesto. Pero esa opción
 * cubre lo que el SDK SABE que es personal: la IP, las cookies, el usuario
 * autenticado. No cubre lo que esta aplicación mete en un error sin pensarlo:
 * el cuerpo de la petición con un DNI dentro, el nombre de un medicamento en
 * el mensaje de una excepción, una cabecera `Authorization` en los datos de
 * contexto.
 *
 * En una botica eso no es una fuga cualquiera. «Falló al guardar la receta de
 * 47382910» cuenta quién es la persona y qué le pasa. Un panel de errores es
 * un sitio al que entra gente que no tiene por qué saber eso.
 *
 * LA REGLA: LISTA BLANCA, NO LISTA NEGRA
 *
 * De la petición se conservan solo los campos nombrados. Cualquier otro se
 * descarta sin mirarlo. Al revés —quitar los que suenan sensibles— el día que
 * alguien añada un campo nuevo, el campo viaja.
 */
class Depurador
{
    /**
     * Lo único que se conserva del cuerpo y de la consulta.
     *
     * Son campos de control: no identifican a nadie y permiten entender qué
     * se estaba haciendo. `email` no está, y es a propósito: identifica.
     */
    private const CAMPOS_PERMITIDOS = [
        'page', 'per_page', 'origen', 'estado', 'status', 'tipo', 'orden',
        'paginate', 'dias', 'modo', 'formato', 'sprint', 'categoria_id',
    ];

    /**
     * Cabeceras que se conservan. El resto desaparece, empezando por
     * `authorization` y `cookie`, que llevan la sesión entera.
     */
    private const CABECERAS_PERMITIDAS = [
        'accept', 'accept-language', 'content-type', 'user-agent', 'referer',
    ];

    /**
     * Patrones de dato personal que pueden aparecer en CUALQUIER texto.
     *
     * Se aplican al mensaje de la excepción y a los valores que quedan, porque
     * un identificador no siempre viaja en un campo con nombre reconocible:
     * puede estar incrustado en «No se encontró el usuario 47382910».
     */
    private const PATRONES = [
        // Correo electrónico.
        '/[\w.+-]+@[\w-]+\.[\w.-]+/u' => '[correo]',
        // DNI peruano: exactamente 8 dígitos aislados.
        '/(?<![\d-])\d{8}(?![\d-])/u' => '[dni]',
        // RUC: 11 dígitos que empiezan por 10 o 20.
        '/(?<![\d-])(?:10|20)\d{9}(?![\d-])/u' => '[ruc]',
        // Tokens de Sanctum («12|abc…»), claves de API y portadores.
        '/\b\d+\|[A-Za-z0-9]{20,}/u' => '[token]',
        '/\bBearer\s+[A-Za-z0-9._\-]+/iu' => 'Bearer [token]',
        '/\b(?:re|sk|pk|ghp|gho)_[A-Za-z0-9_-]{10,}/u' => '[clave]',
        // Teléfono peruano de nueve dígitos que empieza por 9.
        '/(?<![\d-])9\d{8}(?![\d-])/u' => '[telefono]',
    ];

    /** Aplica los patrones a un texto cualquiera. */
    public static function texto(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return $valor;
        }

        foreach (self::PATRONES as $patron => $reemplazo) {
            $valor = (string) preg_replace($patron, $reemplazo, $valor);
        }

        return $valor;
    }

    /**
     * Deja de la petición solo lo que no identifica a nadie.
     *
     * @param  array<string, mixed>  $peticion
     * @return array<string, mixed>
     */
    public static function peticion(array $peticion): array
    {
        /* El cuerpo se vacía ENTERO. No se filtra campo a campo porque el
           cuerpo de esta API lleva contraseñas, documentos, recetas y líneas
           de pedido; conservar «los que no parezcan sensibles» es apostar a
           que nadie añadirá uno nuevo. Para depurar basta con saber qué ruta
           falló y con qué parámetros de control. */
        unset($peticion['data']);

        if (isset($peticion['query_string']) && is_array($peticion['query_string'])) {
            $peticion['query_string'] = array_intersect_key(
                $peticion['query_string'],
                array_flip(self::CAMPOS_PERMITIDOS),
            );
        } else {
            /* Llega como cadena: se quita entera. Una cadena de consulta sin
               analizar puede llevar un término de búsqueda de medicamento. */
            unset($peticion['query_string']);
        }

        if (isset($peticion['headers']) && is_array($peticion['headers'])) {
            $peticion['headers'] = array_intersect_key(
                array_change_key_case($peticion['headers']),
                array_flip(self::CABECERAS_PERMITIDAS),
            );
        }

        /* La URL puede llevar el identificador en la ruta («/pedidos/217») y
           la consulta en la cola. Se conserva sin la cola. */
        if (isset($peticion['url']) && is_string($peticion['url'])) {
            $peticion['url'] = self::texto(strtok($peticion['url'], '?'));
        }

        unset($peticion['cookies'], $peticion['env']);

        return $peticion;
    }
}
