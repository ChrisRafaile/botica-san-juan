<?php

namespace Tests\Feature;

use App\Support\Observabilidad\Depurador;
use Tests\TestCase;

/**
 * Lo que un reporte de error NO puede llevarse del servidor.
 *
 * Estas pruebas son el contrato de privacidad del monitoreo. No comprueban
 * que Sentry funcione —eso lo hace Sentry— sino que, pase lo que pase antes,
 * lo que sale va depurado.
 *
 * Importa que estén escritas sobre casos reales de este sistema: un DNI
 * dentro del mensaje de una excepción, un token de Sanctum en una cabecera,
 * el nombre de un medicamento en una búsqueda. Una prueba con «foo» y «bar»
 * no habría encontrado ninguno de los tres.
 */
class DepuracionSentryTest extends TestCase
{
    // ------------------------------------------------- texto libre

    public function test_el_dni_no_sobrevive_en_un_mensaje(): void
    {
        $limpio = Depurador::texto('No se encontró el usuario 47382910 al emitir la boleta');

        $this->assertStringNotContainsString('47382910', (string) $limpio);
        $this->assertStringContainsString('[dni]', (string) $limpio);
    }

    public function test_el_correo_tampoco(): void
    {
        $limpio = Depurador::texto('Fallo al avisar a rosa.quispe@gmail.com');

        $this->assertStringNotContainsString('rosa.quispe@gmail.com', (string) $limpio);
        $this->assertStringContainsString('[correo]', (string) $limpio);
    }

    public function test_un_token_de_sanctum_no_viaja(): void
    {
        $limpio = Depurador::texto('Authorization: Bearer 43|BFHLaamKd9ET1MQHAGrfHmrIMZmHHIQqAEmR25Tk');

        $this->assertStringNotContainsString('BFHLaamKd9ET1MQHAGrfHmrIMZmHHIQqAEmR25Tk', (string) $limpio);
    }

    public function test_una_clave_de_api_no_viaja(): void
    {
        /* Las claves de Resend empiezan por `re_`; las de otros proveedores,
           por `sk_` o `pk_`. Una excepción de integración las arrastra con
           facilidad al mensaje. */
        /* La clave es FICTICIA y se nota a simple vista. Una cadena con pinta
           de clave real en un repositorio publico la marcan los escaneres de
           secretos, y quien la lea dentro de un ano no podra saber si lo era. */
        $limpio = Depurador::texto('Rechazado por el proveedor con la clave re_test_mock_secret_key');

        $this->assertStringNotContainsString('re_test_mock_secret_key', (string) $limpio);
        $this->assertStringContainsString('[clave]', (string) $limpio);
    }

    public function test_el_ruc_y_el_telefono_se_enmascaran(): void
    {
        /* RUC FICTICIO. El del emisor real no se escribe en ningun archivo
           versionado, ni siquiera como dato de prueba: este archivo acaba en
           GitHub igual que el codigo. Lo que la prueba comprueba es el
           PATRON —11 digitos con prefijo 10 o 20—, y para eso un numero
           inventado sirve exactamente igual. */
        $limpio = (string) Depurador::texto('Emisor 20000000001, contacto 987654321');

        $this->assertStringNotContainsString('20000000001', $limpio);
        $this->assertStringNotContainsString('987654321', $limpio);
    }

    public function test_no_destroza_un_mensaje_sin_datos_personales(): void
    {
        /* Si el depurador fuera demasiado agresivo, los errores llegarían
           ilegibles y nadie los miraría. Un identificador de pedido de cinco
           cifras o un importe no son datos personales. */
        $mensaje = 'Stock insuficiente en el pedido 217: se pidieron 10 y hay 3';

        $this->assertSame($mensaje, Depurador::texto($mensaje));
    }

    // ------------------------------------------------------- petición

    public function test_el_cuerpo_de_la_peticion_se_descarta_entero(): void
    {
        $limpia = Depurador::peticion([
            'url' => 'http://api/pedidos/confirmar',
            'data' => ['password' => 'secreta', 'cliente_documento' => '47382910'],
        ]);

        /* El cuerpo de esta API lleva contraseñas, documentos y recetas.
           Filtrarlo campo a campo es apostar a que nadie añadirá uno nuevo. */
        $this->assertArrayNotHasKey('data', $limpia);
    }

    public function test_la_cabecera_de_autorizacion_desaparece(): void
    {
        $limpia = Depurador::peticion([
            'headers' => [
                'Authorization' => 'Bearer 43|abcdefghijklmnopqrstuvwxyz123456',
                'Cookie' => 'sesion=xyz',
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertArrayNotHasKey('authorization', $limpia['headers']);
        $this->assertArrayNotHasKey('cookie', $limpia['headers']);
        $this->assertSame('application/json', $limpia['headers']['accept']);
    }

    public function test_la_busqueda_de_medicamentos_no_viaja(): void
    {
        $limpia = Depurador::peticion([
            'query_string' => ['q' => 'amoxicilina', 'page' => '2'],
        ]);

        /* El término de búsqueda de un medicamento, atado a una sesión, dice
           qué está buscando una persona. La página no dice nada. */
        $this->assertArrayNotHasKey('q', $limpia['query_string']);
        $this->assertSame('2', $limpia['query_string']['page']);
    }

    public function test_la_url_pierde_su_cadena_de_consulta(): void
    {
        $limpia = Depurador::peticion([
            'url' => 'https://api/productos?q=paracetamol&dni=47382910',
        ]);

        $this->assertSame('https://api/productos', $limpia['url']);
    }

    public function test_las_cookies_y_el_entorno_se_quitan(): void
    {
        $limpia = Depurador::peticion([
            'cookies' => ['sesion' => 'xyz'],
            'env' => ['DB_PASSWORD' => 'secreta'],
            'url' => 'https://api/salud',
        ]);

        $this->assertArrayNotHasKey('cookies', $limpia);
        $this->assertArrayNotHasKey('env', $limpia);
    }

    public function test_una_consulta_sin_analizar_se_descarta(): void
    {
        /* Llega como cadena cuando el SDK no la separó. Conservarla sería
           dejar pasar cualquier parámetro sin mirar. */
        $limpia = Depurador::peticion(['query_string' => 'q=amoxicilina&dni=47382910']);

        $this->assertArrayNotHasKey('query_string', $limpia);
    }

    // ------------------------------------------------------ configuración

    public function test_sentry_no_envia_datos_personales_por_omision(): void
    {
        $this->assertFalse(config('sentry.send_default_pii'));
        $this->assertFalse(config('sentry.breadcrumbs.sql_bindings'));
    }

    public function test_sin_dsn_el_monitoreo_queda_inactivo(): void
    {
        /* El estado por omisión y el de desarrollo. Activarlo es un acto
           deliberado por entorno, no algo que ocurre por instalar.

           Se comprueba que esté VACÍO, no que sea exactamente `null`. Las dos
           cosas significan lo mismo para el SDK, pero no llegan igual: sin la
           variable, `env()` devuelve null; con la variable declarada y sin
           valor —que es como la deja `.env.example`, y por tanto como queda
           en integración continua— devuelve la cadena vacía.

           La primera versión de esta prueba afirmaba `assertNull` y pasaba en
           local y fallaba en CI por esa diferencia. El fallo estaba en la
           prueba: afirmaba un detalle de cómo llega el dato en lugar del
           contrato, que es «no hay DSN, así que no se envía nada». */
        $this->assertTrue(
            blank(config('sentry.dsn')),
            'El DSN de Sentry deberia estar vacio por omision.',
        );
    }
}
