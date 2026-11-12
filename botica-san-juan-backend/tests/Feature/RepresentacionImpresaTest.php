<?php

namespace Tests\Feature;

use App\Models\ComprobanteElectronico;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\ComprobanteService;
use App\Services\Sunat\RepresentacionImpresa;
use App\Services\VentaService;
use chillerlan\QRCode\QRCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Representación impresa de la boleta y de la factura electrónica.
 * ---------------------------------------------------------------------------
 * QUE SE FIJA AQUI
 *
 * Lo que un cliente —o un fiscalizador— puede contrastar con el papel en la
 * mano: que el recuadro diga la denominación oficial y la serie que toca, que
 * gravada + IGV devuelva el total al céntimo, que las letras digan lo mismo
 * que el número y que el QR lleve exactamente la cadena que lee la aplicación
 * de la SUNAT.
 *
 * Las ventas se registran con `VentaService`, no insertando pedidos a mano:
 * es la única forma de que los subtotales que se imprimen sean los que el
 * sistema calcula de verdad. Por eso cada producto lleva su lote: la venta
 * descuenta por FEFO sobre `lotes`, no sobre `productos.stock`.
 *
 * El RUC del emisor es ficticio y se fija en `setUp`: estas pruebas no pueden
 * depender del `.env` de la máquina, ni dejar su valor en una aserción.
 */
class RepresentacionImpresaTest extends TestCase
{
    use RefreshDatabase;

    private const RUC_EMISOR  = '20123456789';
    private const RUC_CLIENTE = '20600000001';
    private const DNI_CLIENTE = '45678912';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'company.ruc'              => self::RUC_EMISOR,
            'company.razon_social'     => 'BOTICA SAN JUAN S.A.C.',
            'company.nombre_comercial' => 'Botica San Juan',
            'company.direccion'        => 'Av. Los Próceres 123, San Juan de Lurigancho, Lima',
            'company.telefono'         => '(01) 555 0100',
            'company.email'            => 'ventas@boticasanjuan.example',
            'company.logo_path'        => '',
        ]);

        /* `storage/framework` está fuera del control de versiones: en un
           clon o un worktree recién creado no existe, y Blade no compila sin
           su carpeta. La prueba no debe depender de que alguien la creara.
           La configuración se reescribe porque `config/view.php` resuelve la
           ruta con `realpath`, que devuelve false si la carpeta aún no está. */
        File::ensureDirectoryExists(storage_path('framework/views'));
        config(['view.compiled' => storage_path('framework/views')]);

        /* La fecha entra en el QR: se congela para poder compararlo entero. */
        $this->travelTo('2026-11-11 10:30:00');
    }

    /** @var array<string, Producto> */
    private array $productos = [];

    private ?Usuario $vendedor = null;

    /**
     * Devuelve el mismo producto si se pide dos veces: el catálogo tiene un
     * índice único de identidad, y la prueba que emite boleta y factura
     * vende la misma amoxicilina en las dos.
     */
    private function producto(string $nombre, string $concentracion, float $precio, string $afectacion = Producto::GRAVADO): Producto
    {
        return $this->productos[$nombre.' '.$concentracion]
            ??= $this->crearProducto($nombre, $concentracion, $precio, $afectacion);
    }

    private function crearProducto(string $nombre, string $concentracion, float $precio, string $afectacion): Producto
    {
        $producto = Producto::create([
            'nombre'              => $nombre,
            'concentracion'       => $concentracion,
            'presentacion'        => 'Caja x 100',
            'laboratorio'         => 'LAB PRUEBA',
            'tipo'                => 'TABLETA',
            'stock'               => 100,
            'precio'              => $precio,
            'tipo_afectacion_igv' => $afectacion,
        ]);

        Lote::create([
            'producto_id'       => $producto->id,
            'codigo_lote'       => 'L-'.$producto->id,
            'cantidad_inicial'  => 100,
            'cantidad_actual'   => 100,
            'fecha_vencimiento' => now()->addYear()->toDateString(),
            'estado'            => 'activo',
        ]);

        return $producto;
    }

    /**
     * Vende en mostrador y emite el comprobante que corresponda al documento.
     *
     * @param  array<int, array{0: Producto, 1: int}>  $lineas
     */
    private function vender(array $lineas, array $cliente): ComprobanteElectronico
    {
        $pedido = app(VentaService::class)->registrar(
            items: array_map(fn (array $linea) => [
                'producto_id'  => $linea[0]->id,
                'unidad_venta' => 'unidad',
                'cantidad'     => $linea[1],
            ], $lineas),
            datosCliente: $cliente,
            vendedorId: ($this->vendedor ??= $this->crearAdministrador())->id,
        );

        return app(ComprobanteService::class)->emitir($pedido);
    }

    private function boletaDeAmoxicilina(): ComprobanteElectronico
    {
        return $this->vender(
            [[$this->producto('AMOXICILINA', '500 mg', 12.50), 3]],
            [
                'cliente_nombre'         => 'Rosa Quispe Mamani',
                'cliente_documento'      => self::DNI_CLIENTE,
                'cliente_tipo_documento' => 'dni',
            ],
        );
    }

    private function facturaDeAmoxicilina(): ComprobanteElectronico
    {
        return $this->vender(
            [[$this->producto('AMOXICILINA', '500 mg', 12.50), 10]],
            [
                'cliente_nombre'         => 'CLINICA SANTA ROSA S.A.C.',
                'cliente_documento'      => self::RUC_CLIENTE,
                'cliente_tipo_documento' => 'ruc',
            ],
        );
    }

    private function representacion(): RepresentacionImpresa
    {
        return app(RepresentacionImpresa::class);
    }

    // ------------------------------------------------- denominación y serie

    public function test_la_boleta_lleva_su_denominacion_oficial_y_serie_b(): void
    {
        $datos = $this->representacion()->datos($this->boletaDeAmoxicilina());

        $this->assertSame('BOLETA DE VENTA ELECTRÓNICA', $datos['comprobante']['denominacion']);
        $this->assertSame('03', $datos['comprobante']['codigo_tipo']);
        $this->assertSame('B001', $datos['comprobante']['serie']);
        $this->assertSame('B001-00000001', $datos['comprobante']['identificador']);
        $this->assertSame(self::RUC_EMISOR, $datos['emisor']['ruc']);
    }

    public function test_la_factura_lleva_su_denominacion_oficial_y_serie_f(): void
    {
        $datos = $this->representacion()->datos($this->facturaDeAmoxicilina());

        $this->assertSame('FACTURA ELECTRÓNICA', $datos['comprobante']['denominacion']);
        $this->assertSame('01', $datos['comprobante']['codigo_tipo']);
        $this->assertSame('F001-00000001', $datos['comprobante']['identificador']);
        $this->assertSame('6', $datos['cliente']['tipo_documento']);
        $this->assertSame('RUC', $datos['cliente']['etiqueta_documento']);
        $this->assertSame(self::RUC_CLIENTE, $datos['cliente']['numero_documento']);
    }

    // ------------------------------------------------------ cliente y pago

    public function test_identifica_al_cliente_con_dni_y_declara_moneda_y_medio_de_pago(): void
    {
        $datos = $this->representacion()->datos($this->boletaDeAmoxicilina());

        $this->assertSame('1', $datos['cliente']['tipo_documento']);
        $this->assertSame('DNI', $datos['cliente']['etiqueta_documento']);
        $this->assertSame(self::DNI_CLIENTE, $datos['cliente']['numero_documento']);
        $this->assertSame('Rosa Quispe Mamani', $datos['cliente']['nombre']);
        $this->assertSame('11/11/2026', $datos['comprobante']['fecha_emision']);
        $this->assertSame('SOLES', $datos['comprobante']['moneda_nombre']);
        $this->assertSame('Efectivo', $datos['comprobante']['medio_pago']);
    }

    // ------------------------------------------------------------- importes

    public function test_gravada_mas_igv_cuadra_con_el_total_al_centimo(): void
    {
        $datos   = $this->representacion()->datos($this->boletaDeAmoxicilina());
        $totales = $datos['totales'];

        /* 3 × 12.50 = 37.50 con IGV incluido; 37.50 / 1.18 = 31.78. */
        $this->assertSame(31.78, $totales['gravada']);
        $this->assertSame(5.72, $totales['igv']);
        $this->assertSame(37.50, $totales['total']);
        $this->assertSame(0.18, $totales['tasa_igv']);

        /* Se compara en céntimos enteros: sumar floats y comparar con `===`
           fallaría por la representación, no por el cálculo. */
        $this->assertSame(
            (int) round($totales['total'] * 100),
            (int) round($totales['gravada'] * 100) + (int) round($totales['igv'] * 100),
        );
    }

    public function test_el_detalle_imprime_cantidad_unidad_descripcion_precio_e_importe(): void
    {
        $datos = $this->representacion()->datos($this->boletaDeAmoxicilina());

        $this->assertCount(1, $datos['items']);
        $this->assertSame([
            'cantidad'        => 3,
            'unidad'          => 'UNIDAD',
            'descripcion'     => 'AMOXICILINA 500 mg',
            'valor_unitario'  => 10.59,
            'precio_unitario' => 12.50,
            'importe'         => 37.50,
        ], $datos['items'][0]);
    }

    public function test_un_producto_exonerado_no_genera_igv_y_el_total_sigue_cuadrando(): void
    {
        $comprobante = $this->vender(
            [
                [$this->producto('AMOXICILINA', '500 mg', 12.50), 3],
                [$this->producto('TAMOXIFENO', '20 mg', 80.00, Producto::EXONERADO), 1],
            ],
            [
                'cliente_nombre'         => 'Rosa Quispe Mamani',
                'cliente_documento'      => self::DNI_CLIENTE,
                'cliente_tipo_documento' => 'dni',
            ],
        );

        $totales = $this->representacion()->datos($comprobante)['totales'];

        $this->assertSame(31.78, $totales['gravada']);
        $this->assertSame(80.00, $totales['exonerada']);
        /* El IGV sale solo de la amoxicilina: 117.50 / 1.18 daría 17.92. */
        $this->assertSame(5.72, $totales['igv']);
        $this->assertSame(117.50, $totales['total']);
    }

    public function test_un_pedido_sin_desglose_fiscal_se_imprime_como_gravado_y_cuadra(): void
    {
        /* Los pedidos anteriores al desglose no tienen subtotales: el panel
           los factura igual, y su PDF no puede salir con totales a cero. */
        $pedido = Pedido::create([
            'usuario_id'   => $this->crearCliente()->id,
            'fecha_pedido' => now(),
            'total'        => 100.00,
            'estado'       => 'completado',
        ]);

        $comprobante = app(ComprobanteService::class)->emitir($pedido);
        $datos       = $this->representacion()->datos($comprobante);

        $this->assertSame(84.75, $datos['totales']['gravada']);
        $this->assertSame(15.25, $datos['totales']['igv']);
        $this->assertSame(100.00, $datos['totales']['total']);
        $this->assertSame('Venta general', $datos['items'][0]['descripcion']);
        $this->assertSame('SON: CIEN CON 00/100 SOLES', $datos['importe_en_letras']);
    }

    // ------------------------------------------------------ importe en letras

    public function test_el_importe_en_letras_corresponde_al_total(): void
    {
        $boleta  = $this->representacion()->datos($this->boletaDeAmoxicilina());
        $factura = $this->representacion()->datos($this->facturaDeAmoxicilina());

        $this->assertSame('SON: TREINTA Y SIETE CON 50/100 SOLES', $boleta['importe_en_letras']);
        $this->assertSame('SON: CIENTO VEINTICINCO CON 00/100 SOLES', $factura['importe_en_letras']);
    }

    // ----------------------------------------------------------------- QR

    public function test_el_qr_de_la_boleta_lleva_la_cadena_oficial(): void
    {
        $comprobante = $this->boletaDeAmoxicilina();

        /* RUC | tipo | serie | número | IGV | total | fecha | tipo doc | nº doc | */
        $esperada = '20123456789|03|B001|00000001|5.72|37.50|2026-11-11|1|45678912|';

        $this->assertSame($esperada, $this->representacion()->cadenaQr($comprobante));
        $this->assertSame($esperada, $this->representacion()->datos($comprobante)['qr']['cadena']);
    }

    public function test_el_qr_de_la_factura_lleva_la_cadena_oficial(): void
    {
        $this->assertSame(
            '20123456789|01|F001|00000001|19.07|125.00|2026-11-11|6|20600000001|',
            $this->representacion()->cadenaQr($this->facturaDeAmoxicilina()),
        );
    }

    public function test_el_qr_de_una_boleta_sin_documento_declara_al_cliente_con_guiones(): void
    {
        $comprobante = $this->vender(
            [[$this->producto('PARACETAMOL', '500 mg', 0.50), 10]],
            ['cliente_nombre' => 'Cliente de mostrador'],
        );

        $this->assertSame(
            '20123456789|03|B001|00000001|0.76|5.00|2026-11-11|-|-|',
            $this->representacion()->cadenaQr($comprobante),
        );
    }

    /**
     * El mostrador puede registrar el número sin elegir el tipo. Nueve
     * cifras no son DNI ni RUC, pero el cliente sí se identificó: no puede
     * salir como «sin documento».
     */
    public function test_un_carne_de_extranjeria_sin_tipo_declarado_no_sale_como_sin_documento(): void
    {
        $comprobante = $this->vender(
            [[$this->producto('AMOXICILINA', '500 mg', 12.50), 3]],
            [
                'cliente_nombre'    => 'María Fernanda Rojas Pérez',
                'cliente_documento' => '001234567',
            ],
        );

        $cliente = $this->representacion()->datos($comprobante)['cliente'];

        $this->assertSame('4', $cliente['tipo_documento']);
        $this->assertSame('CARNÉ DE EXTRANJERÍA', $cliente['etiqueta_documento']);
        $this->assertSame('001234567', $cliente['numero_documento']);
        $this->assertSame(
            '20123456789|03|B001|00000001|5.72|37.50|2026-11-11|4|001234567|',
            $this->representacion()->cadenaQr($comprobante),
        );
    }

    public function test_sin_numero_alguno_el_cliente_se_imprime_como_sin_documento(): void
    {
        $comprobante = $this->vender(
            [[$this->producto('PARACETAMOL', '500 mg', 0.50), 10]],
            ['cliente_nombre' => 'Cliente de mostrador'],
        );

        $cliente = $this->representacion()->datos($comprobante)['cliente'];

        $this->assertSame('-', $cliente['tipo_documento']);
        $this->assertSame('SIN DOCUMENTO', $cliente['etiqueta_documento']);
        $this->assertSame('-', $cliente['numero_documento']);
    }

    public function test_el_qr_se_incrusta_como_imagen_png(): void
    {
        $imagen = $this->representacion()->datos($this->boletaDeAmoxicilina())['qr']['imagen'];

        $this->assertNotNull($imagen);
        $this->assertStringStartsWith('data:image/png;base64,', $imagen);

        /* Se decodifica para comprobar que es un PNG de verdad y no una
           cadena con el prefijo correcto: una data URI codificada dos veces
           pasaría la primera aserción y saldría como imagen rota en el PDF. */
        $binario = base64_decode(substr($imagen, strlen('data:image/png;base64,')), true);
        $this->assertSame("\x89PNG", substr((string) $binario, 0, 4));

        /* Y se lee de vuelta, como haría un teléfono: lo que importa no es
           que haya un QR, sino que diga la cadena de la SUNAT. */
        $this->assertSame(
            '20123456789|03|B001|00000001|5.72|37.50|2026-11-11|1|45678912|',
            (new QRCode)->readFromBlob((string) $binario)->data,
        );
    }

    // ---------------------------------------------------- plantilla y PDF

    public function test_la_plantilla_imprime_el_recuadro_los_totales_y_la_leyenda(): void
    {
        $html = view('pdf.comprobante', $this->representacion()->datos($this->boletaDeAmoxicilina()))->render();

        foreach ([
            'RUC 20123456789',
            'BOLETA DE VENTA ELECTRÓNICA',
            'B001-00000001',
            'BOTICA SAN JUAN S.A.C.',
            'Rosa Quispe Mamani',
            '45678912',
            'AMOXICILINA 500 mg',
            'Op. Gravada',
            'S/ 31.78',
            'IGV 18 %',
            'S/ 5.72',
            'Total a Pagar',
            'S/ 37.50',
            'SON: TREINTA Y SIETE CON 50/100 SOLES',
            'Representación impresa de la BOLETA DE VENTA ELECTRÓNICA.',
        ] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }

        $this->assertStringNotContainsString('Op. Exonerada', $html);
    }

    public function test_la_plantilla_de_la_factura_no_dice_boleta(): void
    {
        $html = view('pdf.comprobante', $this->representacion()->datos($this->facturaDeAmoxicilina()))->render();

        $this->assertStringContainsString('FACTURA ELECTRÓNICA', $html);
        $this->assertStringContainsString('F001-00000001', $html);
        $this->assertStringNotContainsString('BOLETA', $html);
    }

    public function test_la_descarga_devuelve_un_pdf_con_su_cabecera(): void
    {
        $boleta  = $this->boletaDeAmoxicilina();
        $factura = $this->facturaDeAmoxicilina();

        Sanctum::actingAs($this->vendedor);

        foreach ([[$boleta, 'B001-00000001.pdf'], [$factura, 'F001-00000001.pdf']] as [$comprobante, $archivo]) {
            $respuesta = $this->get("/api/facturacion/documentos/{$comprobante->id}/pdf");

            $respuesta->assertOk();
            $respuesta->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringContainsString($archivo, (string) $respuesta->headers->get('Content-Disposition'));
            $this->assertStringStartsWith('%PDF-', $respuesta->getContent());

            $this->guardarMuestra($archivo, $respuesta->getContent());
        }
    }

    /**
     * Deja el PDF en disco cuando se pide, para poder mirarlo.
     *
     * Solo actúa con la variable MUESTRA_PDF_DIR definida; en una ejecución
     * normal no escribe nada. Así la evidencia de docs/evidencias/boleta-sunat
     * se regenera con el mismo código y los mismos datos ficticios que se
     * prueban, en vez de con un guion aparte que nadie mantendría.
     */
    private function guardarMuestra(string $archivo, string $contenido): void
    {
        $directorio = getenv('MUESTRA_PDF_DIR');

        if (! $directorio || ! is_dir($directorio)) {
            return;
        }

        file_put_contents(rtrim($directorio, '/\\').DIRECTORY_SEPARATOR.'muestra-'.$archivo, $contenido);
    }
}
