/**
 * usePedidosPendientes · Botica San Juan
 * ---------------------------------------------------------------------------
 * Pedidos del cliente que siguen esperando pago, compartidos entre pantallas.
 *
 * POR QUÉ EXISTE
 *
 * Confirmar el carrito registra el pedido —con su stock descontado y su
 * comprobante emitido— y vacía el carrito. A partir de ahí el pedido solo
 * vivía en la URL de `/checkout/pago?pedido=…`: quien pulsaba «volver al
 * carrito» encontraba un carrito vacío y ninguna pista de que tenía un pago
 * a medias. Agregaba otro producto, confirmaba, y salía con dos pedidos
 * creyendo haber editado uno.
 *
 * Un pedido registrado no se edita: no hay anulación de venta en el sistema
 * que devuelva el stock a su lote y dé de baja el comprobante. Lo que sí se
 * puede hacer es no esconderlo. Este estado es lo que permite que el carrito
 * y el catálogo sigan enseñando el pago pendiente y cómo retomarlo.
 *
 * El estado vive a nivel de módulo, como el del tema, para que al navegar
 * del pago al carrito el aviso se pinte en el primer fotograma en vez de
 * aparecer después y empujar la página.
 */

import { computed, ref } from 'vue'
import {
  cargarPedidosCliente,
  pedidoRequierePago,
  type PedidoCliente,
} from '@/services/pedidosCliente'

const pendientes = ref<PedidoCliente[]>([])
let usuarioCargado: number | null = null
let consultaEnCurso: Promise<void> | null = null

export function usePedidosPendientes() {
  /** Deja el estado como si nadie hubiera iniciado sesión. */
  function olvidar(): void {
    pendientes.value = []
    usuarioCargado = null
  }

  /**
   * Fija la lista con datos que otra pantalla ya pidió, para no repetir la
   * consulta. Recibe el historial completo y se queda con lo impago.
   */
  function sembrar(usuarioId: number, pedidos: PedidoCliente[]): void {
    usuarioCargado = usuarioId
    pendientes.value = pedidos.filter(pedidoRequierePago)
  }

  async function refrescar(usuarioId: number | null | undefined): Promise<void> {
    if (!usuarioId) {
      olvidar()
      return
    }

    /* Otra cuenta en el mismo navegador: lo que hubiera en memoria es de
       otra persona y no debe verse ni un instante. */
    if (usuarioCargado !== usuarioId) {
      pendientes.value = []
      usuarioCargado = usuarioId
    }

    consultaEnCurso ??= cargarPedidosCliente(usuarioId)
      .then((pedidos) => {
        if (usuarioCargado === usuarioId) {
          pendientes.value = pedidos.filter(pedidoRequierePago)
        }
      })
      .catch(() => {
        /* Sin red se conserva lo último conocido: un aviso de pago pendiente
           que desaparece por un fallo de conexión es peor que uno viejo. */
      })
      .finally(() => {
        consultaEnCurso = null
      })

    return consultaEnCurso
  }

  return {
    pendientes: computed(() => pendientes.value),
    refrescar,
    sembrar,
    olvidar,
  }
}
