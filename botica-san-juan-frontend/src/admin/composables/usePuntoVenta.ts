/**
 * usePuntoVenta · Estado y operaciones del carrito de mostrador
 * ---------------------------------------------------------------------------
 * Separa la lógica de la venta de su presentación, por dos razones prácticas:
 * se puede probar sin montar la pantalla, y la pantalla queda libre para
 * ocuparse solo de lo que el vendedor ve y teclea.
 *
 * SOBRE LOS TOTALES
 * Los que se calculan aquí son para MOSTRAR mientras se arma la venta. El
 * importe que vale es el que devuelve el servidor al registrarla: el precio y
 * el stock pueden haber cambiado entre que se agregó la línea y se cobró. El
 * cliente nunca es la fuente de verdad del dinero.
 *
 * SOBRE LOS PRECIOS
 * Los precios de la base ya incluyen IGV: son el importe final que paga el
 * cliente. El impuesto se extrae hacia atrás (base = precio / 1.18); nunca se
 * suma encima.
 */

import { computed, ref } from 'vue'
import api from '@/services/api'

/* --------------------------------------------------------------------------
   Tipos que devuelve la API del POS
   -------------------------------------------------------------------------- */

export interface FormaVenta {
  unidad_venta: 'unidad' | 'blister' | 'caja'
  factor_unidades: number
  precio: number
  maximo_vendible: number
  disponible: boolean
}

export interface ProductoPos {
  id: number
  nombre: string
  concentracion: string | null
  presentacion: string | null
  laboratorio: string | null
  principio_activo: string | null
  codigo_barras: string | null
  requiere_receta: boolean
  afecto_igv: boolean
  /**
   * Catálogo 07 de la SUNAT: 10 gravado, 20 exonerado, 30 inafecto.
   *
   * Es del PRODUCTO, no de la presentación. Vender en caja o suelto cambia la
   * cantidad y el precio; no cambia si la operación paga IGV. Por eso este
   * campo vive aquí y no dentro de `formas_venta`.
   */
  tipo_afectacion_igv: '10' | '20' | '30'
  afectacion: string
  stock_contable: number
  stock_disponible: number
  venta_fraccionada: boolean
  formas_venta: FormaVenta[]
}

/** Una línea del carrito, ya resuelta a precio y unidades. */
export interface LineaVenta {
  /** Identificador local de la línea; permite repetir el mismo producto
      en dos presentaciones distintas sin que se pisen. */
  clave: string
  producto: ProductoPos
  forma: FormaVenta
  cantidad: number
  /** Resultado de la última verificación contra el servidor. */
  disponibilidad?: {
    faltante: number
    cantidad_atendible: number
    lotes: { codigo_lote: string; fecha_vencimiento: string | null; cantidad: number }[]
  }
}

/* --------------------------------------------------------------------------
   Cobro
   -------------------------------------------------------------------------- */

export type MedioPago = 'efectivo' | 'tarjeta' | 'yape' | 'plin' | 'transferencia'

export interface LineaPago {
  medio: MedioPago
  /** Importe que cubre este medio. Con un solo medio lo deduce el servidor. */
  monto?: number
  /** Sólo efectivo: lo que puso el cliente sobre el mostrador. */
  monto_recibido?: number
  /** Últimos 4 de la tarjeta, código de operación de Yape/Plin. */
  referencia?: string
}

export const MEDIOS_PAGO: { valor: MedioPago; etiqueta: string; atajo: string }[] = [
  { valor: 'efectivo', etiqueta: 'Efectivo', atajo: '1' },
  { valor: 'tarjeta', etiqueta: 'Tarjeta', atajo: '2' },
  { valor: 'yape', etiqueta: 'Yape', atajo: '3' },
  { valor: 'plin', etiqueta: 'Plin', atajo: '4' },
  { valor: 'transferencia', etiqueta: 'Transferencia', atajo: '5' },
]

/** De estos medios sí se da vuelto: sólo del efectivo. */
export function daVuelto(medio: MedioPago): boolean {
  return medio === 'efectivo'
}

const TASA_IGV = 0.18

export function usePuntoVenta() {
  const lineas = ref<LineaVenta[]>([])
  const buscando = ref(false)
  const resultados = ref<ProductoPos[]>([])
  const registrando = ref(false)

  const cliente = ref({
    nombre: '',
    documento: '',
    tipo_documento: 'sin_documento' as 'dni' | 'ruc' | 'ce' | 'sin_documento',
  })

  let peticionBusqueda: AbortController | null = null

  /* ------------------------------------------------------------------------
     Búsqueda
     ------------------------------------------------------------------------ */

  /**
   * Busca productos. Cancela la petición anterior si aún está en vuelo: sin
   * esto, escribir rápido deja varias respuestas compitiendo y la lista puede
   * terminar mostrando el resultado de una búsqueda ya obsoleta.
   */
  async function buscar(termino: string): Promise<void> {
    peticionBusqueda?.abort()

    if (termino.trim().length < 2) {
      resultados.value = []
      buscando.value = false
      return
    }

    peticionBusqueda = new AbortController()
    buscando.value = true

    try {
      const { data } = await api.get<{ data: ProductoPos[] }>('/pos/productos', {
        params: { q: termino.trim(), limit: 20 },
        signal: peticionBusqueda.signal,
      })
      resultados.value = data.data
    } catch (error: unknown) {
      /* Una petición cancelada no es un fallo: es lo que se buscaba. */
      const cancelada = (error as { code?: string })?.code === 'ERR_CANCELED'
      if (!cancelada) {
        resultados.value = []
        throw error
      }
    } finally {
      buscando.value = false
    }
  }

  function limpiarBusqueda(): void {
    resultados.value = []
  }

  /* ------------------------------------------------------------------------
     Carrito
     ------------------------------------------------------------------------ */

  function claveDe(productoId: number, unidad: string): string {
    return `${productoId}:${unidad}`
  }

  /**
   * Agrega una línea o incrementa la existente.
   *
   * Si el producto ya está en el carrito con la MISMA presentación, suma a esa
   * línea en lugar de duplicarla. Con presentación distinta crea una nueva:
   * dos cajas y tres unidades sueltas del mismo medicamento son dos conceptos
   * con precios diferentes.
   */
  function agregar(producto: ProductoPos, forma: FormaVenta, cantidad = 1): void {
    const clave = claveDe(producto.id, forma.unidad_venta)
    const existente = lineas.value.find((l) => l.clave === clave)

    if (existente) {
      existente.cantidad += cantidad
      return
    }

    lineas.value.push({ clave, producto, forma, cantidad })
  }

  function cambiarCantidad(clave: string, cantidad: number): void {
    const linea = lineas.value.find((l) => l.clave === clave)
    if (!linea) return

    if (cantidad <= 0) {
      quitar(clave)
      return
    }

    linea.cantidad = cantidad
  }

  function quitar(clave: string): void {
    lineas.value = lineas.value.filter((l) => l.clave !== clave)
  }

  function vaciar(): void {
    lineas.value = []
    cliente.value = { nombre: '', documento: '', tipo_documento: 'sin_documento' }
  }

  /* ------------------------------------------------------------------------
     Totales (solo para mostrar)
     ------------------------------------------------------------------------ */

  const totales = computed(() => {
    let gravadoConIgv = 0
    let exonerado = 0
    let inafecto = 0

    for (const linea of lineas.value) {
      /* El importe depende de la presentación; el tratamiento tributario, no.
         La afectación se lee del PRODUCTO aunque se esté vendiendo en caja. */
      const importe = linea.forma.precio * linea.cantidad

      switch (linea.producto.tipo_afectacion_igv) {
        case '20':
          exonerado += importe
          break
        case '30':
          inafecto += importe
          break
        default:
          gravadoConIgv += importe
      }
    }

    /* Los precios YA incluyen IGV: se extrae dividiendo, no sumando. Lo
       exonerado y lo inafecto no llevan impuesto que extraer. */
    const base = gravadoConIgv / (1 + TASA_IGV)
    const igv = gravadoConIgv - base

    return {
      base: redondear(base),
      igv: redondear(igv),
      exonerado: redondear(exonerado),
      inafecto: redondear(inafecto),
      total: redondear(gravadoConIgv + exonerado + inafecto),
      unidades: lineas.value.reduce((n, l) => n + l.cantidad * l.forma.factor_unidades, 0),
      lineas: lineas.value.length,
      /* Para avisar en pantalla sólo cuando hay algo que avisar. */
      hayExonerados: exonerado > 0 || inafecto > 0,
    }
  })

  function redondear(valor: number): number {
    return Math.round(valor * 100) / 100
  }

  /* ------------------------------------------------------------------------
     Verificación y registro
     ------------------------------------------------------------------------ */

  const itemsParaApi = computed(() =>
    lineas.value.map((l) => ({
      producto_id: l.producto.id,
      unidad_venta: l.forma.unidad_venta,
      cantidad: l.cantidad,
    })),
  )

  /**
   * Contrasta el carrito con el stock real y anota el resultado en cada línea.
   * Se llama antes de cobrar, para poder avisar de faltantes a tiempo.
   */
  async function verificar(): Promise<{ hayFaltantes: boolean }> {
    if (lineas.value.length === 0) return { hayFaltantes: false }

    const { data } = await api.post<{
      data: Array<{
        producto_id: number
        faltante: number
        cantidad_atendible: number
        lotes: { codigo_lote: string; fecha_vencimiento: string | null; cantidad: number }[]
      }>
      hay_faltantes: boolean
    }>('/pos/verificar', { items: itemsParaApi.value })

    /* El servidor responde en el mismo orden en que se enviaron los items. */
    data.data.forEach((resultado, indice) => {
      const linea = lineas.value[indice]
      if (!linea) return

      linea.disponibilidad = {
        faltante: resultado.faltante,
        cantidad_atendible: resultado.cantidad_atendible,
        lotes: resultado.lotes ?? [],
      }
    })

    return { hayFaltantes: data.hay_faltantes }
  }

  /**
   * Registra la venta.
   *
   * `confirmarParcial` viaja al servidor: si hay faltantes y no se confirma,
   * la API responde 409 y no registra nada. Es la salvaguarda de la regla
   * acordada — nunca se cobra una entrega parcial sin que alguien la acepte.
   */
  async function registrar(confirmarParcial = false, pagos: LineaPago[] = []) {
    if (lineas.value.length === 0) {
      throw new Error('No hay productos en la venta.')
    }

    registrando.value = true

    try {
      const { data } = await api.post('/pos/ventas', {
        items: itemsParaApi.value,
        cliente_nombre: cliente.value.nombre || null,
        cliente_documento: cliente.value.documento || null,
        cliente_tipo_documento: cliente.value.tipo_documento,
        confirmar_parcial: confirmarParcial,
        /* Sin medios declarados el servidor asume efectivo exacto: no se
           envía un arreglo vacío disfrazado de decisión. */
        ...(pagos.length > 0 ? { pagos } : {}),
      })

      return data.data
    } finally {
      registrando.value = false
    }
  }

  return {
    lineas,
    resultados,
    buscando,
    registrando,
    cliente,
    totales,
    buscar,
    limpiarBusqueda,
    agregar,
    cambiarCantidad,
    quitar,
    vaciar,
    verificar,
    registrar,
  }
}
