<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsuarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('usuarios')->updateOrInsert(
            ['dni' => '74239474'],
            [
                'nombre' => 'Christopher',
                'email' => 'newblas12@gmail.com',
                'password' => '$2y$10$DdR3qCsgk9GMk31EiCuiFuX1HG.VerHV.PK611nRo8Hma9Xk2oZbC',
                'telefono' => '987654321',
                'foto_perfil' => 'perfil_1.jpg',
                'foto_portada' => null,
                'rol' => 'cliente',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('usuarios')->updateOrInsert(
            ['dni' => '12345678'],
            [
                'nombre' => 'Administrador',
                'email' => 'admin@example.com',
                'password' => '$2y$12$GRa6T.mLjiBLh043eXlTkugSXx07jRcvn.59ftI/SKutZb2zVhWzi', // password: 123456
                'telefono' => '987654322',
                'foto_perfil' => null,
                'foto_portada' => null,
                'rol' => 'administrador',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('usuarios')->updateOrInsert(
            ['dni' => '87654321'],
            [
                'nombre' => 'Cliente Prueba',
                'email' => 'cliente@botica.com',
                'password' => '$2y$12$GRa6T.mLjiBLh043eXlTkugSXx07jRcvn.59ftI/SKutZb2zVhWzi', // password: 123456
                'telefono' => '987654323',
                'foto_perfil' => null,
                'foto_portada' => null,
                'rol' => 'cliente',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
