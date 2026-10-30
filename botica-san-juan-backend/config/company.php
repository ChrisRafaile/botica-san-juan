<?php

return [
    'ruc' => env('COMPANY_RUC', '20123456789'),
    'razon_social' => env('COMPANY_RAZON_SOCIAL', 'Botica San Juan SAC'),

    /* El nombre comercial es con el que la conoce el cliente; la razon social
       es la que exige la SUNAT en el comprobante. No siempre coinciden, y
       cuando el contribuyente es persona natural no coinciden nunca. */
    'nombre_comercial' => env('COMPANY_NOMBRE_COMERCIAL', 'Botica San Juan'),
    'direccion' => env('COMPANY_DIRECCION', 'Av. Principal 123 - Lima'),
    'telefono' => env('COMPANY_TELEFONO', ''),
    'email' => env('COMPANY_EMAIL', ''),
    'logo_path' => env('COMPANY_LOGO_PATH', 'images/logo.png'),
];
