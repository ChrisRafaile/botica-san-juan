<?php

namespace App\Http\Controllers;

use App\Models\EventoOperacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Perfil de la persona que ha iniciado sesión.
 * ---------------------------------------------------------------------------
 * POR QUE UN CONTROLADOR APARTE Y NO UsuarioController
 *
 * `UsuarioController` es el CRUD de administración: opera sobre el usuario que
 * diga el identificador de la URL. Aquí NUNCA se lee un identificador de la
 * petición: se actúa siempre sobre `$request->user()`. Esa es toda la
 * diferencia, y es la que impide que una cuenta cambie la foto —o la
 * contraseña— de otra cambiando un número en la URL.
 *
 * LA FOTO ES UN ARCHIVO, NO UNA CADENA
 *
 * Antes `foto_perfil` se validaba como `string|max:255` y se guardaba tal
 * cual: el cliente mandaba una ruta y el servidor la creía. Eso permitía
 * apuntar el avatar a cualquier cosa y no subía nada, de modo que el campo
 * acababa señalando archivos que no existían. Ahora el único camino para
 * escribir ese campo es subir un archivo real por aquí.
 */
class PerfilController extends Controller
{
    /** Carpeta dentro del disco público. */
    private const CARPETA = 'perfiles';

    /** Devuelve los datos de la cuenta actual. */
    public function mostrar(Request $request)
    {
        return response()->json($request->user());
    }

    /** Actualiza los datos personales de la cuenta actual. */
    public function actualizar(Request $request)
    {
        $usuario = $request->user();

        $validado = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => [
                'sometimes', 'required', 'email', 'max:255',
                Rule::unique('usuarios', 'email')->ignore($usuario->id),
            ],
            /* El rol NO se acepta aquí a propósito. Si se aceptara, cualquier
               cliente podría ascenderse a administrador editando su perfil.

               El DNI tampoco se CAMBIA: identifica a la persona ante la SUNAT
               y ya figura en los comprobantes emitidos.

               Pero sí se puede COMPLETAR una vez, y solo si está vacío. Ese
               es el caso de quien entró con Google: Google acredita un correo
               y no sabe el documento de nadie, así que esas cuentas nacen sin
               DNI. Sin esta puerta, la única forma de ponerlo sería tecleando
               el documento en cada compra, para siempre.

               `sometimes` + la comprobación de abajo: la regla de validación
               no puede por sí sola distinguir «vacío» de «ya tiene uno». */
            'dni' => ['sometimes', 'required', 'string', 'size:8', 'regex:/^\d{8}$/', Rule::unique('usuarios', 'dni')],
        ]);

        if (array_key_exists('dni', $validado) && ! $usuario->necesitaCompletarDni()) {
            return response()->json([
                'message' => 'Tu DNI ya está registrado y no se puede cambiar desde aquí. Si hay un error, avísanos en el mostrador.',
                'errors' => ['dni' => ['El DNI no se puede modificar.']],
            ], 422);
        }

        /* Si cambia el correo, la verificacion anterior deja de significar
           algo: acreditaba la direccion VIEJA. Mantener la marca permitiria
           registrarse con un correo propio, confirmarlo, y despues apuntar la
           cuenta a una direccion que nadie ha comprobado conservando el sello
           de verificada.

           Se compara en minusculas porque cambiar solo la caja no es cambiar
           de buzon, y obligaria a reverificar sin motivo. */
        $correoNuevo = isset($validado['email'])
            && Str::lower($validado['email']) !== Str::lower((string) $usuario->email);

        $usuario->fill($validado);

        if ($correoNuevo) {
            $usuario->email_verified_at = null;
        }

        $usuario->save();

        if ($correoNuevo) {
            /* Igual que en el registro: un fallo del servicio de correo no
               debe tumbar el guardado, que ya ocurrio. */
            try {
                $usuario->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar la verificacion tras cambiar el correo', [
                    'usuario_id' => $usuario->id,
                    'excepcion' => $e::class,
                ]);
            }
        }

        return response()->json($usuario->fresh());
    }

    /**
     * Sustituye la foto de perfil.
     *
     * Reglas que importan, y por qué:
     *
     * - `File::image()` comprueba el contenido, no la extensión. Un `.jpg` que
     *   por dentro es un script deja de pasar.
     * - El nombre lo genera Laravel. Usar el que trae el archivo permite
     *   recorridos de ruta y colisiones entre usuarios.
     * - Hay tope de peso y de dimensiones: sin ellos, una foto de 40 megapíxeles
     *   entra entera y luego hay que servirla en un círculo de 32 píxeles.
     * - La anterior se borra. Si no, cada cambio deja basura que nadie recoge.
     */
    public function subirFoto(Request $request)
    {
        $request->validate([
            'foto' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(2 * 1024)          // 2 MB
                    ->dimensions(
                        Rule::dimensions()->maxWidth(4000)->maxHeight(4000),
                    ),
            ],
        ], [
            'foto.required' => 'Elige una imagen.',
            'foto.image' => 'El archivo no es una imagen válida.',
            'foto.max' => 'La imagen no puede pesar más de 2 MB.',
        ]);

        $usuario = $request->user();
        $anterior = $usuario->foto_perfil;

        $ruta = $request->file('foto')->store(self::CARPETA, 'public');

        /* Se guarda con el prefijo `storage/` porque es la ruta pública: el
           enlace simbólico de `public/storage` apunta a `storage/app/public`.
           El frontend la resuelve contra el origen de la API sin saber nada
           más. */
        $usuario->forceFill(['foto_perfil' => 'storage/'.$ruta])->save();

        $this->borrarAnterior($anterior);

        EventoOperacion::info(
            'PERFIL_FOTO_ACTUALIZADA',
            "El usuario {$usuario->id} actualizó su foto de perfil.",
            ['usuario_id' => $usuario->id],
        );

        return response()->json([
            'mensaje' => 'Foto actualizada.',
            'foto_perfil' => $usuario->foto_perfil,
            'usuario' => $usuario->fresh(),
        ]);
    }

    /** Quita la foto y vuelve a las iniciales. */
    public function eliminarFoto(Request $request)
    {
        $usuario = $request->user();
        $anterior = $usuario->foto_perfil;

        $usuario->forceFill(['foto_perfil' => null])->save();
        $this->borrarAnterior($anterior);

        return response()->json([
            'mensaje' => 'Foto eliminada.',
            'usuario' => $usuario->fresh(),
        ]);
    }

    /**
     * Cambia la contraseña de la cuenta actual.
     *
     * Se exige la contraseña vigente aunque la sesión ya esté iniciada: un
     * equipo desatendido en el mostrador es el escenario habitual en una
     * botica, y sin esta comprobación bastaría con sentarse delante para
     * quedarse con la cuenta.
     */
    public function cambiarContrasena(Request $request)
    {
        $usuario = $request->user();

        $validado = $request->validate([
            'contrasena_actual' => ['required', 'string'],
            'contrasena' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validado['contrasena_actual'], $usuario->password)) {
            return response()->json([
                'message' => 'La contraseña actual no es correcta.',
                'errors' => ['contrasena_actual' => ['La contraseña actual no es correcta.']],
            ], 422);
        }

        /* `establecerContrasena` escribe el hash Y la marca de que la eligió
           una persona. Esa marca es la que después permite desvincular Google
           sin dejar la cuenta sin acceso. Ver Usuario. */
        $usuario->establecerContrasena($validado['contrasena']);
        $usuario->save();

        /* Se cierran las demás sesiones: si la contraseña se cambia porque se
           sospecha que alguien más la tenía, dejar sus tokens vivos vacía el
           cambio de sentido. El token con el que se hace la petición sobrevive
           para no echar de la aplicación a quien la está usando.

           `currentAccessToken()` puede devolver un token transitorio —en las
           pruebas, y con la sesión de navegador de Sanctum— que no tiene
           identificador. En ese caso se revocan todos los tokens personales,
           que es el lado seguro de equivocarse. */
        $actual = $usuario->currentAccessToken();
        $idActual = $actual instanceof \Laravel\Sanctum\PersonalAccessToken ? $actual->id : null;

        $usuario->tokens()
            ->when($idActual, fn ($consulta) => $consulta->where('id', '!=', $idActual))
            ->delete();

        EventoOperacion::info(
            'PERFIL_CONTRASENA_CAMBIADA',
            "El usuario {$usuario->id} cambió su contraseña; se revocaron sus otras sesiones.",
            ['usuario_id' => $usuario->id],
        );

        return response()->json(['mensaje' => 'Contraseña actualizada.']);
    }

    /**
     * Desvincula la cuenta de Google.
     *
     * LA REGLA QUE PROTEGE ESTE ENDPOINT
     *
     * Una cuenta nacida con Google tiene en `password` un hash aleatorio de 64
     * caracteres que nadie ha visto. Quitarle Google sin más la deja sin
     * ninguna vía de entrada: el botón ya no la reconoce y la contraseña no
     * existe para nadie. No es un error que se note al instante —la sesión en
     * curso sigue abierta— sino al día siguiente, cuando ya no se puede
     * arreglar desde dentro.
     *
     * Por eso el permiso se consulta al modelo, que exige contraseña propia y
     * correo verificado, y devuelve el motivo concreto cuando falta algo. Ver
     * Usuario::motivoParaNoDesvincularGoogle.
     *
     * NO SE PIDE LA CONTRASEÑA ACTUAL, y es deliberado: a diferencia de
     * cambiarla, esto no da acceso a nada. Resta una vía de entrada en lugar
     * de abrir una, así que el peor caso de un equipo desatendido es una
     * molestia reversible: el dueño vuelve a pulsar «entrar con Google».
     */
    public function desvincularGoogle(Request $request)
    {
        $usuario = $request->user();

        $motivo = $usuario->motivoParaNoDesvincularGoogle();

        if ($motivo !== null) {
            return response()->json([
                'message' => $motivo,
                'errors' => ['google' => [$motivo]],
            ], 422);
        }

        $usuario->forceFill([
            'google_id' => null,
            'google_vinculado_en' => null,
        ])->save();

        /* En la bitácora va el identificador, no el correo: un registro de
           operación no es el sitio donde deben acumularse direcciones. Y se
           anota porque perder una vía de acceso es exactamente el tipo de
           cambio que alguien querrá reconstruir después. */
        EventoOperacion::info(
            'PERFIL_GOOGLE_DESVINCULADO',
            "El usuario {$usuario->id} desvinculó su cuenta de Google.",
            ['usuario_id' => $usuario->id],
        );

        return response()->json([
            'mensaje' => 'Google desvinculado. A partir de ahora entra con tu correo y contraseña.',
            'usuario' => $usuario->fresh(),
        ]);
    }

    /**
     * Borra el archivo anterior, si lo había y vivía donde debe.
     *
     * La comprobación de la carpeta no es paranoia: en la base quedan valores
     * antiguos escritos cuando el campo era una cadena libre, y alguno apunta
     * fuera del disco de perfiles. Borrar a ciegas lo que diga la base sería
     * dejar que un valor viejo decida qué archivo se elimina.
     */
    private function borrarAnterior(?string $ruta): void
    {
        if (! $ruta) {
            return;
        }

        $relativa = preg_replace('#^storage/#', '', $ruta);

        if (! str_starts_with($relativa, self::CARPETA.'/')) {
            return;
        }

        Storage::disk('public')->delete($relativa);
    }
}
