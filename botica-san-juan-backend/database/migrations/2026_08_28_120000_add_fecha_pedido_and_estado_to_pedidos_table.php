<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alinea la tabla 'pedidos' con el modelo Eloquent y los controladores.
 *
 * PedidoController, ReporteController y FacturacionController consultan
 * 'fecha_pedido' y 'estado', y el modelo Pedido los declara como atributos
 * asignables. Ninguna migracion previa creo esas columnas: la tabla solo
 * tenia 'fecha'. Sobre PostgreSQL esto provoca SQLSTATE[42703] (columna
 * inexistente) y devuelve HTTP 500 en GET /api/pedidos y en los reportes.
 *
 * La migracion es aditiva: crea las dos columnas y copia 'fecha' en
 * 'fecha_pedido' para conservar el historial ya cargado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'fecha_pedido')) {
                $table->dateTime('fecha_pedido')->nullable()->after('usuario_id');
            }

            if (!Schema::hasColumn('pedidos', 'estado')) {
                $table->string('estado', 30)->default('pendiente')->after('total');
            }
        });

        // Respalda el historial existente: la fecha operativa se conserva.
        DB::table('pedidos')->whereNull('fecha_pedido')->update([
            'fecha_pedido' => DB::raw('COALESCE(fecha, created_at)'),
        ]);

        Schema::table('pedidos', function (Blueprint $table) {
            $table->index('fecha_pedido', 'pedidos_fecha_pedido_index');
            $table->index('estado', 'pedidos_estado_index');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex('pedidos_fecha_pedido_index');
            $table->dropIndex('pedidos_estado_index');
            $table->dropColumn(['fecha_pedido', 'estado']);
        });
    }
};
