import axios from 'axios'
import type { InternalAxiosRequestConfig, AxiosResponse, AxiosError } from 'axios'
import logger from '@/utils/logger'
import notificaciones from '@/composables/useNotificaciones'

/**
 * Cliente HTTP unico de la aplicacion.
 *
 * La URL de la API se toma de la variable de entorno VITE_API_URL para que el
 * mismo build funcione en desarrollo, en la aplicacion de escritorio y en
 * produccion. Ver .env.example.
 */
const baseURL = import.meta.env.VITE_API_URL ?? '/api'

const api = axios.create({
  baseURL,
  timeout: Number(import.meta.env.VITE_API_TIMEOUT ?? 20000),
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

if (import.meta.env.DEV) {
  logger.debug('Cliente API inicializado', { baseURL })
}

/** Rutas publicas donde no se debe forzar el cierre de sesion. */
const RUTAS_DE_AUTENTICACION = ['/login', '/register', '/forgot-password']

/** Extrae los mensajes de validacion de una respuesta 422 de Laravel. */
function extraerErroresDeValidacion(datos: unknown): string[] {
  if (!datos || typeof datos !== 'object') return []
  const errores = (datos as { errors?: Record<string, string[]> }).errors
  if (!errores) return []
  return Object.values(errores).flat()
}

/** Obtiene el mensaje que envia el backend, si existe. */
function mensajeDelServidor(datos: unknown): string | undefined {
  if (!datos || typeof datos !== 'object') return undefined
  const mensaje = (datos as { message?: string }).message
  return typeof mensaje === 'string' && mensaje.trim() !== '' ? mensaje : undefined
}

// --------------------------------------------------------------------------
// Peticion: adjunta el token de sesion
// --------------------------------------------------------------------------
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig): InternalAxiosRequestConfig => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    logger.debug('Peticion HTTP', {
      method: config.method?.toUpperCase(),
      url: `${config.baseURL ?? ''}${config.url ?? ''}`,
      autenticada: Boolean(token),
    })

    return config
  },
  (error: AxiosError) => {
    logger.error('Error al preparar la peticion', { error: error.message })
    return Promise.reject(error)
  },
)

// --------------------------------------------------------------------------
// Respuesta: manejo centralizado de errores
// --------------------------------------------------------------------------
api.interceptors.response.use(
  (response: AxiosResponse): AxiosResponse => {
    logger.debug('Respuesta HTTP', {
      status: response.status,
      url: response.config.url,
      method: response.config.method?.toUpperCase(),
    })
    return response
  },
  (error: AxiosError): Promise<AxiosError> => {
    const status = error.response?.status
    const datos = error.response?.data
    const url = error.config?.url

    logger.error('Error HTTP', {
      status,
      url,
      method: error.config?.method?.toUpperCase(),
      code: error.code,
      message: error.message,
    })

    // La peticion se cancelo de forma deliberada: no es un error del usuario.
    if (axios.isCancel(error)) {
      return Promise.reject(error)
    }

    // Sin respuesta del servidor: caida de red, CORS o tiempo de espera agotado.
    if (!error.response) {
      const esTimeout = error.code === 'ECONNABORTED'
      notificaciones.error(
        esTimeout ? 'El servidor tardo demasiado en responder' : 'Sin conexion con el servidor',
        {
          detalle: esTimeout
            ? 'Vuelve a intentarlo. Si el problema persiste, avisa al administrador.'
            : 'Verifica tu conexion a internet o que el servicio este disponible.',
        },
      )
      return Promise.reject(error)
    }

    switch (status) {
      case 401: {
        const rutaActual = window.location.pathname
        const enPantallaDeAcceso = RUTAS_DE_AUTENTICACION.includes(rutaActual)

        if (!enPantallaDeAcceso) {
          localStorage.removeItem('auth_token')
          notificaciones.aviso(
            'Tu sesion expiro',
            'Vuelve a iniciar sesion para continuar.',
          )
          window.location.href = `/login?redirect=${encodeURIComponent(rutaActual)}`
        }
        break
      }

      case 403:
        notificaciones.error('No tienes permiso para esta accion', {
          detalle:
            mensajeDelServidor(datos) ??
            'Esta operacion requiere el rol de administrador.',
        })
        break

      case 404:
        notificaciones.aviso(
          'No se encontro el recurso',
          mensajeDelServidor(datos) ?? 'Es posible que haya sido eliminado.',
        )
        break

      case 409:
        notificaciones.error('Conflicto con el estado actual', {
          detalle: mensajeDelServidor(datos) ?? 'Actualiza la pagina e intentalo de nuevo.',
        })
        break

      case 422: {
        const errores = extraerErroresDeValidacion(datos)
        notificaciones.error('Revisa los datos ingresados', {
          detalle: errores.length === 0 ? mensajeDelServidor(datos) : undefined,
          errores,
        })
        break
      }

      case 429:
        notificaciones.aviso(
          'Demasiados intentos',
          'Espera unos segundos antes de volver a intentarlo.',
        )
        break

      default:
        if (status !== undefined && status >= 500) {
          notificaciones.error('Error en el servidor', {
            detalle:
              'La operacion no pudo completarse. El incidente quedo registrado; ' +
              'si vuelve a ocurrir, avisa al administrador.',
          })
        }
        break
    }

    return Promise.reject(error)
  },
)

export default api
