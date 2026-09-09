<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PedidoDetalle;

class PedidoDetalleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PedidoDetalle::insert([
            ['id' => 1, 'pedido_id' => 29, 'producto_id' => 1, 'cantidad' => 2, 'precio' => 1.00],
            ['id' => 2, 'pedido_id' => 29, 'producto_id' => 2, 'cantidad' => 2, 'precio' => 2.00],
            ['id' => 3, 'pedido_id' => 29, 'producto_id' => 3, 'cantidad' => 2, 'precio' => 40.00],
            ['id' => 4, 'pedido_id' => 29, 'producto_id' => 4, 'cantidad' => 2, 'precio' => 40.00],
            ['id' => 5, 'pedido_id' => 31, 'producto_id' => 2, 'cantidad' => 7, 'precio' => 2.00],
            ['id' => 6, 'pedido_id' => 31, 'producto_id' => 3, 'cantidad' => 3, 'precio' => 40.00],
            ['id' => 7, 'pedido_id' => 31, 'producto_id' => 9, 'cantidad' => 3, 'precio' => 40.00],
            ['id' => 8, 'pedido_id' => 31, 'producto_id' => 10, 'cantidad' => 3, 'precio' => 40.00],
            ['id' => 9, 'pedido_id' => 32, 'producto_id' => 54, 'cantidad' => 2, 'precio' => 10.00],
            ['id' => 10, 'pedido_id' => 32, 'producto_id' => 55, 'cantidad' => 2, 'precio' => 10.00],
            ['id' => 11, 'pedido_id' => 32, 'producto_id' => 56, 'cantidad' => 2, 'precio' => 10.00],
        ]);
    }
}
