/**
 * Correo transaccional: verificación de cuenta y recuperación de contraseña.
 * ---------------------------------------------------------------------------
 * POR QUÉ `solicitarEnlace` NO DISTINGUE SI EL CORREO EXISTE
 *
 * No es que el servicio pierda la información: es que el backend no la da, a
 * propósito. Responde lo mismo exista o no la cuenta, porque un formulario
 * público que confirmara cuáles de mil direcciones tienen cuenta sería un
 * verificador de clientes de una botica.
 *
 * Esta capa no intenta deducirlo por otros medios —tiempos de respuesta,
 * reintentos—: devuelve el mensaje tal cual y la pantalla lo muestra.
 */
import api from './api'

export interface DatosRestablecer {
  token: string
  email: string
  password: string
  password_confirmation: string
}

/** Errores de validación por campo, tal como los devuelve Laravel. */
export interface FalloDeCampos {
  message: string
  errors: Record<string, string[]>
}

/*
 * Aquí NO hay función para confirmar el correo, y es deliberado.
 *
 * El enlace del correo apunta a la API, firmado, y la API redirige al portal
 * con el resultado en `?resultado=`. La pantalla solo lee ese parámetro.
 *
 * El primer diseño sí tenía esa función: recibía el enlace firmado y lo
 * invocaba con `fetch`. No funcionó —la API y el portal están en orígenes
 * distintos y el navegador bloqueó la llamada— y arreglarlo habría significado
 * abrir CORS para un enlace que llega por correo. Navegar sale más barato.
 */

/** Pide un correo de verificación nuevo. Requiere sesión. */
export async function reenviarVerificacion(): Promise<{ enviado: boolean; message: string }> {
  const { data } = await api.post('/email/verificacion/reenviar')
  return data
}

/** Pide el enlace de restablecimiento. Nunca revela si la cuenta existe. */
export async function solicitarEnlace(email: string): Promise<string> {
  const { data } = await api.post<{ message: string }>('/password/olvide', { email })
  return data.message
}

/** Fija la contraseña nueva con el token del correo. */
export async function restablecerContrasena(datos: DatosRestablecer): Promise<string> {
  const { data } = await api.post<{ message: string }>('/password/restablecer', datos)
  return data.message
}
