<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Cuentas de demostracion del entorno desplegado.
 * ---------------------------------------------------------------------------
 * POR QUE EXISTE ESTE SEEDER Y NO SE COPIARON LOS USUARIOS REALES
 *
 * La base local tiene cinco usuarios con nombre, DNI, correo y telefono
 * reales. El entorno desplegado es una URL publica que se entrega como
 * evidencia academica: copiar alli esos registros seria publicar datos
 * personales de terceros para ilustrar un trabajo de curso.
 *
 * El catalogo de productos si se copia —no identifica a nadie— y es lo que
 * hace que la demostracion sea representativa: 3361 productos reales, con sus
 * precios y su stock.
 *
 * Las dos cuentas de aqui son ficticias y existen solo para que alguien pueda
 * recorrer el sistema. Sus contrasenas se pasan por variables de entorno y no
 * viven en el repositorio.
 */
class UsuariosDemostracionSeeder extends Seeder
{
    public function run(): void
    {
        /* Las cuentas se declaran en config/demostracion.php y no con env()
           aqui: en produccion el arranque cachea la configuracion y a partir
           de ahi env() deja de leer el .env. Ese mismo descuido ya rompio la
           configuracion de CORS una vez. */
        $cuentas = array_values(config('demostracion.cuentas', []));

        foreach ($cuentas as $cuenta) {
            if (blank($cuenta['password'])) {
                $this->command->warn(
                    "Se omite {$cuenta['dni']}: falta su contrasena en las variables de entorno."
                );

                continue;
            }

            /* updateOrCreate y no create: el seeder debe poder ejecutarse en
               cada despliegue sin duplicar cuentas ni fallar por el indice
               unico del DNI. */
            Usuario::updateOrCreate(
                ['dni' => $cuenta['dni']],
                [
                    'nombre'   => $cuenta['nombre'],
                    'email'    => $cuenta['email'],
                    'rol'      => $cuenta['rol'],
                    'password' => $cuenta['password'],
                ]
            );

            $this->command->info("Cuenta lista: {$cuenta['dni']} ({$cuenta['rol']})");
        }
    }
}
