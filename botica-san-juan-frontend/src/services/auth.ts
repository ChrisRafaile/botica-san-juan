import api from './api'
import type { AxiosResponse } from 'axios'
import logger from '@/utils/logger'

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
    logger.info('🔐 Intentando login', { dni: credentials.dni })

    try {
      logger.debug('📡 Enviando petición de login a API', {
        url: api.defaults.baseURL + '/login',
        credentials: { ...credentials, password: '[REDACTED]' }
      })
      // Additional debug to print actual baseURL
       
      console.log('[DEV] auth.ts: api.defaults.baseURL =', api.defaults.baseURL)

      const response: AxiosResponse<AuthResponse> = await api.post('/login', credentials);

      logger.info('✅ Login exitoso', {
        userId: response.data.user.id,
        userName: response.data.user.nombre,
        userRole: response.data.user.rol
      })

      return response.data;
    } catch (error: unknown) {
      logger.error('❌ Error en login', {
        error: error,
        credentials: { ...credentials, password: '[REDACTED]' }
      })

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
      const response: AxiosResponse<User> = await api.put('/profile', userData)
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
      const response: AxiosResponse<User> = await api.post('/profile/photo', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })
      return response.data
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