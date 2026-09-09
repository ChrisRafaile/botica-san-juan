<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->use([
            \App\Http\Middleware\AttachRequestId::class,
            \App\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdminRole::class,
            'audit.critical' => \App\Http\Middleware\AuditCriticalChanges::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Esta aplicacion expone una API sin sesiones de navegador. Sin esta
        // configuracion, una peticion no autenticada que no envie la cabecera
        // "Accept: application/json" hace que Laravel intente redirigir a la
        // ruta nombrada 'login' (inexistente) y responda 500 en lugar de 401.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
