<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    /**
     * La lista sale de config/cors.php y no de env().
     *
     * Con `config:cache` —que el arranque de produccion ejecuta— Laravel deja
     * de cargar el .env, asi que env() devolvia null aqui y la lista quedaba
     * vacia. La API seguia respondiendo bien a curl y el navegador bloqueaba
     * cada respuesta sin registrar ningun error del lado del servidor.
     */
    private function allowedOrigins(): array
    {
        return (array) config('cors.allowed_origins', []);
    }

    /**
     * Dice si la ruta pertenece al carril publico de interoperabilidad.
     *
     * Ese carril existe para que un sistema de terceros pueda consumir el
     * catalogo o registrar una venta desde un navegador sin figurar en la
     * lista de origenes. No afloja ninguna proteccion: CORS no autoriza
     * nada, solo decide si el navegador deja leer la respuesta. Quien no
     * traiga token sigue recibiendo 401.
     */
    private function esRutaPublica(Request $request): bool
    {
        foreach ((array) config('cors.rutas_publicas', []) as $patron) {
            if ($request->is($patron)) {
                return true;
            }
        }

        return false;
    }

    private function resolveOrigin(Request $request): ?string
    {
        $origin = (string) $request->headers->get('Origin', '');
        if ($origin === '') {
            return null;
        }

        foreach ($this->allowedOrigins() as $allowedOrigin) {
            if ($allowedOrigin === '*' || strcasecmp($allowedOrigin, $origin) === 0) {
                return $origin;
            }
        }

        return null;
    }

    /**
     * Cabeceras que van tanto en la respuesta al preflight como en la real.
     *
     * Estaban escritas dos veces con contenidos distintos: el preflight
     * anunciaba unos metodos y la respuesta real otros, que es justo la clase
     * de discrepancia que rompe una peticion PATCH sin explicar por que.
     *
     * @return array<string, string>
     */
    private function cabecerasComunes(bool $publica = false): array
    {
        $credenciales = ! $publica && config('cors.supports_credentials');

        return [
            'Access-Control-Allow-Methods'     => implode(', ', (array) config('cors.allowed_methods', [])),
            'Access-Control-Allow-Headers'     => implode(', ', (array) config('cors.allowed_headers', [])),
            'Access-Control-Allow-Credentials' => $credenciales ? 'true' : 'false',
        ];
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $publica = $this->esRutaPublica($request);

        /* En el carril publico el origen se refleja como '*' y las
           credenciales se desactivan. El navegador rechaza esa pareja si se
           anuncian juntas, asi que no es una eleccion de estilo. */
        $allowedOrigin = $publica ? '*' : $this->resolveOrigin($request);

        // Handle preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200)->withHeaders($this->cabecerasComunes($publica));

            if ($allowedOrigin !== null) {
                $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            }

            return $response;
        }

        $response = $next($request);

        if ($allowedOrigin !== null) {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Vary', 'Origin');
        }

        foreach ($this->cabecerasComunes($publica) as $nombre => $valor) {
            $response->headers->set($nombre, $valor);
        }

        return $response;
    }
}
