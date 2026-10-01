<?php

/*
|--------------------------------------------------------------------------
| Cuentas de demostracion del entorno desplegado
|--------------------------------------------------------------------------
|
| POR QUE ESTO VIVE EN UN ARCHIVO DE CONFIGURACION Y NO SE LEE CON env()
|
| En produccion el arranque ejecuta `php artisan config:cache`, y a partir de
| ese momento Laravel ya NO carga el archivo .env: env() solo devuelve algo si
| la variable existe tambien como variable de entorno del proceso. Depender de
| eso es fragil y en este proyecto ya costo un fallo —el de CORS, que funciono
| en desarrollo y dejo de funcionar al cachear la configuracion—, asi que la
| regla es la misma de siempre: env() solo dentro de config/.
|
| Las contrasenas NO tienen valor por defecto a proposito. Si faltan, el
| seeder omite la cuenta y lo dice, en vez de crear un administrador con una
| clave conocida en una URL publica.
|
*/

return [

    'cuentas' => [

        'administrador' => [
            'dni'      => '10000001',
            'nombre'   => 'Administrador de demostracion',
            'email'    => 'admin.demo@boticasanjuan.test',
            'rol'      => 'administrador',
            'password' => env('DEMO_ADMIN_PASSWORD'),
        ],

        'cliente' => [
            'dni'      => '10000002',
            'nombre'   => 'Cliente de demostracion',
            'email'    => 'cliente.demo@boticasanjuan.test',
            'rol'      => 'cliente',
            'password' => env('DEMO_CLIENTE_PASSWORD'),
        ],

    ],

];
