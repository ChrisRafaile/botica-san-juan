<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la dirección de entrega predeterminada al perfil del usuario
     * para precargarla en futuras compras a domicilio.
     */
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('direccion_entrega', 255)->nullable()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('direccion_entrega');
        });
    }
};
