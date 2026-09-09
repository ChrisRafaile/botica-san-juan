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
