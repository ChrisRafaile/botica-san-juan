import { reactive, readonly } from 'vue'

/**
 * Sistema de notificaciones de la aplicacion.
 *
 * Se implementa como un modulo reactivo y no como un store de Pinia porque
 * debe poder invocarse desde el interceptor HTTP, que se ejecuta fuera del
 * contexto de un componente y antes de que Pinia este disponible.
 */

export type TipoNotificacion = 'exito' | 'error' | 'aviso' | 'info'

export interface Notificacion {
  id: number
  tipo: TipoNotificacion
  titulo: string
  detalle?: string
  /** Lista de errores de validacion, campo por campo. */
  errores?: string[]
  /** Milisegundos antes de descartarse. 0 mantiene la notificacion fija. */
  duracion: number
}

const estado = reactive<{ items: Notificacion[] }>({ items: [] })

let contador = 0

/** Duracion por defecto segun la gravedad del mensaje. */
const DURACION_POR_TIPO: Record<TipoNotificacion, number> = {
  exito: 3500,
  info: 4000,
  aviso: 6000,
  error: 8000,
}

function agregar(
  tipo: TipoNotificacion,
  titulo: string,
  opciones: { detalle?: string; errores?: string[]; duracion?: number } = {},
): number {
  const id = ++contador
  const duracion = opciones.duracion ?? DURACION_POR_TIPO[tipo]

  estado.items.push({
    id,
    tipo,
    titulo,
    detalle: opciones.detalle,
    errores: opciones.errores,
    duracion,
  })

  // Se limita la pila visible para no tapar la interfaz.
  if (estado.items.length > 4) {
    estado.items.splice(0, estado.items.length - 4)
  }

  if (duracion > 0) {
    window.setTimeout(() => descartar(id), duracion)
  }

  return id
}

function descartar(id: number): void {
  const indice = estado.items.findIndex((n) => n.id === id)
  if (indice !== -1) estado.items.splice(indice, 1)
}

function limpiar(): void {
  estado.items.splice(0, estado.items.length)
}

export const notificaciones = {
  items: readonly(estado).items,
  exito: (titulo: string, detalle?: string) => agregar('exito', titulo, { detalle }),
  error: (titulo: string, opciones?: { detalle?: string; errores?: string[]; duracion?: number }) =>
    agregar('error', titulo, opciones ?? {}),
  aviso: (titulo: string, detalle?: string) => agregar('aviso', titulo, { detalle }),
  info: (titulo: string, detalle?: string) => agregar('info', titulo, { detalle }),
  descartar,
  limpiar,
}

export function useNotificaciones() {
  return notificaciones
}

export default notificaciones
