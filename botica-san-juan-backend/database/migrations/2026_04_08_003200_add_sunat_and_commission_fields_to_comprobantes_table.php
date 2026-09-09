<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->string('sunat_ticket', 64)->nullable()->after('codigo_respuesta_sunat');
            $table->json('sunat_payload')->nullable()->after('sunat_ticket');
            $table->json('sunat_response')->nullable()->after('sunat_payload');
            $table->decimal('monto_comision', 10, 2)->default(0)->after('total');
            $table->enum('estado_comision', ['sin_comision', 'pendiente', 'liquidada'])->default('sin_comision')->after('monto_comision');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->dropColumn([
                'sunat_ticket',
                'sunat_payload',
                'sunat_response',
                'monto_comision',
                'estado_comision',
            ]);
        });
    }
};
