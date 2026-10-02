import api from './api'
import type { Product } from './products'

/**
 * Carrito del portal.
 *
 * FALLO CORREGIDO: APUNTABA A UN SISTEMA QUE YA NO EXISTE
 *
 * Este servicio llamaba a `view_cart.php`, `add_to_cart.php` y
 * `eliminar_del_carrito.php`, scripts del sistema PHP anterior, enviando
 * FormData. La API de Laravel nunca los ha servido: cada operacion respondia
 * 404 y el carrito quedaba siempre vacio, sin que la interfaz lo dijera.
 *
 * La API real es /carrito, que es un recurso REST ligado al usuario:
 *   GET    /carrito/usuario/{id}   lo que ese usuario tiene en el carrito
 *   POST   /carrito                { usuario_id, producto_id, cantidad }
 *   PUT    /carrito/{id}           cambiar la cantidad de una linea
 *   DELETE /carrito/{id}           quitar una linea
 *
 * OJO CON LA CLAVE: el servidor identifica cada linea por su PROPIO id, no
 * por el del producto. El codigo anterior mandaba el id del producto, asi que
 * aunque los endpoints hubieran existido habria borrado la linea equivocada.
 */

export interface CartItem {
  /** Identificador de la LINEA del carrito, no del producto. */
  id: number
  usuario_id: number
  producto_id: number
  cantidad: number
  producto?: Product
}

/** El carrito vive bajo el usuario: sin sesion no hay carrito que pedir. */
function usuarioActual(): number | null {
  try {
    /* La clave es 'user': la escribe services/auth.ts al iniciar sesion. */
    const crudo = localStorage.getItem('user')
    if (!crudo) return null
    const id = JSON.parse(crudo)?.id
    return typeof id === 'number' ? id : null
  } catch {
    return null
  }
}

function normalizar(linea: CartItem): CartItem {
  return {
    ...linea,
    cantidad: Number(linea.cantidad ?? 0),
    producto: linea.producto
      ? { ...linea.producto, precio: Number(linea.producto.precio ?? 0) }
      : undefined,
  }
}

class CartService {
  /**
   * Lineas del carrito del usuario conectado.
   *
   * Sin sesion devuelve lista vacia en vez de lanzar: un visitante mirando el
   * catalogo no tiene por que ver un error solo porque aun no ha entrado.
   */
  async getCart(): Promise<CartItem[]> {
    const usuarioId = usuarioActual()
    if (!usuarioId) return []

    const respuesta = await api.get(`/carrito/usuario/${usuarioId}`)
    const cuerpo = respuesta.data as CartItem[] | { data: CartItem[] }
    const lineas = Array.isArray(cuerpo) ? cuerpo : (cuerpo?.data ?? [])
    return lineas.map(normalizar)
  }

  async addToCart(productoId: number, cantidad = 1): Promise<void> {
    const usuarioId = usuarioActual()
    if (!usuarioId) throw new Error('Inicia sesión para agregar productos al carrito')

    /* El servidor ya suma la cantidad si la linea existe, asi que no hace
       falta mirar antes si el producto estaba: se evita una condicion de
       carrera entre dos pestanas del mismo usuario. */
    await api.post('/carrito', {
      usuario_id: usuarioId,
      producto_id: productoId,
      cantidad,
    })
  }

  /** Quita una linea. Recibe el id del PRODUCTO y resuelve la linea. */
  async removeFromCart(productoId: number): Promise<void> {
    const linea = (await this.getCart()).find(l => l.producto_id === productoId)
    if (!linea) return
    await api.delete(`/carrito/${linea.id}`)
  }

  async updateCartItem(productoId: number, cantidad: number): Promise<void> {
    if (cantidad <= 0) {
      await this.removeFromCart(productoId)
      return
    }

    const linea = (await this.getCart()).find(l => l.producto_id === productoId)
    if (!linea) {
      await this.addToCart(productoId, cantidad)
      return
    }

    await api.put(`/carrito/${linea.id}`, {
      usuario_id: linea.usuario_id,
      producto_id: productoId,
      cantidad,
    })
  }

  /** Vacia el carrito linea a linea: la API no ofrece un borrado masivo. */
  async clearCart(): Promise<void> {
    const lineas = await this.getCart()
    await Promise.all(lineas.map(l => api.delete(`/carrito/${l.id}`)))
  }

  async getCartTotal(): Promise<number> {
    const lineas = await this.getCart()
    return lineas.reduce((suma, l) => suma + (l.producto?.precio ?? 0) * l.cantidad, 0)
  }

  async getCartItemCount(): Promise<number> {
    const lineas = await this.getCart()
    return lineas.reduce((suma, l) => suma + l.cantidad, 0)
  }
}

export default new CartService()
