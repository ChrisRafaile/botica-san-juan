<?php

namespace Database\Seeders;

use App\Models\Compra;
use App\Models\Proveedor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ProveedorCompraSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'nombre' => 'Laboratorios San Gabriel SAC',
                'ruc' => '20512345671',
                'contacto' => 'Marco Rojas',
                'telefono' => '987654321',
                'email' => 'ventas@sangabriel.pe',
                'direccion' => 'Av. Nicolas Arriola 2450, Lima',
                'dias_credito' => 30,
                'activo' => true,
            ],
            [
                'nombre' => 'Drogueria Norte Salud EIRL',
                'ruc' => '20623456782',
                'contacto' => 'Ana Luque',
                'telefono' => '965741852',
                'email' => 'contacto@nortesalud.pe',
                'direccion' => 'Jr. Bolognesi 470, Trujillo',
                'dias_credito' => 15,
                'activo' => true,
            ],
            [
                'nombre' => 'Medifarma Distribuciones SAC',
                'ruc' => '20434567893',
                'contacto' => 'Rocio Salinas',
                'telefono' => '944332211',
                'email' => 'distribucion@medifarma.pe',
                'direccion' => 'Av. La Marina 1200, Callao',
                'dias_credito' => 20,
                'activo' => true,
            ],
        ];

        $supplierByRuc = [];
        foreach ($suppliers as $supplierData) {
            $supplier = Proveedor::query()->updateOrCreate(
                ['ruc' => $supplierData['ruc']],
                $supplierData
            );

            $supplierByRuc[$supplierData['ruc']] = $supplier;
        }

        $today = Carbon::today();
        $purchases = [
            [
                'numero_compra' => 'CP-2026-00001',
                'proveedor_ruc' => '20512345671',
                'fecha_compra' => $today->copy()->subDays(7)->toDateString(),
                'estado' => 'recibida',
                'total' => 1250.50,
                'observaciones' => 'Reposicion de analgesicos y antihistaminicos.',
            ],
            [
                'numero_compra' => 'CP-2026-00002',
                'proveedor_ruc' => '20623456782',
                'fecha_compra' => $today->copy()->subDays(4)->toDateString(),
                'estado' => 'emitida',
                'total' => 840.00,
                'observaciones' => 'Compra semanal de antibioticos.',
            ],
            [
                'numero_compra' => 'CP-2026-00003',
                'proveedor_ruc' => '20434567893',
                'fecha_compra' => $today->copy()->subDays(2)->toDateString(),
                'estado' => 'borrador',
                'total' => 530.90,
                'observaciones' => 'Pendiente de confirmacion de entrega.',
            ],
        ];

        foreach ($purchases as $purchaseData) {
            $supplier = $supplierByRuc[$purchaseData['proveedor_ruc']] ?? null;
            if (!$supplier) {
                continue;
            }

            Compra::query()->updateOrCreate(
                ['numero_compra' => $purchaseData['numero_compra']],
                [
                    'proveedor_id' => $supplier->id,
                    'fecha_compra' => $purchaseData['fecha_compra'],
                    'estado' => $purchaseData['estado'],
                    'total' => $purchaseData['total'],
                    'observaciones' => $purchaseData['observaciones'],
                ]
            );
        }
    }
}
