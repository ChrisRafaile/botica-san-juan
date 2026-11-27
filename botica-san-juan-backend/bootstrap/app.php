<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // `use()` REEMPLAZA la pila global por defecto, y con ella se había
        // perdido TrustProxies. Detrás de Render eso hacía que $request->ip()
        // fuera la IP interna del proxy en todas las peticiones: cada
        // limitador «por IP» era un solo cubo para todo el público, y HSTS no
        // salía porque la petición no parecía segura. En Render solo el
        // balanceador llega al contenedor, así que se confía en el salto
        // inmediato; la IP del cliente es la que ese salto añade al final de
        // X-Forwarded-For, y no la que el cliente quiera anteponer.
        $middleware->trustProxies(at: '*');

        $middleware->use([
            \Illuminate\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\AttachRequestId::class,
            \App\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdminRole::class,
            'audit.critical' => \App\Http\Middleware\AuditCriticalChanges::class,
            'idempotencia' => \App\Http\Middleware\Idempotencia::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Desde Laravel 11 el paquete de Sentry no se engancha solo: sin esta
        // linea, con DSN puesto, no llegaba ninguna excepcion. Sin DSN no se
        // envia nada.
        Integration::handles($exceptions);

        // Esta aplicacion expone una API sin sesiones de navegador. Sin esta
        // configuracion, una peticion no autenticada que no envie la cabecera
        // "Accept: application/json" hace que Laravel intente redirigir a la
        // ruta nombrada 'login' (inexistente) y responda 500 en lugar de 401.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
