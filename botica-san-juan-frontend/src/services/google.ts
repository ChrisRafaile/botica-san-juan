/**
 * Acceso con Google.
 * ---------------------------------------------------------------------------
 * POR QUÉ HAY DOS LLAMADAS Y NO UNA
 *
 * El navegador sale del portal, va a Google, y vuelve a la API. La API no
 * puede devolverle el token al portal directamente: lo único que puede hacer
 * es redirigir, y un token en la dirección queda en el historial, en la
 * cabecera Referer y en los registros de cualquier intermediario.
 *
 * Así que la API redirige con un CÓDIGO de un solo uso que vive 60 segundos,
 * y el portal lo canjea por POST. El token llega en el cuerpo de la
 * respuesta, que no se registra en ningún sitio.
 *
 * El código se gasta al canjearlo: si alguien lo saca del historial, ya no
 * vale.
 */
import api from './api'
import type { User } from './auth'

export interface SesionGoogle {
  user: User
  token: string
  token_type: string
  /** Una cuenta creada con Google nace sin DNI: Google no lo sabe. */
  necesita_dni: boolean
}

/** Motivos por los que el retorno puede no traer sesión. */
export type MotivoFallo =
  | 'cancelado'
  | 'state'
  | 'google'
  | 'sin-correo'
  | 'correo-sin-verificar'
  | 'cuenta-ocupada'
  | 'no-configurado'

/**
 * Pide a la API la dirección de Google y lleva allí al navegador.
 *
 * La URL la construye el backend porque es quien tiene el identificador del
 * cliente y quien genera el `state` que ata la vuelta con la ida. Armarla en
 * el portal obligaría a publicar ese identificador y dejaría el `state` en
 * manos del navegador, que es justo de quien hay que protegerlo.
 */
export async function entrarConGoogle(): Promise<void> {
  const { data } = await api.get<{ url: string }>('/auth/google/redirect')

  /* `location.assign` y no `window.open`: es una navegación, no una ventana
     nueva. Un emergente lo bloquean los navegadores cuando no viene de un
     gesto directo, y aquí media una petición al servidor. */
  window.location.assign(data.url)
}

/** Canjea el código del retorno por el token de sesión. Un solo uso. */
export async function canjearCodigo(codigo: string): Promise<SesionGoogle> {
  const { data } = await api.post<SesionGoogle>('/auth/google/intercambio', { codigo })
  return data
}

/**
 * Qué decirle a la persona según lo que falló.
 *
 * Cada motivo lleva su texto porque «ha ocurrido un error» no deja saber si
 * hay que reintentar, usar otra cuenta o avisar a alguien.
 */
export function explicar(motivo: string): string {
  const textos: Record<MotivoFallo, string> = {
    cancelado: 'Cancelaste el acceso con Google. Puedes intentarlo otra vez o entrar con tu DNI.',
    state: 'La sesión de acceso caducó o no se pudo comprobar. Vuelve a empezar desde el botón de Google.',
    google: 'Google no pudo confirmar tu cuenta. Inténtalo de nuevo en un momento.',
    'sin-correo': 'Tu cuenta de Google no comparte un correo, y lo necesitamos para identificarte.',
    'correo-sin-verificar':
      'Google no confirma que ese correo sea tuyo, así que no podemos vincularlo. Verifícalo en tu cuenta de Google e inténtalo otra vez.',
    'cuenta-ocupada':
      'Ese correo ya está vinculado a otra cuenta de Google en nuestro sistema. Escríbenos y lo revisamos.',
    'no-configurado': 'El acceso con Google no está disponible en este momento.',
  }

  return textos[motivo as MotivoFallo] ?? 'No pudimos completar el acceso con Google.'
}
