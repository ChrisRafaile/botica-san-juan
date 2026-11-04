<?php

/*
| Mensajes del gestor de contraseñas de Laravel.
|
| Los dos últimos NO los usa esta aplicación: el controlador de recuperación
| responde siempre lo mismo exista o no la cuenta, precisamente para que el
| formulario no sirva de verificador de correos. Se traducen igualmente para
| que ningún camino futuro devuelva la clave en crudo.
*/

return [

    'reset' => 'Tu contraseña quedó actualizada.',
    'sent' => 'Te enviamos un enlace para restablecer la contraseña.',
    'throttled' => 'Espera unos momentos antes de volver a intentarlo.',
    'token' => 'El enlace ya no es válido.',
    'user' => 'No se pudo completar la operación.',

];
