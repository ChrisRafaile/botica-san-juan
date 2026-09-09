<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_detalles', function (Blueprint $table) {
            $table->enum('unidad_venta', ['unidad', 'blister', 'caja'])->default('unidad')->after('producto_id');
            $table->unsignedInteger('factor_unidades')->default(1)->after('unidad_venta');
            $table->unsignedInteger('cantidad_unidades')->default(0)->after('cantidad');
            $table->decimal('precio_unitario', 10, 2)->nullable()->after('precio');
            $table->decimal('subtotal', 10, 2)->nullable()->after('precio_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_detalles', function (Blueprint $table) {
            $table->dropColumn([
                'unidad_venta',
                'factor_unidades',
                'cantidad_unidades',
                'precio_unitario',
                'subtotal',
            ]);
        });
    }
};
