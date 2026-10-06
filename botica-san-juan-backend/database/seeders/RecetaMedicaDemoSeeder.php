<?php

namespace Database\Seeders;

use App\Models\Producto;
use Illuminate\Database\Seeder;

/**
 * Marca como venta bajo receta un conjunto reducido de familias terapéuticas.
 *
 *   php artisan db:seed --class=RecetaMedicaDemoSeeder
 *
 * ESTO NO ES LA FUENTE REGULATORIA. LÉASE ANTES DE USARLO.
 *
 * Qué producto se vende bajo receta en el Perú lo determina DIGEMID, y el
 * sistema ya sabe leerlo del sitio correcto: cuando un producto tiene
 * `codigo_digemid`, `ProductoController::validateDigemidCompliance()` copia
 * `requiere_receta` desde `digemid_catalogos` automáticamente. Ese es el camino
 * bueno.
 *
 * El problema es que hoy esa tabla tiene cinco filas de demostración y un solo
 * producto del catálogo las cruza. Resultado medido: de 884 productos, **cero**
 * estaban marcados, así que la advertencia de receta del carrito —implementada y
 * cubierta por pruebas— no se disparaba nunca y no había forma de verla
 * funcionar ni de capturarla como evidencia.
 *
 * Este seeder cubre ese hueco de datos de forma acotada y reversible, para poder
 * demostrar el comportamiento. **Cuando se cargue el catálogo DIGEMID real, este
 * seeder sobra y debe retirarse**: mantener dos fuentes para el mismo dato es
 * exactamente el problema que el proyecto ya tuvo con el WhatsApp del portal.
 *
 * POR QUÉ POR NOMBRE Y NO POR ID
 *
 * Los identificadores no sobreviven a un entorno nuevo: la fusión de duplicados
 * ya movió cuáles eran las filas vivas, y en producción serán otros. Marcando
 * por principio activo, el seeder dice lo que quiere decir —"los antibióticos
 * sistémicos van bajo receta"— y sigue siendo correcto allí donde se ejecute.
 *
 * LAS FAMILIAS ELEGIDAS, Y POR QUÉ ESTAS
 *
 * No es una lista exhaustiva ni pretende serlo: son tres grupos donde la
 * exigencia de receta no admite discusión, de modo que marcarlos no introduce
 * una afirmación dudosa en el catálogo.
 */
class RecetaMedicaDemoSeeder extends Seeder
{
    /**
     * Principio activo => motivo. El motivo no se guarda en la base; está aquí
     * para que quien lea el seeder sepa por qué entra cada familia y pueda
     * discutirlo con criterio.
     */
    private const FAMILIAS = [
        'AMOXICILINA'   => 'Antibiótico sistémico: la dispensación sin receta es el motor de la resistencia antimicrobiana.',
        'AZITROMICINA'  => 'Antibiótico macrólido sistémico.',
        'CIPROFLOXACINO' => 'Antibiótico fluoroquinolona sistémica.',
        'CLOBETASOL'    => 'Corticoide tópico de potencia muy alta: el uso prolongado sin control atrofia la piel.',
        'DEXAMETASONA'  => 'Corticoide sistémico.',
    ];

    public function run(): void
    {
        $totalMarcados = 0;

        foreach (self::FAMILIAS as $principio => $motivo) {
            /* `requiere_receta` se filtra en la consulta para que el seeder sea
               idempotente de verdad: volver a ejecutarlo no reescribe filas que
               ya estaban marcadas, y el recuento informa de lo que CAMBIÓ, no de
               lo que coincide con el patrón. */
            $marcados = Producto::query()
                ->where('nombre', 'like', "%{$principio}%")
                ->where(function ($q) {
                    $q->where('requiere_receta', false)->orWhereNull('requiere_receta');
                })
                ->update(['requiere_receta' => true]);

            $enTotal = Producto::where('nombre', 'like', "%{$principio}%")->count();
            $totalMarcados += $marcados;

            $this->command?->line(sprintf(
                '  %-15s %2d productos (%d nuevos)   %s',
                $principio,
                $enTotal,
                $marcados,
                $motivo
            ));
        }

        $bajoReceta = Producto::where('requiere_receta', true)->count();
        $catalogo   = Producto::count();

        $this->command?->newLine();
        $this->command?->info(sprintf(
            'Venta bajo receta: %d de %d productos del catálogo (%.1f %%). Marcados en esta ejecución: %d.',
            $bajoReceta,
            $catalogo,
            $catalogo > 0 ? $bajoReceta / $catalogo * 100 : 0,
            $totalMarcados
        ));
        $this->command?->comment(
            'Marcación de demostración. La fuente real es el catálogo DIGEMID; '
            .'cuando se cargue, retirar este seeder.'
        );
    }
}
