<?php

use App\Support\Observabilidad\FiltroDeEnvio;

/*
|--------------------------------------------------------------------------
| Monitoreo de errores · Sentry
|--------------------------------------------------------------------------
|
| SIN DSN NO SE ENVÍA NADA
|
| El SDK queda inactivo cuando `SENTRY_LARAVEL_DSN` está vacío, que es el
| estado por omisión y el de desarrollo. Activarlo es un acto deliberado por
| entorno, no algo que ocurre por instalar el paquete.
|
| LO QUE SALE DE AQUÍ VA DEPURADO
|
| `send_default_pii => false` cubre lo que el SDK sabe que es personal: la IP,
| las cookies, el usuario autenticado. No cubre lo que esta aplicación mete en
| un error sin pensarlo —un DNI dentro del cuerpo, el nombre de un
| medicamento en el mensaje de una excepción—, así que encima hay un
| `before_send` propio. Ver app/Support/Observabilidad/Depurador.php.
|
*/

return [

    'dsn' => env('SENTRY_LARAVEL_DSN'),

    /* Nunca true. Con `send_default_pii` activo, Sentry adjunta la IP, las
       cookies y los datos del usuario autenticado a cada error. En una botica
       eso ata un fallo a una persona concreta y a lo que estaba comprando. */
    'send_default_pii' => false,

    'environment' => env('APP_ENV', 'production'),
    'release' => env('SENTRY_RELEASE'),

    /* Muestreo del rendimiento: apagado por omisión. Las trazas llevan
       nombres de rutas con identificadores dentro y multiplican el volumen
       de datos sin que hoy nadie los mire. */
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),

    /* Las consultas SQL se envían SIN sus parámetros. Con los parámetros, una
       consulta de búsqueda de producto lleva el término que tecleó la
       persona, y una de usuarios lleva su DNI. */
    'breadcrumbs' => [
        'sql_queries' => true,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
    ],

    'tracing' => [
        'queries' => false,
        'views' => false,
        'http_client_requests' => false,
    ],

    /*
    |----------------------------------------------------------------------
    | Última línea antes de salir del servidor
    |----------------------------------------------------------------------
    |
    | Se ejecuta para CADA evento, justo antes del envío. Devolver `null`
    | descarta el evento entero. La lógica vive en la clase y NO aquí como
    | closure, por un motivo que costó un despliegue:
    |
    |     `config:cache` serializa esta configuración con `var_export`, y un
    |     closure no se puede exportar. El contenedor moría al arrancar con
    |     «Call to undefined method Closure::__set_state()», Render mantenía
    |     viva la versión anterior, y el servicio seguía respondiendo 200 con
    |     código de hacía cuatro días.
    |
    | Ver app/Support/Observabilidad/FiltroDeEnvio.php.
    */
    'before_send' => new FiltroDeEnvio,

];
