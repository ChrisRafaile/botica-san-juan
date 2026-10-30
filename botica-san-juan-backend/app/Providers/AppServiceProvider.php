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

        /* El orden importa: la cabecera se registra en la configuración por
           omisión y la referencia Scalar se clona de ella. Al revés, la
           segunda interfaz saldría con el título de fábrica. */
        $this->identificarDocumentoOpenApi();
        $this->registrarReferenciaScalar();
    }

    /**
     * Cabecera del documento OpenAPI.
     *
     * Sin esto el documento se titula «Laravel», porque el valor por omisión
     * es el nombre de la aplicación en el entorno. Eso es un detalle menor en
     * la pantalla de documentación y un problema en cuanto el archivo
     * `openapi.json` sale del repositorio: es el nombre que aparece en el
     * grafo de servicios y en cualquier catálogo que lo importe, y «Laravel»
     * no dice de qué sistema se trata.
     *
     * Se fija aquí, en código, y no en el entorno, para que no dependa de que
     * cada máquina tenga APP_NAME bien puesto.
     */
    private function identificarDocumentoOpenApi(): void
    {
        \Dedoc\Scramble\Scramble::extendOpenApi(function (\Dedoc\Scramble\Support\Generator\OpenApi $documento): void {
            $documento->info->title = 'API Botica San Juan';

            $documento->info
                ->setDescription(
                    'Servicios del sistema de gestión de la Botica San Juan: catálogo, '
                    .'inventario, punto de venta, pedidos del portal, sincronización de '
                    .'ventas en contingencia y facturación electrónica. '
                    .'Las rutas protegidas esperan un token Bearer emitido por /api/login.'
                );
        });
    }

    /**
     * Segunda lectura del mismo documento, en /docs/v1, con Scalar.
     *
     * No es una documentación aparte: es la MISMA especificación que sirve
     * /docs/api.json, presentada con otro visor. Se registra como una API
     * adicional de Scramble, clonada de la configuración por omisión, en vez
     * de escribir una plantilla propia; así la plantilla, la versión del
     * visor y el manejo del CSRF los mantiene el paquete y no este proyecto.
     *
     * Si las dos interfaces llegaran a mostrar cosas distintas, el problema
     * estaría en el documento y no en los visores.
     */
    private function registrarReferenciaScalar(): void
    {
        \Dedoc\Scramble\Scramble::registerApi('v1', [
            'renderer' => 'scalar',
            'ui' => ['title' => 'API Botica San Juan'],
        ])->expose(ui: 'docs/v1', document: false);
    }
}
