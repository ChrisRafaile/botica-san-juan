<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Tarea programada minuto a minuto para liberar reservas de stock de pedidos web
 * que expiraron sin completar el pago con tarjeta.
 */
Schedule::command('pedidos:liberar-expirados')
    ->everyMinute()
    ->withoutOverlapping();

/*
 * Worker de la cola de correos sin proceso dedicado: en la capa gratuita de
 * Render no hay otro. Solo esa cola; `--stop-when-empty` la hace barata.
 */
Schedule::command('queue:work --queue=correos --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->when(fn () => config('queue.default') !== 'sync')
    ->name('correos:atender-cola');

/*
 * Purga diaria de claves de idempotencia cuya vigencia de 24 horas haya expirado.
 */
Schedule::call(function () {
    \App\Models\ClaveIdempotencia::where('expira_en', '<', now())->delete();
})->daily()->name('idempotencia:purgar-vencidas');
