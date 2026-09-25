<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use Illuminate\Console\Command;

/**
 * Revoca los tokens creados por `dev:token`.
 *
 *   php artisan dev:token:revocar
 *
 * `dev:token` terminaba diciendo "Revocalo al terminar: php artisan
 * dev:token:revocar", pero ese comando no existia: el mensaje mandaba a una
 * puerta cerrada y los tokens de prueba se quedaban vivos en la base entre
 * sesion y sesion. Un token de Sanctum no caduca solo.
 *
 * Solo borra los tokens con el nombre que pone `dev:token`, para no cerrar de
 * paso la sesion de nadie mas.
 */
class RevocarTokenDesarrollo extends Command
{
    protected $signature = 'dev:token:revocar {--email= : Correo del usuario; por defecto todos}';
    protected $description = 'Revoca los tokens de API creados por dev:token';

    /** Debe coincidir con el nombre que usa TokenDesarrollo al crearlos. */
    private const NOMBRE = 'dev-token';

    public function handle(): int
    {
        if (!app()->environment('local')) {
            $this->error('Solo disponible en entorno local.');

            return self::FAILURE;
        }

        $usuarios = $this->option('email')
            ? Usuario::where('email', $this->option('email'))->get()
            : Usuario::all();

        if ($usuarios->isEmpty()) {
            $this->error('No se encontro ningun usuario.');

            return self::FAILURE;
        }

        $revocados = 0;

        foreach ($usuarios as $usuario) {
            $revocados += $usuario->tokens()->where('name', self::NOMBRE)->delete();
        }

        $this->newLine();

        if ($revocados === 0) {
            $this->comment('No habia tokens de desarrollo activos.');
        } else {
            $this->info("Tokens de desarrollo revocados: {$revocados}");
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
