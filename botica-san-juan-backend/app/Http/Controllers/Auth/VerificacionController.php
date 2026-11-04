<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Verificacion del correo de la cuenta.
 * ---------------------------------------------------------------------------
 * POR QUE EL ENLACE DEL CORREO APUNTA A LA API Y NO AL PORTAL
 *
 * El primer intento fue al reves: el correo llevaba a una pantalla del portal
 * que recibia el enlace firmado y lo invocaba con `fetch`. No funciono, y el
 * motivo no era un descuido sino la forma del problema: la API y el portal
 * viven en origenes distintos, de modo que esa llamada es una peticion entre
 * origenes. En la prueba la bloqueo el propio navegador antes incluso de
 * llegar a CORS. Montarlo asi obligaba a abrir CORS para un enlace que llega
 * por correo, que es justo el tipo de cosa que conviene no hacer.
 *
 * Ahora la persona abre el enlace de la API directamente —el navegador navega,
 * no hace una llamada de fondo— y la API responde con una redireccion al
 * portal llevando el resultado en un parametro. Sin peticion entre origenes,
 * sin CORS, y sin que el cliente vea nunca un JSON.
 *
 * LA FIRMA SE COMPRUEBA A MANO, A PROPOSITO
 *
 * El middleware `signed` devuelve un 403 con la pagina de error de Laravel.
 * Eso, para quien acaba de pulsar un enlace en su correo, es una pantalla en
 * ingles sin salida. Comprobandola aqui se puede redirigir al portal y
 * explicarle en su idioma que el enlace caduco y como pedir otro.
 */
class VerificacionController extends Controller
{
    /** Resultados que entiende la pantalla del portal. */
    private const CONFIRMADO = 'confirmado';
    private const YA_CONFIRMADO = 'ya-confirmado';
    private const INVALIDO = 'invalido';

    public function verificar(Request $request, string $id, string $hash): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->alPortal(self::INVALIDO);
        }

        $usuario = Usuario::find($id);

        /* El hash cubre un caso que la firma no ve: que el usuario haya
           CAMBIADO de correo despues de pedir la verificacion. Ese enlace
           acredita una direccion que ya no es la de la cuenta. */
        if (! $usuario || ! hash_equals($hash, sha1($usuario->getEmailForVerification()))) {
            return $this->alPortal(self::INVALIDO);
        }

        if ($usuario->hasVerifiedEmail()) {
            /* Volver a pulsar el enlace no es un fallo: para el cliente su
               correo esta confirmado, que era lo que queria. */
            return $this->alPortal(self::YA_CONFIRMADO);
        }

        $usuario->markEmailAsVerified();

        return $this->alPortal(self::CONFIRMADO);
    }

    /**
     * Reenvia el correo de verificacion al usuario de la sesion.
     *
     * Exige sesion a proposito: si aceptara un correo arbitrario seria un
     * motor de envio gratuito contra terceros, y ademas revelaria que cuentas
     * existen segun si el envio ocurre o no.
     */
    public function reenviar(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario->hasVerifiedEmail()) {
            return response()->json([
                'enviado' => false,
                'message' => 'Tu correo ya está confirmado.',
            ]);
        }

        $usuario->sendEmailVerificationNotification();

        return response()->json([
            'enviado' => true,
            'message' => 'Te enviamos un correo de confirmación. Revisa tu bandeja y la carpeta de no deseados.',
        ]);
    }

    private function alPortal(string $resultado): RedirectResponse
    {
        return redirect()->away(
            rtrim((string) config('app.frontend_url'), '/')
            .'/verificar-correo?resultado='.$resultado
        );
    }
}
