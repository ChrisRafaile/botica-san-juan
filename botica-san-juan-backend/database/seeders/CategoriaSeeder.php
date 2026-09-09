<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['id' => 1, 'nombre' => 'Medicamentos', 'slug' => 'medicamentos', 'descripcion' => 'Medicamentos con y sin receta medica.', 'color' => 'blue', 'activa' => true],
            ['id' => 2, 'nombre' => 'Cuidado Personal', 'slug' => 'cuidado-personal', 'descripcion' => 'Higiene y cuidado diario.', 'color' => 'green', 'activa' => true],
            ['id' => 3, 'nombre' => 'Suplementos y Vitaminas', 'slug' => 'suplementos-vitaminas', 'descripcion' => 'Vitaminas y soporte nutricional.', 'color' => 'orange', 'activa' => true],
            ['id' => 4, 'nombre' => 'Dispositivos Medicos', 'slug' => 'dispositivos-medicos', 'descripcion' => 'Equipos y dispositivos para control de salud.', 'color' => 'purple', 'activa' => true],
            ['id' => 5, 'nombre' => 'Bebes y Maternidad', 'slug' => 'bebes-maternidad', 'descripcion' => 'Linea de cuidado para bebes y madres.', 'color' => 'pink', 'activa' => true],
            ['id' => 6, 'nombre' => 'Primeros Auxilios', 'slug' => 'primeros-auxilios', 'descripcion' => 'Productos para botiquin y emergencias basicas.', 'color' => 'red', 'activa' => true],
        ];

        foreach ($categorias as $categoria) {
            DB::table('categorias')->updateOrInsert(
                ['slug' => $categoria['slug']],
                [
                    'id' => $categoria['id'],
                    'nombre' => $categoria['nombre'],
                    'descripcion' => $categoria['descripcion'],
                    'color' => $categoria['color'],
                    'activa' => $categoria['activa'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $subcategorias = [
            ['categoria_id' => 1, 'nombre' => 'Tabletas', 'slug' => 'tabletas'],
            ['categoria_id' => 1, 'nombre' => 'Capsulas', 'slug' => 'capsulas'],
            ['categoria_id' => 1, 'nombre' => 'Suspensiones', 'slug' => 'suspensiones'],
            ['categoria_id' => 1, 'nombre' => 'Soluciones', 'slug' => 'soluciones'],
            ['categoria_id' => 1, 'nombre' => 'Inyectables', 'slug' => 'inyectables'],
            ['categoria_id' => 1, 'nombre' => 'Cremas y Topicos', 'slug' => 'cremas-topicos'],
            ['categoria_id' => 2, 'nombre' => 'Higiene Oral', 'slug' => 'higiene-oral'],
            ['categoria_id' => 2, 'nombre' => 'Higiene Corporal', 'slug' => 'higiene-corporal'],
            ['categoria_id' => 3, 'nombre' => 'Multivitaminicos', 'slug' => 'multivitaminicos'],
            ['categoria_id' => 3, 'nombre' => 'Minerales', 'slug' => 'minerales'],
            ['categoria_id' => 4, 'nombre' => 'Monitoreo', 'slug' => 'monitoreo'],
            ['categoria_id' => 4, 'nombre' => 'Equipos Basicos', 'slug' => 'equipos-basicos'],
            ['categoria_id' => 5, 'nombre' => 'Cuidado del Bebe', 'slug' => 'cuidado-bebe'],
            ['categoria_id' => 6, 'nombre' => 'Botiquin', 'slug' => 'botiquin'],
        ];

        foreach ($subcategorias as $subcategoria) {
            DB::table('subcategorias')->updateOrInsert(
                ['categoria_id' => $subcategoria['categoria_id'], 'slug' => $subcategoria['slug']],
                [
                    'nombre' => $subcategoria['nombre'],
                    'descripcion' => null,
                    'activa' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
