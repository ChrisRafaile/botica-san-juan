<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los mensajes de validación salen en español, no en crudo.
 *
 * POR QUÉ EXISTE ESTA PRUEBA
 *
 * Al poner la aplicación en español se añadió lang/es.json, que cubre las
 * frases de las plantillas de correo pero NO los mensajes de validación, que
 * Laravel busca en un archivo de arreglo. Durante un tiempo cualquier campo
 * mal rellenado devolvía la clave literal:
 *
 *     {"message":"validation.required", ...}
 *
 * Y eso se pintaba tal cual en la pantalla del cliente.
 *
 * Las pruebas que ya existían no lo detectaron, y la razón merece anotarse:
 * `assertJsonValidationErrors('archivo')` comprueba QUÉ campo falla, no qué
 * dice el mensaje. Una suite entera en verde con todos los formularios de la
 * aplicación mostrando «validation.required».
 *
 * Esta prueba mira el texto. No comprueba la redacción exacta —cambiarla no
 * debería romper nada— sino lo único que de verdad importa: que no empiece
 * por «validation.», que es la forma que tiene una clave sin traducir.
 */
class MensajesDeValidacionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> Todos los mensajes de error de la respuesta. */
    private function mensajes(array $json): array
    {
        $mensajes = [$json['message'] ?? ''];

        foreach (($json['errors'] ?? []) as $porCampo) {
            foreach ((array) $porCampo as $texto) {
                $mensajes[] = $texto;
            }
        }

        return $mensajes;
    }

    private function assertTraducidos(array $json): void
    {
        foreach ($this->mensajes($json) as $texto) {
            $this->assertStringStartsNotWith(
                'validation.',
                $texto,
                "El mensaje «{$texto}» es la clave sin traducir: falta en lang/es/validation.php.",
            );
        }
    }

    public function test_el_registro_devuelve_mensajes_en_castellano(): void
    {
        $respuesta = $this->postJson('/api/register', [])->assertStatus(422);

        $this->assertTraducidos($respuesta->json());
        $this->assertStringContainsString('obligatorio', mb_strtolower($respuesta->json('errors.nombre.0')));
    }

    public function test_el_formulario_de_contacto_tambien(): void
    {
        $this->assertTraducidos(
            $this->postJson('/api/contacto', [])->assertStatus(422)->json(),
        );
    }

    public function test_el_restablecimiento_de_contrasena_tambien(): void
    {
        $this->assertTraducidos(
            $this->postJson('/api/password/restablecer', [])->assertStatus(422)->json(),
        );
    }

    public function test_el_nombre_del_campo_no_sale_con_guion_bajo(): void
    {
        $respuesta = $this->postJson('/api/register', [])->assertStatus(422);

        /* «acepta_terminos es obligatorio» es el nombre de la columna, no el
           de la cosa. Para eso está la sección `attributes`. */
        $texto = (string) $respuesta->json('errors.acepta_terminos.0');

        $this->assertStringNotContainsString('acepta_terminos', $texto);
    }
}
