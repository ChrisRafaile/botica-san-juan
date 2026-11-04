<?php

/*
| Mensajes de autenticación.
|
| «failed» no distingue entre usuario inexistente y contraseña equivocada, y
| eso es deliberado: separarlos convertiría el formulario de acceso en un
| comprobador de qué documentos están registrados en la botica.
*/

return [

    'failed' => 'Las credenciales no son correctas.',
    'password' => 'La contraseña no es correcta.',
    'throttle' => 'Demasiados intentos. Vuelve a intentarlo en :seconds segundos.',

];
