<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    private function allowedOrigins(): array
    {
        $raw = (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173');

        return array_values(array_filter(array_map(
            static fn (string $origin) => trim($origin),
            explode(',', $raw)
        )));
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
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigin = $this->resolveOrigin($request);

        // Handle preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200)->withHeaders([
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN',
                'Access-Control-Allow-Credentials' => 'true'
            ]);

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

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}
