<?php

namespace App\Policies;

use App\Models\ComprobanteElectronico;
use App\Models\Usuario;

/**
 * Quien puede ver un comprobante.
 * ---------------------------------------------------------------------------
 * POR QUE EXISTE
 *
 * Las rutas `facturacion/documentos/{id}/xml` y `/pdf` vivian dentro del grupo
 * autenticado pero SIN `middleware('admin')`, y los controladores no
 * comprobaban nada: hacian `findOrFail($id)` y devolvian el documento. Es
 * decir, cualquier cuenta con sesion iniciada podia recorrer identificadores y
 * descargarse los comprobantes de los demas, con el nombre del cliente, su
 * DNI o RUC, los productos que compro y el importe.
 *
 * Es el mismo defecto que ya se corrigio una vez en el historial de pedidos
 * (D-05 del APF2). Reaparecio en otro endpoint porque la regla vivia escrita a
 * mano dentro de un controlador en lugar de en un sitio que se pueda aplicar a
 * todos. De ahi esta politica.
 *
 * LA PROPIEDAD SE DECIDE POR LA CUENTA, NO POR EL DOCUMENTO DE IDENTIDAD
 *
 * Un pedido de invitado guarda `cliente_documento` pero no `usuario_id`.
 * Seria comodo dejar que un cliente registrado viera los comprobantes cuyo
 * documento coincide con el suyo, y seria un error: el DNI de otra persona se
 * consigue con facilidad, y bastaria con ponerlo en el perfil para heredar sus
 * compras. La propiedad se mide contra la cuenta que hizo el pedido y por nada
 * mas. Un comprobante de invitado no tiene duenyo en el portal; se recoge en
 * el mostrador, que es donde se emitio.
 */
class ComprobanteElectronicoPolicy
{
    /**
     * El administrador atiende el mostrador y emite los comprobantes: los ve
     * todos por definicion del puesto, no por privilegio.
     */
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, ComprobanteElectronico $comprobante): bool
    {
        if ($this->esGestor($usuario)) {
            return true;
        }

        $pedido = $comprobante->pedido;

        if ($pedido === null || $pedido->usuario_id === null) {
            return false;
        }

        return (int) $pedido->usuario_id === (int) $usuario->id;
    }

    /**
     * Emitir, reenviar a SUNAT o anular no es cosa del cliente en ningun caso.
     */
    public function gestionar(Usuario $usuario): bool
    {
        return $this->esGestor($usuario);
    }

    private function esGestor(Usuario $usuario): bool
    {
        return $usuario->rol === 'administrador';
    }
}
