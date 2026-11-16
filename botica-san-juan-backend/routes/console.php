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
 * Purga diaria de claves de idempotencia cuya vigencia de 24 horas haya expirado.
 */
Schedule::call(function () {
    \App\Models\ClaveIdempotencia::where('expira_en', '<', now())->delete();
})->daily()->name('idempotencia:purgar-vencidas');
