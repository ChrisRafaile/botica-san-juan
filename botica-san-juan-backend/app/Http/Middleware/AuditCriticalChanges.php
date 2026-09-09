<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditCriticalChanges
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = $next($request);

        $user = $request->user();
        $payload = $request->except([
            'password',
            'password_confirmation',
            'token',
            'current_password',
            'new_password',
        ]);

        Log::channel('audit')->info('audit.critical_change', [
            'when' => now()->toIso8601String(),
            'request_id' => (string) ($request->attributes->get('request_id') ?? $request->header('X-Request-Id') ?? ''),
            'actor_user_id' => $user?->id,
            'actor_dni' => $user?->dni,
            'actor_role' => $user?->rol,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'payload' => $payload,
        ]);

        return $response;
    }
}
