<?php

/*
|--------------------------------------------------------------------------
| Mensajes de validación en español
|--------------------------------------------------------------------------
|
| POR QUÉ EXISTE ESTE ARCHIVO
|
| Al poner la aplicación en español (APP_LOCALE=es) se añadió lang/es.json,
| que cubre las frases sueltas de las plantillas de correo. Lo que NO cubre
| son los mensajes de validación, que Laravel busca en un archivo de arreglo,
| no en el JSON. Sin este archivo, cada campo mal rellenado devolvía la clave
| en crudo:
|
|     {"message":"validation.required","errors":{"archivo":["validation.required"]}}
|
| Y eso se veía en pantalla, literalmente, en cualquier formulario de la
| aplicación. Se descubrió al adjuntar una receta, pero afectaba a todo:
| registro, perfil, contacto, productos.
|
| El archivo está completo a propósito. Traducir solo las reglas que hoy se
| usan dejaría una trampa para la siguiente: el día que alguien añada un
| `->before()` o un `mimetypes`, el mensaje volvería a salir en crudo sin que
| ninguna prueba lo note, porque las pruebas comprueban QUÉ campo falla, no
| qué dice el mensaje.
|
| Como red de seguridad adicional, APP_FALLBACK_LOCALE vuelve a `en`: si
| alguna clave futura faltara aquí, saldrá en inglés —legible— en lugar de
| como «validation.loquesea».
|
*/

return [

    'accepted' => 'Debes aceptar :attribute.',
    'accepted_if' => 'Debes aceptar :attribute cuando :other sea :value.',
    'active_url' => ':attribute no es una dirección web válida.',
    'after' => ':attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => ':attribute debe ser una fecha posterior o igual a :date.',
    'alpha' => ':attribute solo puede contener letras.',
    'alpha_dash' => ':attribute solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num' => ':attribute solo puede contener letras y números.',
    'any_of' => ':attribute no es válido.',
    'array' => ':attribute debe ser una lista.',
    'ascii' => ':attribute solo puede contener caracteres y símbolos de un byte.',
    'before' => ':attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => ':attribute debe ser una fecha anterior o igual a :date.',
    'between' => [
        'array' => ':attribute debe tener entre :min y :max elementos.',
        'file' => ':attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => ':attribute debe estar entre :min y :max.',
        'string' => ':attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => ':attribute debe ser verdadero o falso.',
    'can' => ':attribute contiene un valor no autorizado.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'contains' => 'A :attribute le falta un valor obligatorio.',
    'current_password' => 'La contraseña no es correcta.',
    'date' => ':attribute no es una fecha válida.',
    'date_equals' => ':attribute debe ser una fecha igual a :date.',
    'date_format' => ':attribute no corresponde al formato :format.',
    'decimal' => ':attribute debe tener :decimal decimales.',
    'declined' => 'Debes rechazar :attribute.',
    'declined_if' => 'Debes rechazar :attribute cuando :other sea :value.',
    'different' => ':attribute y :other deben ser distintos.',
    'digits' => ':attribute debe tener :digits dígitos.',
    'digits_between' => ':attribute debe tener entre :min y :max dígitos.',
    'dimensions' => 'Las dimensiones de la imagen de :attribute no son válidas.',
    'distinct' => ':attribute tiene un valor repetido.',
    'doesnt_contain' => ':attribute no puede contener ninguno de estos valores: :values.',
    'doesnt_end_with' => ':attribute no puede terminar por ninguno de estos valores: :values.',
    'doesnt_start_with' => ':attribute no puede empezar por ninguno de estos valores: :values.',
    'email' => ':attribute no es un correo electrónico válido.',
    'encoding' => ':attribute no usa la codificación de caracteres correcta.',
    'ends_with' => ':attribute debe terminar por uno de estos valores: :values.',
    'enum' => 'El valor de :attribute no es válido.',
    'exists' => 'El valor de :attribute no existe.',
    'extensions' => ':attribute debe tener una de estas extensiones: :values.',
    'file' => ':attribute debe ser un archivo.',
    'filled' => ':attribute no puede quedar vacío.',
    'gt' => [
        'array' => ':attribute debe tener más de :value elementos.',
        'file' => ':attribute debe pesar más de :value kilobytes.',
        'numeric' => ':attribute debe ser mayor que :value.',
        'string' => ':attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'array' => ':attribute debe tener :value elementos o más.',
        'file' => ':attribute debe pesar :value kilobytes o más.',
        'numeric' => ':attribute debe ser mayor o igual que :value.',
        'string' => ':attribute debe tener :value caracteres o más.',
    ],
    'hex_color' => ':attribute debe ser un color hexadecimal válido.',
    'image' => ':attribute debe ser una imagen.',
    'in' => 'El valor de :attribute no es válido.',
    'in_array' => ':attribute no está entre los valores de :other.',
    'in_array_keys' => ':attribute debe contener al menos una de estas claves: :values.',
    'integer' => ':attribute debe ser un número entero.',
    'ip' => ':attribute debe ser una dirección IP válida.',
    'ipv4' => ':attribute debe ser una dirección IPv4 válida.',
    'ipv6' => ':attribute debe ser una dirección IPv6 válida.',
    'json' => ':attribute debe ser un texto JSON válido.',
    'list' => ':attribute debe ser una lista.',
    'lowercase' => ':attribute debe ir en minúsculas.',
    'lt' => [
        'array' => ':attribute debe tener menos de :value elementos.',
        'file' => ':attribute debe pesar menos de :value kilobytes.',
        'numeric' => ':attribute debe ser menor que :value.',
        'string' => ':attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'array' => ':attribute no debe tener más de :value elementos.',
        'file' => ':attribute no debe pesar más de :value kilobytes.',
        'numeric' => ':attribute debe ser menor o igual que :value.',
        'string' => ':attribute no debe tener más de :value caracteres.',
    ],
    'mac_address' => ':attribute debe ser una dirección MAC válida.',
    'max' => [
        'array' => ':attribute no puede tener más de :max elementos.',
        'file' => ':attribute no puede pesar más de :max kilobytes.',
        'numeric' => ':attribute no puede ser mayor que :max.',
        'string' => ':attribute no puede tener más de :max caracteres.',
    ],
    'max_digits' => ':attribute no puede tener más de :max dígitos.',
    'mimes' => ':attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => ':attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => ':attribute debe tener al menos :min elementos.',
        'file' => ':attribute debe pesar al menos :min kilobytes.',
        'numeric' => ':attribute debe ser al menos :min.',
        'string' => ':attribute debe tener al menos :min caracteres.',
    ],
    'min_digits' => ':attribute debe tener al menos :min dígitos.',
    'missing' => ':attribute no debe enviarse.',
    'missing_if' => ':attribute no debe enviarse cuando :other sea :value.',
    'missing_unless' => ':attribute no debe enviarse salvo que :other sea :value.',
    'missing_with' => ':attribute no debe enviarse si está presente :values.',
    'missing_with_all' => ':attribute no debe enviarse si están presentes :values.',
    'multiple_of' => ':attribute debe ser múltiplo de :value.',
    'not_in' => 'El valor de :attribute no es válido.',
    'not_regex' => 'El formato de :attribute no es válido.',
    'numeric' => ':attribute debe ser un número.',
    'password' => [
        'letters' => ':attribute debe contener al menos una letra.',
        'mixed' => ':attribute debe contener al menos una mayúscula y una minúscula.',
        'numbers' => ':attribute debe contener al menos un número.',
        'symbols' => ':attribute debe contener al menos un símbolo.',
        'uncompromised' => 'Esta contraseña ha aparecido en filtraciones de datos. Elige otra.',
    ],
    'present' => ':attribute debe enviarse.',
    'present_if' => ':attribute debe enviarse cuando :other sea :value.',
    'present_unless' => ':attribute debe enviarse salvo que :other sea :value.',
    'present_with' => ':attribute debe enviarse si está presente :values.',
    'present_with_all' => ':attribute debe enviarse si están presentes :values.',
    'prohibited' => ':attribute no está permitido.',
    'prohibited_if' => ':attribute no está permitido cuando :other sea :value.',
    'prohibited_if_accepted' => ':attribute no está permitido cuando se acepta :other.',
    'prohibited_if_declined' => ':attribute no está permitido cuando se rechaza :other.',
    'prohibited_unless' => ':attribute no está permitido salvo que :other esté entre :values.',
    'prohibits' => ':attribute impide que se envíe :other.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => ':attribute es obligatorio.',
    'required_array_keys' => ':attribute debe incluir las claves: :values.',
    'required_if' => ':attribute es obligatorio cuando :other es :value.',
    'required_if_accepted' => ':attribute es obligatorio cuando se acepta :other.',
    'required_if_declined' => ':attribute es obligatorio cuando se rechaza :other.',
    'required_unless' => ':attribute es obligatorio salvo que :other esté entre :values.',
    'required_with' => ':attribute es obligatorio si está presente :values.',
    'required_with_all' => ':attribute es obligatorio si están presentes :values.',
    'required_without' => ':attribute es obligatorio si no está presente :values.',
    'required_without_all' => ':attribute es obligatorio si no está presente ninguno de :values.',
    'same' => ':attribute y :other deben coincidir.',
    'size' => [
        'array' => ':attribute debe tener :size elementos.',
        'file' => ':attribute debe pesar :size kilobytes.',
        'numeric' => ':attribute debe ser :size.',
        'string' => ':attribute debe tener :size caracteres.',
    ],
    'starts_with' => ':attribute debe empezar por uno de estos valores: :values.',
    'string' => ':attribute debe ser texto.',
    'timezone' => ':attribute debe ser una zona horaria válida.',
    'unique' => ':attribute ya está registrado.',
    'uploaded' => 'No se pudo subir :attribute. Puede que el archivo sea demasiado grande.',
    'uppercase' => ':attribute debe ir en mayúsculas.',
    'url' => ':attribute no es una dirección web válida.',
    'ulid' => ':attribute debe ser un ULID válido.',
    'uuid' => ':attribute debe ser un UUID válido.',

    /*
    |----------------------------------------------------------------------
    | Mensajes propios
    |----------------------------------------------------------------------
    |
    | Aquí van los casos donde el mensaje genérico no basta porque la
    | persona no sabría qué corregir.
    |
    */

    'custom' => [
        'archivo' => [
            'required' => 'Adjunta la receta: una foto o un PDF.',
            'max' => 'La receta no puede pesar más de 8 MB. Si es una foto, bájale la calidad.',
        ],
        'dni' => [
            'size' => 'El DNI tiene 8 dígitos.',
            'unique' => 'Ya hay una cuenta registrada con ese DNI.',
        ],
        'email' => [
            'unique' => 'Ya hay una cuenta registrada con ese correo.',
        ],
        'motivo_rechazo' => [
            'required' => 'Escribe por qué se rechaza: el cliente solo verá esto.',
        ],
        'acepta_terminos' => [
            'required' => 'Tienes que aceptar los términos para crear la cuenta.',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Nombres de los campos
    |----------------------------------------------------------------------
    |
    | Sin esto, el mensaje dice «motivo_rechazo es obligatorio», con el
    | guion bajo y todo, que es el nombre de la columna y no el de la cosa.
    |
    */

    'attributes' => [
        'archivo' => 'la receta',
        'nombre' => 'el nombre',
        'email' => 'el correo electrónico',
        'password' => 'la contraseña',
        'password_confirmation' => 'la confirmación de la contraseña',
        'contrasena' => 'la contraseña',
        'contrasena_actual' => 'la contraseña actual',
        'dni' => 'el DNI',
        'telefono' => 'el teléfono',
        'foto' => 'la foto',
        'estado' => 'el estado',
        'motivo_rechazo' => 'el motivo del rechazo',
        'token' => 'el enlace',
        'acepta_terminos' => 'la aceptación de los términos',
        'productos' => 'los productos',
        'cantidad' => 'la cantidad',
        'precio' => 'el precio',
        'stock' => 'el stock',
        'categoria' => 'la categoría',
        'laboratorio' => 'el laboratorio',
        'presentacion' => 'la presentación',
        'concentracion' => 'la concentración',
        'mensaje' => 'el mensaje',
        'asunto' => 'el asunto',
    ],

];
