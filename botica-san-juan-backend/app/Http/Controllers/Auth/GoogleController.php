<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Acceso con Google.
 * ---------------------------------------------------------------------------
 * EL TOKEN NO VIAJA EN LA URL
 *
 * El recorrido natural sería: la API recibe el retorno de Google, crea el
 * token de Sanctum y redirige al portal con el token en la dirección. Es lo
 * que se encuentra en la mitad de los tutoriales, y deja el token:
 *
 *   - en el historial del navegador,
 *   - en la cabecera Referer de la siguiente petición a cualquier tercero,
 *   - en los registros de acceso de cualquier intermediario,
 *   - y a la vista en la barra de direcciones, en el mostrador.
 *
 * Aquí la API redirige con un CÓDIGO de un solo uso que vive 60 segundos en
 * caché. El portal lo canjea por POST y recibe el token en el cuerpo de la
 * respuesta, que no se registra en ningún sitio. Un código robado del
 * historial no sirve: o ya se gastó, o caducó.
 *
 * EL PARÁMETRO `state` NO ES DECORATIVO
 *
 * Sin `state`, cualquiera puede inducir a una persona a completar un flujo de
 * OAuth que no empezó, y acabar con la sesión de OTRA cuenta de Google en su
 * navegador. Socialite lo guarda en la sesión, pero esta API es sin estado:
 * el `state` se genera aquí, se guarda en caché cinco minutos y se compara al
 * volver. Si no casa, el flujo se corta.
 *
 * LO QUE GOOGLE ACREDITA, Y LO QUE NO
 *
 * Google acredita el control de un correo. No sabe el DNI de nadie, y este
 * sistema identifica a las personas por DNI porque acaba en el comprobante.
 * Por eso una cuenta creada aquí nace SIN DNI y el documento se pide cuando
 * de verdad hace falta: al emitir el comprobante. Ver la migración
 * 2026_11_02_100000 para el razonamiento completo.
 */
class GoogleController extends Controller
{
    /** Ventana para completar el flujo en Google. */
    private const VIDA_STATE = 300;

    /** Ventana para canjear el código. Corta a propósito: el portal lo canjea
     *  en cuanto carga la pantalla de retorno. */
    private const VIDA_CODIGO = 60;

    // ------------------------------------------------------------- ida

    /**
     * Devuelve la URL de Google a la que el portal debe enviar al navegador.
     *
     * Devuelve JSON en lugar de redirigir porque quien llama es el portal,
     * desde otro origen: una redirección de la API no movería su ventana.
     */
    public function redirigir(Request $request): JsonResponse
    {
        if (! $this->configurado()) {
            return response()->json([
                'message' => 'El acceso con Google no está configurado en este entorno.',
            ], 503);
        }

        $state = Str::random(40);

        /* Se guarda el `state` junto con la IP que lo pidió. Comprobar las dos
           cosas al volver encarece el ataque: no basta con hacerse con el
           valor, hay que volver desde la misma dirección. */
        Cache::put($this->claveState($state), $request->ip(), self::VIDA_STATE);

        $url = Socialite::driver('google')
            ->stateless()
            ->scopes(['openid', 'profile', 'email'])
            ->with([
                'state' => $state,
                /* `select_account` evita el peor momento de este flujo: que
                   Google reutilice en silencio la sesión abierta y la persona
                   acabe entrando con una cuenta que no quería. En un equipo
                   compartido de mostrador eso es entrar como otra persona. */
                'prompt' => 'select_account',
            ])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    // ---------------------------------------------------------- vuelta

    /**
     * Retorno de Google. Termina siempre en el portal, nunca en un JSON.
     *
     * Quien llega aquí es el navegador de una persona, no un programa: un
     * error devuelto como JSON la dejaría mirando una llave y un corchete.
     */
    public function callback(Request $request): RedirectResponse
    {
        if (! $this->configurado()) {
            return $this->alPortal(['error' => 'no-configurado']);
        }

        /* Google avisa aquí cuando la persona pulsa «Cancelar». No es un
           fallo: es una decisión, y el portal la trata como tal. */
        if ($request->filled('error')) {
            return $this->alPortal(['error' => 'cancelado']);
        }

        if (! $this->stateValido($request)) {
            return $this->alPortal(['error' => 'state']);
        }

        try {
            $cuenta = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            /* En el log va el tipo de excepción, nunca el código de
               autorización ni el perfil: son credenciales y datos personales
               que no tienen por qué acumularse en un archivo de texto. */
            Log::warning('Fallo al resolver la cuenta de Google', ['excepcion' => $e::class]);

            return $this->alPortal(['error' => 'google']);
        }

        $correo = Str::lower(trim((string) $cuenta->getEmail()));

        if ($correo === '') {
            return $this->alPortal(['error' => 'sin-correo']);
        }

        /* Google puede devolver un correo NO verificado en algunos casos de
           Workspace. Vincular por un correo sin verificar permitiría tomar la
           cuenta de quien se registró con esa dirección. */
        if ($this->correoSinVerificar($cuenta)) {
            return $this->alPortal(['error' => 'correo-sin-verificar']);
        }

        $usuario = $this->vincularOCrear((string) $cuenta->getId(), $correo, (string) $cuenta->getName());

        if ($usuario === null) {
            return $this->alPortal(['error' => 'cuenta-ocupada']);
        }

        $codigo = Str::random(48);

        Cache::put($this->claveCodigo($codigo), $usuario->id, self::VIDA_CODIGO);

        return $this->alPortal(['codigo' => $codigo]);
    }

    /**
     * Canjea el código por el token de sesión. Un solo uso.
     */
    public function intercambiar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'size:48'],
        ]);

        $clave = $this->claveCodigo($datos['codigo']);

        /* `pull` lee y borra en la misma operación. Con `get` + `forget` dos
           peticiones simultáneas con el mismo código podrían colarse las dos
           entre la lectura y el borrado. */
        $usuarioId = Cache::pull($clave);

        if ($usuarioId === null) {
            return response()->json([
                'message' => 'Este acceso ya se usó o caducó. Vuelve a entrar con Google.',
            ], 422);
        }

        $usuario = Usuario::find($usuarioId);

        if ($usuario === null) {
            return response()->json(['message' => 'La cuenta ya no existe.'], 422);
        }

        return response()->json([
            'user' => $usuario,
            'token' => $usuario->createToken('Acceso con Google')->plainTextToken,
            'token_type' => 'Bearer',
            /* El portal lo usa para avisar de que falta el documento antes de
               que la persona llegue al checkout y se lleve la sorpresa. */
            'necesita_dni' => $usuario->necesitaCompletarDni(),
        ]);
    }

    // ------------------------------------------------------------ interno

    private function configurado(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function claveState(string $state): string
    {
        return 'google:state:'.hash('sha256', $state);
    }

    private function claveCodigo(string $codigo): string
    {
        /* En caché va el hash, no el código. Si alguien consigue leer la
           caché, lo que encuentra no sirve para canjear nada. */
        return 'google:codigo:'.hash('sha256', $codigo);
    }

    private function stateValido(Request $request): bool
    {
        $state = (string) $request->input('state');

        if ($state === '') {
            return false;
        }

        $ip = Cache::pull($this->claveState($state));

        return $ip !== null && hash_equals((string) $ip, (string) $request->ip());
    }

    /**
     * ¿El correo que devuelve Google está verificado?
     *
     * Socialite no expone `email_verified` en su interfaz, así que se lee del
     * arreglo crudo. Si el campo no viniera, se asume NO verificado: ante la
     * duda, el lado seguro de equivocarse es no vincular.
     */
    private function correoSinVerificar(object $cuenta): bool
    {
        $crudo = method_exists($cuenta, 'getRaw') ? $cuenta->getRaw() : [];

        return ($crudo['email_verified'] ?? $crudo['verified_email'] ?? false) !== true;
    }

    /**
     * Encuentra la cuenta, la vincula o la crea.
     *
     * TRES CASOS, Y EL DEL MEDIO ES EL DELICADO
     *
     * 1. Ya hay una cuenta con ese `google_id`: entra.
     * 2. Hay una cuenta con ese correo pero sin vincular: se vincula.
     * 3. No hay nada: se crea.
     *
     * El segundo es el que merece explicación, porque vincular por correo es
     * la vía clásica de apropiación de cuentas. Aquí es aceptable por una
     * razón concreta: Google ya acreditó el control de ese buzón —se
     * comprobó arriba que el correo está verificado—, y controlar el buzón es
     * exactamente lo que este sistema pide para dar por verificada una
     * dirección. Quien puede recibir el correo de recuperación puede, de
     * todas formas, hacerse con la cuenta.
     *
     * Por eso mismo la vinculación marca el correo como verificado: Google
     * acaba de demostrar lo que nuestro propio correo de verificación
     * demostraría.
     *
     * Lo que NO se hace es tocar la contraseña ni el rol. Una cuenta de
     * administrador que se vincule con Google sigue entrando con su
     * contraseña, y entrar con Google no asciende a nadie.
     */
    private function vincularOCrear(string $googleId, string $correo, string $nombre): ?Usuario
    {
        return DB::transaction(function () use ($googleId, $correo, $nombre) {
            $porGoogle = Usuario::where('google_id', $googleId)->lockForUpdate()->first();

            if ($porGoogle !== null) {
                /* El correo de una cuenta de Google puede cambiar. Manda el
                   identificador, que es lo que no cambia, y el correo se
                   actualiza salvo que ya lo tenga otra cuenta del sistema. */
                if ($porGoogle->email !== $correo && ! $this->correoDeOtro($correo, $porGoogle->id)) {
                    $porGoogle->forceFill(['email' => $correo])->save();
                }

                return $porGoogle;
            }

            $porCorreo = Usuario::where('email', $correo)->lockForUpdate()->first();

            if ($porCorreo !== null) {
                if ($porCorreo->google_id !== null) {
                    /* Ese correo ya cuelga de OTRA cuenta de Google. No se
                       toca: significa que alguien reusó la dirección. */
                    return null;
                }

                $porCorreo->forceFill([
                    'google_id' => $googleId,
                    'google_vinculado_en' => now(),
                    'email_verified_at' => $porCorreo->email_verified_at ?? now(),
                ])->save();

                return $porCorreo;
            }

            $usuario = Usuario::create([
                'nombre' => $nombre !== '' ? mb_substr($nombre, 0, 255) : 'Cliente',
                'email' => $correo,
                /* Sin DNI: Google no lo sabe y no se inventa. Se pide al
                   emitir el comprobante, que es cuando hace falta. */
                'dni' => null,
                /* Una contraseña aleatoria que nadie conoce ni puede usar: la
                   columna es obligatoria y el acceso de esta cuenta es
                   Google. Si quiere contraseña, la pide por «olvidé mi
                   contraseña», que va a su correo ya verificado. */
                'password' => Hash::make(Str::random(64)),
                'rol' => 'cliente',
                'google_id' => $googleId,
                'google_vinculado_en' => now(),
            ]);

            /* `email_verified_at` va en un `forceFill` y no en el `create`
               porque NO está en `$fillable`, y eso es deliberado: dejarlo
               asignable en masa significaría que cualquier endpoint que pase
               el cuerpo de la petición a `create()` o `update()` permitiría
               autoverificarse un correo.

               Pasarlo en el arreglo de arriba no daba error: la asignación
               en masa lo descarta en silencio. La cuenta quedaba sin
               verificar y el portal le pedía confirmar un correo que Google
               acababa de acreditar. */
            $usuario->forceFill(['email_verified_at' => now()])->save();

            return $usuario;
        });
    }

    private function correoDeOtro(string $correo, int $exceptoId): bool
    {
        return Usuario::where('email', $correo)->where('id', '!=', $exceptoId)->exists();
    }

    /** @param array<string, string> $parametros */
    private function alPortal(array $parametros): RedirectResponse
    {
        return redirect()->away(
            rtrim((string) config('app.frontend_url'), '/')
            .'/auth/google?'.http_build_query($parametros)
        );
    }
}
