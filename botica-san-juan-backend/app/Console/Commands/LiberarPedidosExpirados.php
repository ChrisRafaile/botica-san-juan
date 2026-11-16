<?php

namespace App\Console\Commands;

use App\Services\PedidoService;
use Illuminate\Console\Command;

class LiberarPedidosExpirados extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pedidos:liberar-expirados
                            {--limite= : Cantidad máxima de pedidos a procesar}
                            {--dry-run : Ejecutar en modo simulación sin alterar la base de datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Libera reservas de stock de pedidos web expirados sin pago confirmado';

    /**
     * Execute the console command.
     */
    public function handle(PedidoService $pedidoService): int
    {
        $limite = $this->option('limite') !== null ? (int) $this->option('limite') : null;
        $dryRun = (bool) $this->option('dry-run');

        $resultado = $pedidoService->liberarExpirados($limite, $dryRun);

        if ($dryRun) {
            $this->info(sprintf(
                '[DRY RUN] Se identificaron %d pedidos expirados.',
                $resultado['encontrados']
            ));
        } else {
            $this->info(sprintf(
                'Liberación completada: %d identificados, %d liberados, %d saltados.',
                $resultado['encontrados'],
                $resultado['liberados'],
                $resultado['saltados']
            ));
        }

        return self::SUCCESS;
    }
}
