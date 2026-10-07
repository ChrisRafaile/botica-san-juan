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

        /* Confirmacion de pedido del portal. Es publica para no obligar a
           registrarse, pero a diferencia del contacto ESCRIBE en la base y
           descuenta inventario: un abuso aqui no llena un buzon, deja el
           anaquel descuadrado y bloquea stock en pedidos que nadie recogera.
           De ahi que el limite sea mas estrecho que el general.

           Se reparte por cuenta cuando hay sesion y por IP cuando no, para que
           varias personas tras la misma conexion —una familia, un locutorio— no
           se bloqueen entre si. */
        RateLimiter::for('checkout', function (Request $request) {
            $clave = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute(6)->by((string) $clave),
                Limit::perHour(30)->by((string) $clave),
            ];
        });
    }
}
