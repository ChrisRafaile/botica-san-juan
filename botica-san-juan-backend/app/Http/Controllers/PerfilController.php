<?php

namespace App\Http\Controllers;

use App\Models\EventoOperacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
               Lo mismo con el DNI, que identifica a la persona ante la SUNAT
               y no es algo que se corrija desde una pantalla de perfil. */
        ]);

        $usuario->fill($validado)->save();

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

        $usuario->forceFill(['password' => Hash::make($validado['contrasena'])])->save();

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
