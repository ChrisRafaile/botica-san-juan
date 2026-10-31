<?php

namespace Tests\Feature;

use App\Models\InteraccionFarmacologica;
use App\Models\PrincipioActivo;
use App\Models\Producto;
use App\Services\Clinico\NormalizadorPrincipioActivo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consultor de interacciones.
 *
 * Lo que estas pruebas protegen no es que el consultor encuentre
 * interacciones —eso es lo fácil— sino las tres cosas que lo hacen seguro:
 *
 *   1. Nunca declara nada seguro.
 *   2. Una regla sin aprobar no llega al cliente.
 *   3. Un producto no identificado se declara en voz alta.
 *
 * Si alguna de las tres se rompe, el consultor pasa de ayudar a engañar.
 */
class ConsultorInteraccionesTest extends TestCase
{
    use RefreshDatabase;

    private function principio(string $nombre, array $sinonimos = []): PrincipioActivo
    {
        return PrincipioActivo::create(['nombre' => $nombre, 'sinonimos' => $sinonimos]);
    }

    private function producto(string $nombre, ?PrincipioActivo $principio = null): Producto
    {
        return Producto::create([
            'nombre' => $nombre,
            'concentracion' => '500 mg',
            'laboratorio' => 'Lab',
            'presentacion' => 'Caja',
            'tipo' => 'medicamento',
            'categoria' => 'General',
            'precio' => 10,
            'stock' => 5,
            'principio_activo_id' => $principio?->id,
        ]);
    }

    private function regla(
        PrincipioActivo $a,
        PrincipioActivo $b,
        string $severidad = 'moderada',
        bool $aprobada = true,
    ): InteraccionFarmacologica {
        [$menor, $mayor] = InteraccionFarmacologica::ordenarPar($a->id, $b->id);

        return InteraccionFarmacologica::create([
            'principio_menor_id' => $menor,
            'principio_mayor_id' => $mayor,
            'severidad' => $severidad,
            'efecto' => 'Efecto de prueba.',
            'recomendacion' => 'Consultar al químico farmacéutico.',
            'fuente' => 'Prueba automatizada.',
            'aprobada_en' => $aprobada ? now() : null,
        ]);
    }

    private function evaluar(array $ids): array
    {
        return $this->postJson('/api/interacciones/evaluar', ['productos' => $ids])
            ->assertOk()
            ->json();
    }

    // ------------------------------------- la regla que no se puede romper

    public function test_nunca_dice_que_algo_sea_seguro(): void
    {
        $a = $this->principio('ALFA');
        $b = $this->principio('BETA');

        $respuesta = $this->evaluar([
            $this->producto('ALFA', $a)->id,
            $this->producto('BETA', $b)->id,
        ]);

        /* Sin reglas registradas, el mensaje dice que no se ENCONTRARON
           interacciones registradas; no que no las haya. La diferencia es
           toda la diferencia: la tabla cubre una parte pequeña de las
           combinaciones posibles. */
        $texto = mb_strtolower($respuesta['mensaje'].' '.$respuesta['aviso_legal']);

        foreach (['es seguro', 'son seguros', 'sin riesgo', 'puede tomarlos', 'no hay interacci'] as $prohibida) {
            $this->assertStringNotContainsString($prohibida, $texto, "El consultor no debe decir «{$prohibida}».");
        }

        $this->assertStringContainsString('registradas', $texto);
    }

    public function test_el_aviso_legal_viaja_siempre_con_el_resultado(): void
    {
        $respuesta = $this->evaluar([$this->producto('CUALQUIERA')->id]);

        $this->assertStringContainsString('químico farmacéutico', $respuesta['aviso_legal']);
        $this->assertStringContainsString('no sustituye', mb_strtolower($respuesta['aviso_legal']));
    }

    // ----------------------------------------------- aprobación profesional

    public function test_una_regla_sin_aprobar_no_llega_al_cliente(): void
    {
        $a = $this->principio('ALFA');
        $b = $this->principio('BETA');
        $this->regla($a, $b, aprobada: false);

        $respuesta = $this->evaluar([
            $this->producto('ALFA', $a)->id,
            $this->producto('BETA', $b)->id,
        ]);

        /* Existe para que un químico farmacéutico la revise, no para que un
           cliente la lea. */
        $this->assertSame([], $respuesta['avisos']);
    }

    public function test_una_regla_aprobada_si_se_muestra(): void
    {
        $a = $this->principio('ALFA');
        $b = $this->principio('BETA');
        $this->regla($a, $b, 'grave');

        $respuesta = $this->evaluar([
            $this->producto('ALFA', $a)->id,
            $this->producto('BETA', $b)->id,
        ]);

        $this->assertCount(1, $respuesta['avisos']);
        $this->assertSame('grave', $respuesta['avisos'][0]['severidad']);
        $this->assertTrue($respuesta['derivar']);
    }

    public function test_los_avisos_graves_van_primero(): void
    {
        $a = $this->principio('ALFA');
        $b = $this->principio('BETA');
        $c = $this->principio('GAMMA');

        $this->regla($a, $b, 'leve');
        $this->regla($a, $c, 'grave');

        $respuesta = $this->evaluar([
            $this->producto('ALFA', $a)->id,
            $this->producto('BETA', $b)->id,
            $this->producto('GAMMA', $c)->id,
        ]);

        $this->assertSame('grave', $respuesta['avisos'][0]['severidad']);
    }

    // --------------------------------------------------- transparencia

    public function test_un_producto_sin_perfil_se_declara_y_quita_lo_concluyente(): void
    {
        $a = $this->principio('ALFA');

        $respuesta = $this->evaluar([
            $this->producto('ALFA', $a)->id,
            $this->producto('MARCA COMERCIAL')->id,
        ]);

        $this->assertCount(1, $respuesta['revisados']);
        $this->assertCount(1, $respuesta['no_identificados']);
        $this->assertFalse($respuesta['concluyente']);
        $this->assertTrue($respuesta['derivar']);
        $this->assertStringContainsString('no puede pronunciarse', $respuesta['mensaje']);
    }

    public function test_si_nada_se_identifica_lo_dice_y_deriva(): void
    {
        $respuesta = $this->evaluar([
            $this->producto('MARCA UNO')->id,
            $this->producto('MARCA DOS')->id,
        ]);

        $this->assertSame([], $respuesta['revisados']);
        $this->assertFalse($respuesta['concluyente']);
        $this->assertTrue($respuesta['derivar']);
        $this->assertStringContainsString('químico farmacéutico', $respuesta['mensaje']);
    }

    public function test_con_todo_identificado_y_sin_avisos_sigue_sin_ser_un_visto_bueno(): void
    {
        $a = $this->principio('ALFA');
        $b = $this->principio('BETA');

        $respuesta = $this->evaluar([
            $this->producto('ALFA', $a)->id,
            $this->producto('BETA', $b)->id,
        ]);

        /* `concluyente` dice que se pudo revisar todo el carrito. NO dice que
           la combinación sea segura, y por eso el aviso legal sigue ahí. */
        $this->assertTrue($respuesta['concluyente']);
        $this->assertNotEmpty($respuesta['aviso_legal']);
    }

    // ------------------------------------------------------ normalizador

    public function test_el_normalizador_no_confunde_marcas_parecidas(): void
    {
        $this->principio('ACICLOVIR');
        $normalizador = app(NormalizadorPrincipioActivo::class);

        /* ACICLAV es amoxicilina con ácido clavulánico, no aciclovir. Un
           emparejamiento por parecido los uniría y produciría un aviso
           equivocado sobre lo que alguien va a tomar. */
        $this->assertNull($normalizador->identificar('ACICLAV'));
        $this->assertNotNull($normalizador->identificar('ACICLOVIR'));
        $this->assertNotNull($normalizador->identificar('ACICLOVIR 200 MG'));
    }

    public function test_el_normalizador_ignora_tildes_y_signos(): void
    {
        $this->principio('ACIDO ACETILSALICILICO', ['ASPIRINA']);
        $normalizador = app(NormalizadorPrincipioActivo::class);

        $this->assertNotNull($normalizador->identificar('ÁCIDO ACETILSALICÍLICO 100 mg'));
        $this->assertNotNull($normalizador->identificar('ASPIRINA'));
    }

    public function test_no_casa_un_nombre_que_solo_empieza_parecido(): void
    {
        $this->principio('PARACETAMOL');
        $normalizador = app(NormalizadorPrincipioActivo::class);

        /* Conformarse con que «contenga» la cadena uniría productos
           distintos. Se exige límite de palabra. */
        $this->assertNull($normalizador->identificar('PARACETAMOLINA'));
    }

    // ----------------------------------------------------------- límites

    public function test_se_rechaza_una_lista_vacia(): void
    {
        $this->postJson('/api/interacciones/evaluar', ['productos' => []])->assertStatus(422);
    }

    public function test_se_rechaza_una_lista_desmesurada(): void
    {
        $this->postJson('/api/interacciones/evaluar', ['productos' => range(1, 51)])->assertStatus(422);
    }
}
