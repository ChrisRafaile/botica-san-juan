<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('concentracion');
            $table->string('adicional')->nullable();
            $table->string('laboratorio');
            $table->string('presentacion');
            $table->string('tipo');
            $table->string('categoria');
            $table->integer('stock');
            $table->decimal('precio', 10, 2);
            $table->string('imagen')->default('images/default_image.png');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
