<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Izipay (plataforma Lyra / micuentaweb)
     |--------------------------------------------------------------------------
     |
     | 'mode' = 'simulado' resuelve las operaciones localmente y permite probar
     | el flujo completo sin credenciales; 'api' invoca al proveedor real.
     |
     | Se manejan DOS claves de firma distintas, tal como exige la plataforma:
     |   - sha256_key: valida el 'kr-hash' que devuelve el navegador.
     |   - password:   valida el 'kr-hash' de la notificacion servidor a
     |                 servidor (IPN).
     |
     | Ninguna de las dos sale nunca del backend. Solo 'public_key' se entrega
     | al frontend.
     */
    'izipay' => [
        'mode' => env('IZIPAY_MODE', 'simulado'),
        'base_url' => env('IZIPAY_BASE_URL', 'https://api.micuentaweb.pe'),
        'username' => env('IZIPAY_USERNAME'),
        'password' => env('IZIPAY_PASSWORD'),
        'public_key' => env('IZIPAY_PUBLIC_KEY'),
        'sha256_key' => env('IZIPAY_SHA256_KEY'),
        'webhook_path' => env('IZIPAY_WEBHOOK_PATH', 'cambia-este-segmento-secreto'),
        // URL completa de notificacion que se envia en cada operacion
        // (campo ipnTargetUrl, maximo 255 caracteres). Incluye el segmento
        // secreto, de modo que el punto de entrada no sea adivinable.
        'ipn_url' => env('IZIPAY_IPN_URL'),
        // Dominio desde el que el navegador carga el cliente JavaScript y el
        // tema del formulario embebido. Es publico por naturaleza: no es una
        // credencial, solo la ubicacion de un recurso estatico.
        'client_url' => env('IZIPAY_CLIENT_URL', 'https://static.micuentaweb.pe'),
        // Tema visual del formulario. La plataforma publica varios; el par
        // hoja de estilos + script debe corresponder al mismo tema.
        'client_theme' => env('IZIPAY_CLIENT_THEME', 'neon'),
        'currency' => env('IZIPAY_CURRENCY', 'PEN'),
        'timeout' => env('IZIPAY_TIMEOUT', 20),
    ],

    'sunat' => [
        'mode' => env('SUNAT_MODE', 'simulado'),
        'base_url' => env('SUNAT_BASE_URL'),
        'token' => env('SUNAT_TOKEN'),
        'timeout' => env('SUNAT_TIMEOUT', 20),
    ],

];
