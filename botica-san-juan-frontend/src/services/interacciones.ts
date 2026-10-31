/**
 * Consultor de interacciones.
 *
 * La forma de la respuesta no es casual: trae por separado lo revisado, lo
 * que no se pudo identificar y los avisos. La pantalla está obligada a
 * enseñar las tres cosas, porque enseñar solo los avisos convertiría un
 * «no lo sé» en un «no pasa nada».
 */
import api from './api'

export interface ProductoRevisado {
  producto_id: number
  nombre: string
  principio: string
}

export interface ProductoNoIdentificado {
  producto_id: number
  nombre: string
}

export interface AvisoInteraccion {
  severidad: 'leve' | 'moderada' | 'grave'
  principios: (string | null)[]
  productos: string[]
  efecto: string
  recomendacion: string
}

export interface ResultadoInteracciones {
  revisados: ProductoRevisado[]
  no_identificados: ProductoNoIdentificado[]
  avisos: AvisoInteraccion[]
  /** Verdadero solo si se pudo identificar TODO el carrito. */
  concluyente: boolean
  /** Verdadero si hay algo que un profesional deba mirar. */
  derivar: boolean
  mensaje: string
  aviso_legal: string
}

export async function evaluarInteracciones(
  productoIds: number[],
): Promise<ResultadoInteracciones> {
  const { data } = await api.post<ResultadoInteracciones>('/interacciones/evaluar', {
    productos: productoIds,
  })
  return data
}
