import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import api from '@/services/api'

/**
 * Carrito del portal público.
 *
 * QUÉ CAMBIÓ Y POR QUÉ
 *
 * El carrito anterior vivía entero en el servidor, en la tabla `carrito`, ligado
 * a un `usuario_id`. Eso tenía dos consecuencias que nadie había medido:
 *
 *   1. **Un visitante sin cuenta no podía usarlo.** `addToCart` lanzaba
 *      "Inicia sesión para agregar productos". En una botica de barrio, pedir
 *      registro antes de dejar elegir es perder al cliente en el primer clic.
 *   2. Cada operación —añadir, cambiar cantidad, quitar— disparaba una ida y
 *      vuelta a la red, y `removeFromCart` además releía el carrito entero sólo
 *      para traducir un id de producto a un id de línea. Subir una unidad
 *      costaba dos peticiones.
 *
 * Ahora manda el navegador: en `localStorage` se guarda la lista de
 * `{ producto_id, cantidad }` y nada más. Funciona sin cuenta, sobrevive a la
 * recarga y a navegar entre páginas, y responde al instante.
 *
 * LO QUE NO SE GUARDA, Y ES DELIBERADO
 *
 * Ni el precio, ni el stock, ni el nombre, ni si necesita receta. Todo eso se
 * pide a `POST /carrito/cotizar` cada vez que hace falta mostrar el carrito:
 *
 *   - el precio de hace tres días no es el de hoy;
 *   - el stock pudo acabarse mientras la pestaña estaba abierta;
 *   - y lo que está en `localStorage` lo puede editar cualquiera desde la
 *     consola, así que un precio guardado ahí no es un dato, es una sugerencia.
 *
 * El servidor vuelve a topar la cantidad contra el stock vendible real aunque
 * aquí se haya topado ya. El tope de esta clase es comodidad; el que cuenta es
 * el del servidor.
 */

const CLAVE = 'botica:carrito'
const MAX_POR_LINEA = 99

/** Lo único que se persiste. */
export interface LineaGuardada {
  producto_id: number
  cantidad: number
}

/** Lo que devuelve el servidor por línea, con los datos frescos. */
export interface LineaCotizada {
  producto_id: number
  nombre: string
  concentracion: string | null
  presentacion: string | null
  laboratorio: string | null
  tipo: string | null
  imagen: string | null
  precio: number
  cantidad: number
  subtotal: number
  stock_disponible: number
  requiere_receta: boolean
  afectacion_igv: string
}

export interface Ajuste {
  producto_id: number
  nombre?: string
  motivo: 'sin_stock' | 'stock_insuficiente' | 'no_disponible'
  solicitada?: number
  servible?: number
  mensaje: string
}

export interface Desglose {
  subtotal_gravado: number
  subtotal_exonerado: number
  subtotal_inafecto: number
  igv: number
  tasa_igv: number
  total: number
}

const DESGLOSE_CERO: Desglose = {
  subtotal_gravado: 0,
  subtotal_exonerado: 0,
  subtotal_inafecto: 0,
  igv: 0,
  tasa_igv: 0.18,
  total: 0,
}

/**
 * Lee lo guardado, descartando cualquier cosa que no cuadre.
 *
 * Es deliberadamente desconfiado: el contenido puede venir de una versión
 * anterior del portal, de otra pestaña, o de alguien que lo editó a mano. Un
 * carrito corrupto debe quedar vacío, nunca reventar la pantalla.
 */
function leer(): LineaGuardada[] {
  try {
    const crudo = localStorage.getItem(CLAVE)
    if (!crudo) return []

    const datos = JSON.parse(crudo)
    if (!Array.isArray(datos)) return []

    return datos
      .map((l): LineaGuardada => ({
        producto_id: Number(l?.producto_id),
        cantidad: Math.min(MAX_POR_LINEA, Math.max(1, Math.trunc(Number(l?.cantidad)))),
      }))
      .filter((l) => Number.isInteger(l.producto_id) && l.producto_id > 0 && l.cantidad > 0)
  } catch {
    /* localStorage puede fallar entero en modo privado o con las cookies
       bloqueadas. El carrito entonces vive sólo en memoria, que es peor pero
       sigue funcionando durante la visita. */
    return []
  }
}

function guardar(lineas: LineaGuardada[]): void {
  try {
    localStorage.setItem(CLAVE, JSON.stringify(lineas))
  } catch {
    /* Sin sitio o sin permiso: no hay nada que hacer y no es motivo para
       interrumpir al usuario. */
  }
}

export const useCartStore = defineStore('cart', () => {
  const guardadas = ref<LineaGuardada[]>(leer())
  const lineas = ref<LineaCotizada[]>([])
  const ajustes = ref<Ajuste[]>([])
  const desglose = ref<Desglose>({ ...DESGLOSE_CERO })
  const requiereReceta = ref(false)
  const cargando = ref(false)
  const error = ref<string | null>(null)
  /** Evita pintar "tu carrito está vacío" antes de la primera cotización. */
  const cotizadoAlgunaVez = ref(false)

  /* Toda escritura pasa por aquí: así no hay forma de cambiar el carrito y
     olvidarse de persistirlo. */
  watch(guardadas, (valor) => guardar(valor), { deep: true })

  /**
   * Otra pestaña del mismo navegador tocó el carrito.
   *
   * Sin esto, tener el catálogo en una pestaña y el carrito en otra lleva a que
   * una de las dos pise lo que hizo la otra al guardar.
   */
  if (typeof window !== 'undefined') {
    window.addEventListener('storage', (evento) => {
      if (evento.key !== CLAVE) return
      guardadas.value = leer()
    })
  }

  const totalUnidades = computed(() =>
    guardadas.value.reduce((suma, l) => suma + l.cantidad, 0),
  )

  const vacio = computed(() => guardadas.value.length === 0)

  const cantidadDe = (productoId: number) =>
    guardadas.value.find((l) => l.producto_id === productoId)?.cantidad ?? 0

  const estaEnCarrito = (productoId: number) => cantidadDe(productoId) > 0

  /**
   * Pide al servidor precio, stock y avisos actuales.
   *
   * Si el servidor recortó alguna cantidad por falta de stock, el carrito local
   * se alinea con lo que el servidor dice que es servible. No tendría sentido
   * seguir guardando "quiero 10" cuando hay 3: al volver a entrar mostraría otra
   * vez un número que no se puede cumplir.
   */
  const cotizar = async () => {
    if (guardadas.value.length === 0) {
      lineas.value = []
      ajustes.value = []
      desglose.value = { ...DESGLOSE_CERO }
      requiereReceta.value = false
      cotizadoAlgunaVez.value = true
      return
    }

    cargando.value = true
    error.value = null

    try {
      const { data } = await api.post('/carrito/cotizar', { items: guardadas.value })

      lineas.value = (data.lineas ?? []).map((l: LineaCotizada) => ({
        ...l,
        /* Los importes pueden llegar como cadena según el driver de base de
           datos; ya rompió el render del catálogo una vez. */
        precio: Number(l.precio),
        subtotal: Number(l.subtotal),
      }))
      ajustes.value = data.ajustes ?? []
      requiereReceta.value = Boolean(data.receta)
      desglose.value = {
        ...DESGLOSE_CERO,
        ...data.desglose,
        subtotal_gravado: Number(data.desglose?.subtotal_gravado ?? 0),
        igv: Number(data.desglose?.igv ?? 0),
        total: Number(data.desglose?.total ?? 0),
      }

      /* Alineación con la realidad: se queda lo servible y desaparece lo que
         ya no existe. */
      guardadas.value = lineas.value
        .filter((l) => l.cantidad > 0)
        .map((l) => ({ producto_id: l.producto_id, cantidad: l.cantidad }))
    } catch {
      /* Mensaje sin detalle técnico: quien compra no puede hacer nada con un
         código de estado, y el detalle ya está en la consola del navegador. */
      error.value = 'No pudimos consultar los precios y el stock. Revisa tu conexión e inténtalo otra vez.'
    } finally {
      cargando.value = false
      cotizadoAlgunaVez.value = true
    }
  }

  /**
   * Añade unidades.
   *
   * `stockDisponible` es opcional porque quien añade desde el catálogo ya lo
   * sabe —viene en la ficha— y así se evita una consulta. Cuando no se pasa, el
   * tope lo pone el servidor al cotizar.
   */
  const agregar = (productoId: number, cantidad = 1, stockDisponible?: number) => {
    const existente = guardadas.value.find((l) => l.producto_id === productoId)
    const techo = Math.min(MAX_POR_LINEA, stockDisponible ?? MAX_POR_LINEA)

    if (techo <= 0) return 0

    const nueva = Math.min(techo, (existente?.cantidad ?? 0) + Math.max(1, cantidad))

    if (existente) {
      existente.cantidad = nueva
    } else {
      guardadas.value = [...guardadas.value, { producto_id: productoId, cantidad: nueva }]
    }

    return nueva
  }

  /** Fija la cantidad exacta. Con 0 o menos, quita la línea. */
  const fijarCantidad = (productoId: number, cantidad: number, stockDisponible?: number) => {
    const techo = Math.min(MAX_POR_LINEA, stockDisponible ?? MAX_POR_LINEA)
    const valor = Math.min(techo, Math.trunc(cantidad))

    if (!Number.isFinite(valor) || valor <= 0) {
      quitar(productoId)
      return 0
    }

    const existente = guardadas.value.find((l) => l.producto_id === productoId)
    if (existente) {
      existente.cantidad = valor
    } else {
      guardadas.value = [...guardadas.value, { producto_id: productoId, cantidad: valor }]
    }

    /* La línea cotizada se actualiza en el acto para que el desglose no quede
       un paso por detrás mientras llega la respuesta. */
    const cotizada = lineas.value.find((l) => l.producto_id === productoId)
    if (cotizada) {
      cotizada.cantidad = valor
      cotizada.subtotal = Number((cotizada.precio * valor).toFixed(2))
    }

    return valor
  }

  const quitar = (productoId: number) => {
    guardadas.value = guardadas.value.filter((l) => l.producto_id !== productoId)
    lineas.value = lineas.value.filter((l) => l.producto_id !== productoId)
  }

  const vaciar = () => {
    guardadas.value = []
    lineas.value = []
    ajustes.value = []
    desglose.value = { ...DESGLOSE_CERO }
    requiereReceta.value = false
  }

  /** Descarta los avisos ya leídos sin volver a pedir nada al servidor. */
  const descartarAjustes = () => {
    ajustes.value = []
  }

  /**
   * Confirma el pedido contra la API.
   *
   * Sirve a los dos caminos con la misma llamada: si hay sesión, el
   * interceptor de `api` manda el token y el servidor liga el pedido a esa
   * cuenta; si no, van los datos del checkout rápido. El servidor decide, no
   * esta función.
   *
   * No se le pasa el precio: lo vuelve a calcular el servidor sobre el
   * catálogo, igual que al cotizar.
   */
  const confirmar = async (datosInvitado?: {
    cliente_nombre: string
    cliente_documento: string
    cliente_telefono: string
  }) => {
    const { data } = await api.post('/pedidos/confirmar', {
      items: guardadas.value,
      ...(datosInvitado ?? {}),
    })

    /* El carrito se vacía SÓLO si el servidor confirmó. Vaciarlo antes, o a la
       vez que se envía, pierde el pedido del cliente cuando la respuesta trae
       un 422 por falta de stock. */
    vaciar()

    return data as {
      pedido: { id: number; total: number }
      comprobante: { tipo: string; identificador: string; estado_sunat: string }
      seguimiento: { pedido_id: number; invitado: boolean }
    }
  }

  return {
    guardadas,
    lineas,
    ajustes,
    desglose,
    requiereReceta,
    cargando,
    error,
    cotizadoAlgunaVez,
    totalUnidades,
    vacio,
    cantidadDe,
    estaEnCarrito,
    cotizar,
    agregar,
    fijarCantidad,
    quitar,
    vaciar,
    descartarAjustes,
    confirmar,
  }
})
