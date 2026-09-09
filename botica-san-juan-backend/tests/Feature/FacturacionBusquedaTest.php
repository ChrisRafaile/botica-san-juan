<?php

namespace Tests\Feature;

use App\Models\ComprobanteElectronico;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba de regresion del hallazgo C-04 de la auditoria.
 *
 * La busqueda de comprobantes usaba CONCAT(serie, '-', LPAD(numero, 8, '0')),
 * funciones que no existen en SQLite y que hacian fallar el endpoint en
 * tiempo de ejecucion. Estas pruebas garantizan que la busqueda funcione
 * en cualquier motor soportado.
 */
class FacturacionBusquedaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El listado de comprobantes dejo de ser publico: expone el nombre y el
     * documento de identidad del cliente, de modo que ahora exige token.
     */
    private function consultar(string $ruta)
    {
        $admin = $this->crearAdministrador();
        $token = $admin->createToken('prueba')->plainTextToken;

        return $this->withHeader('Authorization', "Bearer {$token}")->getJson($ruta);
    }

    private function crearComprobante(string $serie, int $numero, string $cliente): ComprobanteElectronico
    {
        $usuario = $this->crearCliente([
            'dni' => str_pad((string) (30000000 + $numero), 8, '0', STR_PAD_LEFT),
            'email' => "cliente{$numero}@boticasanjuan.pe",
        ]);

        // La tabla 'pedidos' del proyecto usa 'fecha' y no maneja columna de estado.
        $pedido = Pedido::create([
            'usuario_id' => $usuario->id,
            'total' => 100.00,
            'fecha' => now(),
        ]);

        return ComprobanteElectronico::create([
            'pedido_id' => $pedido->id,
            'tipo_comprobante' => 'boleta',
            'serie' => $serie,
            'numero' => $numero,
            'cliente_nombre' => $cliente,
            // Documento sin digitos que puedan colisionar con la busqueda por
            // numero de comprobante, para que cada caso valide un solo criterio.
            'cliente_documento' => str_pad((string) (70000000 + $numero), 8, '0', STR_PAD_LEFT),
            'total' => 100.00,
            'estado_sunat' => 'pendiente',
            'fecha_emision' => now(),
        ]);
    }

    public function test_la_busqueda_de_comprobantes_no_lanza_error_de_sql(): void
    {
        $this->crearComprobante('B001', 12, 'Vilma Urrutia Vasquez');

        // Antes de la correccion este endpoint devolvia 500 por «no such function: CONCAT».
        $this->consultar('/api/facturacion/documentos?q=B001')->assertSuccessful();
    }

    public function test_busca_por_numero_completo_con_ceros_a_la_izquierda(): void
    {
        $this->crearComprobante('B001', 12, 'Vilma Urrutia Vasquez');
        $this->crearComprobante('B001', 13, 'Otro Cliente');

        $respuesta = $this->consultar('/api/facturacion/documentos?q=B001-00000012');

        $respuesta->assertSuccessful();
        $this->assertCount(1, $respuesta->json('data') ?? $respuesta->json());
    }

    public function test_busca_por_numero_correlativo_suelto(): void
    {
        $this->crearComprobante('B001', 12, 'Vilma Urrutia Vasquez');
        $this->crearComprobante('B001', 13, 'Otro Cliente');

        $respuesta = $this->consultar('/api/facturacion/documentos?q=12');

        $respuesta->assertSuccessful();
        $this->assertCount(1, $respuesta->json('data') ?? $respuesta->json());
    }

    public function test_busca_por_nombre_del_cliente(): void
    {
        $this->crearComprobante('B001', 12, 'Vilma Urrutia Vasquez');
        $this->crearComprobante('B001', 13, 'Otro Cliente');

        $respuesta = $this->consultar('/api/facturacion/documentos?q=Urrutia');

        $respuesta->assertSuccessful();
        $this->assertCount(1, $respuesta->json('data') ?? $respuesta->json());
    }

    public function test_una_busqueda_sin_coincidencias_devuelve_una_lista_vacia(): void
    {
        $this->crearComprobante('B001', 12, 'Vilma Urrutia Vasquez');

        $respuesta = $this->consultar('/api/facturacion/documentos?q=F001-99999999');

        $respuesta->assertSuccessful();
        $this->assertCount(0, $respuesta->json('data') ?? $respuesta->json());
    }

    public function test_la_serie_y_el_numero_son_unicos_en_conjunto(): void
    {
        $this->crearComprobante('B001', 12, 'Vilma Urrutia Vasquez');

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->crearComprobante('B001', 12, 'Cliente Duplicado');
    }
}
