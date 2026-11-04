<?php

namespace App\Support\Observabilidad;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\UserDataBag;

/**
 * Última puerta antes de que un evento salga hacia Sentry.
 * ---------------------------------------------------------------------------
 * POR QUÉ ESTO ES UNA CLASE Y NO UN CLOSURE EN config/sentry.php
 *
 * Lo era, y tumbó un despliegue entero.
 *
 * `php artisan config:cache` serializa toda la configuración con `var_export`
 * y la guarda como PHP. Un closure no se puede exportar así: el archivo
 * generado queda con `\Closure::__set_state(...)` y al cargarlo revienta con
 *
 *     Your configuration files could not be serialized because the value
 *     at "sentry.before_send" is non-serializable.
 *     Call to undefined method Closure::__set_state()
 *
 * En desarrollo no se nota, porque la configuración no se cachea nunca: la
 * suite entera pasaba en verde. El entrypoint de producción sí ejecuta
 * `config:cache`, así que el contenedor moría al arrancar. Y como Render
 * mantiene viva la versión anterior cuando el despliegue falla, el servicio
 * seguía respondiendo 200 con el código de hacía cuatro días: desde fuera no
 * se veía absolutamente nada.
 *
 * Una clase invocable sí se exporta, siempre que sepa reconstruirse. De ahí
 * el `__set_state` de abajo, que es lo único que `var_export` necesita.
 */
class FiltroDeEnvio
{
    /**
     * Reconstruye la instancia al cargar la configuración cacheada.
     *
     * `var_export` escribe `\App\...\FiltroDeEnvio::__set_state(array())` y
     * PHP llama a este método al evaluar ese archivo. Sin él, el error es el
     * mismo que con el closure. No hay estado que restaurar: el filtro no
     * guarda nada entre llamadas.
     *
     * @param  array<string, mixed>  $estado
     */
    public static function __set_state(array $estado): self
    {
        return new self;
    }

    /**
     * Se ejecuta para CADA evento. Devolver `null` lo descarta entero.
     */
    public function __invoke(Event $evento, ?EventHint $pista): ?Event
    {
        /* El mensaje de una excepción es texto libre escrito por quien la
           lanzó: «No se encontró el usuario 47382910» es perfectamente
           posible. Se limpia con los mismos patrones que todo lo demás. */
        foreach ($evento->getExceptions() as $excepcion) {
            $excepcion->setValue((string) Depurador::texto($excepcion->getValue()));
        }

        if ($evento->getMessage() !== null) {
            $evento->setMessage((string) Depurador::texto($evento->getMessage()));
        }

        $peticion = $evento->getRequest();

        if ($peticion !== []) {
            $evento->setRequest(Depurador::peticion($peticion));
        }

        /* El usuario se reduce a su identificador. Sin correo, sin nombre,
           sin DNI y sin IP: con el id basta para saber si un fallo afecta a
           una cuenta o a muchas, que es la única pregunta que un panel de
           errores necesita responder sobre personas. */
        $usuario = $evento->getUser();

        if ($usuario !== null) {
            $evento->setUser(UserDataBag::createFromArray([
                'id' => $usuario->getId(),
            ]));
        }

        /* Los datos extra y las etiquetas los pone el código de la
           aplicación: se limpian uno a uno porque nada impide que alguien
           adjunte ahí el pedido entero. */
        $extra = [];

        foreach ($evento->getExtra() as $clave => $valor) {
            $extra[$clave] = is_string($valor) ? Depurador::texto($valor) : $valor;
        }

        $evento->setExtra($extra);

        foreach ($evento->getTags() as $clave => $valor) {
            $evento->setTag($clave, (string) Depurador::texto($valor));
        }

        return $evento;
    }
}
