import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import authService from '@/services/auth'
import type { User } from '@/services/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const isLoading = ref(false)

  const isAuthenticated = computed(() => !!user.value)

  const setUser = (userData: User) => {
    user.value = userData
  }

  /**
   * SE RETIRARON LOS REGISTROS DE DEPURACION DE ESTA FUNCION
   *
   * Imprimian por consola la respuesta completa del servidor y el objeto de
   * usuario. La respuesta completa INCLUYE EL TOKEN DE SESION: quedaba escrito
   * en claro en la consola del navegador en cada inicio de sesion, en
   * produccion igual que en desarrollo. Quien tenga ese token no necesita la
   * contrasena para actuar como esa persona hasta que caduque.
   *
   * El objeto de usuario llevaba ademas nombre, DNI, correo y telefono.
   *
   * El token sigue guardandose en localStorage, que es donde el interceptor lo
   * busca; lo que se elimina es publicarlo por consola. Si hace falta seguir
   * el flujo, el resultado de la funcion y el estado del store ya lo dicen sin
   * sacar credenciales por ningun lado.
   */
  const login = async (dni: string, password: string) => {
    try {
      isLoading.value = true
      const response = await authService.login({ dni, password })

      // Guardar token en localStorage
      localStorage.setItem('auth_token', response.token)
      setUser(response.user)

      return { success: true }
    } catch (error) {
      console.error('❌ Error al iniciar sesión:', error)
      return {
        success: false,
        message: error instanceof Error ? error.message : 'Error al iniciar sesión'
      }
    } finally {
      isLoading.value = false
    }
  }

  const register = async (userData: {
    nombre: string
    email: string
    dni: string
    password: string
    telefono: string
    acepta_terminos: boolean
  }) => {
    try {
      isLoading.value = true
      const response = await authService.register(userData)
      setUser(response.user)
      return { success: true }
    } catch (error) {
      console.error('Register error:', error)
      return {
        success: false,
        message: error instanceof Error ? error.message : 'Error al registrarse'
      }
    } finally {
      isLoading.value = false
    }
  }

  const logout = async () => {
    try {
      await authService.logout()
    } catch (error) {
      console.error('Logout error:', error)
    } finally {
      user.value = null
      localStorage.removeItem('auth_token')
    }
  }

  const checkAuth = async () => {
    try {
      const token = localStorage.getItem('auth_token')
      if (token) {
        const userData = await authService.getProfile()
        setUser(userData)
      }
    } catch (error) {
      console.error('Check auth error:', error)
      user.value = null
      localStorage.removeItem('auth_token')
    }
  }

  const updateProfile = async (profileData: Partial<User>) => {
    try {
      isLoading.value = true
      const updatedUser = await authService.updateProfile(profileData)
      setUser(updatedUser)
      return { success: true }
    } catch (error) {
      console.error('Update profile error:', error)
      return {
        success: false,
        message: error instanceof Error ? error.message : 'Error al actualizar perfil'
      }
    } finally {
      isLoading.value = false
    }
  }

  const uploadPhoto = async (file: File) => {
    try {
      isLoading.value = true
      const formData = new FormData()
      formData.append('photo', file)
      const updatedUser = await authService.uploadProfilePhoto(formData)
      setUser(updatedUser)
      return { success: true }
    } catch (error) {
      console.error('Upload photo error:', error)
      return {
        success: false,
        message: error instanceof Error ? error.message : 'Error al subir foto'
      }
    } finally {
      isLoading.value = false
    }
  }

  return {
    user,
    isLoading,
    isAuthenticated,
    login,
    register,
    logout,
    checkAuth,
    updateProfile,
    uploadPhoto
  }
})
