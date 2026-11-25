<?php

namespace App\Services\Correo;

use RuntimeException;

/**
 * Sustituye a la excepción del proveedor para que la cola reintente.
 *
 * No encadena la original a propósito: `failed_jobs.exception` la guardaría
 * entera, y los mensajes de Resend suelen llevar la dirección del destinatario.
 */
class CorreoNoEnviado extends RuntimeException {}
