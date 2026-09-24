/**
 * useConteo · Conteo físico por ciclos
 * ---------------------------------------------------------------------------
 * Separa el estado de la sesión de conteo de su presentación.
 *
 * SOBRE EL ORDEN DE LAS COSAS
 * Contar no modifica el inventario. Lo que se teclea aquí queda anotado en la
 * sesión, y sólo al cerrarla el servidor aplica los ajustes. Esa separación no
 * es un capricho: entre que se cuenta el anaquel y que se corrige el sistema
 * sigue habiendo ventas, y sin la foto del stock tomada al abrir, una venta de
 * media mañana aparecería como un descuadre del conteo.
 */

import { computed, ref } from 'vue'
import api from '@/services/api'

export type CriterioConteo = 'rotacion' | 'sin_lote' | 'vencimiento' | 'categoria' | 'manual'

export interface LoteContado {
  codigo_lote: string
  fecha_vencimiento: string | null
  cantidad: number
}

export interface LineaConteo {
  id: number
  producto_id: number
  nombre: string
  concentracion: string | null
  presentacion: string | null
  laboratorio: string | null
  codigo_barras: string | null
  stock_sistema: number
  cantidad_contada: number | null
  diferencia: number | null
  lotes_contados: LoteContado[]
  observacion: string | null
  contado_en: string | null
  diferencia_aplicada: number | null
  /** Lo que el sistema cree tener hoy, por lote. Es la referencia visible. */
  lotes_sistema: LoteContado[]
}

export interface SesionConteo {
  id: number
  codigo: string
  estado: 'abierto' | 'cerrado' | 'anulado'
  criterio: CriterioConteo
  ambito: string | null
  abierto_en: string | null
  cerrado_en: string | null
  abierto_por: string | null
  total: number
  contados: number
  lineas: LineaConteo[]
}

export interface ResumenCierre {
  ajustados: number
  sin_diferencia: number
  no_contados: number
  unidades_netas: number
  lotes_capturados: number
}

export const CRITERIOS: { valor: CriterioConteo; etiqueta: string; explicacion: string }[] = [
  {
    valor: 'sin_lote',
    etiqueta: 'Sin fecha de vencimiento',
    explicacion: 'Productos cuyo stock no tiene lote fechado. Es lo que más le falta al sistema para avisar de vencimientos.',
  },
  {
    valor: 'rotacion',
    etiqueta: 'Los que más se venden',
    explicacion: 'Donde antes se descuadra el stock, porque es donde más movimiento hay.',
  },
  {
    valor: 'vencimiento',
    etiqueta: 'Próximos a vencer',
    explicacion: 'Para verificarlos mientras todavía se pueden devolver al proveedor.',
  },
]

export function useConteo() {
  const sesion = ref<SesionConteo | null>(null)
  const cargando = ref(false)
  const guardando = ref(false)
  const cerrando = ref(false)

  const haySesion = computed(() => sesion.value !== null && sesion.value.estado === 'abierto')

  const pendientes = computed(
    () => sesion.value?.lineas.filter((l) => l.cantidad_contada === null) ?? [],
  )

  const contadas = computed(
    () => sesion.value?.lineas.filter((l) => l.cantidad_contada !== null) ?? [],
  )

  const conDiferencia = computed(
    () => contadas.value.filter((l) => (l.diferencia ?? 0) !== 0),
  )

  /** Cuántas líneas capturaron al menos un lote con fecha. */
  const conFechaCapturada = computed(
    () => contadas.value.filter(
      (l) => l.lotes_contados.some((lote) => lote.fecha_vencimiento),
    ).length,
  )

  const progreso = computed(() => {
    const total = sesion.value?.lineas.length ?? 0
    return total === 0 ? 0 : Math.round((contadas.value.length / total) * 100)
  })

  /* ------------------------------------------------------------------------
     Carga
     ------------------------------------------------------------------------ */

  async function cargarAbierto(): Promise<void> {
    cargando.value = true

    try {
      const { data } = await api.get<{ data: SesionConteo | null }>('/conteos/abierto')
      sesion.value = data.data
    } finally {
      cargando.value = false
    }
  }

  async function abrir(
    criterio: CriterioConteo,
    cantidad = 25,
    ambito: string | null = null,
  ): Promise<void> {
    cargando.value = true

    try {
      const { data } = await api.post<{ data: SesionConteo }>('/conteos', {
        criterio,
        cantidad,
        ambito,
      })
      sesion.value = data.data
    } finally {
      cargando.value = false
    }
  }

  /* ------------------------------------------------------------------------
     Registrar
     ------------------------------------------------------------------------ */

  /**
   * Anota lo contado de un producto.
   *
   * La respuesta del servidor reemplaza la línea entera en lugar de mutar sólo
   * la cantidad: así la diferencia y la marca de tiempo vienen calculadas por
   * quien manda, y la pantalla no se queda con una versión propia que podría no
   * coincidir con lo que quedó guardado.
   */
  async function registrar(
    linea: LineaConteo,
    cantidad: number,
    lotes: LoteContado[] = [],
    observacion: string | null = null,
  ): Promise<void> {
    if (!sesion.value) return

    guardando.value = true

    try {
      const { data } = await api.put<{ data: LineaConteo }>(
        `/conteos/${sesion.value.id}/detalles/${linea.id}`,
        {
          cantidad_contada: cantidad,
          lotes: lotes.filter((l) => l.codigo_lote.trim() && l.cantidad > 0),
          observacion,
        },
      )

      const indice = sesion.value.lineas.findIndex((l) => l.id === linea.id)
      if (indice !== -1) sesion.value.lineas[indice] = data.data
    } finally {
      guardando.value = false
    }
  }

  /* ------------------------------------------------------------------------
     Cierre
     ------------------------------------------------------------------------ */

  async function cerrar(): Promise<ResumenCierre> {
    if (!sesion.value) throw new Error('No hay conteo abierto.')

    cerrando.value = true

    try {
      const { data } = await api.post<{ resumen: ResumenCierre; data: SesionConteo }>(
        `/conteos/${sesion.value.id}/cerrar`,
      )
      sesion.value = null
      return data.resumen
    } finally {
      cerrando.value = false
    }
  }

  async function anular(): Promise<void> {
    if (!sesion.value) return

    await api.post(`/conteos/${sesion.value.id}/anular`)
    sesion.value = null
  }

  return {
    sesion,
    cargando,
    guardando,
    cerrando,
    haySesion,
    pendientes,
    contadas,
    conDiferencia,
    conFechaCapturada,
    progreso,
    cargarAbierto,
    abrir,
    registrar,
    cerrar,
    anular,
  }
}
