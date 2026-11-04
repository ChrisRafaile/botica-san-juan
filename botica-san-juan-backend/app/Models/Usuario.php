<?php

namespace App\Models;

use App\Notifications\RestablecerContrasena;
use App\Notifications\VerificarCorreo;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'dni',
        'telefono',
        'foto',
        'foto_perfil',
        'foto_portada',
        'rol',
        'mfa_enabled',
        'mfa_secret',
        'mfa_enabled_at',
        'google_id',
        'google_vinculado_en',
    ];

    protected $hidden = [
        'password',
        'mfa_secret',
        /* El identificador de Google no es un secreto, pero tampoco tiene
           nada que hacer en una respuesta de la API: nadie lo necesita fuera
           del backend y publicarlo solo da material para correlacionar
           cuentas entre servicios. El portal pregunta por `tiene_google`. */
        'google_id',
    ];

    protected $appends = [
        'tiene_google',
        'tiene_contrasena',
        'puede_desvincular_google',
    ];

    protected $casts = [
        'password' => 'hashed',
        'mfa_enabled' => 'boolean',
        'mfa_enabled_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'google_vinculado_en' => 'datetime',
        'contrasena_establecida_en' => 'datetime',
    ];

    /* ------------------------------------------------------------------
     | Correo transaccional
     |
     | Las dos notificaciones de Laravel se sustituyen por las propias por
     | dos razones concretas, no por gusto:
     |
     |   1. Las de serie estan en ingles y firman "Laravel".
     |   2. Las de serie construyen el enlace contra la URL de la API. Quien
     |      recibe el correo abre el enlace en su navegador, y la API solo
     |      devuelve JSON: el enlace tiene que llevar al portal.
     |
     | Se sobrescriben los dos metodos en lugar de registrar callbacks en un
     | proveedor de servicios para que, leyendo el modelo, se vea que estos
     | correos existen.
     */

    /* ------------------------------------------------------------------
     | Acceso con Google
     */

    /**
     * ¿Esta cuenta entra con Google?
     *
     * Se expone esto y no el identificador: al portal le basta saber que el
     * botón de Google funciona para esta cuenta, y el `sub` de Google no
     * tiene nada que hacer en una respuesta de la API.
     */
    public function getTieneGoogleAttribute(): bool
    {
        return $this->google_id !== null;
    }

    /**
     * Una cuenta creada con Google nace sin DNI, y el comprobante lo exige.
     *
     * Esta pregunta la hacen dos sitios: el checkout, para pedir el documento
     * como ya se lo pide a un invitado, y el perfil, para ofrecer
     * completarlo. Está aquí para que no haya dos formas de preguntarlo.
     */
    public function necesitaCompletarDni(): bool
    {
        return blank($this->dni);
    }

    /* ------------------------------------------------------------------
     | Contraseña y vías de acceso
     |
     | Las tres cosas que siguen existen por un motivo que no se ve leyendo
     | la tabla: la columna `password` NUNCA es nula. Una cuenta creada con
     | Google lleva un hash aleatorio de 64 caracteres que nadie ha visto.
     | Preguntar `password !== null` responde «sí» para esa cuenta y es una
     | respuesta falsa a la pregunta que importa.
     */

    /**
     * Guarda una contraseña ELEGIDA POR UNA PERSONA.
     *
     * Este es el único camino por el que debe escribirse `password` cuando el
     * valor lo eligió alguien: el registro, el cambio desde el perfil, el
     * restablecimiento por correo y la edición desde administración pasan por
     * aquí. Así la marca y el hash se escriben juntos y no hay forma de
     * actualizar uno olvidando el otro.
     *
     * La contraseña aleatoria de una cuenta nacida con Google NO pasa por
     * aquí, a propósito: no la conoce nadie y marcarla sería mentir.
     *
     * NO guarda: rellena el modelo y deja el `save()` a quien llama. Así vale
     * igual para una cuenta que ya existe y para una que se está creando
     * —`new Usuario([...])` antes del primer `save()`—, que es lo que hace el
     * registro.
     */
    public function establecerContrasena(string $enClaro): void
    {
        $this->forceFill([
            'password' => Hash::make($enClaro),
            'contrasena_establecida_en' => now(),
        ]);
    }

    /** ¿Existe una contraseña que esta persona conozca? */
    public function getTieneContrasenaAttribute(): bool
    {
        return $this->contrasena_establecida_en !== null;
    }

    /**
     * ¿Se puede desvincular Google sin dejar la cuenta sin acceso?
     *
     * Devuelve el MOTIVO del bloqueo, o `null` si se puede. Un booleano
     * obligaría a quien lo use a averiguar aparte qué falta, y lo que falta es
     * justo lo que hay que decirle a la persona.
     *
     * Las dos condiciones no son intercambiables:
     *
     *   - Sin contraseña propia, al quitar Google no queda ninguna credencial.
     *   - Con contraseña pero sin correo verificado, sí queda credencial, pero
     *     no queda forma de RECUPERARLA: el enlace de «olvidé mi contraseña»
     *     va a una dirección que nadie ha confirmado. Es la misma cuenta
     *     perdida, solo que más tarde.
     */
    public function motivoParaNoDesvincularGoogle(): ?string
    {
        if (! $this->tiene_google) {
            return 'Esta cuenta no está vinculada con Google.';
        }

        if (! $this->tiene_contrasena) {
            return 'Antes de desvincular Google necesitas una contraseña propia. '
                .'Pídela desde «olvidé mi contraseña»: el enlace llega al correo '
                .'que Google ya verificó.';
        }

        if (! $this->hasVerifiedEmail()) {
            return 'Antes de desvincular Google necesitas confirmar tu correo. '
                .'Sin eso no podrías recuperar la contraseña si la olvidas.';
        }

        return null;
    }

    public function getPuedeDesvincularGoogleAttribute(): bool
    {
        return $this->tiene_google && $this->motivoParaNoDesvincularGoogle() === null;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerificarCorreo);
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RestablecerContrasena($token));
    }

    public function carritos()
    {
        return $this->hasMany(Carrito::class, 'usuario_id');
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'usuario_id');
    }
}
