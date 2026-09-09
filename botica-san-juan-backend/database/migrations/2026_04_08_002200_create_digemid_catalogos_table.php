<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digemid_catalogos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_digemid', 64)->unique();
            $table->string('nombre_producto', 255);
            $table->string('principio_activo', 255)->nullable();
            $table->string('laboratorio_fabricante', 255)->nullable();
            $table->boolean('requiere_receta')->default(false);
            $table->decimal('precio_maximo_regulado', 10, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digemid_catalogos');
    }
};
