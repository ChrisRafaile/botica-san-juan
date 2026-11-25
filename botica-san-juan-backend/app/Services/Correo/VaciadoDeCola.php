<?php

namespace App\Services\Correo;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Procesa unos pocos trabajos dentro del proceso web, ya enviada la respuesta.
 *
 * Usa `runNextJob` y no `queue:work`: el comando instala señales y termina el
 * proceso, que aquí es el que atiende la siguiente petición.
 */
class VaciadoDeCola
{
    private const MAXIMO = 3;

    private bool $archivaFallidos = false;

    public function vaciar(string $cola): void
    {
        try {
            $this->archivarFallidos();

            for ($i = 0; $i < self::MAXIMO; $i++) {
                /* Sin trabajos disponibles vuelve enseguida: `sleep: 0`. */
                app('queue.worker')->runNextJob(
                    (string) config('queue.default'),
                    $cola,
                    new WorkerOptions(sleep: 0, maxTries: 3),
                );
            }
        } catch (\Throwable $e) {
            /* El trabajo sigue en la cola: lo recoge el planificador. */
            Log::warning('No se pudo drenar la cola tras la respuesta', ['excepcion' => $e::class]);
        }
    }

    /**
     * Escribir en `failed_jobs` lo hace el comando `queue:work`, no el worker:
     * sin esto, un trabajo que agote sus intentos aquí desaparecería sin rastro.
     */
    private function archivarFallidos(): void
    {
        if ($this->archivaFallidos) {
            return;
        }

        $this->archivaFallidos = true;

        Event::listen(JobFailed::class, fn (JobFailed $evento) => app('queue.failer')->log(
            $evento->connectionName,
            $evento->job->getQueue(),
            $evento->job->getRawBody(),
            $evento->exception,
        ));
    }
}
