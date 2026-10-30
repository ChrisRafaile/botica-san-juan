<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Ruta 'login' nombrada
|--------------------------------------------------------------------------
| Esta aplicacion es una API sin sesiones de navegador, pero el middleware de
| autenticacion de Laravel redirige a la ruta nombrada 'login' cuando la
| peticion no declara que espera JSON. Sin esta ruta, ese camino terminaba en
| un error 500 «Route [login] not defined» en lugar de un 401 limpio.
*/
Route::get('/login', function () {
    return response()->json([
        'message' => 'No autenticado. Envia el token en la cabecera Authorization.',
    ], 401);
})->name('login');

/*
|--------------------------------------------------------------------------
| Documentacion interactiva de la API
|--------------------------------------------------------------------------
|
| Las dos interfaces —/docs/api con Stoplight Elements y /docs/v1 con Scalar—
| las registra Scramble desde AppServiceProvider, sobre el MISMO documento.
| Aqui solo vive la descarga del esquema.
|
| Descarga directa para herramientas externas —entre ellas
| jsoncrack.com, que dibuja el grafo de servicios a partir del JSON—. Se
| sirve como adjunto y con nombre fijo para que el archivo llegue listo para
| arrastrar, en lugar de abrirse en el navegador.
*/
Route::get('/docs/openapi.json', function () {
    $generador = app(\Dedoc\Scramble\Generator::class);
    $configuracion = \Dedoc\Scramble\Scramble::getGeneratorConfig('default');

    /* Se genera en el momento, no se lee un archivo exportado antes: un
       esquema guardado se desactualiza en silencio y el grafo mostraría
       servicios que ya no existen. El archivo versionado en docs/ sirve para
       consultar sin levantar el servidor; esta ruta es la fuente viva. */
    $documento = $generador->generate($configuracion)->spec();

    return response()->json($documento, 200, [
        'Content-Disposition' => 'attachment; filename=openapi.json',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
})->name('docs.openapi');
