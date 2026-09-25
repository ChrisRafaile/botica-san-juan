<?php

use App\Http\Controllers\CarritoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\ComisionController;
use App\Http\Controllers\FacturacionController;
use App\Http\Controllers\DigemidCatalogoController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\ReporteContableController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ConteoController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PedidoDetalleController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\SubcategoriaController;
use App\Http\Controllers\TableroController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Test route
Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'botica-san-juan-backend',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/health/db', function () {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'status' => 'ok',
            'database' => 'connected',
            'timestamp' => now()->toIso8601String(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'database' => 'disconnected',
            'message' => 'No se pudo conectar a la base de datos.',
            'timestamp' => now()->toIso8601String(),
        ], 503);
    }
});

// Public routes (no authentication required)

/*
| Latido del servicio.
|
| Existe para que el panel sepa si el sistema responde, no para adornar. En un
| mostrador importa de verdad: si la API se cae a media mañana, el vendedor
| tiene que enterarse en ese momento y no cuando intente cobrar y se le quede
| la venta a medias.
|
| Va sin autenticación a propósito —un chequeo de vida que exige sesión no
| sirve cuando lo que falla es la sesión— y no expone nada: sólo confirma que
| el proceso está en pie y que la base responde.
*/
Route::get('/salud', function () {
    $baseViva = true;

    try {
        DB::connection()->getPdo();
    } catch (\Throwable) {
        $baseViva = false;
    }

    return response()->json([
        'ok'   => $baseViva,
        'base' => $baseViva,
        'hora' => now()->toIso8601String(),
    ], $baseViva ? 200 : 503);
})->middleware('throttle:api');

// Authentication routes
Route::post('/login', [UsuarioController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [UsuarioController::class, 'register'])->middleware('throttle:register');
Route::post('/logout', [UsuarioController::class, 'logout'])->middleware(['auth:sanctum', 'throttle:api']);

// API Routes for resources (protected)
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::apiResource('carrito', CarritoController::class);

    // Mismo motivo que en productos/resumen: si fuera despues del apiResource,
    // la ruta pedidos/{pedido} capturaria "resumen" como si fuera un id.
    Route::get('pedidos/resumen', [PedidoController::class, 'resumen']);
    Route::apiResource('pedidos', PedidoController::class);
    Route::apiResource('pedido-detalles', PedidoDetalleController::class);
    Route::apiResource('contacto', ContactoController::class);
    // Tablero: todas las cifras se calculan en la base, no en el navegador.
    Route::get('tablero', [TableroController::class, 'index']);

    Route::get('reportes/ventas', [ReporteController::class, 'ventas']);

    // Registro de ventas para el contador. Reemplaza el Excel que hoy se llena
    // a mano cada noche y se envia por correo.
    Route::get('reportes/registro-ventas', [ReporteContableController::class, 'index']);
    Route::get('reportes/registro-ventas/csv', [ReporteContableController::class, 'csv']);
    Route::get('reportes/gerencial', [ReporteController::class, 'gerencial']);

    // Confirmacion de venta: crea el pedido, su detalle y descuenta el stock
    // dentro de una unica transaccion (RF-07 / RNF-05).
    Route::post('pedidos/confirmar', [PedidoController::class, 'confirmar']);

    // Cobro. El importe lo toma del pedido, nunca de la peticion.
    Route::post('pedidos/{pedido}/pago', [PagoController::class, 'iniciar']);
    Route::get('pagos/{referencia}/estado', [PagoController::class, 'estado']);
    Route::post('pagos/validar-retorno', [PagoController::class, 'validarRetorno']);

    // Additional routes for cart by user
    Route::get('carrito/usuario/{usuarioId}', [CarritoController::class, 'getByUser']);

    // Additional routes for orders by user
    Route::get('pedidos/usuario/{usuarioId}', [PedidoController::class, 'getByUser']);
});

// Notificacion servidor a servidor de la pasarela de pagos.
//
// No puede exigir token porque quien la invoca es el proveedor, no un usuario.
// Su proteccion son el segmento secreto de la ruta, la firma HMAC-SHA-256 del
// mensaje y, en modo real, la reconsulta del estado contra la API.
Route::post(
    'pagos/notificacion/{secreto}',
    [PagoController::class, 'notificacion']
)->where('secreto', '[A-Za-z0-9\-_]{8,120}')
 ->middleware('throttle:api');

// Documentos tributarios: exponen nombre y documento de identidad del cliente,
// de modo que su lectura exige identidad. Antes eran de acceso anonimo.
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('facturacion/documentos', [FacturacionController::class, 'index']);
    Route::get('facturacion/documentos/{id}/xml', [FacturacionController::class, 'descargarXml']);
    Route::get('facturacion/documentos/{id}/pdf', [FacturacionController::class, 'descargarPdf']);
    Route::get('facturacion/comisiones', [ComisionController::class, 'index']);
});

Route::middleware('throttle:api')->group(function () {
    // Read-only catalog and reporting endpoints
    //
    // El resumen va ANTES del apiResource: si fuera despues, la ruta
    // productos/{producto} capturaria "resumen" como si fuera un id.
    Route::get('productos/resumen', [ProductoController::class, 'resumen']);
    Route::apiResource('productos', ProductoController::class)->only(['index', 'show']);
    Route::apiResource('categorias', CategoriaController::class)->only(['index', 'show']);
    Route::apiResource('subcategorias', SubcategoriaController::class)->only(['index', 'show']);
    Route::apiResource('proveedores', ProveedorController::class)->only(['index', 'show']);
    Route::apiResource('compras', CompraController::class)->only(['index', 'show']);
    Route::get('digemid-catalogo/alertas-cumplimiento', [DigemidCatalogoController::class, 'alertasCumplimiento']);
    Route::apiResource('digemid-catalogo', DigemidCatalogoController::class)->only(['index', 'show']);

});

Route::middleware(['auth:sanctum', 'admin', 'throttle:api', 'audit.critical'])->group(function () {
    Route::post('mfa/setup', [UsuarioController::class, 'mfaSetup']);
    Route::post('mfa/enable', [UsuarioController::class, 'mfaEnable']);
    Route::post('mfa/disable', [UsuarioController::class, 'mfaDisable']);

    Route::apiResource('usuarios', UsuarioController::class);

    // Admin mutation routes
    Route::post('productos', [ProductoController::class, 'store']);
    Route::put('productos/{producto}', [ProductoController::class, 'update']);
    Route::patch('productos/{producto}', [ProductoController::class, 'update']);
    Route::delete('productos/{producto}', [ProductoController::class, 'destroy']);
    Route::post('productos/bulk', [ProductoController::class, 'bulkStore']);

    Route::post('categorias', [CategoriaController::class, 'store']);
    Route::put('categorias/{categoria}', [CategoriaController::class, 'update']);
    Route::patch('categorias/{categoria}', [CategoriaController::class, 'update']);
    Route::delete('categorias/{categoria}', [CategoriaController::class, 'destroy']);

    Route::post('subcategorias', [SubcategoriaController::class, 'store']);
    Route::put('subcategorias/{subcategoria}', [SubcategoriaController::class, 'update']);
    Route::patch('subcategorias/{subcategoria}', [SubcategoriaController::class, 'update']);
    Route::delete('subcategorias/{subcategoria}', [SubcategoriaController::class, 'destroy']);

    Route::post('proveedores', [ProveedorController::class, 'store']);
    Route::put('proveedores/{proveedor}', [ProveedorController::class, 'update']);
    Route::patch('proveedores/{proveedor}', [ProveedorController::class, 'update']);
    Route::delete('proveedores/{proveedor}', [ProveedorController::class, 'destroy']);

    Route::post('compras', [CompraController::class, 'store']);
    Route::put('compras/{compra}', [CompraController::class, 'update']);
    Route::patch('compras/{compra}', [CompraController::class, 'update']);
    Route::delete('compras/{compra}', [CompraController::class, 'destroy']);

    Route::post('digemid-catalogo/import', [DigemidCatalogoController::class, 'import']);
    Route::post('digemid-catalogo', [DigemidCatalogoController::class, 'store']);
    Route::put('digemid-catalogo/{digemid_catalogo}', [DigemidCatalogoController::class, 'update']);
    Route::patch('digemid-catalogo/{digemid_catalogo}', [DigemidCatalogoController::class, 'update']);
    Route::delete('digemid-catalogo/{digemid_catalogo}', [DigemidCatalogoController::class, 'destroy']);

    // La exportacion consolida informacion comercial y financiera de toda la
    // botica: se restringe al rol administrador, no solo a estar autenticado.
    Route::get('reportes/gerencial/export/csv', [ReporteController::class, 'exportGerencialCsv']);

    Route::post('facturacion/generar-desde-pedidos', [FacturacionController::class, 'generarDesdePedidos']);
    Route::post('facturacion/documentos/{id}/enviar-sunat', [FacturacionController::class, 'enviarSunat']);
    Route::post('facturacion/documentos/{id}/enviar-sunat-async', [FacturacionController::class, 'encolarEnvioSunat']);
    Route::post('facturacion/documentos/{id}/comision', [ComisionController::class, 'registrar']);
    Route::post('facturacion/comisiones/{id}/liquidar', [ComisionController::class, 'liquidar']);
});


/*
|--------------------------------------------------------------------------
| Punto de venta (mostrador)
|--------------------------------------------------------------------------
|
| Requieren sesion: toda venta queda atribuida a quien la registro, y todo
| ajuste de stock a quien lo hizo. Sin usuario identificado no hay
| responsabilidad posible sobre el inventario.
|
| El ajuste de lote pasa ademas por 'audit.critical' porque modifica
| inventario fuera del flujo normal de venta o compra.
*/
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('pos')->group(function () {
    Route::get('/productos', [PosController::class, 'buscarProductos']);
    Route::get('/productos/{producto}/lotes', [PosController::class, 'lotesDeProducto']);
    Route::post('/verificar', [PosController::class, 'verificar']);
    Route::post('/ventas', [PosController::class, 'registrarVenta']);
    Route::get('/ventas/{pedido}', [PosController::class, 'verVenta']);
});

Route::middleware(['auth:sanctum', 'throttle:api', 'audit.critical'])->prefix('pos')->group(function () {
    Route::post('/lotes/{lote}/ajustar', [PosController::class, 'ajustarLote']);
});


/*
|--------------------------------------------------------------------------
| Conteo fisico por ciclos
|--------------------------------------------------------------------------
|
| Contar no altera nada: se anota lo que hay en el anaquel y punto. Por eso
| abrir una sesion y registrar lineas van con el resto de rutas de sesion.
|
| Cerrar es otra cosa: aplica ajustes de stock sobre el inventario real, igual
| que el ajuste manual de lote, asi que pasa por 'audit.critical'. Anular
| tambien, porque descarta trabajo ya hecho y conviene saber quien lo descarto.
*/
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('conteos')->group(function () {
    Route::get('/', [ConteoController::class, 'index']);
    Route::get('/abierto', [ConteoController::class, 'abierto']);
    Route::post('/', [ConteoController::class, 'store']);
    Route::get('/{conteo}', [ConteoController::class, 'show']);
    Route::put('/{conteo}/detalles/{detalle}', [ConteoController::class, 'registrar']);
});

Route::middleware(['auth:sanctum', 'throttle:api', 'audit.critical'])->prefix('conteos')->group(function () {
    Route::post('/{conteo}/cerrar', [ConteoController::class, 'cerrar']);
    Route::post('/{conteo}/anular', [ConteoController::class, 'anular']);
});

