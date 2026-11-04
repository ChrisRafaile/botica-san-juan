<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Recuperacion de contrasena.
 * ---------------------------------------------------------------------------
 * LA REGLA QUE GOBIERNA ESTE CONTROLADOR: NO SE REVELA QUIEN TIENE CUENTA
 *
 * `solicitar` responde EXACTAMENTE lo mismo exista o no la cuenta, y con el
 * mismo codigo 200. Distinguir los dos casos convierte el formulario en un
 * verificador de correos: se prueban mil direcciones y se sabe cuales son
 * clientes de la botica. En una botica eso no es un dato cualquiera —dice que
 * esa persona compra medicamentos aqui—, y el formulario es publico.
 *
 * Por el mismo motivo:
 *   - el limitador cuenta por IP y por correo, no solo por IP;
 *   - el log registra el intento SIN decir si habia cuenta detras;
 *   - `restablecer` no distingue "token invalido" de "correo desconocido".
 *
 * El coste de esta decision es real: quien se equivoca al teclear su correo no
 * recibe nada y no se le avisa. Se asume, y por eso el mensaje dice "si existe
 * una cuenta" en lugar de prometer un correo que quizas no llegue.
 */
class ContrasenaController extends Controller
{
    /**
     * Envia el enlace de restablecimiento, si hay cuenta.
     */
    public function solicitar(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        /* El resultado del broker se descarta a proposito. Tanto
           RESET_LINK_SENT como INVALID_USER salen por la misma puerta. */
        Password::broker()->sendResetLink(['email' => $validado['email']]);

        return response()->json([
            'message' => 'Si existe una cuenta con ese correo, te enviamos un enlace para restablecer la contraseña. Revisa también la carpeta de no deseados.',
        ]);
    }

    /**
     * Fija la contrasena nueva a partir del token del correo.
     */
    public function restablecer(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $resultado = Password::broker()->reset(
            $validado,
            function (Usuario $usuario, string $contrasena) {
                /* El ejemplo de Laravel renueva aqui `remember_token`. La
                   tabla `usuarios` no tiene esa columna, y no es un olvido:
                   esta API no usa la cookie de "recuerdame", autentica con
                   tokens de Sanctum. Lo que hay que invalidar son esos
                   tokens, y es lo que se hace justo debajo. */
                /* `establecerContrasena` deja además la marca de que la
                   contraseña la eligió una persona. Importa en este flujo más
                   que en ninguno: es por aquí por donde una cuenta nacida con
                   Google consigue su primera contraseña propia, y por tanto la
                   posibilidad de desvincular Google después. */
                $usuario->establecerContrasena($contrasena);
                $usuario->save();

                /* Se revocan TODOS los tokens, sin excepcion. A diferencia del
                   cambio de contrasena desde el perfil —donde se conserva el
                   token de quien esta usando la aplicacion—, aqui el punto de
                   partida es que la contrasena anterior pudo estar en manos de
                   otra persona. Dejar una sola sesion viva vaciaria de sentido
                   el restablecimiento.

                   Quien restablece no tiene sesion abierta en este flujo: llega
                   desde un correo, sin token. No se le expulsa de nada. */
                $usuario->tokens()->delete();

                event(new PasswordReset($usuario));
            }
        );

        if ($resultado !== Password::PASSWORD_RESET) {
            /* Un solo mensaje para token caducado, token ya usado, token
               manipulado y correo inexistente. Separarlos diria al atacante
               cual de las cuatro cosas acerto. */
            return response()->json([
                'message' => 'El enlace ya no es válido. Pide uno nuevo desde «Olvidé mi contraseña».',
                'errors' => ['token' => ['El enlace ya no es válido.']],
            ], 422);
        }

        return response()->json([
            'message' => 'Tu contraseña quedó actualizada. Ya puedes iniciar sesión.',
        ]);
    }
}
