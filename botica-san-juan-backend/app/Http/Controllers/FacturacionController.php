<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarComprobanteASunat;
use App\Models\ComprobanteElectronico;
use App\Models\Pedido;
use App\Services\SunatClient;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FacturacionController extends Controller
{
    public function __construct(private readonly SunatClient $sunatClient)
    {
    }

    public function index(Request $request)
    {
        $query = ComprobanteElectronico::query()
            ->with(['pedido.usuario'])
            ->orderByDesc('fecha_emision');

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($search) {
                // La busqueda por numero de comprobante se resuelve sobre las columnas
                // 'serie' y 'numero' por separado, sin funciones especificas del motor:
                // CONCAT y LPAD no existen en SQLite y rompian esta consulta en ejecucion.
                $this->aplicarBusquedaPorNumero($inner, $search);

                $inner->orWhere('cliente_nombre', 'like', "%{$search}%")
                    ->orWhere('cliente_documento', 'like', "%{$search}%")
                    ->orWhereHas('pedido', function ($pedidoQuery) use ($search) {
                        $pedidoQuery->where('id', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('tipo_comprobante') && $request->input('tipo_comprobante') !== 'all') {
            $query->where('tipo_comprobante', $request->input('tipo_comprobante'));
        }

        if ($request->filled('estado_sunat') && $request->input('estado_sunat') !== 'all') {
            $query->where('estado_sunat', $request->input('estado_sunat'));
        }

        $shouldPaginate = $request->has('page') || $request->has('per_page') || $request->boolean('paginate');
        $result = $shouldPaginate
            ? $query->paginate(max(1, min((int) $request->input('per_page', 20), 100)))
            : $query->get();

        if ($shouldPaginate) {
            $result->getCollection()->transform(fn ($doc) => $this->transformDoc($doc));
            return $result;
        }

        return $result->map(fn ($doc) => $this->transformDoc($doc));
    }

    public function generarDesdePedidos(Request $request)
    {
        $pedidos = Pedido::query()
            ->whereDoesntHave('comprobanteElectronico')
            ->with(['usuario', 'pedidoDetalles'])
            ->orderBy('id')
            ->limit(max(1, min((int) $request->input('limit', 200), 500)))
            ->get();

        $created = 0;
        foreach ($pedidos as $pedido) {
            $this->crearComprobanteParaPedido($pedido);
            $created++;
        }

        return response()->json([
            'message' => 'Comprobantes generados correctamente.',
            'created' => $created,
        ]);
    }

    public function enviarSunat(Request $request, string $id)
    {
        $doc = ComprobanteElectronico::findOrFail($id);

        $validated = $request->validate([
            'force_status' => ['nullable', 'in:aceptada,rechazada'],
        ]);

        $payload = [
            'tipo_comprobante' => $doc->tipo_comprobante,
            'serie' => $doc->serie,
            'numero' => $doc->numero,
            'cliente_nombre' => $doc->cliente_nombre,
            'cliente_documento' => $doc->cliente_documento,
            'total' => (float) $doc->total,
            'fecha_emision' => optional($doc->fecha_emision)->toDateTimeString(),
        ];

        if (!empty($validated['force_status'])) {
            $payload['__force_status'] = $validated['force_status'];
        }

        $result = $this->sunatClient->send($payload);
        $accepted = (bool) ($result['ok'] ?? false);

        $doc->estado_sunat = $accepted ? 'aceptada' : 'rechazada';
        $doc->codigo_respuesta_sunat = (string) ($result['code'] ?? ($accepted ? '0' : '1032'));
        $doc->sunat_ticket = $result['ticket'] ?? null;
        $doc->sunat_payload = $payload;
        $doc->sunat_response = $result['response'] ?? null;
        $doc->mensaje_sunat = (string) ($result['message'] ?? ($accepted ? 'Aceptado por SUNAT.' : 'Documento rechazado por SUNAT.'));
        $doc->fecha_envio_sunat = Carbon::now();
        $doc->hash_documento = hash('sha256', $doc->serie . '-' . $doc->numero . '-' . $doc->total . '-' . now()->timestamp);
        $doc->save();

        return response()->json([
            'message' => $accepted ? 'Documento aceptado por SUNAT.' : 'Documento rechazado por SUNAT.',
            'documento' => $this->transformDoc($doc->fresh('pedido.usuario')),
        ]);
    }

    /**
     * Encola el envio del comprobante al operador de servicios electronicos.
     *
     * Devuelve de inmediato con estado "en_proceso": la latencia del proveedor
     * externo deja de bloquear al usuario. El desenlace lo escribe el trabajo
     * en cola y el consumidor lo consulta releyendo el documento.
     */
    public function encolarEnvioSunat(Request $request, string $id)
    {
        $doc = ComprobanteElectronico::findOrFail($id);

        $validated = $request->validate([
            'force_status' => ['nullable', 'in:aceptada,rechazada'],
        ]);

        if ($doc->estado_sunat === 'aceptada') {
            return response()->json([
                'message' => 'El comprobante ya fue aceptado por SUNAT; no se reenvia.',
                'documento' => $this->transformDoc($doc),
            ], 200);
        }

        EnviarComprobanteASunat::dispatch($doc->id, $validated['force_status'] ?? null);

        return response()->json([
            'message' => 'Envio encolado. El estado se actualizara al resolverse.',
            'documento' => $this->transformDoc($doc),
        ], 202);
    }

    public function descargarXml(string $id)
    {
        $doc = ComprobanteElectronico::with('pedido.usuario')->findOrFail($id);

        $xmlContent = $this->buildXml($doc);
        $fileName = sprintf('%s-%08d.xml', $doc->serie, $doc->numero);
        $relativePath = 'facturacion/xml/' . $fileName;

        Storage::disk('local')->put($relativePath, $xmlContent);

        $doc->xml_path = $relativePath;
        $doc->save();

        return response($xmlContent, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=' . $fileName,
        ]);
    }

    public function descargarPdf(string $id)
    {
        $doc = ComprobanteElectronico::with([
            'pedido.usuario',
            'pedido.pedidoDetalles.producto',
        ])->findOrFail($id);

        $company = $this->resolveCompanyData();

        $items = $this->resolveDocumentItems($doc);
        $total = (float) $doc->total;
        $subtotal = (float) collect($items)->sum('subtotal');
        if ($subtotal <= 0.0) {
            $subtotal = $total;
        }

        $baseImponible = round($total / 1.18, 2);
        $igv = round($total - $baseImponible, 2);

        $pdf = Pdf::loadView('pdf.comprobante', [
            'documento' => $doc,
            'items' => $items,
            'subtotal' => $subtotal,
            'baseImponible' => $baseImponible,
            'igv' => $igv,
            'total' => $total,
            'company' => $company,
            'logoDataUri' => $this->resolveLogoDataUri(),
            'qrDataUri' => $this->resolveQrDataUri($doc, $company),
            'documentNumber' => sprintf('%s-%08d', $doc->serie, $doc->numero),
        ])->setPaper('a4');

        $fileName = sprintf('%s-%08d.pdf', $doc->serie, $doc->numero);
        return $pdf->download($fileName);
    }

    /**
     * Filtra por numero de comprobante sin depender de funciones propietarias del motor.
     *
     * Acepta tres formas de busqueda: "B001-00000012", "B001" o "12".
     */
    private function aplicarBusquedaPorNumero($query, string $search): void
    {
        $termino = strtoupper(trim($search));

        // Formato completo "SERIE-NUMERO": se separa y se compara columna por columna.
        if (preg_match('/^([A-Z0-9]{1,4})\s*-\s*0*(\d+)$/', $termino, $partes)) {
            $query->where(function ($q) use ($partes) {
                $q->where('serie', $partes[1])
                    ->where('numero', (int) $partes[2]);
            });

            return;
        }

        // Solo digitos: se busca por numero correlativo exacto.
        if (preg_match('/^0*(\d+)$/', $termino, $partes)) {
            $query->where('numero', (int) $partes[1]);

            return;
        }

        // Cualquier otro texto: se interpreta como serie parcial.
        $query->where('serie', 'like', "%{$termino}%");
    }

    /**
     * Crea el comprobante reservando el correlativo dentro de una transaccion.
     *
     * El bloqueo pesimista sobre los comprobantes de la serie impide que dos
     * emisiones simultaneas obtengan el mismo numero y choquen contra la
     * restriccion UNIQUE(serie, numero).
     */
    private function crearComprobanteParaPedido(Pedido $pedido): ComprobanteElectronico
    {
        $totalPedido = $this->resolvePedidoTotal($pedido);
        $tipo = $totalPedido >= 700 ? 'factura' : 'boleta';
        $serie = $tipo === 'factura' ? 'F001' : 'B001';

        return DB::transaction(function () use ($pedido, $tipo, $serie, $totalPedido) {
            $ultimoNumero = (int) ComprobanteElectronico::query()
                ->where('serie', $serie)
                ->lockForUpdate()
                ->max('numero');

            return ComprobanteElectronico::create([
                'pedido_id' => $pedido->id,
                'tipo_comprobante' => $tipo,
                'serie' => $serie,
                'numero' => $ultimoNumero + 1,
                'cliente_nombre' => $pedido->usuario->nombre ?? ('Cliente ' . $pedido->usuario_id),
                'cliente_documento' => $pedido->usuario->dni ?? null,
                'total' => $totalPedido,
                'estado_sunat' => 'pendiente',
                'fecha_emision' => $pedido->fecha_pedido ?? now(),
            ]);
        });
    }

    private function resolvePedidoTotal(Pedido $pedido): float
    {
        if ($pedido->total !== null) {
            return (float) $pedido->total;
        }

        $detalleTotal = $pedido->pedidoDetalles->sum(function ($detalle) {
            $cantidad = (float) ($detalle->cantidad ?? 0);
            $precio = (float) ($detalle->precio ?? 0);
            return $cantidad * $precio;
        });

        return (float) $detalleTotal;
    }

    private function transformDoc(ComprobanteElectronico $doc): array
    {
        return [
            'id' => $doc->id,
            'orderId' => $doc->pedido_id,
            'number' => sprintf('%s-%08d', $doc->serie, $doc->numero),
            'type' => $doc->tipo_comprobante,
            'customerName' => $doc->cliente_nombre,
            'customerDoc' => $doc->cliente_documento ?: 'Sin documento',
            'total' => (float) $doc->total,
            'sunatStatus' => $doc->estado_sunat,
            'sunatCode' => $doc->codigo_respuesta_sunat,
            'sunatTicket' => $doc->sunat_ticket,
            'sunatMessage' => $doc->mensaje_sunat,
            'commissionAmount' => (float) ($doc->monto_comision ?? 0),
            'commissionStatus' => (string) ($doc->estado_comision ?? 'sin_comision'),
            'issuedAt' => optional($doc->fecha_emision)->toDateTimeString(),
            'sentAt' => optional($doc->fecha_envio_sunat)->toDateTimeString(),
        ];
    }

    private function buildXml(ComprobanteElectronico $doc): string
    {
        $issuedAt = optional($doc->fecha_emision)->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s');
        $safeName = htmlspecialchars((string) $doc->cliente_nombre, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $safeDoc = htmlspecialchars((string) ($doc->cliente_documento ?: ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<ComprobanteElectronico>\n"
            . "  <Numero>" . sprintf('%s-%08d', $doc->serie, $doc->numero) . "</Numero>\n"
            . "  <Tipo>" . $doc->tipo_comprobante . "</Tipo>\n"
            . "  <PedidoId>{$doc->pedido_id}</PedidoId>\n"
            . "  <ClienteNombre>{$safeName}</ClienteNombre>\n"
            . "  <ClienteDocumento>{$safeDoc}</ClienteDocumento>\n"
            . "  <Total>" . number_format((float) $doc->total, 2, '.', '') . "</Total>\n"
            . "  <EstadoSunat>{$doc->estado_sunat}</EstadoSunat>\n"
            . "  <FechaEmision>{$issuedAt}</FechaEmision>\n"
            . "</ComprobanteElectronico>\n";
    }

    private function resolveDocumentItems(ComprobanteElectronico $doc): array
    {
        $detalles = $doc->pedido?->pedidoDetalles ?? collect();
        if ($detalles->isEmpty()) {
            return [[
                'descripcion' => 'Venta general',
                'unidad' => 'unidad',
                'cantidad' => 1,
                'precio_unitario' => (float) $doc->total,
                'subtotal' => (float) $doc->total,
            ]];
        }

        return $detalles->map(function ($detalle) {
            $cantidad = (float) ($detalle->cantidad ?? 0);
            $precioUnitario = (float) ($detalle->precio_unitario ?? $detalle->precio ?? 0);
            $subtotal = (float) ($detalle->subtotal ?? ($cantidad * $precioUnitario));

            return [
                'descripcion' => (string) ($detalle->producto->nombre ?? ('Producto #' . ($detalle->producto_id ?? 'N/A'))),
                'unidad' => (string) ($detalle->unidad_venta ?? 'unidad'),
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'subtotal' => $subtotal,
            ];
        })->values()->all();
    }

    private function resolveLogoDataUri(): ?string
    {
        $candidates = [
            config('company.logo_path') ? public_path((string) config('company.logo_path')) : null,
            public_path('images/logo.png'),
            public_path('images/default_image.png'),
        ];

        foreach ($candidates as $path) {
            if (!$path || !is_file($path)) {
                continue;
            }

            $content = @file_get_contents($path);
            if ($content === false) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode($content);
        }

        return null;
    }

    private function resolveCompanyData(): array
    {
        return [
            'ruc' => (string) config('company.ruc', '20123456789'),
            'razon_social' => (string) config('company.razon_social', 'Botica San Juan SAC'),
            'direccion' => (string) config('company.direccion', 'Av. Principal 123 - Lima'),
            'telefono' => (string) config('company.telefono', ''),
            'email' => (string) config('company.email', ''),
        ];
    }

    private function resolveQrDataUri(ComprobanteElectronico $doc, array $company): ?string
    {
        try {
            $docNumber = sprintf('%s-%08d', $doc->serie, $doc->numero);
            $qrPayload = implode('|', [
                $company['ruc'] ?: '-',
                strtoupper((string) $doc->tipo_comprobante),
                $docNumber,
                number_format((float) $doc->total, 2, '.', ''),
                $doc->sunat_ticket ?: '-',
                $doc->hash_documento ?: '-',
            ]);

            $options = new QROptions([
                'outputType' => 'png',
                'eccLevel' => EccLevel::M,
                'scale' => 5,
                'imageBase64' => false,
            ]);

            $pngData = (new QRCode($options))->render($qrPayload);
            return 'data:image/png;base64,' . base64_encode($pngData);
        } catch (\Throwable) {
            return null;
        }
    }
}
