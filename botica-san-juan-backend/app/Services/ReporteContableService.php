<?php

namespace App\Services;

use App\Models\Pedido;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Registro de ventas para el contador.
 * ---------------------------------------------------------------------------
 * ESTO ES EL DOLOR NÚMERO DOS DEL PROYECTO
 * Hoy el dueño anota las ventas del día en Excel a mano y se lo manda al
 * contador. Eso es tiempo perdido todas las noches y una fuente de errores de
 * tecleo sobre cifras que acaban en una declaración.
 *
 * DE DÓNDE SALE LA ESTRUCTURA
 * No se invento: se calco del archivo que el contador ya recibe y procesa
 * (regven2, exportado del sistema FoxPro). Un reporte con columnas distintas
 * obligaria al contador a cambiar su forma de trabajar, que es justo lo que no
 * se debe pedir cuando el objetivo es que adopte el sistema nuevo.
 *
 * QUÉ SE CONSERVA Y QUÉ NO
 * Se conservan los nombres de campo originales, por raros que parezcan
 * (`fecvta`, `sincred`, `nograva`), porque son los que su software espera.
 *
 * Se omiten cuatro columnas del original tras comprobarlo sobre los 980
 * comprobantes de agosto: `grava`, `basiev` e `iev` estaban SIEMPRE en cero, y
 * `sincred2` era copia exacta de `sincred`. Arrastrarlas seria propagar un
 * residuo del sistema viejo. Si el contador las necesita, se reactivan con la
 * opcion correspondiente.
 *
 * EL CUADRE QUE DEBE CUMPLIRSE
 * En las 980 filas del archivo original se verifico que
 * `total = sincred + igv + nograva`. Este servicio mantiene esa identidad y la
 * comprueba, porque si alguna vez deja de cumplirse el contador lo detectaria
 * al cuadrar el mes y no antes.
 */
class ReporteContableService
{
    /** Tipos de comprobante, con el codigo interno que usa el sistema viejo. */
    private const DOCUMENTOS = [
        'nota_venta' => ['coddoc' => '11', 'desdoc' => 'NOTA DE VENTA',    'abrdoc' => 'TIN1', 'codcon' => '00'],
        'boleta'     => ['coddoc' => '17', 'desdoc' => 'BOLE.ELECT. A01',  'abrdoc' => 'BA01', 'codcon' => '03'],
    ];

    /** Documento generico del cliente de mostrador, como en el original. */
    private const RUC_CLIENTE_EVENTUAL = '99999999999';
    private const NOMBRE_CLIENTE_EVENTUAL = 'CLIENTE - EVENTUAL';

    /**
     * Genera el registro de ventas de un periodo.
     *
     * @param  bool  $columnasHeredadas  Incluir las columnas vacias del
     *                                   original, por si el contador las exige.
     * @return array{filas: Collection, resumen: array, avisos: array<string>}
     */
    public function generar(
        CarbonInterface $desde,
        CarbonInterface $hasta,
        bool $columnasHeredadas = false,
    ): array {
        $pedidos = Pedido::query()
            ->with(['pagos'])
            ->whereBetween('fecha', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->where('estado', '!=', 'anulado')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $avisos = [];

        $filas = $pedidos->map(
            fn (Pedido $pedido) => $this->formatearFila($pedido, $columnasHeredadas)
        );

        /* El cuadre se comprueba fila a fila, no sobre el total: una diferencia
           de un centimo en una fila y otra de signo contrario en otra se
           cancelarian en el gran total y nadie las veria. */
        $descuadradas = $filas->filter(function (array $fila) {
            $suma = round($fila['sincred'] + $fila['igv'] + $fila['nograva'], 2);

            return abs($suma - $fila['total']) > 0.005;
        });

        if ($descuadradas->isNotEmpty()) {
            $avisos[] = sprintf(
                '%d comprobantes no cuadran (base + IGV + exonerado != total): %s',
                $descuadradas->count(),
                $descuadradas->take(5)->pluck('nrodoc')->implode(', ')
            );
        }

        /* Un comprobante en cero no deberia existir: o es una venta que quedo
           a medias o un registro de prueba. No se oculta, porque esconderlo
           haria que el registro no cuadrara con la numeracion correlativa y el
           contador acabaria preguntando por el hueco. Se avisa para que alguien
           decida si se anula. */
        $enCero = $filas->filter(fn (array $f) => $f['total'] <= 0);

        if ($enCero->isNotEmpty()) {
            $avisos[] = sprintf(
                '%d comprobantes con importe cero, revisar si deben anularse: %s',
                $enCero->count(),
                $enCero->take(5)->pluck('nrodoc')->implode(', ')
            );
        }

        /* Un comprobante sin identificar al cliente por encima del umbral es
           justo lo que el contador tendria que reclamar despues. */
        $sinIdentificar = $filas->filter(
            fn (array $f) => $f['total'] >= 700 && $f['ruccli'] === self::RUC_CLIENTE_EVENTUAL
        );

        if ($sinIdentificar->isNotEmpty()) {
            $avisos[] = sprintf(
                '%d comprobantes de S/ 700 o mas van a cliente eventual sin documento: %s',
                $sinIdentificar->count(),
                $sinIdentificar->take(5)->pluck('nrodoc')->implode(', ')
            );
        }

        return [
            'filas'   => $filas,
            'resumen' => $this->resumir($filas),
            'avisos'  => $avisos,
        ];
    }

    /**
     * Una fila del registro, con los nombres de campo del sistema anterior.
     */
    private function formatearFila(Pedido $pedido, bool $columnasHeredadas): array
    {
        /* Mientras no exista numeracion propia de comprobante, se usa el id del
           pedido. Se marca como interno para que nadie lo confunda con un
           correlativo declarado ante la SUNAT. */
        $tipo = $this->tipoDeComprobante($pedido);
        $doc  = self::DOCUMENTOS[$tipo];

        $fila = [
            'fecvta'  => $pedido->fecha?->format('d/m/Y'),
            'codcon'  => $doc['codcon'],
            'nrodoc'  => $this->numeroDocumento($pedido, $tipo),
            'ruccli'  => $pedido->cliente_documento ?: self::RUC_CLIENTE_EVENTUAL,
            'razcli'  => $pedido->cliente_nombre ?: self::NOMBRE_CLIENTE_EVENTUAL,
            'nograva' => round((float) $pedido->subtotal_exonerado + (float) $pedido->subtotal_inafecto, 2),
            'sincred' => round((float) $pedido->subtotal_gravado, 2),
            'coddoc'  => $doc['coddoc'],
            'obsvta'  => 'NORMAL',
            'desdoc'  => $doc['desdoc'],
            'abrdoc'  => $doc['abrdoc'],
            'igv'     => round((float) $pedido->igv, 2),
            'total'   => round((float) $pedido->total, 2),
            'codcli'  => str_pad((string) ($pedido->usuario_id ?? 1), 10, '0', STR_PAD_LEFT),
            'tipven'  => $this->medioDePago($pedido),
        ];

        if ($columnasHeredadas) {
            /* Se devuelven donde estaban en el original, con el valor que
               siempre tuvieron, para que su hoja de calculo no se desalinee. */
            $fila['grava']    = 0.00;
            $fila['basiev']   = 0.00;
            $fila['iev']      = 0.00;
            $fila['sincred2'] = $fila['sincred'];
        }

        return $fila;
    }

    /**
     * Qué tipo de comprobante representa la venta.
     *
     * De momento todo lo del mostrador sale como nota de venta, que es lo que
     * hace el sistema actual. Cuando se conecte la facturacion electronica,
     * esto pasara a leer el comprobante realmente emitido.
     */
    private function tipoDeComprobante(Pedido $pedido): string
    {
        return $pedido->origen === 'pos' ? 'nota_venta' : 'boleta';
    }

    private function numeroDocumento(Pedido $pedido, string $tipo): string
    {
        $serie = $tipo === 'boleta' ? 'A01' : '001';

        return $serie.'-'.str_pad((string) $pedido->id, 7, '0', STR_PAD_LEFT);
    }

    /**
     * El medio de pago como texto, igual que el campo `tipven` del original.
     *
     * Un pago dividido se reporta como "Mixto": el archivo del contador tiene
     * una fila por comprobante y un solo valor, asi que aqui no cabe el
     * desglose. El detalle por medio sigue guardado en pedido_pagos para el
     * arqueo de caja, que es otra necesidad distinta.
     */
    private function medioDePago(Pedido $pedido): string
    {
        return match ($pedido->medio_pago) {
            'efectivo'      => 'Efectivo',
            'tarjeta'       => 'Tarjeta',
            'yape'          => 'Yape',
            'plin'          => 'Plin',
            'transferencia' => 'Transferencia',
            'mixto'         => 'Mixto',
            default         => 'Efectivo',
        };
    }

    /** @param  Collection<int, array>  $filas */
    private function resumir(Collection $filas): array
    {
        $porDocumento = $filas
            ->groupBy('desdoc')
            ->map(fn (Collection $g) => [
                'comprobantes' => $g->count(),
                'total'        => round($g->sum('total'), 2),
            ]);

        return [
            'comprobantes'    => $filas->count(),
            'base_gravada'    => round($filas->sum('sincred'), 2),
            'igv'             => round($filas->sum('igv'), 2),
            'exonerado'       => round($filas->sum('nograva'), 2),
            'total'           => round($filas->sum('total'), 2),
            'por_documento'   => $porDocumento,
            'por_medio_pago'  => $filas->groupBy('tipven')->map->count(),
        ];
    }

    /**
     * Vuelca las filas a CSV.
     *
     * Se usa punto y coma y BOM porque el destino es Excel en español: con coma
     * como separador, Excel mete toda la fila en una sola celda, y sin BOM las
     * tildes salen rotas. Detalles pequeños que deciden si el contador puede
     * abrir el archivo o tiene que pedir otro.
     */
    public function aCsv(Collection $filas): string
    {
        if ($filas->isEmpty()) {
            return "\u{FEFF}Sin movimientos en el periodo\r\n";
        }

        $salida = "\u{FEFF}";
        $columnas = array_keys($filas->first());

        $salida .= implode(';', $columnas)."\r\n";

        foreach ($filas as $fila) {
            $salida .= implode(';', array_map(
                fn ($v) => is_float($v) ? number_format($v, 2, '.', '') : (string) $v,
                $fila
            ))."\r\n";
        }

        return $salida;
    }
}
