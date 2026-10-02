<?php

namespace App\Console\Commands;

use App\Models\Lote;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\VentaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Ejecuta el paquete de casos de prueba de validacion del servicio.
 *
 *   php artisan servicio:validar
 *
 * POR QUE ESTE COMANDO Y NO SOLO LA SUITE DE PHPUNIT
 *
 * PHPUnit responde "89 pruebas en verde", que es cierto pero no se puede
 * leer. Un caso de prueba tiene que decir que se pidio, que se esperaba, que
 * ocurrio y COMO SE COMPRUEBA en la base. Este comando imprime esas cuatro
 * cosas por caso, incluida la consulta que lo demuestra, de forma que la
 * salida sirva de evidencia por si misma.
 *
 * Todo ocurre dentro de una transaccion que se revierte: ejecutarlo no deja
 * ventas de prueba en el historial del negocio.
 */
class ValidarServicio extends Command
{
    protected $signature = 'servicio:validar {--caso= : Ejecuta un solo caso, por ejemplo CP-F-02}';
    protected $description = 'Ejecuta los casos de prueba funcionales y no funcionales con su evidencia';

    private array $resultados = [];

    public function handle(VentaService $ventas): int
    {
        $this->newLine();
        $this->line('=================================================================');
        $this->line(' VALIDACION DEL SERVICIO · Sistema Web Botica San Juan');
        $this->line(' ' . now()->format('d/m/Y H:i'));
        $this->line('=================================================================');

        DB::beginTransaction();

        try {
            $filtro = $this->option('caso');

            $casos = [
                'CP-F-01' => fn () => $this->cpF01(),
                'CP-F-02' => fn () => $this->cpF02($ventas),
                'CP-F-03' => fn () => $this->cpF03($ventas),
                'CP-F-04' => fn () => $this->cpF04($ventas),
                'CP-F-05' => fn () => $this->cpF05($ventas),
                'CP-NF-01' => fn () => $this->cpNf01(),
                'CP-NF-02' => fn () => $this->cpNf02(),
            ];

            foreach ($casos as $id => $caso) {
                if ($filtro && $filtro !== $id) {
                    continue;
                }
                $caso();
            }

            $this->resumen();

            DB::rollBack();
            $this->newLine();
            $this->comment('Transaccion revertida: no queda ninguna venta de prueba en la base.');

            return collect($this->resultados)->contains('estado', 'FALLIDO')
                ? self::FAILURE
                : self::SUCCESS;
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('Error inesperado: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /* ===================================================================== */
    /* Casos funcionales                                                      */
    /* ===================================================================== */

    /**
     * RF01 · Autenticacion con credenciales validas.
     *
     * Este caso ejercita el ENDPOINT REAL, no el hash. Una version anterior
     * solo comprobaba que la contrasena estuviera cifrada con bcrypt, cosa que
     * habria aprobado aunque el inicio de sesion estuviera completamente roto.
     * Ahora la peticion atraviesa el enrutador, la validacion, el limitador de
     * intentos y el controlador, igual que la del navegador.
     *
     * El usuario se crea aqui con una contrasena conocida porque la base no
     * guarda contrasenas en claro: no hay forma de autenticar a una cuenta
     * existente sin conocer su clave. Se crea dentro de la transaccion que el
     * comando revierte al terminar.
     */
    private function cpF01(): void
    {
        $this->cabecera('CP-F-01', 'RF01', 'Autenticar contra el endpoint de inicio de sesion');

        $dni = '19000001';
        $clave = 'Clave#Prueba2026';

        $usuario = Usuario::create([
            'nombre' => 'Usuario de validacion',
            'email' => 'validacion.apf2@boticasanjuan.pe',
            'password' => $clave,
            'dni' => $dni,
            'telefono' => '987000099',
            'rol' => 'administrador',
            'mfa_enabled' => false,
        ]);

        $this->dato('Datos de prueba', "DNI {$dni} con contrasena conocida (cuenta creada para la prueba y revertida al terminar)");
        $this->dato('Pasos', 'POST /api/login con la clave correcta y despues con una incorrecta');
        $this->dato('Resultado esperado', 'La correcta devuelve 200 con token; la incorrecta devuelve 401 sin revelar cual dato fallo');

        $correcta = $this->peticion('POST', '/api/login', ['dni' => $dni, 'password' => $clave]);
        $incorrecta = $this->peticion('POST', '/api/login', ['dni' => $dni, 'password' => 'ClaveEquivocada999']);

        $cuerpoOk = json_decode($correcta->getContent(), true) ?: [];
        $cuerpoMal = json_decode($incorrecta->getContent(), true) ?: [];

        $token = $cuerpoOk['token'] ?? $cuerpoOk['access_token'] ?? null;
        $sinPassword = !str_contains(strtolower($correcta->getContent()), '"password"');
        $mensajeGenerico = !str_contains(strtolower($cuerpoMal['message'] ?? ''), 'dni no existe');

        $this->evidencia(
            "SELECT dni, rol, LEFT(password, 4) AS algoritmo FROM usuarios WHERE dni = '{$dni}'",
            sprintf('dni=%s  rol=%s  algoritmo=%s (bcrypt)', $usuario->dni, $usuario->rol, substr((string) $usuario->password, 0, 4))
        );
        $this->evidencia(
            'SELECT COUNT(*) FROM personal_access_tokens WHERE tokenable_id = ' . $usuario->id,
            sprintf('tokens emitidos tras el inicio de sesion correcto: %d',
                DB::table('personal_access_tokens')->where('tokenable_id', $usuario->id)->count())
        );

        $ok = $correcta->getStatusCode() === 200
            && is_string($token) && $token !== ''
            && $sinPassword
            && $incorrecta->getStatusCode() === 401
            && $mensajeGenerico;

        $this->dato('Resultado obtenido', sprintf(
            'Clave correcta: HTTP %d con token. Clave incorrecta: HTTP %d, mensaje "%s". La contrasena no aparece en la respuesta.',
            $correcta->getStatusCode(), $incorrecta->getStatusCode(), $cuerpoMal['message'] ?? ''
        ));

        $this->registrar('CP-F-01', $ok ? 'APROBADO' : 'FALLIDO',
            'El mensaje de error es generico a proposito: decir "ese DNI no existe" permitiria averiguar quien esta registrado.');
    }

    /**
     * Lanza una peticion real por el nucleo HTTP, para que el caso atraviese
     * enrutador, validacion y middleware en vez de llamar al controlador a mano.
     */
    private function peticion(string $metodo, string $ruta, array $datos): \Symfony\Component\HttpFoundation\Response
    {
        $peticion = \Illuminate\Http\Request::create(
            $ruta, $metodo, [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            json_encode($datos)
        );

        return app(\Illuminate\Contracts\Http\Kernel::class)->handle($peticion);
    }

    /** RF06 y RF07 · Venta de mostrador que descuenta stock. */
    private function cpF02(VentaService $ventas): void
    {
        $this->cabecera('CP-F-02', 'RF06, RF07', 'Registrar una venta y descontar el inventario');

        $producto = $this->productoConStock(3);

        if (!$producto) {
            $this->registrar('CP-F-02', 'PENDIENTE', 'No hay producto con stock suficiente.');

            return;
        }

        $antes = (int) Lote::where('producto_id', $producto->id)->sum('cantidad_actual');

        $this->dato('Datos de prueba', "Producto #{$producto->id} · {$producto->nombre} · 2 unidades");
        $this->dato('Pasos', 'Registrar la venta por el servicio de ventas y volver a consultar los lotes');
        $this->dato('Resultado esperado', 'Se crea el pedido, se genera una linea y el stock baja exactamente 2');

        $pedido = $ventas->registrar(
            items: [['producto_id' => $producto->id, 'cantidad' => 2, 'unidad_venta' => 'unidad']],
            datosCliente: ['cliente_nombre' => 'Cliente de prueba CP-F-02'],
            origen: Pedido::ORIGEN_POS,
        );

        $despues = (int) Lote::where('producto_id', $producto->id)->sum('cantidad_actual');
        $lineas = $pedido->detalles()->count();

        $this->evidencia(
            "SELECT COALESCE(SUM(cantidad_actual),0) FROM lotes WHERE producto_id = {$producto->id}",
            sprintf('antes = %d   despues = %d   diferencia = %d', $antes, $despues, $antes - $despues)
        );
        $this->evidencia(
            "SELECT id, estado, estado_pago, total FROM pedidos WHERE id = {$pedido->id}",
            sprintf('id=%d  estado=%s  estado_pago=%s  total=%s  lineas=%d',
                $pedido->id, $pedido->estado, $pedido->estado_pago, $pedido->total, $lineas)
        );

        $ok = ($antes - $despues) === 2 && $lineas === 1 && $pedido->estado === Pedido::ESTADO_COMPLETADO;

        $this->dato('Resultado obtenido', $ok
            ? "Pedido #{$pedido->id} registrado, 1 linea y el stock bajo 2 unidades"
            : 'El descuento de stock o el estado del pedido no coinciden con lo esperado');

        $this->registrar('CP-F-02', $ok ? 'APROBADO' : 'FALLIDO',
            'La venta de mostrador nace completada y pagada.');
    }

    /** RF08 · Venta por presentacion con precio propio. */
    private function cpF03(VentaService $ventas): void
    {
        $this->cabecera('CP-F-03', 'RF08', 'Vender por blister aplicando su precio y su equivalencia');

        $producto = $this->productoConStock(12);

        if (!$producto) {
            $this->registrar('CP-F-03', 'PENDIENTE', 'No hay producto con stock suficiente.');

            return;
        }

        /* El catalogo real todavia no trae presentaciones cargadas, asi que el
           caso las define para poder ejercitar la regla. Lo que se prueba es
           la conversion, no el dato del catalogo. */
        $producto->forceFill([
            'unidades_por_blister' => 10,
            'precio_blister' => round((float) $producto->precio * 9, 2),
            'venta_fraccionada' => true,
        ])->save();

        $antes = (int) Lote::where('producto_id', $producto->id)->sum('cantidad_actual');

        $this->dato('Datos de prueba', "Producto #{$producto->id} · 1 blister de 10 unidades · precio de blister S/ {$producto->precio_blister}");
        $this->dato('ORIGEN DEL DATO', 'La equivalencia y el precio de blister los DEFINE ESTE CASO: el catalogo'
            . ' real todavia no tiene presentaciones cargadas (0 de 3361 productos). Lo que se prueba es la regla'
            . ' de conversion y de precio, no el dato del catalogo.');
        $this->dato('Pasos', 'Vender 1 blister y comprobar unidades descontadas e importe cobrado');
        $this->dato('Resultado esperado', 'Descuenta 10 unidades y cobra el precio del blister, no 10 veces el unitario');

        $pedido = $ventas->registrar(
            items: [['producto_id' => $producto->id, 'cantidad' => 1, 'unidad_venta' => 'blister']],
            datosCliente: ['cliente_nombre' => 'Cliente de prueba CP-F-03'],
            origen: Pedido::ORIGEN_POS,
        );

        $despues = (int) Lote::where('producto_id', $producto->id)->sum('cantidad_actual');
        $unitarioPorDiez = round((float) $producto->precio * 10, 2);

        $this->evidencia(
            "SELECT COALESCE(SUM(cantidad_actual),0) FROM lotes WHERE producto_id = {$producto->id}",
            sprintf('antes = %d   despues = %d   diferencia = %d', $antes, $despues, $antes - $despues)
        );
        $this->evidencia(
            "SELECT unidad_venta, cantidad, subtotal FROM pedido_detalles WHERE pedido_id = {$pedido->id}",
            sprintf('total cobrado = S/ %s   ·   10 x precio unitario habria sido S/ %s',
                $pedido->total, number_format($unitarioPorDiez, 2))
        );

        $ok = ($antes - $despues) === 10 && abs((float) $pedido->total - (float) $producto->precio_blister) < 0.02;

        $this->dato('Resultado obtenido', $ok
            ? 'Descuenta 10 unidades y aplica el precio propio del blister'
            : 'La equivalencia o el precio del blister no se aplicaron');

        $this->registrar('CP-F-03', $ok ? 'APROBADO' : 'FALLIDO',
            'El blister no es el precio unitario multiplicado.');
    }

    /** RF07 · El stock nunca queda negativo. */
    private function cpF04(VentaService $ventas): void
    {
        $this->cabecera('CP-F-04', 'RF07', 'Rechazar un encargo del portal cuando no alcanza el stock');

        $producto = $this->productoConStock(1);

        if (!$producto) {
            $this->registrar('CP-F-04', 'PENDIENTE', 'No hay producto con stock.');

            return;
        }

        $disponible = (int) Lote::where('producto_id', $producto->id)->sum('cantidad_actual');
        $pedir = $disponible + 50;
        $pedidosAntes = Pedido::count();

        $this->dato('Datos de prueba', "Producto #{$producto->id} con {$disponible} unidades; se piden {$pedir}");
        $this->dato('Pasos', 'Registrar un encargo del portal por mas unidades de las disponibles');
        $this->dato('Resultado esperado', 'Se rechaza la operacion completa y no se crea ningun pedido');

        $rechazado = false;
        $mensaje = '';

        try {
            $ventas->registrar(
                items: [['producto_id' => $producto->id, 'cantidad' => $pedir]],
                origen: Pedido::ORIGEN_WEB,
            );
        } catch (Throwable $e) {
            $rechazado = true;
            $mensaje = $e->getMessage();
        }

        $pedidosDespues = Pedido::count();
        $stockDespues = (int) Lote::where('producto_id', $producto->id)->sum('cantidad_actual');

        $this->evidencia('SELECT COUNT(*) FROM pedidos', sprintf('antes = %d   despues = %d', $pedidosAntes, $pedidosDespues));
        $this->evidencia(
            "SELECT COALESCE(SUM(cantidad_actual),0) FROM lotes WHERE producto_id = {$producto->id}",
            sprintf('stock intacto: %d unidades', $stockDespues)
        );

        $ok = $rechazado && $pedidosDespues === $pedidosAntes && $stockDespues === $disponible;

        $this->dato('Resultado obtenido', $ok
            ? 'Rechazado: ' . $mensaje
            : 'El sistema no rechazo la operacion o dejo rastro en la base');

        $this->registrar('CP-F-04', $ok ? 'APROBADO' : 'FALLIDO',
            'Por la web se rechaza entero; en mostrador si se admite entrega parcial acordada con el cliente.');
    }

    /** RF06 · Encargo del portal, que nace pendiente y sin cobrar. */
    private function cpF05(VentaService $ventas): void
    {
        $this->cabecera('CP-F-05', 'RF06', 'Registrar un encargo del portal web');

        $producto = $this->productoConStock(2);

        if (!$producto) {
            $this->registrar('CP-F-05', 'PENDIENTE', 'No hay producto con stock.');

            return;
        }

        $this->dato('Datos de prueba', "Producto #{$producto->id} · 1 unidad · cliente del portal");
        $this->dato('Pasos', 'Registrar el encargo por el canal web y consultar su estado en la base');
        $this->dato('Resultado esperado', 'El pedido queda pendiente y sin cobrar, no completado');

        $pedido = $ventas->registrar(
            items: [['producto_id' => $producto->id, 'cantidad' => 1]],
            origen: Pedido::ORIGEN_WEB,
        );

        $fila = DB::table('pedidos')->where('id', $pedido->id)
            ->select('id', 'origen', 'estado', 'estado_pago', 'medio_pago', 'total')->first();

        $this->evidencia(
            "SELECT id, origen, estado, estado_pago, medio_pago FROM pedidos WHERE id = {$pedido->id}",
            sprintf('id=%d  origen=%s  estado=%s  estado_pago=%s  medio_pago=%s',
                $fila->id, $fila->origen, $fila->estado, $fila->estado_pago, $fila->medio_pago ?? 'NULL')
        );

        $ok = $fila->origen === Pedido::ORIGEN_WEB
            && $fila->estado === Pedido::ESTADO_PENDIENTE
            && $fila->estado_pago === 'pendiente'
            && $fila->medio_pago === null;

        $this->dato('Resultado obtenido', $ok
            ? 'El encargo queda pendiente, sin pago y sin medio de cobro declarado'
            : 'El encargo del portal no nacio con el estado correcto');

        $this->registrar('CP-F-05', $ok ? 'APROBADO' : 'FALLIDO',
            'Darlo por cobrado lo meteria en la caja del dia sin que hubiera entrado un sol.');
    }

    /* ===================================================================== */
    /* Casos no funcionales                                                   */
    /* ===================================================================== */

    /** RNF03 · Tiempo de respuesta de la busqueda del punto de venta. */
    private function cpNf01(): void
    {
        $this->cabecera('CP-NF-01', 'RNF03', 'La busqueda del punto de venta responde en menos de 2 segundos');

        $this->dato('Datos de prueba', 'Termino "PARACETAMOL" sobre el catalogo completo');
        $this->dato('Pasos', 'Ejecutar diez veces la consulta de busqueda y tomar el peor tiempo');
        $this->dato('Criterio', 'Menos de 2 000 ms en el peor caso');

        $tiempos = [];
        for ($i = 0; $i < 10; $i++) {
            $t0 = microtime(true);
            $n = Producto::where('nombre', 'ILIKE', '%PARACETAMOL%')->limit(30)->count();
            $tiempos[] = (microtime(true) - $t0) * 1000;
        }

        sort($tiempos);
        $peor = end($tiempos);
        $mediana = $tiempos[5];

        $this->evidencia(
            "SELECT COUNT(*) FROM productos WHERE nombre ILIKE '%PARACETAMOL%'",
            sprintf('%d coincidencias · mediana %.0f ms · peor caso %.0f ms · sobre %d productos',
                $n, $mediana, $peor, Producto::count())
        );

        $ok = $peor < 2000;
        $this->dato('Resultado obtenido', sprintf('Peor caso %.0f ms', $peor));
        $this->registrar('CP-NF-01', $ok ? 'APROBADO' : 'FALLIDO',
            'Medido sobre el catalogo real, no sobre datos de laboratorio.');
    }

    /** RNF06 · Atomicidad de la operacion que afecta al inventario. */
    private function cpNf02(): void
    {
        $this->cabecera('CP-NF-02', 'RNF06', 'Una venta interrumpida no deja el inventario a medias');

        $producto = $this->productoConStock(5);

        if (!$producto) {
            $this->registrar('CP-NF-02', 'PENDIENTE', 'No hay producto con stock.');

            return;
        }

        $stockAntes = (int) Lote::where('producto_id', $producto->id)->sum('cantidad_actual');
        $pedidosAntes = Pedido::count();
        $movimientosAntes = DB::table('movimientos_stock')->count();

        $this->dato('Datos de prueba', "Producto #{$producto->id} con {$stockAntes} unidades");
        $this->dato('Pasos', 'Forzar un fallo a mitad de la venta y comprobar que nada quedo escrito');
        $this->dato('Criterio', 'Stock, pedidos y movimientos vuelven exactamente a su valor previo');

        try {
            DB::transaction(function () use ($producto) {
                app(VentaService::class)->registrar(
                    items: [['producto_id' => $producto->id, 'cantidad' => 1, 'unidad_venta' => 'unidad']],
                    origen: Pedido::ORIGEN_POS,
                );

                throw new \RuntimeException('Interrupcion forzada a mitad de la operacion');
            });
        } catch (Throwable $e) {
            // Esperado.
        }

        $stockDespues = (int) Lote::where('producto_id', $producto->id)->sum('cantidad_actual');
        $pedidosDespues = Pedido::count();
        $movimientosDespues = DB::table('movimientos_stock')->count();

        $this->evidencia(
            'SELECT (SELECT COUNT(*) FROM pedidos), (SELECT COUNT(*) FROM movimientos_stock)',
            sprintf('pedidos %d -> %d   ·   movimientos %d -> %d   ·   stock %d -> %d',
                $pedidosAntes, $pedidosDespues, $movimientosAntes, $movimientosDespues, $stockAntes, $stockDespues)
        );

        $ok = $stockAntes === $stockDespues
            && $pedidosAntes === $pedidosDespues
            && $movimientosAntes === $movimientosDespues;

        $this->dato('Resultado obtenido', $ok
            ? 'Todo volvio a su estado previo: la transaccion revirtio completa'
            : 'Quedaron rastros de la operacion interrumpida');

        $this->registrar('CP-NF-02', $ok ? 'APROBADO' : 'FALLIDO',
            'Sin esto, un corte de red dejaria stock descontado sin venta registrada.');
    }

    /* ===================================================================== */
    /* Presentacion                                                           */
    /* ===================================================================== */

    private function productoConStock(int $minimo): ?Producto
    {
        $id = DB::table('lotes')
            ->select('producto_id')
            ->where('cantidad_actual', '>=', $minimo)
            ->where('estado', 'activo')
            ->orderBy('producto_id')
            ->value('producto_id');

        return $id ? Producto::find($id) : null;
    }

    private function cabecera(string $id, string $req, string $objetivo): void
    {
        $this->newLine();
        $this->line('-----------------------------------------------------------------');
        $this->line(" {$id}  ·  {$req}");
        $this->line(" Objetivo: {$objetivo}");
        $this->line('-----------------------------------------------------------------');
    }

    private function dato(string $etiqueta, string $valor): void
    {
        $this->line(sprintf('  %-20s %s', $etiqueta . ':', $valor));
    }

    private function evidencia(string $consulta, string $salida): void
    {
        $this->line('  Evidencia en BD:     ' . $consulta);
        $this->line('                       -> ' . $salida);
    }

    private function registrar(string $id, string $estado, string $nota): void
    {
        $this->resultados[] = ['id' => $id, 'estado' => $estado, 'nota' => $nota];
        $this->line(sprintf('  %-20s %s', 'Estado:', $estado));
        $this->line('  Nota:                ' . $nota);
    }

    private function resumen(): void
    {
        $this->newLine();
        $this->line('=================================================================');
        $this->line(' RESUMEN');
        $this->line('=================================================================');

        foreach ($this->resultados as $r) {
            $this->line(sprintf('  %-10s %s', $r['id'], $r['estado']));
        }

        $aprobados = collect($this->resultados)->where('estado', 'APROBADO')->count();
        $this->newLine();
        $this->line(sprintf('  %d de %d casos aprobados', $aprobados, count($this->resultados)));
    }
}
