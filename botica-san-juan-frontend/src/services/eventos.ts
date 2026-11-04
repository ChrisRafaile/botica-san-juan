/**
 * Bitácora de operación.
 * ---------------------------------------------------------------------------
 * Solo lectura. No hay función para crear ni para borrar, y no es un olvido:
 * los eventos los escribe el código que los provoca, nunca una persona. Una
 * bitácora que se puede editar desde la interfaz deja de servir como
 * evidencia de lo que pasó.
 */
import api from './api'

/** Los tres niveles del modelo, tal cual los guarda el servidor. */
export type NivelEvento = 'info' | 'warning' | 'error'

export interface EventoDeOperacion {
  id: number
  nivel: NivelEvento
  codigo: string
  mensaje: string
  origen: string
  dispositivo: string | null
  usuario_id: number | null
  pedido_id: number | null
  /** Campo libre. Llega ya depurado por el servidor. */
  contexto: Record<string, unknown> | null
  ocurrio_en: string | null
}

export interface PaginaDeEventos {
  datos: EventoDeOperacion[]
  paginacion: {
    pagina: number
    por_pagina: number
    total: number
    paginas: number
  }
}

export interface ResumenDeEventos {
  niveles: Record<NivelEvento, number>
  total: number
  codigos: Array<{ codigo: string; total: number }>
  ultimo: string | null
}

export interface FiltrosDeEventos {
  nivel?: NivelEvento | ''
  codigo?: string
  buscar?: string
  desde?: string
  hasta?: string
  page?: number
  per_page?: number
}

/**
 * Quita del objeto lo que está vacío.
 *
 * Mandar `?nivel=` vacío no es lo mismo que no mandarlo: el servidor valida
 * el parámetro y una cadena vacía ensucia la URL que acaba en el historial y
 * en los registros.
 */
function soloLoQueTieneValor(filtros: FiltrosDeEventos): Record<string, string | number> {
  const limpio: Record<string, string | number> = {}

  for (const [clave, valor] of Object.entries(filtros)) {
    if (valor !== undefined && valor !== null && valor !== '') {
      limpio[clave] = valor as string | number
    }
  }

  return limpio
}

export async function listarEventos(filtros: FiltrosDeEventos = {}): Promise<PaginaDeEventos> {
  const { data } = await api.get<PaginaDeEventos>('/eventos', {
    params: soloLoQueTieneValor(filtros),
  })

  return data
}

export async function resumenDeEventos(): Promise<ResumenDeEventos> {
  const { data } = await api.get<ResumenDeEventos>('/eventos/resumen')
  return data
}
