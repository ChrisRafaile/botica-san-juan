<?php

namespace App\Services\Pagos;

/**
 * Contrato de la pasarela de pagos.
 *
 * El sistema depende de esta interfaz y no de Izipay. Sustituir de proveedor
 * consiste en escribir otra implementacion y cambiar el enlace en el
 * contenedor de servicios; ni el controlador ni el servicio de dominio se
 * enteran.
 */
interface PasarelaPago
{
    /**
     * Prepara la operacion de cobro y devuelve lo que el navegador necesita
     * para desplegar el formulario del proveedor.
     *
     * @return array{ok:bool, form_token:?string, public_key:?string, referencia:string, mensaje:?string}
     */
    public function crearOperacion(string $referencia, float $monto, string $moneda, string $correoCliente): array;

    /**
     * Verifica la firma de un mensaje del proveedor.
     *
     * @param  string  $contexto  'navegador' usa la clave HMAC-SHA-256;
     *                            'ipn' usa la contrasena de la tienda.
     */
    public function verificarFirma(string $respuesta, string $firma, string $contexto): bool;

    /**
     * El proveedor declara con que algoritmo firmo el mensaje. Aceptar solo
     * los algoritmos previstos evita que una implementacion futura acepte por
     * descuido un mecanismo mas debil que el proveedor introduzca.
     */
    public function algoritmoAceptado(?string $algoritmo): bool;

    /**
     * El proveedor declara tambien con cual de sus dos claves firmo. Que la
     * clave declarada corresponda al canal por el que llego el mensaje impide
     * que una respuesta firmada con la clave del navegador -que viaja al
     * cliente y por tanto no es secreta frente a el- se acepte en el canal
     * servidor a servidor, que es el unico que mueve el estado.
     *
     * @param  string  $contexto  'ipn' o 'navegador'
     */
    public function claveDeclaradaCoincide(?string $claveDeclarada, string $contexto): bool;

    /**
     * Reconsulta el estado real de la operacion contra el proveedor.
     *
     * Es la fuente de verdad: el cuerpo de una notificacion nunca decide por
     * si solo si un pedido esta pagado.
     *
     * @return array{ok:bool, estado_proveedor:?string, estado_detallado:?string, pago_id:?string, metodo:?string, marca:?string, ultimos4:?string, monto:?float, mensaje:?string}
     */
    public function consultarOperacion(string $referencia): array;

    public function llavePublica(): ?string;

    public function modo(): string;
}
