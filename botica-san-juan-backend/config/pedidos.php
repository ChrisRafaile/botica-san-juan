<?php

return [
    /*
     * Tiempo de vida de la reserva de stock (en minutos) para pedidos originados
     * en el portal web con cliente autenticado y método de pago con tarjeta.
     */
    'ttl_reserva_minutos' => (int) env('PEDIDOS_TTL_RESERVA_MINUTOS', 15),
];
