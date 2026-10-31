/**
 * Perfil de la cuenta en sesión.
 * ---------------------------------------------------------------------------
 * Ninguna de estas llamadas lleva identificador de usuario: el servidor actúa
 * siempre sobre quien presenta el token. Por eso el mismo servicio sirve para
 * cliente y para administrador.
 */
import api from './api'
import type { User } from './auth'

export interface DatosPersonales {
  nombre?: string
  email?: string
  telefono?: string | null
}

export interface CambioDeContrasena {
  contrasena_actual: string
  contrasena: string
  contrasena_confirmation: string
}

export async function obtenerPerfil(): Promise<User> {
  const { data } = await api.get<User>('/perfil')
  return data
}

export async function guardarDatosPersonales(datos: DatosPersonales): Promise<User> {
  const { data } = await api.put<User>('/perfil', datos)
  return data
}

/**
 * Sube la foto.
 *
 * Se manda como `FormData` y SIN fijar `Content-Type` a mano: el navegador
 * tiene que ponerlo él para incluir el `boundary` del multipart. Escribirlo
 * nosotros produce un cuerpo que el servidor no sabe partir, y el error que
 * devuelve —«el campo foto es obligatorio»— no apunta a la causa.
 */
export async function subirFoto(archivo: File): Promise<User> {
  const cuerpo = new FormData()
  cuerpo.append('foto', archivo)

  const { data } = await api.post<{ usuario: User }>('/perfil/foto', cuerpo)
  return data.usuario
}

export async function eliminarFoto(): Promise<User> {
  const { data } = await api.delete<{ usuario: User }>('/perfil/foto')
  return data.usuario
}

export async function cambiarContrasena(datos: CambioDeContrasena): Promise<void> {
  await api.put('/perfil/contrasena', datos)
}
