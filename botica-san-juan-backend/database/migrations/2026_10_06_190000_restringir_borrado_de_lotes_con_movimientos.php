<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `movimientos_stock.lote_id` pasa de ON DELETE SET NULL a ON DELETE RESTRICT.
 *
 * EL PROBLEMA QUE CIERRA
 *
 * Con `SET NULL`, borrar un lote no fallaba: la base ponía a NULL el `lote_id`
 * de todos sus movimientos y seguía adelante **sin un solo aviso**. El
 * movimiento quedaba diciendo "salieron 7 unidades" sin decir de dónde.
 *
 * En una botica eso no es un detalle de integridad: es la trazabilidad
 * farmacéutica. Ante una alerta sanitaria sobre un lote concreto —un retiro del
 * mercado, un defecto de fabricación— la pregunta que hay que poder responder
 * es "¿a quién le vendimos de ese lote?". Si el `lote_id` se puso a NULL, la
 * respuesta ya no existe y no hay forma de reconstruirla.
 *
 * `pedido_detalle_lotes.lote_id` ya era `RESTRICT` por este mismo motivo. Esta
 * migración deja las dos tablas con el mismo criterio: **un lote con historial
 * no se borra; si estorba, se desactiva.**
 *
 * LA COLUMNA SIGUE SIENDO NULLABLE, Y ES A PROPÓSITO
 *
 * No se confunda `nullable` con `nullOnDelete`. Un ajuste de inventario puede
 * no atribuirse a ningún lote mientras se regulariza el stock heredado, así que
 * la columna debe admitir NULL **al crearse**. Lo que se prohíbe aquí es otra
 * cosa: que la base la ponga a NULL *después*, al borrar el lote, destruyendo
 * un dato que sí se había registrado.
 *
 * CONSECUENCIA QUE HAY QUE CONOCER
 *
 * `lotes.producto_id` es CASCADE. Por lo tanto, borrar un PRODUCTO intentaba
 * borrar sus lotes y ahora chocará con esta restricción si alguno tiene
 * movimientos. Eso es lo correcto —un producto con historial de ventas no debe
 * poder desaparecer—, pero sin un aviso explícito el usuario vería un error 500.
 * Por eso `ProductoController::destroy()` comprueba la condición antes y
 * responde 409 con el motivo.
 *
 * SI ESTA MIGRACIÓN FALLA AL APLICARSE
 *
 * Significa que ya hay movimientos con `lote_id` NULL cuyo lote fue borrado:
 * trazabilidad perdida antes de este cambio. La migración no los inventa ni los
 * borra; hay que decidir a mano qué hacer con ellos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            /* En PostgreSQL no se puede cambiar la acción de una clave foránea
               en sitio: hay que soltarla y volver a crearla. El nombre lo
               deduce Laravel de tabla + columna, que es como la creó la
               migración original. */
            $table->dropForeign(['lote_id']);

            $table->foreign('lote_id')
                ->references('id')
                ->on('lotes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table) {
            $table->dropForeign(['lote_id']);

            $table->foreign('lote_id')
                ->references('id')
                ->on('lotes')
                ->nullOnDelete();
        });
    }
};
