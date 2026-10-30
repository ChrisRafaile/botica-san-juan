<?php

namespace App\Services\Sunat;

use RuntimeException;

/**
 * Credenciales del emisor para el envío a la SUNAT.
 * ---------------------------------------------------------------------------
 * POR QUE UN OBJETO PARA TRES CADENAS
 *
 * Porque la regla que las une es fácil de incumplir y difícil de diagnosticar
 * después: el usuario que viaja en el sobre es el RUC y el usuario SOL
 * **pegados**, sin separador, y el usuario tiene que ser **secundario**. Con
 * el principal, el servicio rechaza la petición por política y el mensaje de
 * vuelta no lo dice con claridad.
 *
 * Teniendo esa regla en un solo sitio, nadie la reescribe mal en el segundo
 * sitio donde haga falta.
 *
 * LO QUE NUNCA SE REGISTRA
 *
 * La clave no aparece en `__toString`, ni en `toArray`, ni en el registro de
 * eventos. Un volcado de depuración con la clave SOL dentro es una fuga que
 * sobrevive en los registros mucho después de que a nadie le importe el error
 * que se estaba depurando.
 */
final class CredencialesSunat
{
    public function __construct(
        public readonly string $ruc,
        public readonly string $usuarioSol,
        public readonly string $clave,
    ) {
        if (strlen(preg_replace('/\D/', '', $ruc) ?? '') !== 11) {
            throw new RuntimeException('El RUC del emisor debe tener 11 dígitos.');
        }

        if (trim($usuarioSol) === '' || trim($clave) === '') {
            throw new RuntimeException(
                'Faltan el usuario SOL secundario o su clave. Revisa SUNAT_SOL_USUARIO y SUNAT_SOL_CLAVE.'
            );
        }
    }

    public static function desdeConfiguracion(): self
    {
        return new self(
            ruc: (string) config('company.ruc', ''),
            usuarioSol: (string) config('services.sunat.sol_usuario', ''),
            clave: (string) config('services.sunat.sol_clave', ''),
        );
    }

    /** RUC y usuario SOL pegados, que es como los espera el sobre. */
    public function usuarioCompleto(): string
    {
        return $this->ruc.trim($this->usuarioSol);
    }

    /**
     * Representación segura para la bitácora.
     *
     * @return array<string, string>
     */
    public function paraBitacora(): array
    {
        return [
            'ruc' => $this->ruc,
            'usuario' => $this->usuarioCompleto(),
            // La clave no se incluye, y no es un olvido.
        ];
    }
}
