<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El disco de Render es efímero: una foto en storage/app/public desaparece en
 * cada despliegue. La imagen reducida vive en la base, que sí persiste, y la de
 * Google va en su propia columna para que un acceso posterior no pise la que la
 * persona subió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->text('avatar_data')->nullable();
            $table->text('avatar_oauth_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['avatar_data', 'avatar_oauth_url']);
        });
    }
};
