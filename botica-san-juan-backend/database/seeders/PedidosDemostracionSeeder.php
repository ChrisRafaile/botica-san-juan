<?php

namespace Database\Seeders;

use App\Models\ComprobanteElectronico;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\DesgloseFiscalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos de demostracion para la cuenta de cliente.
 * ---------------------------------------------------------------------------
 * PARA QUE SIRVE
 *
 * El portal del cliente muestra pedidos por estado, gasto acumulado,
 * historial y comprobantes. Con la cuenta de demostracion recien creada todo
 * eso sale a cero, que es un estado legitimo del sistema pero no permite
 * comprobar que las cifras se calculan bien ni sirve como evidencia del
 * avance.
 *
 * Este seeder crea tres pedidos en estados distintos —uno pendiente, uno
 * confirmado y uno completado— con su detalle real sobre productos del
 * catalogo y un comprobante para los que ya se cobraron.
 *
 * LO QUE NO HACE, Y ES DELIBERADO
 *
 * NO toca el stock. Un pedido de demostracion no es una venta: descontar
 * unidades alteraria el inventario real del catalogo y ensuciaria las
 * comprobaciones de trazabilidad por lote, que son las que sostienen la
 * respuesta a «¿a quien le vendimos de este lote?». Para probar el descuento
 * de stock esta `pos:venta-demostracion`, que si pasa por el servicio de
 * venta y por eso tiene modo de simulacion.
 *
 * SOLO SE EJECUTA EN LOCAL
 *
 * Inventar compras en el entorno desplegado seria falsear el historial de un
 * sistema que se presenta como operativo.
 */
class PedidosDemostracionSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command->warn('Omitido: este seeder solo corre en local.');

            return;
        }

        /* Se prefiere una cuenta claramente de prueba sobre la primera que
           aparezca. La base local tiene cuentas de personas reales, y las
           capturas de evidencia muestran el nombre del titular en pantalla:
           colgarle compras inventadas a alguien real para ilustrar un trabajo
           de curso no es aceptable aunque sea su propia base. */
        $cliente = Usuario::where('rol', 'cliente')
            ->orderByRaw("case
                when dni = '10000002' then 0
                when nombre ilike '%prueba%' then 1
                else 2
            end")
            ->orderBy('id')
            ->first();

        if ($cliente === null) {
            $this->command->error('No hay ninguna cuenta con rol cliente. Siembra antes las cuentas de demostracion.');

            return;
        }

        /* Idempotente: si ya se sembraron, no se duplican. Se reconocen por
           su marca en las observaciones, no por la fecha ni por el importe,
           que podrian coincidir con un pedido real. */
        $yaSembrados = Pedido::where('usuario_id', $cliente->id)
            ->where('observacion', 'like', '%[demo-apf3]%')
            ->count();

        if ($yaSembrados > 0) {
            $this->command->info("Ya existen {$yaSembrados} pedidos de demostracion para {$cliente->nombre}.");

            return;
        }

        $productos = Producto::query()
            ->where('precio', '>', 0)
            ->orderBy('id')
            ->limit(6)
            ->get();

        if ($productos->count() < 3) {
            $this->command->error('Hacen falta al menos 3 productos con precio en el catalogo.');

            return;
        }

        $fiscal = app(DesgloseFiscalService::class);

        $guiones = [
            ['estado' => Pedido::ESTADO_COMPLETADO, 'dias' => 21, 'articulos' => 3, 'comprobante' => true],
            ['estado' => Pedido::ESTADO_COMPLETADO, 'dias' => 9,  'articulos' => 2, 'comprobante' => true],
            ['estado' => Pedido::ESTADO_CONFIRMADO, 'dias' => 3,  'articulos' => 2, 'comprobante' => false],
            ['estado' => Pedido::ESTADO_PENDIENTE,  'dias' => 1,  'articulos' => 1, 'comprobante' => false],
        ];

        DB::transaction(function () use ($guiones, $cliente, $productos, $fiscal) {
            $serie = ComprobanteElectronico::where('serie', 'B001')->max('numero') ?? 0;

            foreach ($guiones as $i => $guion) {
                $elegidos = $productos->slice($i, $guion['articulos']);

                if ($elegidos->count() < $guion['articulos']) {
                    $elegidos = $productos->take($guion['articulos']);
                }

                $total = 0.0;

                $pedido = Pedido::create([
                    'usuario_id'        => $cliente->id,
                    'origen'            => Pedido::ORIGEN_WEB,
                    'cliente_nombre'    => $cliente->nombre,
                    'cliente_documento' => $cliente->dni,
                    'fecha'             => now()->subDays($guion['dias']),
                    'estado'            => $guion['estado'],
                    'total'             => 0,
                    'observacion'       => '[demo-apf3] Pedido de demostracion para las evidencias del avance.',
                ]);

                foreach ($elegidos as $j => $producto) {
                    $cantidad = $j + 1;
                    $precio = (float) $producto->precio;
                    $subtotal = round($precio * $cantidad, 2);
                    $total += $subtotal;

                    PedidoDetalle::create([
                        'pedido_id'       => $pedido->id,
                        'producto_id'     => $producto->id,
                        'cantidad'        => $cantidad,
                        'precio_unitario' => $precio,
                        'precio'          => $precio,
                        'subtotal'        => $subtotal,
                    ]);
                }

                $pedido->update(['total' => round($total, 2)]);

                if ($guion['comprobante']) {
                    $reparto = $fiscal->repartir(round($total, 2));

                    ComprobanteElectronico::create([
                        'pedido_id'         => $pedido->id,
                        'tipo_comprobante'  => 'boleta',
                        'serie'             => 'B001',
                        'numero'            => ++$serie,
                        'cliente_nombre'    => $cliente->nombre,
                        'cliente_documento' => $cliente->dni,
                        'total'             => round($total, 2),
                        'estado_sunat'      => 'pendiente',
                        'fecha_emision'     => $pedido->fecha,
                    ]);

                    $this->command->info(sprintf(
                        'Pedido #%d (%s) S/ %.2f · boleta B001-%08d · IGV S/ %.2f',
                        $pedido->id,
                        $guion['estado'],
                        $total,
                        $serie,
                        $reparto['igv'] ?? 0
                    ));
                } else {
                    $this->command->info(sprintf(
                        'Pedido #%d (%s) S/ %.2f · sin comprobante',
                        $pedido->id,
                        $guion['estado'],
                        $total
                    ));
                }
            }
        });

        $this->command->info("Listo. El portal de {$cliente->nombre} ya tiene actividad que mostrar.");
    }
}
