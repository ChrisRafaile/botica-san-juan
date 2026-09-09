<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El sistema depende del contrato, no de Izipay. Cambiar de pasarela
        // consiste en enlazar otra implementacion aqui.
        $this->app->bind(
            \App\Services\Pagos\PasarelaPago::class,
            \App\Services\Pagos\IzipayClient::class
        );

        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute(120)->by((string) $key),
            ];
        });

        RateLimiter::for('login', function (Request $request) {
            $dni = (string) $request->input('dni', 'anon');

            return [
                Limit::perMinute(5)->by($request->ip() . '|' . $dni),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return [
                Limit::perMinute(3)->by((string) $request->ip()),
            ];
        });
    }
}
