<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductoCatalogBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $medicamentosId = (int) DB::table('categorias')->where('slug', 'medicamentos')->value('id');
        $cuidadoId = (int) DB::table('categorias')->where('slug', 'cuidado-personal')->value('id');
        $dispositivosId = (int) DB::table('categorias')->where('slug', 'dispositivos-medicos')->value('id');

        if ($medicamentosId === 0) {
            return;
        }

        DB::table('productos')->whereNull('categoria_id')->update([
            'categoria_id' => $medicamentosId,
        ]);

        DB::table('productos')
            ->whereIn('tipo', ['CREMA', 'EMULSION', 'LOCION', 'AEROSOL'])
            ->update(['categoria_id' => $cuidadoId > 0 ? $cuidadoId : $medicamentosId]);

        DB::table('productos')
            ->whereIn('tipo', ['ANILLO'])
            ->update(['categoria_id' => $dispositivosId > 0 ? $dispositivosId : $medicamentosId]);

        $subMap = [
            'TABLETA' => 'tabletas',
            'CAPSULA' => 'capsulas',
            'SUSPENSION' => 'suspensiones',
            'SOLUCION' => 'soluciones',
            'SOLUCION OTICA' => 'soluciones',
            'INYECTABLE' => 'inyectables',
            'CREMA' => 'cremas-topicos',
            'EMULSION' => 'cremas-topicos',
            'LOCION' => 'cremas-topicos',
            'AEROSOL' => 'cremas-topicos',
        ];

        foreach ($subMap as $tipo => $slug) {
            $subId = DB::table('subcategorias')->where('slug', $slug)->value('id');
            if ($subId) {
                DB::table('productos')->where('tipo', $tipo)->update(['subcategoria_id' => $subId]);
            }
        }

        DB::table('productos')->whereNull('stock_minimo')->update(['stock_minimo' => 5]);
        DB::table('productos')->whereNull('stock_reposicion')->update(['stock_reposicion' => 10]);
    }
}
