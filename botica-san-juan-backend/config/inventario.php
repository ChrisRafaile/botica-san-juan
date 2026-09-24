<?php

/**
 * Reglas de inventario y venta de la botica.
 *
 * Están aquí, y no repartidas por el código, porque son decisiones del negocio
 * que el dueño puede querer cambiar sin tocar programación. Todas admiten
 * sobreescritura por variable de entorno.
 */
return [

    /*
     | Margen de seguridad para vender, en días.
     |
     | Un lote que vence dentro de menos días que este valor no se ofrece en la
     | venta, aunque tenga stock. La idea es no entregar un medicamento que el
     | cliente probablemente no alcance a consumir.
     |
     | 0 = sin margen: se vende hasta el día anterior al vencimiento.
     | Los lotes YA vencidos se excluyen siempre, con independencia de esto.
     */
    'dias_minimos_venta' => (int) env('INVENTARIO_DIAS_MINIMOS_VENTA', 0),

    /*
     | Ventanas de alerta por vencimiento, en días.
     | Coinciden con los tokens de color del design system.
     */
    'alertas_vencimiento' => [
        'critico' => 30,
        'alto'    => 60,
        'medio'   => 90,
    ],

    /*
     | Tasa de IGV vigente. Se guarda además en cada comprobante emitido, para
     | que un cambio futuro no altere el histórico.
     */
    'tasa_igv' => (float) env('IGV_TASA', 0.18),

    /*
     | Permitir que el vendedor corrija el stock desde el punto de venta.
     |
     | Cuando el anaquel no coincide con el sistema, obligar a salir del POS
     | para corregir hace que el vendedor acabe ignorando el sistema. Es
     | preferible permitir la corrección dejando registro de quién y por qué.
     */
    'permitir_ajuste_en_venta' => (bool) env('POS_AJUSTE_EN_VENTA', true),

];
