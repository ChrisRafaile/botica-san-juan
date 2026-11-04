<?php

namespace Tests\Feature;

use App\Models\EventoOperacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La bitácora de operación, vista desde el panel.
 * ---------------------------------------------------------------------------
 * Lo que hay que demostrar aquí es lo que pide el punto 30 de la rúbrica:
 * que los eventos de los tres niveles existen, se distinguen y se pueden
 * consultar. Y, además, dos cosas que no pide pero que importan igual: que
 * nadie sin rol pueda leerlos, y que un campo libre no se devuelva en crudo.
 */
class BitacoraDeOperacionTest extends TestCase
{
    use RefreshDatabase;

    private ?Usuario $administrador = null;

    /**
     * El MISMO administrador en todas las llamadas dentro de una prueba.
     *
     * Creándolo cada vez, el segundo `actingAs($this->admin())` del mismo
     * método reventaba por el índice único del correo.
     */
    private function admin(): Usuario
    {
        if ($this->administrador !== null) {
            return $this->administrador;
        }

        $usuario = new Usuario([
            'nombre' => 'Administrador',
            'email' => 'admin.bitacora@prueba.test',
            'dni' => '10000031',
            'rol' => 'administrador',
        ]);

        $usuario->establecerContrasena('clave-de-prueba');
        $usuario->forceFill(['email_verified_at' => now()])->save();

        return $this->administrador = $usuario;
    }

    private function cliente(): Usuario
    {
        $usuario = new Usuario([
            'nombre' => 'Cliente',
            'email' => 'cliente.bitacora@prueba.test',
            'dni' => '10000032',
            'rol' => 'cliente',
        ]);

        $usuario->establecerContrasena('clave-de-prueba');
        $usuario->forceFill(['email_verified_at' => now()])->save();

        return $usuario;
    }

    private function sembrarLosTresNiveles(): void
    {
        EventoOperacion::info('SYNC_VENTA_CREADA', 'Venta de caja registrada con su correlativo');
        EventoOperacion::advertencia('SYNC_VENTA_DUPLICADA', 'Se reenvio una venta que ya existia');
        EventoOperacion::error('SYNC_VENTA_RECHAZADA', 'La venta no se pudo registrar');
    }

    // ------------------------------------------------------------ acceso

    public function test_sin_sesion_la_bitacora_no_se_lee(): void
    {
        $this->getJson('/api/eventos')->assertStatus(401);
        $this->getJson('/api/eventos/resumen')->assertStatus(401);
    }

    public function test_un_cliente_no_puede_leer_la_bitacora(): void
    {
        /* Exigir sesion no es exigir rol. La bitacora enlaza usuarios con
           pedidos y con dispositivos de caja: es un mapa de quien hizo que. */
        $this->actingAs($this->cliente())->getJson('/api/eventos')->assertStatus(403);
    }

    public function test_un_administrador_si(): void
    {
        $this->sembrarLosTresNiveles();

        $this->actingAs($this->admin())->getJson('/api/eventos')->assertOk();
    }

    // ------------------------------------------- los tres niveles (punto 30)

    public function test_los_tres_niveles_quedan_registrados_y_se_distinguen(): void
    {
        /* ESTA ES LA PRUEBA DEL PUNTO 30 DE LA RUBRICA: info, warning y
           error, cada uno con su recuento. */
        $this->sembrarLosTresNiveles();

        $this->actingAs($this->admin())->getJson('/api/eventos/resumen')
            ->assertOk()
            ->assertJsonPath('niveles.info', 1)
            ->assertJsonPath('niveles.warning', 1)
            ->assertJsonPath('niveles.error', 1)
            ->assertJsonPath('total', 3);
    }

    public function test_se_puede_filtrar_por_nivel(): void
    {
        $this->sembrarLosTresNiveles();

        $respuesta = $this->actingAs($this->admin())->getJson('/api/eventos?nivel=error')->assertOk();

        $this->assertSame(1, $respuesta->json('paginacion.total'));
        $this->assertSame('error', $respuesta->json('datos.0.nivel'));
    }

    public function test_un_nivel_inventado_se_rechaza(): void
    {
        /* Sin la validacion, `?nivel=critico` devolveria una lista vacia y
           parecaria que no hubo eventos, en lugar de decir que ese nivel no
           existe. */
        $this->actingAs($this->admin())->getJson('/api/eventos?nivel=critico')->assertStatus(422);
    }

    public function test_se_puede_filtrar_por_codigo(): void
    {
        $this->sembrarLosTresNiveles();

        $this->actingAs($this->admin())->getJson('/api/eventos?codigo=SYNC_VENTA_DUPLICADA')
            ->assertOk()
            ->assertJsonPath('paginacion.total', 1)
            ->assertJsonPath('datos.0.nivel', 'warning');
    }

    public function test_la_busqueda_mira_el_mensaje(): void
    {
        $this->sembrarLosTresNiveles();

        $this->actingAs($this->admin())->getJson('/api/eventos?buscar=correlativo')
            ->assertOk()
            ->assertJsonPath('paginacion.total', 1);
    }

    // ------------------------------------------------------------ fechas

    public function test_el_filtro_de_fecha_incluye_el_dia_pedido_entero(): void
    {
        /* El error clasico: comparar contra la fecha a secas excluye el propio
           dia que se pidio, porque `2026-11-04` es `2026-11-04 00:00:00` y
           cualquier evento de ese dia es posterior. Entonces el dia del
           incidente sale vacio, que es justo el dia que se queria ver. */
        $evento = EventoOperacion::info('PRUEBA_FECHA', 'Ocurrio a media tarde');
        $evento->forceFill(['created_at' => now()->setTime(16, 30)])->save();

        $hoy = now()->toDateString();

        $this->actingAs($this->admin())->getJson("/api/eventos?desde={$hoy}&hasta={$hoy}")
            ->assertOk()
            ->assertJsonPath('paginacion.total', 1);
    }

    public function test_lo_mas_reciente_va_primero(): void
    {
        $viejo = EventoOperacion::info('VIEJO', 'Hace tres dias');
        $viejo->forceFill(['created_at' => now()->subDays(3)])->save();

        EventoOperacion::info('NUEVO', 'Ahora mismo');

        $this->actingAs($this->admin())->getJson('/api/eventos')
            ->assertOk()
            ->assertJsonPath('datos.0.codigo', 'NUEVO');
    }

    // ------------------------------------------------------ privacidad

    public function test_un_dni_en_el_mensaje_no_se_devuelve_en_crudo(): void
    {
        /* El mensaje lo escribe quien registra el evento, y es texto libre:
           «No se encontro al usuario 47382910» es perfectamente posible. Se
           recorta con el mismo depurador que limpia lo que viaja a Sentry. */
        EventoOperacion::error('PRUEBA_DNI', 'No se encontro al usuario 47382910');

        $respuesta = $this->actingAs($this->admin())->getJson('/api/eventos');

        $this->assertStringNotContainsString('47382910', $respuesta->getContent());
    }

    public function test_el_contexto_libre_tampoco(): void
    {
        /* `contexto` es un campo JSON que rellena cada sitio con lo que le
           parecio util: no se puede saber de antemano que lleva dentro, asi
           que se trata como sospechoso aunque hoy solo lleve identificadores. */
        EventoOperacion::error('PRUEBA_CONTEXTO', 'Fallo al avisar', [
            'contexto' => ['destinatario' => 'rosa.quispe@gmail.com'],
        ]);

        $respuesta = $this->actingAs($this->admin())->getJson('/api/eventos');

        $this->assertStringNotContainsString('rosa.quispe@gmail.com', $respuesta->getContent());
        $this->assertStringContainsString('[correo]', $respuesta->getContent());
    }

    // ------------------------------------------------------ solo lectura

    public function test_no_se_puede_escribir_en_la_bitacora_desde_la_interfaz(): void
    {
        /* Una bitacora que se puede editar desde la interfaz deja de servir
           como evidencia de lo que paso. */
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/eventos', [
            'nivel' => 'info', 'codigo' => 'INVENTADO', 'mensaje' => 'A mano',
        ])->assertStatus(405);

        $this->assertSame(0, EventoOperacion::count());
    }

    // ------------------------------------------------------ resumen

    public function test_el_resumen_lista_los_codigos_con_su_recuento(): void
    {
        EventoOperacion::info('SYNC_VENTA_CREADA', 'Una');
        EventoOperacion::info('SYNC_VENTA_CREADA', 'Dos');
        EventoOperacion::error('SYNC_VENTA_RECHAZADA', 'Tres');

        $respuesta = $this->actingAs($this->admin())->getJson('/api/eventos/resumen')->assertOk();

        $codigos = collect($respuesta->json('codigos'))->keyBy('codigo');

        $this->assertSame(2, $codigos['SYNC_VENTA_CREADA']['total']);
        $this->assertSame(1, $codigos['SYNC_VENTA_RECHAZADA']['total']);
    }

    public function test_los_recuentos_no_dependen_de_la_pagina_visible(): void
    {
        /* Un contador que cambia al pasar de pagina no sirve para nada. */
        foreach (range(1, 30) as $i) {
            EventoOperacion::info('MASIVO', "Evento {$i}");
        }

        $this->actingAs($this->admin())->getJson('/api/eventos/resumen')
            ->assertOk()
            ->assertJsonPath('total', 30);

        $this->actingAs($this->admin())->getJson('/api/eventos?per_page=5')
            ->assertOk()
            ->assertJsonPath('paginacion.total', 30)
            ->assertJsonCount(5, 'datos');
    }

    public function test_la_bitacora_vacia_responde_sin_romperse(): void
    {
        /* El estado del primer dia. Una pantalla que revienta cuando no hay
           datos es una pantalla que nadie probo antes de que hubiera datos. */
        $this->actingAs($this->admin())->getJson('/api/eventos')
            ->assertOk()
            ->assertJsonPath('paginacion.total', 0)
            ->assertJsonPath('datos', []);

        $this->actingAs($this->admin())->getJson('/api/eventos/resumen')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('niveles.error', 0);
    }
}
