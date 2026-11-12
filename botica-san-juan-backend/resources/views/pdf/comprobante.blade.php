{{--
    Representación impresa de la boleta y de la factura electrónica.

    Esta plantilla NO calcula: todo llega resuelto desde
    App\Services\Sunat\RepresentacionImpresa. Una cifra recalculada aquí
    podría separarse por un céntimo de la que va dentro del QR.

    La maquetación es con tablas y no con flex ni grid porque quien dibuja es
    dompdf, que no implementa ninguno de los dos.
--}}
@php
    $dinero = fn ($importe) => number_format((float) $importe, 2, '.', ',');
    $simbolo = $comprobante['simbolo'];
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $comprobante['denominacion'] }} {{ $comprobante['identificador'] }}</title>
    <style>
        @page { margin: 28px 30px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            line-height: 1.35;
            color: #0f172a;
            margin: 0;
        }

        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        .derecha { text-align: right; }
        .centro { text-align: center; }
        .tenue { color: #475569; }

        /* --- Cabecera: emisor a la izquierda, recuadro fiscal a la derecha --- */
        .emisor { width: 58%; padding-right: 18px; }
        .emisor .logo { height: 46px; margin-bottom: 6px; }
        .emisor .marca {
            font-size: 21px;
            font-weight: bold;
            color: #1e40af;
            letter-spacing: 0.3px;
            line-height: 1.1;
        }
        .emisor .razon { font-size: 11px; font-weight: bold; margin-top: 3px; }
        .emisor .dato { font-size: 9.5px; color: #475569; }

        .fiscal { width: 42%; }
        .recuadro {
            border: 2px solid #1e40af;
            border-radius: 8px;
            text-align: center;
        }
        .recuadro .ruc {
            font-size: 14px;
            font-weight: bold;
            padding: 10px 8px 8px;
        }
        .recuadro .denominacion {
            background: #1e40af;
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.4px;
            padding: 8px 6px;
        }
        .recuadro .numero {
            font-size: 15px;
            font-weight: bold;
            padding: 8px 8px 10px;
        }

        /* --- Datos del adquirente y de la operación --- */
        .datos {
            margin-top: 16px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
        }
        .datos td { padding: 9px 12px; }
        .datos .operacion { width: 36%; border-left: 1px solid #cbd5e1; }
        .par { margin-bottom: 3px; }
        .par .etiqueta {
            font-size: 8.5px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* --- Detalle --- */
        .detalle { margin-top: 14px; }
        .detalle th {
            background: #1e40af;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 7px 8px;
            text-align: left;
        }
        .detalle th.derecha { text-align: right; }
        .detalle th.centro { text-align: center; }
        .detalle td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        .detalle tr.par-fila td { background: #f8fafc; }

        /* --- Cierre: letras y QR a la izquierda, totales a la derecha --- */
        .cierre { margin-top: 14px; }
        .cierre .izquierda { width: 58%; padding-right: 18px; }
        .cierre .totales-celda { width: 42%; }

        .letras {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            font-weight: bold;
            font-size: 10px;
        }

        .qr-tabla { margin-top: 12px; }
        .qr-tabla .qr-celda { width: 112px; }
        .qr { width: 104px; height: 104px; }
        .qr-texto { padding-left: 10px; font-size: 9px; color: #475569; }
        .qr-texto .titulo { font-weight: bold; color: #0f172a; font-size: 9.5px; }
        .hash { font-size: 8px; word-wrap: break-word; }

        .totales td {
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .totales .concepto { color: #475569; }
        .totales tr.total td {
            background: #1e40af;
            color: #ffffff;
            font-size: 12.5px;
            font-weight: bold;
            border-bottom: none;
            padding: 9px 10px;
        }

        .pie {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #cbd5e1;
            font-size: 8.5px;
            color: #475569;
            text-align: center;
        }
    </style>
</head>
<body>
    <table>
        <tr>
            <td class="emisor">
                @if($logo)
                    <img class="logo" src="{{ $logo }}" alt="">
                @endif
                <div class="marca">{{ $emisor['nombre_comercial'] ?: $emisor['razon_social'] }}</div>
                <div class="razon">{{ $emisor['razon_social'] }}</div>
                <div class="dato">{{ $emisor['direccion'] }}</div>
                @if($emisor['telefono'] !== '')
                    <div class="dato">Teléfono: {{ $emisor['telefono'] }}</div>
                @endif
                @if($emisor['email'] !== '')
                    <div class="dato">{{ $emisor['email'] }}</div>
                @endif
            </td>
            <td class="fiscal">
                <div class="recuadro">
                    <div class="ruc">RUC {{ $emisor['ruc'] }}</div>
                    <div class="denominacion">{{ $comprobante['denominacion'] }}</div>
                    <div class="numero">{{ $comprobante['identificador'] }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="datos">
        <tr>
            <td>
                <div class="par">
                    <span class="etiqueta">{{ $comprobante['tipo'] === 'factura' ? 'Razón social' : 'Cliente' }}:</span>
                    {{ $cliente['nombre'] }}
                </div>
                <div class="par">
                    <span class="etiqueta">{{ $cliente['etiqueta_documento'] }}:</span>
                    {{ $cliente['numero_documento'] }}
                </div>
                @if($cliente['direccion'])
                    <div class="par">
                        <span class="etiqueta">Dirección:</span>
                        {{ $cliente['direccion'] }}
                    </div>
                @endif
            </td>
            <td class="operacion">
                <div class="par">
                    <span class="etiqueta">Fecha de emisión:</span>
                    {{ $comprobante['fecha_emision'] }} {{ $comprobante['hora_emision'] }}
                </div>
                <div class="par">
                    <span class="etiqueta">Moneda:</span>
                    {{ $comprobante['moneda_nombre'] }} ({{ $comprobante['moneda'] }})
                </div>
                <div class="par">
                    <span class="etiqueta">Medio de pago:</span>
                    {{ $comprobante['medio_pago'] }}
                </div>
            </td>
        </tr>
    </table>

    <table class="detalle">
        <thead>
            <tr>
                <th class="centro" style="width: 8%;">Cant.</th>
                <th style="width: 11%;">Unidad</th>
                <th>Descripción</th>
                <th class="derecha" style="width: 13%;">V. unit.</th>
                <th class="derecha" style="width: 13%;">P. unit.</th>
                <th class="derecha" style="width: 14%;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr class="{{ $loop->even ? 'par-fila' : '' }}">
                    <td class="centro">{{ $item['cantidad'] }}</td>
                    <td>{{ $item['unidad'] }}</td>
                    <td>{{ $item['descripcion'] }}</td>
                    <td class="derecha">{{ $dinero($item['valor_unitario']) }}</td>
                    <td class="derecha">{{ $dinero($item['precio_unitario']) }}</td>
                    <td class="derecha">{{ $dinero($item['importe']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="cierre">
        <tr>
            <td class="izquierda">
                <div class="letras">{{ $importe_en_letras }}</div>

                <table class="qr-tabla">
                    <tr>
                        @if($qr['imagen'])
                            <td class="qr-celda">
                                <img class="qr" src="{{ $qr['imagen'] }}" alt="Código QR del comprobante">
                            </td>
                        @endif
                        <td class="qr-texto">
                            <div class="titulo">{{ $leyenda }}</div>
                            <div>El código QR contiene los datos del comprobante en el formato establecido por la SUNAT.</div>
                            @if($comprobante['hash'])
                                <div class="hash" style="margin-top: 5px;">
                                    <strong>Código hash:</strong> {{ $comprobante['hash'] }}
                                </div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td class="totales-celda">
                <table class="totales">
                    <tr>
                        <td class="concepto">Op. Gravada</td>
                        <td class="derecha">{{ $simbolo }} {{ $dinero($totales['gravada']) }}</td>
                    </tr>
                    {{-- Exonerado e inafecto solo se imprimen si los hay: en
                         la mayoría de las ventas serían dos filas a cero. --}}
                    @if($totales['exonerada'] > 0)
                        <tr>
                            <td class="concepto">Op. Exonerada</td>
                            <td class="derecha">{{ $simbolo }} {{ $dinero($totales['exonerada']) }}</td>
                        </tr>
                    @endif
                    @if($totales['inafecta'] > 0)
                        <tr>
                            <td class="concepto">Op. Inafecta</td>
                            <td class="derecha">{{ $simbolo }} {{ $dinero($totales['inafecta']) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="concepto">IGV {{ rtrim(rtrim(number_format($totales['tasa_igv'] * 100, 2, '.', ''), '0'), '.') }} %</td>
                        <td class="derecha">{{ $simbolo }} {{ $dinero($totales['igv']) }}</td>
                    </tr>
                    <tr class="total">
                        <td>Total a Pagar</td>
                        <td class="derecha">{{ $simbolo }} {{ $dinero($totales['total']) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="pie">
        Los precios unitarios incluyen IGV.
    </div>
</body>
</html>
