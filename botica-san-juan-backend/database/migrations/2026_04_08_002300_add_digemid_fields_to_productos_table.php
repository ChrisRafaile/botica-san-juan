<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('codigo_digemid', 64)->nullable()->index()->after('codigo_barras');
            $table->string('principio_activo', 255)->nullable()->after('codigo_digemid');
            $table->boolean('requiere_receta')->default(false)->after('principio_activo');
            $table->string('laboratorio_fabricante', 255)->nullable()->after('requiere_receta');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn([
                'codigo_digemid',
                'principio_activo',
                'requiere_receta',
                'laboratorio_fabricante',
            ]);
        });
    }
};
