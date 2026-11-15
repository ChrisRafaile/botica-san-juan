<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Habilita unaccent + pg_trgm, una envoltura IMMUTABLE apta para índices
     * e índices GIN trigram sobre las columnas de búsqueda de productos.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // PostgreSQL marca unaccent(text) como STABLE; fijar el diccionario lo hace IMMUTABLE para índices.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.f_unaccent(texto text)
            RETURNS text
            LANGUAGE sql
            IMMUTABLE
            PARALLEL SAFE
            STRICT
            AS $$
                SELECT public.unaccent('public.unaccent', texto);
            $$
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS productos_nombre_unaccent_trgm_idx ON productos USING gin (lower(public.f_unaccent(nombre)) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS productos_principio_unaccent_trgm_idx ON productos USING gin (lower(public.f_unaccent(principio_activo)) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS productos_laboratorio_unaccent_trgm_idx ON productos USING gin (lower(public.f_unaccent(laboratorio)) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS productos_presentacion_unaccent_trgm_idx ON productos USING gin (lower(public.f_unaccent(presentacion)) gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS productos_presentacion_unaccent_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS productos_laboratorio_unaccent_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS productos_principio_unaccent_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS productos_nombre_unaccent_trgm_idx');
        DB::statement('DROP FUNCTION IF EXISTS public.f_unaccent(text)');
    }
};
