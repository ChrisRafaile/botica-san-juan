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
    private function cabecerasComunes(): array
    {
        return [
            'Access-Control-Allow-Methods'     => implode(', ', (array) config('cors.allowed_methods', [])),
            'Access-Control-Allow-Headers'     => implode(', ', (array) config('cors.allowed_headers', [])),
            'Access-Control-Allow-Credentials' => config('cors.supports_credentials') ? 'true' : 'false',
        ];
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigin = $this->resolveOrigin($request);

        // Handle preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200)->withHeaders($this->cabecerasComunes());

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

        foreach ($this->cabecerasComunes() as $nombre => $valor) {
            $response->headers->set($nombre, $valor);
        }

        return $response;
    }
}
