<?php

namespace Database\Seeders;

use App\Models\EventoOperacion;
use Illuminate\Database\Seeder;

/**
 * Eventos de ejemplo para demostrar la bitácora.
 * ---------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *
 * El punto 30 de la rúbrica pide enseñar ejemplos de los tres niveles —info,
 * warning y error—. En un entorno recién levantado la bitácora está vacía, y
 * provocar un error de verdad para que quede registrado no siempre es posible
 * delante de nadie.
 *
 * Esto lo deja reproducible: un comando y la pantalla tiene los tres niveles,
 * con mensajes tomados de situaciones que el sistema registra de verdad.
 *
 * NO SE EJECUTA SOLO
 *
 * No está en `DatabaseSeeder`, a propósito: estos eventos son material de
 * demostración y no deben mezclarse con la bitácora real de un entorno que
 * esté operando. Se invoca a mano:
 *
 *     php artisan db:seed --class=BitacoraDemostracionSeeder
 *
 * LOS MENSAJES SON REALISTAS, NO INVENTADOS
 *
 * Cada uno corresponde a un código que el sistema registra de verdad —los de
 * sincronización salen de `SincronizacionController`, el de la foto de
 * `PerfilController`—. Un ejemplo con «evento de prueba 1» no demostraría
 * nada sobre cómo se ve la bitácora cuando pasa algo.
 *
 * El del correo lleva una dirección a propósito: sirve para comprobar, en la
 * propia pantalla, que el depurador la tapa antes de mostrarla.
 */
class BitacoraDemostracionSeeder extends Seeder
{
    public function run(): void
    {
        EventoOperacion::info(
            EventoOperacion::SYNC_CREADA,
            'Venta de caja NV-000012 registrada con su correlativo oficial',
            [
                'origen' => 'caja',
                'dispositivo' => 'CAJA-01',
                'contexto' => ['correlativo' => 'B001-000012', 'lineas' => 3],
            ],
        );

        EventoOperacion::info(
            'PERFIL_FOTO_ACTUALIZADA',
            'El usuario actualizó su foto de perfil',
            ['contexto' => ['tamano_kb' => 184]],
        );

        EventoOperacion::info(
            EventoOperacion::SYNC_LOTE,
            'Lote de 4 ventas sincronizado sin incidencias',
            ['origen' => 'caja', 'dispositivo' => 'CAJA-01'],
        );

        EventoOperacion::advertencia(
            EventoOperacion::SYNC_DUPLICADA,
            'Se reenvió una venta que ya existía; no se duplicó el comprobante',
            [
                'origen' => 'caja',
                'dispositivo' => 'CAJA-02',
                'contexto' => ['accion' => 'ignorada'],
            ],
        );

        EventoOperacion::advertencia(
            EventoOperacion::SYNC_INCIDENCIA,
            'La venta se registró aunque el stock central ya no alcanzaba',
            [
                'origen' => 'caja',
                'contexto' => ['producto_id' => 217, 'faltante' => 2],
            ],
        );

        EventoOperacion::error(
            EventoOperacion::SYNC_RECHAZADA,
            'La venta no se pudo registrar: el lote referido no existe',
            [
                'origen' => 'caja',
                'dispositivo' => 'CAJA-02',
                'contexto' => ['motivo' => 'lote_inexistente'],
            ],
        );

        EventoOperacion::error(
            'CORREO_NO_ENVIADO',
            'No se pudo avisar a rosa.quispe@gmail.com tras el cambio de correo',
            ['contexto' => ['proveedor' => 'resend', 'reintentos' => 2]],
        );

        $this->command?->info('Bitácora de demostración: 3 info, 2 advertencias y 2 errores.');
    }
}
