<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Fuera de local, ninguna URL publicada sale en texto plano.
 * ---------------------------------------------------------------------------
 * EL DEFECTO QUE ESTO IMPIDE
 *
 * La documentación en producción anunciaba el servidor como
 * `http://botica-san-juan-api.onrender.com/api`. Quien pulsa «Try it» en
 * Scalar, o copia el fragmento de código, se lleva una URL con `http`.
 * Funciona de milagro —Render redirige—, pero un cliente que no siga
 * redirecciones falla, y es el ejemplo que van a copiar otros proyectos.
 *
 * La causa es doble: `APP_URL` está declarada con `http://` en el panel del
 * proveedor, y el proxy de Render termina el TLS y habla con el contenedor
 * por HTTP plano, así que el propio Laravel cree que la petición es
 * insegura.
 *
 * SOBRE EL ENTORNO DE ESTAS PRUEBAS
 *
 * La suite corre en `testing`, que está excluido del forzado igual que
 * `local`: forzar https aquí rompería las peticiones de prueba. Así que no
 * se puede comprobar el efecto llamando al método; lo que se comprueba es la
 * REGLA —qué entornos quedan excluidos y cuáles no—, que es lo que de verdad
 * se puede equivocar al tocar esto.
 */
class EsquemaHttpsTest extends TestCase
{
    private function forzar(string $entorno): \Closure
    {
        $proveedor = new \App\Providers\AppServiceProvider($this->app);

        $metodo = new \ReflectionMethod($proveedor, 'forzarHttpsFueraDeLocal');
        $metodo->setAccessible(true);

        return function () use ($metodo, $proveedor, $entorno) {
            $anterior = $this->app['env'];
            $this->app['env'] = $entorno;

            try {
                $metodo->invoke($proveedor);
            } finally {
                $this->app['env'] = $anterior;
            }
        };
    }

    public function test_en_produccion_la_url_de_la_aplicacion_pasa_a_https(): void
    {
        config(['app.url' => 'http://botica-san-juan-api.onrender.com']);

        ($this->forzar('production'))();

        $this->assertSame('https://botica-san-juan-api.onrender.com', config('app.url'));
    }

    public function test_una_url_que_ya_era_https_no_se_toca(): void
    {
        config(['app.url' => 'https://botica-san-juan-api.onrender.com']);

        ($this->forzar('production'))();

        $this->assertSame('https://botica-san-juan-api.onrender.com', config('app.url'));
    }

    public function test_en_local_no_se_fuerza_nada(): void
    {
        /* En desarrollo se sirve por http. Forzar https rompería todos los
           enlaces y el servidor de Vite no tiene certificado. */
        config(['app.url' => 'http://127.0.0.1:8083']);

        ($this->forzar('local'))();

        $this->assertSame('http://127.0.0.1:8083', config('app.url'));
    }

    public function test_en_las_pruebas_tampoco(): void
    {
        config(['app.url' => 'http://localhost']);

        ($this->forzar('testing'))();

        $this->assertSame('http://localhost', config('app.url'));
    }
}
