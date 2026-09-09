<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('unidad_base', 20)->default('unidad')->after('presentacion');
            $table->boolean('venta_fraccionada')->default(false)->after('unidad_base');
            $table->unsignedInteger('unidades_por_blister')->nullable()->after('venta_fraccionada');
            $table->unsignedInteger('blisters_por_caja')->nullable()->after('unidades_por_blister');
            $table->decimal('precio_blister', 10, 2)->nullable()->after('precio');
            $table->decimal('precio_caja', 10, 2)->nullable()->after('precio_blister');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn([
                'unidad_base',
                'venta_fraccionada',
                'unidades_por_blister',
                'blisters_por_caja',
                'precio_blister',
                'precio_caja',
            ]);
        });
    }
};
