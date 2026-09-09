<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->boolean('mfa_enabled')->default(false)->after('rol');
            $table->string('mfa_secret', 64)->nullable()->after('mfa_enabled');
            $table->timestamp('mfa_enabled_at')->nullable()->after('mfa_secret');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['mfa_enabled', 'mfa_secret', 'mfa_enabled_at']);
        });
    }
};
