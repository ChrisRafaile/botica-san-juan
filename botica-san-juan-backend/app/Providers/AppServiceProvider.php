<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        /* Catalogo publico, para consumo externo sin token.
         *
         * Mas generoso que `api` —120 por minuto es para una persona usando
         * el portal; aqui el cliente es un programa que pagina— pero con
         * tope, porque sin el un bucle mal escrito en el proyecto de otro
         * tumba la instancia gratuita de Render para todos.
         *
         * Se limita SIEMPRE por IP: estas rutas no tienen usuario, asi que
         * `$request->user()?->id` seria null y todas las peticiones del mundo
         * compartirian un unico cubo.
         */
        RateLimiter::for('publico', function (Request $request) {
            return [
                Limit::perMinute(300)->by('publico|'.$request->ip()),
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
        /* Subida de foto de perfil. El límite general —120 por minuto— es
           razonable para formularios de texto y demasiado generoso para un
           canal que acepta archivos de 2 MB: a ese ritmo se escriben 240 MB
           por minuto y por cuenta. Nadie cambia su foto seis veces por hora. */
        RateLimiter::for('subida-foto', function (Request $request) {
            $clave = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute(3)->by((string) $clave),
                Limit::perHour(20)->by((string) $clave),
            ];
        });

        /* Acceso con Google. Cubre pedir la URL y canjear el codigo.

           El canje es el que importa: el codigo tiene 48 caracteres
           aleatorios y vive 60 segundos, asi que adivinarlo a ciegas no es
           viable, pero un limitador convierte «no viable» en «ni se intenta»
           y ademas evita que alguien use el endpoint para sondear. */
        RateLimiter::for('acceso-google', function (Request $request) {
            return [
                Limit::perMinute(10)->by((string) $request->ip()),
                Limit::perHour(40)->by((string) $request->ip()),
            ];
        });

        /* Subida de receta medica. Acepta hasta 8 MB, diez veces lo que la
           foto de perfil, porque una foto de movil o un PDF escaneado pesan
           eso. A 120 peticiones por minuto —el limite general— una sola
           cuenta escribiria casi un gigabyte por minuto en el disco.

           El limite por hora es bajo a proposito: adjuntar la receta de un
           pedido es algo que se hace una vez, y como mucho se repite si la
           rechazan. Seis intentos por hora cubren de sobra el caso real. */
        RateLimiter::for('subida-receta', function (Request $request) {
            $clave = (string) ($request->user()?->id ?: $request->ip());

            return [
                Limit::perMinute(3)->by($clave),
                Limit::perHour(6)->by($clave),
            ];
        });

        /* Correo transaccional. Cada peticion aceptada aqui manda un correo
           real a una direccion que elige quien llama, asi que el limitador no
           protege al servidor: protege la bandeja de entrada de un tercero y
           la reputacion del dominio que firma los envios.

           Se cuenta por IP Y por correo, las dos. Solo por IP, cualquiera con
           direcciones rotativas castiga a un buzon ajeno; solo por correo, una
           sola IP puede barrer miles de direcciones distintas. */
        RateLimiter::for('correo-contrasena', function (Request $request) {
            $destino = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(2)->by('ip:'.$request->ip()),
                Limit::perHour(10)->by('ip:'.$request->ip()),
                /* Sin correo en la peticion la validacion la rechazara de
                   todos modos; se usa la IP para no agrupar bajo una clave
                   vacia peticiones de distinta procedencia. */
                Limit::perHour(5)->by('correo:'.($destino !== '' ? sha1($destino) : $request->ip())),
            ];
        });

        /* Fijar la contrasena nueva. Mas holgado que el envio porque aqui no
           se manda ningun correo y una validacion fallida consume cupo. */
        RateLimiter::for('restablecer-contrasena', function (Request $request) {
            return [
                Limit::perMinute(10)->by((string) $request->ip()),
                Limit::perHour(30)->by((string) $request->ip()),
            ];
        });

        /* Reenvio de la verificacion. Exige sesion, de modo que la clave es la
           cuenta: quien insiste solo se castiga a si mismo. */
        RateLimiter::for('correo-verificacion', function (Request $request) {
            $clave = (string) ($request->user()?->id ?: $request->ip());

            return [
                Limit::perMinute(1)->by($clave),
                Limit::perHour(6)->by($clave),
            ];
        });

        $this->forzarHttpsFueraDeLocal();
        $this->identificarDocumentoOpenApi();
        $this->registrarReferenciaScalar();
        $this->decidirQuienVeLaDocumentacion();
    }

    /**
     * Todo lo que esta aplicación publique como URL va en https.
     *
     * EL SÍNTOMA QUE LO DESTAPÓ
     *
     * La documentación publicada anunciaba el servidor como
     * `http://botica-san-juan-api.onrender.com/api`. Quien pulse «Try it» en
     * Scalar, o copie el fragmento de código, se lleva una URL con `http`.
     * Funciona de milagro —Render redirige a https—, pero un cliente que no
     * siga redirecciones falla, y el ejemplo que copian otros proyectos
     * queda mal desde el primer día.
     *
     * LA CAUSA
     *
     * `APP_URL` está declarada con `http://` en el entorno del proveedor, y
     * Scramble construye el servidor a partir de ella. Además, el proxy de
     * Render termina el TLS y habla con el contenedor por HTTP plano, así
     * que `$request->getScheme()` también dice `http`: cualquier URL
     * absoluta que genere Laravel —enlaces firmados de verificación de
     * correo, entre otros— nace con el esquema equivocado.
     *
     * POR QUÉ SE ARREGLA AQUÍ Y NO SOLO EN LA VARIABLE
     *
     * Corregir `APP_URL` en el panel es necesario y hay que hacerlo, pero
     * depende de que alguien se acuerde. Esto lo cierra en el código: fuera
     * de `local`, ninguna URL generada puede salir en texto plano, venga de
     * donde venga la configuración.
     *
     * Se excluye `local` a propósito: en desarrollo se sirve por http y
     * forzar https rompería todos los enlaces.
     */
    private function forzarHttpsFueraDeLocal(): void
    {
        if ($this->app->environment('local', 'testing')) {
            return;
        }

        \Illuminate\Support\Facades\URL::forceScheme('https');

        /* Scramble no pasa por el generador de URL de Laravel: lee
           `app.url` directamente. Se normaliza el esquema para que el
           documento publicado anuncie el servidor correcto. */
        $configurada = (string) config('app.url');

        if (str_starts_with($configurada, 'http://')) {
            config(['app.url' => 'https://'.substr($configurada, 7)]);
        }
    }

    /**
     * Quién puede abrir /docs/api y /docs/v1.
     *
     * EL PROBLEMA QUE RESUELVE
     *
     * `RestrictedDocsAccess` deja pasar solo en el entorno `local` y, fuera de
     * ahí, consulta la autorización `viewApiDocs`. Si nadie la define, Laravel
     * la deniega: en producción las dos interfaces respondían **403**, y el
     * único modo de leer la API era descargar el `openapi.json` a mano.
     *
     * POR QUÉ UNA VARIABLE DE ENTORNO Y NO SIMPLEMENTE ABRIRLO
     *
     * Publicar la documentación no abre ningún endpoint —lo que exige token lo
     * sigue exigiendo—, pero sí enseña el mapa completo de rutas, incluidas
     * las de administración. Eso no es una vulnerabilidad; es información que
     * le ahorra trabajo a quien quiera buscar una. Aquí se acepta ese
     * intercambio a propósito, porque el objetivo es que otros proyectos
     * consuman estos servicios, y una API que no se puede leer no se puede
     * consumir.
     *
     * Aceptarlo a propósito y poder revertirlo cambiando una variable es
     * distinto de que ocurra por descuido, que es lo que pasaría abriéndolo
     * sin condición.
     */
    private function decidirQuienVeLaDocumentacion(): void
    {
        \Illuminate\Support\Facades\Gate::define(
            'viewApiDocs',
            fn ($usuario = null) => (bool) config('scramble.documentacion_publica', false),
        );
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
