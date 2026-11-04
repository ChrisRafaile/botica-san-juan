<?php

use App\Support\Observabilidad\Depurador;
use Sentry\Event;
use Sentry\EventHint;

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
    | descarta el evento entero.
    |
    */
    'before_send' => function (Event $evento, ?EventHint $pista): ?Event {
        /* El mensaje de una excepción es texto libre escrito por quien la
           lanzó: «No se encontró el usuario 47382910» es perfectamente
           posible. Se limpia con los mismos patrones que todo lo demás. */
        foreach ($evento->getExceptions() as $excepcion) {
            $excepcion->setValue((string) Depurador::texto($excepcion->getValue()));
        }

        if ($evento->getMessage() !== null) {
            $evento->setMessage((string) Depurador::texto($evento->getMessage()));
        }

        $peticion = $evento->getRequest();

        if ($peticion !== []) {
            $evento->setRequest(Depurador::peticion($peticion));
        }

        /* El usuario se reduce a su identificador. Sin correo, sin nombre,
           sin DNI y sin IP: con el id basta para saber si un fallo afecta a
           una cuenta o a muchas, que es la única pregunta que un panel de
           errores necesita responder sobre personas. */
        $usuario = $evento->getUser();

        if ($usuario !== null) {
            $evento->setUser(\Sentry\UserDataBag::createFromArray([
                'id' => $usuario->getId(),
            ]));
        }

        /* Los datos extra y las etiquetas los pone el código de la
           aplicación: se limpian uno a uno porque nada impide que alguien
           adjunte ahí el pedido entero. */
        $extra = [];

        foreach ($evento->getExtra() as $clave => $valor) {
            $extra[$clave] = is_string($valor) ? Depurador::texto($valor) : $valor;
        }

        $evento->setExtra($extra);

        foreach ($evento->getTags() as $clave => $valor) {
            $evento->setTag($clave, (string) Depurador::texto($valor));
        }

        return $evento;
    },

];
