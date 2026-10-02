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

        /* Formulario de contacto del portal. Es publico a proposito —quien
           escribe a una botica normalmente todavia no es cliente— y por eso
           necesita freno: tres mensajes por minuto y veinte por hora desde la
           misma direccion. Una persona con una consulta real no llega ni de
           lejos a ese limite; un script que busque buzon, si. */
        RateLimiter::for('contacto', function (Request $request) {
            return [
                Limit::perMinute(3)->by((string) $request->ip()),
                Limit::perHour(20)->by((string) $request->ip()),
            ];
        });
    }
}
