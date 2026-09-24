<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use Illuminate\Console\Command;

/**
 * Emite un token de API para probar endpoints protegidos en desarrollo.
 *
 * Se niega a ejecutarse fuera del entorno local: un comando que fabrica
 * credenciales de administrador no debe existir en producción ni por descuido.
 *
 *   php artisan dev:token
 *   php artisan dev:token --email=admin@botica.pe
 */
class TokenDesarrollo extends Command
{
    protected $signature = 'dev:token {--email= : Correo del usuario; por defecto el primer administrador}';
    protected $description = 'Genera un token de API para pruebas locales (solo entorno local)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Este comando solo puede ejecutarse en el entorno local.');

            return self::FAILURE;
        }

        $usuario = $this->option('email')
            ? Usuario::where('email', $this->option('email'))->first()
            : Usuario::where('rol', 'administrador')->first();

        if ($usuario === null) {
            $this->error('No se encontro un usuario administrador.');

            return self::FAILURE;
        }

        /* Se revocan los tokens de prueba anteriores para no acumularlos. */
        $usuario->tokens()->where('name', 'dev-token')->delete();

        $token = $usuario->createToken('dev-token')->plainTextToken;

        $this->newLine();
        $this->info("Usuario: {$usuario->nombre} <{$usuario->email}> ({$usuario->rol})");
        $this->newLine();
        $this->line($token);
        $this->newLine();
        $this->comment('Revocalo al terminar:  php artisan dev:token:revocar');

        return self::SUCCESS;
    }
}
