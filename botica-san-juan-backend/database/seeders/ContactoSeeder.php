<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Contacto;

class ContactoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Contacto::insert([
            [
                'id' => 1,
                'nombre' => 'dionicia TN',
                'email' => 'dionicia_31@hotmail.com',
                'telefono' => '9976462151',
                'motivo' => 'order-status',
                'mensaje' => 'verificar el estado del pedido',
                'fecha' => '2024-07-17 05:25:23',
            ],
            [
                'id' => 2,
                'nombre' => 'dionicia TN',
                'email' => 'dionicia_31@hotmail.com',
                'telefono' => '9976462151',
                'motivo' => 'order-status',
                'mensaje' => 'verificar el estado del pedido',
                'fecha' => '2024-07-17 17:51:42',
            ],
            [
                'id' => 3,
                'nombre' => 'dionicia TN',
                'email' => 'dionicia_31@hotmail.com',
                'telefono' => '9976462151',
                'motivo' => 'order-status',
                'mensaje' => 'verificar el estado del pedido',
                'fecha' => '2024-07-17 18:15:16',
            ],
        ]);
    }
}
