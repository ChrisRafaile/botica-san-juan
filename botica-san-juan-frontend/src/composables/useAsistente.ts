import { onUnmounted, ref } from 'vue'
import api from '@/services/api'

export interface MensajeAsistente {
  id: number
  role: 'user' | 'assistant'
  content: string
}

export interface FilaImportacionPrevia {
  fila: number
  producto: string
  concentracion: string
  laboratorio: string
  lote: string
  fecha_vencimiento: string
  stock: number
  precio_venta: number
  accion: 'crear_producto_y_lote' | 'agregar_lote' | 'actualizar_lote'
}

export interface PrevisualizacionImportacion {
  formato: 'xlsx' | 'csv'
  token_confirmacion: string | null
  expira_en_minutos: number | null
  resumen: {
    total_filas: number
    validas: number
    con_errores: number
    duplicados: number
    advertencias: number
  }
  filas_validas: FilaImportacionPrevia[]
  duplicados: Array<{ fila: number; tipo: string; producto: string; lote: string; mensaje: string }>
  advertencias: Array<{ fila: number; mensaje: string }>
  errores: Array<{ fila: number; mensaje: string }>
}

export interface ResultadoImportacion {
  filas_procesadas: number
  productos_creados: number
  productos_actualizados: number
  lotes_creados: number
  lotes_actualizados: number
  unidades_ingresadas: number
  evento_id: number
}

export function useAsistente() {
  const habilitado = ref<boolean | null>(null)
  const cargando = ref(false)
  const importando = ref(false)
  const error = ref<string | null>(null)
  const mensajes = ref<MensajeAsistente[]>([])
  const previsualizacion = ref<PrevisualizacionImportacion | null>(null)
  const nombreArchivoImportacion = ref<string | null>(null)

  let controladorActual: AbortController | null = null
  let secuencia = 1

  function cancelar() {
    if (controladorActual) {
      controladorActual.abort()
      controladorActual = null
    }
  }

  async function verificarEstado(): Promise<void> {
    cancelar()
    controladorActual = new AbortController()
    try {
      const { data } = await api.get<{ habilitado: boolean }>('/asistente/estado', {
        signal: controladorActual.signal,
      })
      habilitado.value = Boolean(data.habilitado)
    } catch (err: unknown) {
      if ((err as { name?: string })?.name === 'CanceledError') return
      habilitado.value = false
    } finally {
      controladorActual = null
    }
  }

  async function enviar(texto: string): Promise<void> {
    const limpio = texto.trim()
    if (limpio.length < 2 || cargando.value || habilitado.value === false) return

    error.value = null
    const historial = mensajes.value.slice(-10).map((m) => ({
      role: m.role,
      content: m.content,
    }))

    mensajes.value.push({
      id: secuencia++,
      role: 'user',
      content: limpio,
    })

    cancelar()
    controladorActual = new AbortController()
    cargando.value = true

    try {
      const { data } = await api.post<{ respuesta: string }>(
        '/asistente/mensajes',
        { mensaje: limpio, historial },
        { signal: controladorActual.signal },
      )
      mensajes.value.push({
        id: secuencia++,
        role: 'assistant',
        content: data.respuesta,
      })
    } catch (err: unknown) {
      if ((err as { name?: string })?.name === 'CanceledError') return
      const resp = (err as { response?: { status?: number; data?: { message?: string } } })?.response
      if (resp?.status === 503) {
        habilitado.value = false
      }
      error.value =
        resp?.data?.message ??
        'No se pudo obtener respuesta del asistente. Inténtalo de nuevo.'
    } finally {
      cargando.value = false
      controladorActual = null
    }
  }

  async function previsualizarImportacion(archivo: File): Promise<void> {
    if (importando.value) return

    error.value = null
    cancelar()
    controladorActual = new AbortController()
    importando.value = true

    const formData = new FormData()
    formData.append('archivo', archivo)

    try {
      const { data } = await api.post<PrevisualizacionImportacion>(
        '/asistente/importaciones/previsualizar',
        formData,
        { signal: controladorActual.signal },
      )
      previsualizacion.value = data
      nombreArchivoImportacion.value = archivo.name
    } catch (err: unknown) {
      if ((err as { name?: string })?.name === 'CanceledError') return
      const resp = (err as {
        response?: { data?: { message?: string; errors?: Record<string, string[]> } }
      })?.response
      const primerError = resp?.data?.errors
        ? Object.values(resp.data.errors).flat()[0]
        : undefined
      error.value =
        primerError ??
        resp?.data?.message ??
        'No se pudo procesar el archivo de inventario.'
    } finally {
      importando.value = false
      controladorActual = null
    }
  }

  async function confirmarImportacion(): Promise<void> {
    const token = previsualizacion.value?.token_confirmacion
    if (!token || importando.value) return

    error.value = null
    cancelar()
    controladorActual = new AbortController()
    importando.value = true

    try {
      const { data } = await api.post<{
        message: string
        resultado: ResultadoImportacion
      }>(
        '/asistente/importaciones/confirmar',
        { token_confirmacion: token },
        { signal: controladorActual.signal },
      )

      const r = data.resultado
      mensajes.value.push({
        id: secuencia++,
        role: 'assistant',
        content: `**Importación confirmada:** ${r.filas_procesadas} fila(s) procesada(s), ${r.productos_creados} producto(s) nuevo(s), ${r.lotes_creados + r.lotes_actualizados} lote(s) y ${r.unidades_ingresadas} unidades ingresadas.`,
      })
      previsualizacion.value = null
      nombreArchivoImportacion.value = null
    } catch (err: unknown) {
      if ((err as { name?: string })?.name === 'CanceledError') return
      const resp = (err as { response?: { data?: { message?: string } } })?.response
      error.value =
        resp?.data?.message ??
        'No se pudo confirmar la importación. Vuelve a subir el archivo.'
    } finally {
      importando.value = false
      controladorActual = null
    }
  }

  async function cancelarImportacion(): Promise<void> {
    const token = previsualizacion.value?.token_confirmacion
    previsualizacion.value = null
    nombreArchivoImportacion.value = null

    if (!token) return
    try {
      await api.post('/asistente/importaciones/cancelar', {
        token_confirmacion: token,
      })
    } catch {
      // El token caduca automáticamente en caché si falla la limpieza.
    }
  }

  function limpiar() {
    cancelar()
    mensajes.value = []
    previsualizacion.value = null
    nombreArchivoImportacion.value = null
    error.value = null
  }

  onUnmounted(() => {
    cancelar()
  })

  return {
    habilitado,
    cargando,
    importando,
    error,
    mensajes,
    previsualizacion,
    nombreArchivoImportacion,
    verificarEstado,
    enviar,
    previsualizarImportacion,
    confirmarImportacion,
    cancelarImportacion,
    limpiar,
    cancelar,
  }
}
