<?php

namespace Tests\Feature;

use App\Jobs\EnviarCorreoTransaccional;
use App\Mail\BienvenidaCliente;
use App\Models\EventoOperacion;
use App\Models\Usuario;
use App\Notifications\RestablecerContrasena;
use App\Services\Correo\CorreoTransaccional;
use App\Services\Correo\VaciadoDeCola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ColaDeCorreosTest extends TestCase
{
    use RefreshDatabase;

    private function registrar(string $correo = 'camila@prueba.test'): void
    {
        $this->postJson('/api/register', [
            'nombre' => 'Camila Torres', 'dni' => '71234567', 'email' => $correo,
            'password' => 'clave-segura-123', 'acepta_terminos' => true,
        ])->assertCreated();
    }

    public function test_el_registro_encola_la_bienvenida(): void
    {
        Queue::fake();
        Notification::fake();

        $this->registrar();

        Queue::assertPushedOn('correos', EnviarCorreoTransaccional::class, fn ($t) => $t->tipo === 'bienvenida');
    }

    public function test_la_recuperacion_se_encola_sin_el_correo_ni_el_token(): void
    {
        config(['queue.default' => 'database']);
        $this->mock(VaciadoDeCola::class)->shouldReceive('vaciar')->once()->with('correos');
        $usuario = $this->crearCliente(['email' => 'olvido@prueba.test']);

        $this->postJson('/api/password/olvide', ['email' => 'olvido@prueba.test'])->assertOk();

        $carga = (string) DB::table('jobs')->where('queue', 'correos')->value('payload');
        $this->assertStringContainsString('recuperacion_contrasena', $carga);
        $this->assertStringNotContainsString('olvido@prueba.test', $carga);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertNotNull($usuario);
    }

    public function test_el_correo_sale_al_terminar_la_peticion_sin_worker(): void
    {
        config(['queue.default' => 'database']);
        Mail::fake();
        Notification::fake();

        $this->registrar();

        Mail::assertSent(BienvenidaCliente::class, 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_si_el_proveedor_falla_el_trabajo_queda_para_reintentar_sin_datos_personales(): void
    {
        config(['queue.default' => 'database']);
        Notification::fake();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Resend rechazo camila@prueba.test'));

        $this->registrar();

        $trabajo = DB::table('jobs')->where('queue', 'correos')->first();
        $this->assertNotNull($trabajo);
        $this->assertSame(1, (int) $trabajo->attempts);
        $this->assertGreaterThan(now()->timestamp, (int) $trabajo->available_at);
        $this->assertDatabaseHas('eventos_operacion', ['codigo' => EventoOperacion::CORREO_FALLIDO]);

        // Agotados los intentos, lo archivado tampoco lleva el destinatario.
        DB::table('jobs')->update(['attempts' => 2, 'available_at' => now()->subMinute()->timestamp]);
        app(VaciadoDeCola::class)->vaciar('correos');

        $fallido = DB::table('failed_jobs')->first();
        $this->assertNotNull($fallido);
        $this->assertStringNotContainsString('camila@prueba.test', $fallido->exception.$fallido->payload);
    }

    public function test_un_reintento_de_recuperacion_no_lo_frena_el_limite_del_broker(): void
    {
        config(['queue.default' => 'database']);
        $usuario = $this->crearCliente(['email' => 'reintento@prueba.test']);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Resend caido'));

        $this->postJson('/api/password/olvide', ['email' => 'reintento@prueba.test'])->assertOk();
        $this->assertDatabaseCount('jobs', 1);

        Notification::fake();
        DB::table('jobs')->update(['available_at' => now()->subMinute()->timestamp]);
        app(VaciadoDeCola::class)->vaciar('correos');

        Notification::assertSentTo($usuario, RestablecerContrasena::class);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_si_la_cola_no_admite_el_trabajo_se_envia_en_el_acto(): void
    {
        Mail::fake();
        $usuario = $this->crearCliente();
        config(['queue.default' => 'conexion-inexistente']);

        app(CorreoTransaccional::class)->encolar('bienvenida', $usuario->id);

        Mail::assertSent(BienvenidaCliente::class, 1);
    }
}
