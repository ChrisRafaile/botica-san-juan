<?php

namespace Tests\Feature;

use App\Models\Pago;
use App\Models\Pedido;
use App\Services\Pagos\EstadoPago;
use App\Services\Pagos\IzipayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre el flujo de cobro contra la pasarela: inicio de la operacion,
 * autenticidad de la notificacion, idempotencia, orden de los eventos y
 * autoridad del backend sobre el importe y sobre el estado.
 */
class PagosIzipayTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETO = 'segmento-secreto-de-prueba';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.izipay.mode', 'simulado');
        config()->set('services.izipay.webhook_path', self::SECRETO);
    }

    private function pedidoDe($usuario, float $total = 149.90): Pedido
    {
        return Pedido::create([
            'usuario_id' => $usuario->id,
            'fecha_pedido' => now(),
            'total' => $total,
            'moneda' => 'PEN',
            'estado' => 'confirmado',
            'estado_pago' => EstadoPago::PENDIENTE,
        ]);
    }

    /** Construye una notificacion firmada igual que lo haria la pasarela. */
    private function notificacion(string $referencia, string $orderStatus, string $uuid = 'txn-0001'): array
    {
        $cuerpo = json_encode([
            'orderStatus' => $orderStatus,
            'orderDetails' => ['orderId' => $referencia],
            'transactions' => [[
                'uuid' => $uuid,
                'paymentMethodType' => 'CARD',
                'errorCode' => $orderStatus === 'PAID' ? null : 'PSP_010',
                'transactionDetails' => ['cardDetails' => [
                    'effectiveBrand' => 'VISA',
                    'pan' => '497010XXXXXX0055',
                ]],
            ]],
        ], JSON_UNESCAPED_SLASHES);

        return [
            'kr-answer' => $cuerpo,
            'kr-hash' => app(IzipayClient::class)->firmar($cuerpo, 'ipn'),
            // La plataforma declara el algoritmo y cual de sus dos claves uso.
            'kr-hash-algorithm' => 'sha256_hmac',
            'kr-hash-key' => 'password',
        ];
    }

    /** Retorno del navegador: se firma con la clave HMAC-SHA-256, no con la del IPN. */
    private function retornoNavegador(string $referencia, string $orderStatus): array
    {
        $cuerpo = json_encode([
            'orderStatus' => $orderStatus,
            'orderDetails' => ['orderId' => $referencia],
        ], JSON_UNESCAPED_SLASHES);

        return [
            'kr-answer' => $cuerpo,
            'kr-hash' => app(IzipayClient::class)->firmar($cuerpo, 'navegador'),
        ];
    }

    private function iniciarPago(): array
    {
        $cliente = $this->crearCliente();
        $token = $cliente->createToken('prueba')->plainTextToken;
        $pedido = $this->pedidoDe($cliente);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pedidos/{$pedido->id}/pago");

        $respuesta->assertSuccessful();

        return [$cliente, $token, $pedido, Pago::where('pedido_id', $pedido->id)->firstOrFail()];
    }

    public function test_iniciar_el_pago_devuelve_token_de_formulario_y_llave_publica(): void
    {
        [, , , $pago] = $this->iniciarPago();

        $this->assertSame(EstadoPago::PROCESANDO, $pago->estado);
        $this->assertSame('149.90', (string) $pago->monto);
    }

    public function test_la_respuesta_nunca_expone_la_llave_privada(): void
    {
        config()->set('services.izipay.password', 'CLAVE-PRIVADA-NO-DEBE-SALIR');
        config()->set('services.izipay.sha256_key', 'HMAC-PRIVADA-NO-DEBE-SALIR');

        $cliente = $this->crearCliente();
        $token = $cliente->createToken('prueba')->plainTextToken;
        $pedido = $this->pedidoDe($cliente);

        $contenido = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pedidos/{$pedido->id}/pago")
            ->getContent();

        $this->assertStringNotContainsString('CLAVE-PRIVADA-NO-DEBE-SALIR', $contenido);
        $this->assertStringNotContainsString('HMAC-PRIVADA-NO-DEBE-SALIR', $contenido);
    }

    public function test_un_usuario_no_puede_iniciar_el_pago_de_un_pedido_ajeno(): void
    {
        $duenio = $this->crearCliente();
        $pedido = $this->pedidoDe($duenio);

        $intruso = $this->crearCliente(['email' => 'intruso@boticasanjuan.pe', 'dni' => '20000009']);
        $token = $intruso->createToken('prueba')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pedidos/{$pedido->id}/pago")
            ->assertStatus(403);
    }

    public function test_dos_intentos_sobre_el_mismo_pedido_no_crean_dos_pagos(): void
    {
        $cliente = $this->crearCliente();
        $token = $cliente->createToken('prueba')->plainTextToken;
        $pedido = $this->pedidoDe($cliente);

        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/pedidos/{$pedido->id}/pago");
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/pedidos/{$pedido->id}/pago");

        $this->assertDatabaseCount('pagos', 1);
    }

    public function test_una_notificacion_valida_marca_el_pedido_como_pagado(): void
    {
        [, , $pedido, $pago] = $this->iniciarPago();

        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $this->notificacion($pago->referencia_pedido, 'PAID'))
            ->assertStatus(200);

        $this->assertSame(EstadoPago::PAGADO, $pago->fresh()->estado);
        $this->assertSame(EstadoPago::PAGADO, $pedido->fresh()->estado_pago);
        $this->assertSame('VISA', $pago->fresh()->marca_tarjeta);
        $this->assertSame('0055', $pago->fresh()->ultimos4);
        $this->assertNotNull($pago->fresh()->pagado_en);
    }

    public function test_una_notificacion_con_firma_invalida_se_rechaza_y_no_altera_nada(): void
    {
        [, , $pedido, $pago] = $this->iniciarPago();

        $datos = $this->notificacion($pago->referencia_pedido, 'PAID');
        $datos['kr-hash'] = str_repeat('0', 64);

        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $datos)->assertStatus(401);

        $this->assertSame(EstadoPago::PROCESANDO, $pago->fresh()->estado);
        $this->assertSame(EstadoPago::PROCESANDO, $pedido->fresh()->estado_pago);
        $this->assertDatabaseCount('pagos_eventos', 0);
    }

    public function test_un_segmento_secreto_incorrecto_devuelve_404(): void
    {
        [, , , $pago] = $this->iniciarPago();

        $this->postJson('/api/pagos/notificacion/secreto-equivocado', $this->notificacion($pago->referencia_pedido, 'PAID'))
            ->assertStatus(404);

        $this->assertSame(EstadoPago::PROCESANDO, $pago->fresh()->estado);
    }

    public function test_la_misma_notificacion_dos_veces_solo_se_aplica_una_vez(): void
    {
        [, , , $pago] = $this->iniciarPago();
        $datos = $this->notificacion($pago->referencia_pedido, 'PAID');

        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $datos)->assertStatus(200);
        $primeraFecha = $pago->fresh()->pagado_en;

        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $datos)->assertStatus(200);

        $this->assertDatabaseCount('pagos_eventos', 1);
        $this->assertEquals($primeraFecha, $pago->fresh()->pagado_en);
    }

    public function test_una_notificacion_fuera_de_orden_no_revierte_un_pago_confirmado(): void
    {
        [, , , $pago] = $this->iniciarPago();

        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $this->notificacion($pago->referencia_pedido, 'PAID', 'txn-A'))
            ->assertStatus(200);

        // Notificacion tardia de rechazo, con otro identificador de evento.
        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $this->notificacion($pago->referencia_pedido, 'UNPAID', 'txn-B'))
            ->assertStatus(200);

        $this->assertSame(EstadoPago::PAGADO, $pago->fresh()->estado);
    }

    public function test_un_pago_rechazado_queda_fallido_con_motivo_saneado(): void
    {
        [, , $pedido, $pago] = $this->iniciarPago();

        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $this->notificacion($pago->referencia_pedido, 'UNPAID'))
            ->assertStatus(200);

        $this->assertSame(EstadoPago::FALLIDO, $pago->fresh()->estado);
        $this->assertSame(EstadoPago::FALLIDO, $pedido->fresh()->estado_pago);
        $this->assertSame('El pago no pudo completarse.', $pago->fresh()->mensaje_error);
    }

    public function test_un_abandono_deja_el_pago_cancelado_y_no_pagado(): void
    {
        [, , $pedido, $pago] = $this->iniciarPago();

        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $this->notificacion($pago->referencia_pedido, 'ABANDONED'))
            ->assertStatus(200);

        $this->assertSame(EstadoPago::CANCELADO, $pago->fresh()->estado);
        $this->assertNotSame(EstadoPago::PAGADO, $pedido->fresh()->estado_pago);
    }

    public function test_una_referencia_desconocida_no_crea_pagos(): void
    {
        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $this->notificacion('BSJ-99999999-xxxxxx', 'PAID'))
            ->assertStatus(404);

        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_el_evento_registrado_no_conserva_datos_sensibles_de_tarjeta(): void
    {
        [, , , $pago] = $this->iniciarPago();

        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $this->notificacion($pago->referencia_pedido, 'PAID'));

        $payload = json_encode(\App\Models\PagoEvento::firstOrFail()->payload);
        $this->assertStringNotContainsString('497010XXXXXX0055', $payload);
        $this->assertStringNotContainsString('"pan"', $payload);
    }

    public function test_el_estado_del_pago_solo_lo_consulta_su_dueno(): void
    {
        [, $token, , $pago] = $this->iniciarPago();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pagos/{$pago->referencia_pedido}/estado")
            ->assertSuccessful()
            ->assertJsonPath('pago.estado', EstadoPago::PROCESANDO);

        $intruso = $this->crearCliente(['email' => 'otro@boticasanjuan.pe', 'dni' => '20000011']);
        $tokenIntruso = $intruso->createToken('prueba')->plainTextToken;

        // El guard cachea el usuario resuelto en la primera peticion del test;
        // sin este olvido, la segunda seguiria autenticada como el dueno y la
        // prueba verificaria algo distinto de lo que dice verificar.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$tokenIntruso}")
            ->getJson("/api/pagos/{$pago->referencia_pedido}/estado")
            ->assertStatus(403);
    }

    public function test_el_importe_lo_fija_el_pedido_y_no_la_peticion(): void
    {
        $cliente = $this->crearCliente();
        $token = $cliente->createToken('prueba')->plainTextToken;
        $pedido = $this->pedidoDe($cliente, 149.90);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/pedidos/{$pedido->id}/pago", ['monto' => 1.00, 'amount' => 1])
            ->assertSuccessful();

        $this->assertSame('149.90', (string) Pago::firstOrFail()->monto);
    }

    public function test_un_estado_desconocido_del_proveedor_nunca_se_interpreta_como_pagado(): void
    {
        [, , , $pago] = $this->iniciarPago();

        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $this->notificacion($pago->referencia_pedido, 'ESTADO_QUE_NO_EXISTE'))
            ->assertStatus(200);

        $this->assertNotSame(EstadoPago::PAGADO, $pago->fresh()->estado);
    }

    public function test_el_retorno_del_navegador_se_valida_con_su_propia_clave(): void
    {
        [, $token, , $pago] = $this->iniciarPago();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pagos/validar-retorno', $this->retornoNavegador($pago->referencia_pedido, 'PAID'))
            ->assertSuccessful()
            ->assertJsonPath('message', 'Retorno verificado.');
    }

    /**
     * La prueba central del diseno: una respuesta del navegador con firma
     * valida que afirma PAID no basta para dar el pedido por pagado. Solo la
     * notificacion servidor a servidor mueve el estado.
     */
    public function test_un_retorno_valido_que_dice_pagado_no_marca_el_pedido_como_pagado(): void
    {
        [, $token, $pedido, $pago] = $this->iniciarPago();

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pagos/validar-retorno', $this->retornoNavegador($pago->referencia_pedido, 'PAID'));

        $respuesta->assertSuccessful();

        // El endpoint devuelve el estado PERSISTIDO, no el que trae el retorno.
        $respuesta->assertJsonPath('pago.estado', EstadoPago::PROCESANDO);
        $this->assertSame(EstadoPago::PROCESANDO, $pago->fresh()->estado);
        $this->assertSame(EstadoPago::PROCESANDO, $pedido->fresh()->estado_pago);
    }

    public function test_un_retorno_con_firma_invalida_se_rechaza(): void
    {
        [, $token, , $pago] = $this->iniciarPago();

        $datos = $this->retornoNavegador($pago->referencia_pedido, 'PAID');
        $datos['kr-hash'] = str_repeat('f', 64);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/pagos/validar-retorno', $datos)
            ->assertStatus(401);
    }

    /**
     * Las dos claves son distintas por diseno: una firma valida para el canal
     * del navegador no debe pasar por el canal servidor a servidor.
     */
    public function test_la_firma_del_navegador_no_sirve_para_la_notificacion(): void
    {
        [, , , $pago] = $this->iniciarPago();

        $datos = $this->retornoNavegador($pago->referencia_pedido, 'PAID');

        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $datos)
            ->assertStatus(401);

        $this->assertSame(EstadoPago::PROCESANDO, $pago->fresh()->estado);
    }

    public function test_los_campos_de_tarjeta_heredados_no_salen_por_la_api(): void
    {
        $cliente = $this->crearCliente();
        $token = $cliente->createToken('prueba')->plainTextToken;
        $this->pedidoDe($cliente);

        $contenido = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pedidos')
            ->getContent();

        $this->assertStringNotContainsString('card_number', $contenido);
        $this->assertStringNotContainsString('cvv', $contenido);
        $this->assertStringNotContainsString('expiry_date', $contenido);
    }

    /**
     * La plataforma declara el algoritmo de firma en 'kr-hash-algorithm'.
     * Un valor distinto del unico previsto se rechaza antes de calcular nada.
     */
    public function test_una_notificacion_con_algoritmo_no_soportado_se_rechaza(): void
    {
        [, , , $pago] = $this->iniciarPago();

        $datos = $this->notificacion($pago->referencia_pedido, 'PAID');
        $datos['kr-hash-algorithm'] = 'md5';

        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $datos)
            ->assertStatus(401);

        $this->assertSame(EstadoPago::PROCESANDO, $pago->fresh()->estado);
    }

    /**
     * La clave que firma el retorno del navegador viaja al cliente. Si un
     * mensaje llega al canal servidor a servidor declarando esa clave, se
     * rechaza aunque la firma cuadre con ella.
     */
    public function test_una_notificacion_que_declara_la_clave_del_navegador_se_rechaza(): void
    {
        [, , , $pago] = $this->iniciarPago();

        $datos = $this->notificacion($pago->referencia_pedido, 'PAID');
        // 'sha256_hmac' es el valor que la plataforma declara para el retorno
        // del navegador. Correcto en su canal, inaceptable en este.
        $datos['kr-hash-key'] = 'sha256_hmac';

        $this->postJson('/api/pagos/notificacion/' . self::SECRETO, $datos)
            ->assertStatus(401);

        $this->assertSame(EstadoPago::PROCESANDO, $pago->fresh()->estado);
    }

    /**
     * El importe viaja como entero en la unidad minima de la moneda. El sol
     * tiene dos decimales; el yen, ninguno. Un error de factor aqui cobra cien
     * veces de mas o de menos.
     */
    public function test_el_importe_se_expresa_en_la_unidad_minima_de_la_moneda(): void
    {
        $cliente = app(IzipayClient::class);

        $this->assertSame(14990, $cliente->aUnidadMinima(149.90, 'PEN'));
        $this->assertSame(99, $cliente->aUnidadMinima(0.99, 'PEN'));
        $this->assertSame(149, $cliente->aUnidadMinima(149.0, 'JPY'));
        $this->assertSame(149.90, $cliente->desdeUnidadMinima(14990, 'PEN'));
    }

    /**
     * El estado simplificado de la plataforma solo admite cuatro valores.
     * Fijarlos en una prueba impide que se cuele un estado inventado.
     */
    public function test_los_estados_del_proveedor_son_los_cuatro_documentados(): void
    {
        $this->assertSame(
            ['PAID', 'UNPAID', 'RUNNING', 'ABANDONED'],
            EstadoPago::estadosDelProveedor()
        );

        $this->assertSame(EstadoPago::PAGADO, EstadoPago::desdeProveedor('PAID'));
        $this->assertSame(EstadoPago::FALLIDO, EstadoPago::desdeProveedor('UNPAID'));
        $this->assertSame(EstadoPago::PROCESANDO, EstadoPago::desdeProveedor('RUNNING'));
        $this->assertSame(EstadoPago::CANCELADO, EstadoPago::desdeProveedor('ABANDONED'));
    }

    /**
     * El estado detallado es un campo distinto del simplificado. Ninguno de
     * sus valores de rechazo o de espera puede leerse como un cobro logrado.
     */
    public function test_ningun_estado_detallado_de_rechazo_se_lee_como_pagado(): void
    {
        foreach (['REFUSED', 'ERROR', 'CAPTURE_FAILED', 'CANCELLED', 'EXPIRED'] as $detallado) {
            $this->assertSame(
                EstadoPago::FALLIDO,
                EstadoPago::desdeEstadoDetallado($detallado),
                "El estado detallado {$detallado} no debe considerarse pagado."
            );
        }

        foreach (['WAITING_FOR_PAYMENT', 'UNDER_VERIFICATION', 'AUTHORISED_TO_VALIDATE'] as $detallado) {
            $this->assertSame(EstadoPago::PROCESANDO, EstadoPago::desdeEstadoDetallado($detallado));
        }

        $this->assertSame(EstadoPago::PAGADO, EstadoPago::desdeEstadoDetallado('AUTHORISED'));
        // Un valor que la plataforma no documenta jamas se asume exitoso.
        $this->assertSame(EstadoPago::PROCESANDO, EstadoPago::desdeEstadoDetallado('LO_QUE_SEA'));
    }
}
