<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Services\Clinico\NormalizadorPrincipioActivo;
use Illuminate\Console\Command;

/**
 * Vincula los productos del catálogo con su principio activo.
 *
 *     php artisan clinico:mapear
 *
 * Solo reconoce los productos cuyo NOMBRE es el principio activo. Los nombres
 * comerciales se quedan sin mapear a propósito: deducirlos por parecido
 * produciría avisos equivocados sobre lo que alguien va a tomar, que es peor
 * que no dar ninguno.
 *
 * El hueco que deja este comando lo cierra el padrón oficial
 * (`clinico:importar-padron`) o el químico farmacéutico a mano.
 */
class MapearPrincipiosActivos extends Command
{
    protected $signature = 'clinico:mapear';

    protected $description = 'Vincula los productos cuyo nombre es el principio activo con su ficha clínica.';

    public function handle(NormalizadorPrincipioActivo $normalizador): int
    {
        $this->info('Recorriendo el catálogo…');

        $resultado = $normalizador->mapearCatalogo();

        $total = Producto::count();
        $conPerfil = Producto::whereNotNull('principio_activo_id')->count();
        $cobertura = $total > 0 ? round($conPerfil / $total * 100, 1) : 0.0;

        $this->newLine();
        $this->line("  Revisados sin perfil previo : {$resultado['revisados']}");
        $this->line("  Identificados ahora         : {$resultado['identificados']}");
        $this->line("  Cobertura del catálogo      : {$conPerfil} de {$total} ({$cobertura} %)");
        $this->newLine();

        /* La cifra que importa no es cuántos se identificaron, sino cuántos
           NO. Ese resto es exactamente lo que el consultor tendrá que
           declarar como no revisado delante del cliente. */
        $sinPerfil = $total - $conPerfil;

        if ($sinPerfil > 0) {
            $this->warn(
                "Quedan {$sinPerfil} productos sin principio activo. El consultor de ".
                'interacciones los contará como no revisados y derivará al químico farmacéutico.',
            );
        }

        return self::SUCCESS;
    }
}
