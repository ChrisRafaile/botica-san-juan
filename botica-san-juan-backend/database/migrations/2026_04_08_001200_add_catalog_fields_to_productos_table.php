<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('categoria_id')->nullable()->after('tipo')->constrained('categorias')->nullOnDelete();
            $table->foreignId('subcategoria_id')->nullable()->after('categoria_id')->constrained('subcategorias')->nullOnDelete();
            $table->string('codigo_barras')->nullable()->unique()->after('imagen');
            $table->unsignedInteger('stock_minimo')->default(5)->after('stock');
            $table->unsignedInteger('stock_reposicion')->default(10)->after('stock_minimo');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subcategoria_id');
            $table->dropConstrainedForeignId('categoria_id');
            $table->dropColumn(['codigo_barras', 'stock_minimo', 'stock_reposicion']);
        });
    }
};
