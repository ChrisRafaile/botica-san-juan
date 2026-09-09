<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 160);
            $table->string('ruc', 11)->nullable()->unique();
            $table->string('contacto', 140)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->unsignedTinyInteger('dias_credito')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
