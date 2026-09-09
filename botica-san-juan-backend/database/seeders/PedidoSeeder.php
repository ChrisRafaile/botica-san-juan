<?php

// Los datos de tarjeta se retiraron de este archivo: mantener numeros y
// codigos de verificacion versionados en el repositorio es incompatible con
// cualquier norma de seguridad de medios de pago. El cobro se resuelve ahora
// contra la pasarela y el sistema solo conserva la referencia del pago.

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Pedido;

class PedidoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Pedido::insert([
            ['id' => 1, 'usuario_id' => 1, 'fecha' => '2024-07-17 01:19:43', 'total' => null, 'address' => null, 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 2, 'usuario_id' => 1, 'fecha' => '2024-07-17 01:20:13', 'total' => null, 'address' => null, 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 3, 'usuario_id' => 1, 'fecha' => '2024-07-17 01:21:39', 'total' => null, 'address' => null, 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 4, 'usuario_id' => 1, 'fecha' => '2024-07-17 01:33:39', 'total' => null, 'address' => null, 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 5, 'usuario_id' => 1, 'fecha' => '2024-07-17 02:50:47', 'total' => null, 'address' => null, 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 6, 'usuario_id' => 1, 'fecha' => '2024-07-17 04:18:12', 'total' => null, 'address' => null, 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 7, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 8, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 9, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 10, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 11, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 12, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 13, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 14, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 15, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 16, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 17, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 18, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 19, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 20, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 21, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 22, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 23, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 24, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 25, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 26, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 27, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 28, 'usuario_id' => 1, 'fecha' => null, 'total' => 126.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 29, 'usuario_id' => 1, 'fecha' => null, 'total' => 166.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 30, 'usuario_id' => 1, 'fecha' => null, 'total' => 166.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 31, 'usuario_id' => 1, 'fecha' => null, 'total' => 374.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
            ['id' => 32, 'usuario_id' => 1, 'fecha' => '2024-07-16 23:44:24', 'total' => 60.00, 'address' => 'Calle Las Begonias 544', 'card_number' => null, 'expiry_date' => null, 'cvv' => null],
        ]);
    }
}
