<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UsuarioSeeder::class,
            CategoriaSeeder::class,
            ProductoSeeder::class,
            ProductoCatalogBackfillSeeder::class,
            ProveedorCompraSeeder::class,
            ContactoSeeder::class,
            PedidoSeeder::class,
            PedidoDetalleSeeder::class,
        ]);
    }
}
