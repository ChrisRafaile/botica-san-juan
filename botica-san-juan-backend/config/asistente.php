<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Interruptor general del asistente IA
    |--------------------------------------------------------------------------
    | Aunque esté habilitado, si no existe ninguna clave de proveedor configurada
    | en el entorno, el sistema no carga el SDK de IA y responde 503 controlado.
    */
    'habilitado' => (bool) env('ASISTENTE_HABILITADO', true),

    /*
    |--------------------------------------------------------------------------
    | Proveedor primario y modelos por proveedor (tier gratuito)
    |--------------------------------------------------------------------------
    */
    // ASISTENTE_IA_MODELO fija el modelo del proveedor primario (Groq por defecto).
    'proveedor_primario' => env('ASISTENTE_IA_PROVEEDOR', env('ASISTENTE_PROVEEDOR', 'groq')),

    'proveedores' => [
        'gemini' => [
            'modelo'  => env('ASISTENTE_GEMINI_MODELO', 'gemini-flash-latest'),
            'key_env' => 'GEMINI_API_KEY',
        ],
        'groq' => [
            'modelo'  => env('ASISTENTE_IA_MODELO', env('ASISTENTE_GROQ_MODELO', 'openai/gpt-oss-120b')),
            'key_env' => 'GROQ_API_KEY',
        ],
        'openrouter' => [
            'modelo'  => env('ASISTENTE_OPENROUTER_MODELO', 'google/gemini-2.0-flash-exp:free'),
            'key_env' => 'OPENROUTER_API_KEY',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Orden de respaldo (failover)
    |--------------------------------------------------------------------------
    */
    'fallback' => ['groq', 'gemini', 'openrouter'],

    /*
    |--------------------------------------------------------------------------
    | Límites operativos y de seguridad
    |--------------------------------------------------------------------------
    */
    'limites' => [
        'max_mensajes'           => (int) env('ASISTENTE_MAX_MENSAJES', 12),
        'max_longitud'           => (int) env('ASISTENTE_MAX_LONGITUD', 1000),
        'max_pasos_herramientas' => (int) env('ASISTENTE_MAX_PASOS', 4),
        'timeout'                => (int) env('ASISTENTE_TIMEOUT', 25),
        'rate_limit_por_minuto'  => (int) env('ASISTENTE_RATE_LIMIT', 15),
    ],
];
