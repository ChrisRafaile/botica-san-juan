import api from './api'
import type { AxiosResponse } from 'axios'
import logger from '@/utils/logger'
import { ErrorDeValidacion, erroresPorCampo } from '@/utils/validacionServidor'

// User interface based on Laravel backend structure
export interface User {
  id: number
  nombre: string
  email: string
  dni: string
  telefono?: string
  foto?: string
  foto_perfil?: string
  foto_portada?: string
  rol?: string
  created_at?: string
  updated_at?: string
}

// Auth response types
export interface AuthResponse {
  user: User
  token: string
  token_type: string
}

export interface LoginRequest {
  dni: string
  password: string
}

export interface RegisterRequest {
  nombre: string
  dni: string
  email: string
  password: string
  telefono: string
  acepta_terminos: boolean
}

interface ApiError {
  response?: {
    data?: {
      message?: string
    }
  }
}

class AuthService {
  // Login user
  async login(credentials: LoginRequest): Promise<AuthResponse> {
    /**
     * QUE NO SE REGISTRA AQUI, Y POR QUE
     *
     * Estas lineas escribian el DNI en la consola del navegador en cada
     * intento de acceso —en el mensaje de entrada, en el de depuracion y otra
     * vez en el de error— ademas del nombre de la persona al entrar. El DNI es
     * un documento de identidad: no es un identificador tecnico cualquiera.
     *
     * Y no se quedaba en desarrollo. El `logger` llama a `console.log` sin
     * condicion; la bandera `import.meta.env.DEV` solo guarda un bloque vacio.
     * Asi que en produccion cualquiera con la consola abierta, y cualquier
     * extension o herramienta de errores que capture la consola, veia el DNI
     * de quien acababa de entrar.
     *
     * Se registra el hecho, no a la persona: que hubo un intento, si salio
     * bien y con que rol. Eso es lo que sirve para depurar; la identidad no
     * aporta nada que justifique publicarla.
     */
    logger.info('🔐 Intentando iniciar sesión')

    try {
      const response: AxiosResponse<AuthResponse> = await api.post('/login', credentials);

      logger.info('✅ Inicio de sesión correcto', {
        userId: response.data.user.id,
        userRole: response.data.user.rol
      })

      return response.data;
    } catch (error: unknown) {
      /* El error va sin credenciales: incluirlas volvia a sacar el DNI por
         consola justo en el caso en que mas se mira, el que falla. */
      logger.error('❌ Error al iniciar sesión', { error })

      const err = error as ApiError;
      const errorMessage = err.response?.data?.message || 'Error al iniciar sesión'

      logger.error('📝 Mensaje de error final', { message: errorMessage })
      throw new Error(errorMessage);
    }
  }

  // Register new user
  async register(userData: RegisterRequest): Promise<AuthResponse> {
    try {
      const response: AxiosResponse<AuthResponse> = await api.post('/register', userData)
      return response.data
    } catch (error: unknown) {
      console.error('Error registering:', error)
      const err = error as ApiError

      /* Una validación fallida (422) dice QUÉ campo está mal. Envolverla en un
         `Error` genérico tira esa información y obliga a quien se registra a
         adivinar cuál de los seis campos corregir. Se conserva en un error
         propio para que el formulario pueda pintarla bajo el campo. */
      const datos = (err.response as { data?: unknown } | undefined)?.data
      const campos = erroresPorCampo(datos)

      if (Object.keys(campos).length > 0) {
        throw new ErrorDeValidacion(
          err.response?.data?.message || 'Revisa los datos ingresados',
          campos,
        )
      }

      throw new Error(err.response?.data?.message || 'Error al registrarse')
    }
  }

  // Logout user
  async logout(): Promise<void> {
    try {
      await api.post('/logout')
      // Clear token from localStorage
      localStorage.removeItem('auth_token')
    } catch (error: unknown) {
      console.error('Error logging out:', error)
      const err = error as ApiError
      throw new Error(err.response?.data?.message || 'Error al cerrar sesión')
    }
  }

  // Get current user profile
  //
  // El endpoint GET /api/user de Laravel devuelve el modelo Usuario en la raiz
  // de la respuesta, no envuelto en { user }. Se aceptan ambas formas para que
  // la sesion se restaure correctamente al recargar la pagina.
  async getProfile(): Promise<User> {
    try {
      const response: AxiosResponse<User | { user: User }> = await api.get('/user')
      const datos = response.data as User & { user?: User }
      return datos.user ?? datos
    } catch (error: unknown) {
      console.error('Error fetching profile:', error)
      const err = error as ApiError
      throw new Error(err.response?.data?.message || 'Error al obtener perfil')
    }
  }

  // Update user profile
  async updateProfile(userData: Partial<User>): Promise<User> {
    try {
      /* Antes apuntaba a `/profile`, que nunca existió en la API: cualquier
         intento de guardar el perfil devolvía 404 en silencio. La ruta real
         es `/perfil` y actúa sobre quien presenta el token. */
      const response: AxiosResponse<User> = await api.put('/perfil', userData)
      return response.data
    } catch (error: unknown) {
      console.error('Error updating profile:', error)
      const err = error as ApiError
      throw new Error(err.response?.data?.message || 'Error al actualizar perfil')
    }
  }

  // Upload profile photo
  async uploadProfilePhoto(formData: FormData): Promise<User> {
    try {
      /* Ruta corregida —`/profile/photo` no existía— y sin fijar el
         `Content-Type`: el navegador tiene que ponerlo él para incluir el
         `boundary` del multipart. Escribirlo a mano produce un cuerpo que el
         servidor no sabe partir. */
      const response: AxiosResponse<{ usuario: User }> = await api.post('/perfil/foto', formData)
      return response.data.usuario
    } catch (error: unknown) {
      console.error('Error uploading photo:', error)
      const err = error as ApiError
      throw new Error(err.response?.data?.message || 'Error al subir foto')
    }
  }

  // Check if user is authenticated
  async checkAuth(): Promise<boolean> {
    try {
      await this.getProfile()
      return true
    } catch {
      return false
    }
  }

  // Set authentication data in localStorage
  setAuthData(response: AuthResponse): void {
    localStorage.setItem('auth_token', response.token)
    localStorage.setItem('user', JSON.stringify(response.user))
  }
}

export default new AuthService()