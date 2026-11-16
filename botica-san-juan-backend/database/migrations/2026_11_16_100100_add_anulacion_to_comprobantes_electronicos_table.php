<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admite estado_sunat 'anulada' y registra trazabilidad de anulación en comprobantes.
     *
     * No se altera el índice único parcial sobre pedido_id: un comprobante anulado
     * sigue ocupando su pedido porque este no se vuelve a facturar y el correlativo
     * no se reutiliza jamás.
     */
    public function up(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion', 255)->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE comprobantes_electronicos DROP CONSTRAINT IF EXISTS comprobantes_electronicos_estado_sunat_check');
            DB::statement("ALTER TABLE comprobantes_electronicos ADD CONSTRAINT comprobantes_electronicos_estado_sunat_check CHECK (estado_sunat IN ('pendiente', 'aceptada', 'rechazada', 'anulada'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE comprobantes_electronicos DROP CONSTRAINT IF EXISTS comprobantes_electronicos_estado_sunat_check');
            DB::statement("ALTER TABLE comprobantes_electronicos ADD CONSTRAINT comprobantes_electronicos_estado_sunat_check CHECK (estado_sunat IN ('pendiente', 'aceptada', 'rechazada'))");
        }

        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->dropColumn(['anulado_en', 'motivo_anulacion']);
        });
    }
};
