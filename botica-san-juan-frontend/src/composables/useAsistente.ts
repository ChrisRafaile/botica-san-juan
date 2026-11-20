import { onUnmounted, ref } from 'vue'
import api from '@/services/api'

export interface MensajeAsistente {
  id: number
  role: 'user' | 'assistant'
  content: string
}

export function useAsistente() {
  const habilitado = ref<boolean | null>(null)
  const cargando = ref(false)
  const error = ref<string | null>(null)
  const mensajes = ref<MensajeAsistente[]>([])

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

  function limpiar() {
    cancelar()
    mensajes.value = []
    error.value = null
  }

  onUnmounted(() => {
    cancelar()
  })

  return {
    habilitado,
    cargando,
    error,
    mensajes,
    verificarEstado,
    enviar,
    limpiar,
    cancelar,
  }
}
