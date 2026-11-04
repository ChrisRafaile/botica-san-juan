/**
 * Recetas médicas.
 * ---------------------------------------------------------------------------
 * POR QUÉ EL ARCHIVO SE DESCARGA Y NO SE ENLAZA
 *
 * La receta no tiene URL pública. Lleva el nombre del paciente, el del médico
 * y, por el medicamento, su diagnóstico, así que vive en el disco privado del
 * servidor y sale por un endpoint que comprueba quién pide.
 *
 * Eso tiene una consecuencia incómoda: `<img src="...">` no envía la cabecera
 * de autorización, de modo que no se puede apuntar la etiqueta al endpoint
 * directamente. Se descarga el contenido con la sesión, se envuelve en un
 * object URL y se pinta desde memoria.
 *
 * La alternativa habitual —una URL firmada temporal— se descartó: una URL,
 * aunque caduque, se puede reenviar mientras viva. Así cada petición vuelve a
 * pasar por la comprobación de propiedad.
 *
 * QUIEN PIDE EL ARCHIVO SE ENCARGA DE SOLTARLO
 *
 * `URL.createObjectURL` reserva memoria hasta que se revoca. `descargarArchivo`
 * devuelve también la función para hacerlo, de modo que la pantalla no tenga
 * que acordarse de qué API era.
 */
import api from './api'

export type EstadoReceta = 'pendiente' | 'validada' | 'rechazada'

export interface Receta {
  id: number
  estado: EstadoReceta
  nombre_original: string
  tipo_mime: string
  es_imagen: boolean
  tamano_bytes: number
  /** Cuántas veces se ha adjuntado una receta para este pedido. */
  version: number
  motivo_rechazo: string | null
  revisada_en: string | null
  revisada_por: string | null
  created_at: string
}

export interface EstadoRecetaPedido {
  requiere_receta: boolean
  receta: Receta | null
}

export interface RecetaPendiente extends Receta {
  pedido_id: number
  cliente: string | null
}

export async function estadoDeReceta(pedidoId: number): Promise<EstadoRecetaPedido> {
  const { data } = await api.get<EstadoRecetaPedido>(`/pedidos/${pedidoId}/receta`)
  return data
}

export async function subirReceta(pedidoId: number, archivo: File): Promise<Receta> {
  const cuerpo = new FormData()
  cuerpo.append('archivo', archivo)

  /* Sin fijar el Content-Type a mano: el navegador tiene que ponerlo él para
     incluir el `boundary` del multipart. Escribirlo produce un cuerpo que el
     servidor no sabe partir. */
  const { data } = await api.post<{ receta: Receta }>(`/pedidos/${pedidoId}/receta`, cuerpo)
  return data.receta
}

export async function revisarReceta(
  pedidoId: number,
  estado: 'validada' | 'rechazada',
  motivoRechazo?: string,
): Promise<Receta> {
  const { data } = await api.put<{ receta: Receta }>(`/pedidos/${pedidoId}/receta/revision`, {
    estado,
    motivo_rechazo: motivoRechazo ?? null,
  })
  return data.receta
}

export async function recetasPendientes(): Promise<{ data: RecetaPendiente[]; total: number }> {
  const { data } = await api.get<{ data: RecetaPendiente[]; total: number }>('/recetas/pendientes')
  return data
}

/**
 * Trae el archivo y lo deja listo para pintar.
 *
 * @returns el object URL y la función que lo libera.
 */
export async function descargarArchivo(
  pedidoId: number,
): Promise<{ url: string; soltar: () => void }> {
  const { data } = await api.get<Blob>(`/pedidos/${pedidoId}/receta/archivo`, {
    responseType: 'blob',
  })

  const url = URL.createObjectURL(data)

  return { url, soltar: () => URL.revokeObjectURL(url) }
}
