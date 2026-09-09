<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTestUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-test-user {--dni=11111111} {--password=123456} {--rol=cliente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crear un usuario de prueba para testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dni = $this->option('dni');
        $password = $this->option('password');
        $rol = $this->option('rol');

        // Verificar si el usuario ya existe
        $existingUser = Usuario::where('dni', $dni)->first();
        if ($existingUser) {
            $this->info("El usuario con DNI {$dni} ya existe. Actualizando contraseña...");
            $existingUser->update([
                'password' => Hash::make($password),
                'rol' => $rol
            ]);
        } else {
            Usuario::create([
                'dni' => $dni,
                'nombre' => 'Usuario de Prueba',
                'email' => 'prueba@test.com',
                'password' => Hash::make($password),
                'telefono' => '999999999',
                'rol' => $rol,
            ]);
            $this->info("Usuario de prueba creado exitosamente!");
        }

        $this->info("Credenciales de prueba:");
        $this->info("DNI: {$dni}");
        $this->info("Contraseña: {$password}");
        $this->info("Rol: {$rol}");
    }
}
