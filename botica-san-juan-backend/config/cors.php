<?php

/*
|--------------------------------------------------------------------------
| Origenes permitidos para peticiones entre dominios
|--------------------------------------------------------------------------
|
| POR QUE ESTA AQUI Y NO EN EL MIDDLEWARE
|
| App\Http\Middleware\HandleCors leia la lista con env() en cada peticion.
| Funciona mientras la configuracion no este cacheada, pero en produccion el
| arranque ejecuta `config:cache` y entonces Laravel deja de cargar el archivo
| .env: env() devuelve null fuera de los archivos de configuracion y la lista
| se queda vacia. El sintoma seria el peor posible de diagnosticar: la API
| responde correctamente a curl y el navegador bloquea cada respuesta sin que
| aparezca ningun error en el servidor.
|
| Dentro de config/ el valor se resuelve una sola vez, al construir la cache,
| cuando el .env todavia se esta leyendo.
|
*/

return [

    /*
     | Lista separada por comas. En desarrollo apunta al servidor de Vite; en
     | produccion, al dominio del frontend desplegado.
     |
     | No se admite '*' junto con credenciales: el navegador rechaza esa
     | combinacion, asi que cada origen se nombra explicitamente.
     */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))
    ))),

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'Accept',
    ],

    'supports_credentials' => true,

];
