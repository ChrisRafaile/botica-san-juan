<?php

/**
 * Aborta el arranque si el interprete PHP tiene las vulnerabilidades criticas
 * publicadas el 21 de octubre de 2026.
 *
 *     CVE-2026-109618  RCE por corrupcion durante el manejo de falta de memoria
 *     CVE-2026-109619  RCE por estado global superviviente entre peticiones
 *
 * POR QUE UNA COMPROBACION EN EL ARRANQUE Y NO UN TAG FIJO EN EL DOCKERFILE
 *
 * El Dockerfile usa `dunglas/frankenphp:1-php8.4-alpine`, un tag flotante que
 * sigue a la ultima correccion de la rama 8.4. Eso es lo que se quiere para
 * seguridad: la siguiente actualizacion llega sola al reconstruir, sin que
 * nadie tenga que acordarse de subir un numero.
 *
 * El precio de un tag flotante es que, mirando el repositorio, no se puede
 * demostrar que version se esta ejecutando. Esta comprobacion paga ese precio:
 * el contenedor se niega a arrancar sobre un interprete vulnerable y lo dice
 * con nombre y apellido. Un despliegue que pase de aqui es, por construccion,
 * un despliegue parcheado.
 *
 * Esto ya nos paso una vez al reves. En octubre la imagen quedo en PHP 8.3
 * mientras el lock exigia 8.4.1: los despliegues fallaron NUEVE DIAS sin que
 * se notara, porque Render mantenia vivo el contenedor anterior y el servicio
 * seguia respondiendo 200. La leccion fue que una discrepancia de version
 * tiene que gritar, no quedarse en un registro que nadie mira.
 *
 * POR QUE UNA TABLA POR RAMA Y NO UN UNICO MINIMO
 *
 * Comparar contra un solo numero da un falso negativo en cuanto se cambia de
 * rama: PHP 8.5.11 es MAYOR que 8.4.27 y sin embargo es vulnerable, porque su
 * correccion es la 8.5.12. Cada rama tiene su propio minimo.
 */

declare(strict_types=1);

/** Primera version de cada rama que YA incluye la correccion. */
const VERSIONES_CORREGIDAS = [
    '8.2' => '8.2.35',
    '8.3' => '8.3.36',
    '8.4' => '8.4.27',
    '8.5' => '8.5.12',
];

/** Ramas sin soporte: no reciben correcciones del proyecto PHP. */
const RAMAS_SIN_SOPORTE = ['5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1'];

// El primer argumento permite comprobar una version distinta de la que
// ejecuta el script. Existe para poder PROBAR este archivo: sin eso, la unica
// forma de verificar que detecta una version vulnerable seria instalar una,
// y la rama que nunca se ejecuta es la que acaba estando mal escrita.
$actual = $argv[1] ?? PHP_VERSION;
[$mayor, $menor] = array_pad(explode('.', $actual), 2, '0');
$rama = $mayor . '.' . $menor;

if (in_array($rama, RAMAS_SIN_SOPORTE, true)) {
    fwrite(STDERR, <<<TEXTO

    !!! ARRANQUE ABORTADO · PHP SIN SOPORTE
        Version detectada: {$actual}
        La rama {$rama} no recibe correcciones del proyecto PHP y esta
        expuesta a CVE-2026-109618 y CVE-2026-109619 (RCE sin autenticar).
        Actualiza la imagen base a una rama con soporte.

    TEXTO);
    exit(1);
}

$minima = VERSIONES_CORREGIDAS[$rama] ?? null;

if ($minima === null) {
    // Rama posterior a las conocidas por este archivo (por ejemplo 8.6). Se
    // deja pasar con aviso: bloquear una rama mas nueva que la tabla seria
    // convertir la comprobacion en un obstaculo para actualizar, que es lo
    // contrario de lo que se busca.
    fwrite(STDERR, "    ==> Aviso: PHP {$actual} es posterior a la tabla de versiones corregidas. Revisa el aviso de seguridad vigente.\n");
    exit(0);
}

if (version_compare($actual, $minima, '<')) {
    fwrite(STDERR, <<<TEXTO

    !!! ARRANQUE ABORTADO · INTERPRETE PHP VULNERABLE
        Version detectada : {$actual}
        Version minima    : {$minima}

        CVE-2026-109618 y CVE-2026-109619, CVSS v4.0 9.2, permiten ejecucion
        remota de codigo SIN autenticar. El aviso de PHP del 21/10/2026 nombra
        a FrankenPHP entre los entornos afectados.

        Solucion: reconstruir la imagen para que baje la ultima version de la
        rama. Si persiste, el tag base quedo congelado y hay que revisarlo.

    TEXTO);
    exit(1);
}

echo "==> PHP {$actual} · parcheado contra CVE-2026-109618 y CVE-2026-109619\n";
exit(0);
