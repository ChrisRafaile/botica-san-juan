<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante {{ $documento->serie }}-{{ str_pad((string) $documento->numero, 8, '0', STR_PAD_LEFT) }}</title>
    @php
        $isFactura = $documento->tipo_comprobante === 'factura';
    @endphp
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #0f172a;
            margin: 24px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 18px;
        }
        .header-left,
        .header-right {
            display: table-cell;
            vertical-align: top;
        }
        .header-right {
            text-align: right;
        }
        .logo {
            width: 62px;
            height: 62px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            margin: 4px 0;
        }
        .muted {
            color: #475569;
            font-size: 11px;
        }
        .box {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 10px;
        }
        .doc-chip {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 6px;
            color: #fff;
        }
        .doc-chip.factura {
            background: #0f766e;
        }
        .doc-chip.boleta {
            background: #1d4ed8;
        }
        .document-box.factura {
            border: 2px solid #0f766e;
        }
        .document-box.boleta {
            border: 2px solid #1d4ed8;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
        }
        .grid th,
        .grid td {
            border: 1px solid #cbd5e1;
            padding: 8px;
        }
        .grid th {
            background: #f1f5f9;
            text-align: left;
            font-size: 11px;
        }
        .right {
            text-align: right;
        }
        .totals {
            width: 44%;
            margin-left: auto;
            margin-top: 12px;
            border-collapse: collapse;
        }
        .totals td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
        }
        .totals .label {
            background: #f8fafc;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #cbd5e1;
        }
        .footer {
            margin-top: 16px;
            font-size: 11px;
            color: #334155;
            border-top: 1px dashed #94a3b8;
            padding-top: 10px;
        }
        .meta-row {
            margin-top: 8px;
            font-size: 11px;
            color: #334155;
        }
        .qr-block {
            margin-top: 14px;
            display: table;
            width: 100%;
        }
        .qr-left,
        .qr-right {
            display: table-cell;
            vertical-align: top;
        }
        .qr-right {
            width: 120px;
            text-align: right;
        }
        .qr {
            width: 110px;
            height: 110px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            @if($logoDataUri)
                <img class="logo" src="{{ $logoDataUri }}" alt="Logo">
            @endif
            <div class="title">{{ $company['razon_social'] }}</div>
            <div class="muted">RUC: {{ $company['ruc'] }}</div>
            <div class="muted">Dir: {{ $company['direccion'] }}</div>
            @if(!empty($company['telefono']))
                <div class="muted">Tel: {{ $company['telefono'] }}</div>
            @endif
            @if(!empty($company['email']))
                <div class="muted">Email: {{ $company['email'] }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="box document-box {{ $isFactura ? 'factura' : 'boleta' }}">
                <div class="doc-chip {{ $isFactura ? 'factura' : 'boleta' }}">{{ $isFactura ? 'Factura Electrónica' : 'Boleta Electrónica' }}</div>
                <div style="font-weight: bold; font-size: 14px; text-transform: uppercase;">{{ $documento->tipo_comprobante }}</div>
                <div style="font-size: 16px; font-weight: bold; margin-top: 4px;">
                    {{ $documentNumber }}
                </div>
                <div class="muted" style="margin-top: 6px;">Emision: {{ optional($documento->fecha_emision)->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    </div>

    <div class="box">
        <div><strong>Cliente:</strong> {{ $documento->cliente_nombre }}</div>
        <div><strong>Documento:</strong> {{ $documento->cliente_documento ?: 'Sin documento' }}</div>
        <div><strong>Pedido:</strong> #{{ $documento->pedido_id }}</div>
        <div class="meta-row"><strong>Hash:</strong> {{ $documento->hash_documento ?: 'No generado' }}</div>
        <div>
            <strong>SUNAT:</strong>
            <span class="badge">{{ $documento->estado_sunat }}</span>
            @if($documento->sunat_ticket)
                <span class="muted"> Ticket: {{ $documento->sunat_ticket }}</span>
            @endif
        </div>
    </div>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 45%;">Descripcion</th>
                <th style="width: 12%;">Unidad</th>
                <th style="width: 12%;" class="right">Cant.</th>
                <th style="width: 15%;" class="right">P. Unit</th>
                <th style="width: 16%;" class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item['descripcion'] }}</td>
                    <td>{{ $item['unidad'] }}</td>
                    <td class="right">{{ number_format((float) $item['cantidad'], 2, '.', '') }}</td>
                    <td class="right">S/ {{ number_format((float) $item['precio_unitario'], 2, '.', '') }}</td>
                    <td class="right">S/ {{ number_format((float) $item['subtotal'], 2, '.', '') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Valor venta</td>
            <td class="right">S/ {{ number_format((float) $baseImponible, 2, '.', '') }}</td>
        </tr>
        <tr>
            <td class="label">IGV (18%)</td>
            <td class="right">S/ {{ number_format((float) $igv, 2, '.', '') }}</td>
        </tr>
        <tr>
            <td class="label">Total</td>
            <td class="right"><strong>S/ {{ number_format((float) $total, 2, '.', '') }}</strong></td>
        </tr>
    </table>

    <div class="qr-block">
        <div class="qr-left">
            <div class="muted">Código de validación (RUC | Tipo | Número | Total | Ticket | Hash)</div>
        </div>
        <div class="qr-right">
            @if($qrDataUri)
                <img class="qr" src="{{ $qrDataUri }}" alt="QR">
            @endif
        </div>
    </div>

    <div class="footer">
        Representacion impresa del comprobante electronico.
        El documento tributario oficial para SUNAT se transmite en formato XML firmado.
    </div>
</body>
</html>
