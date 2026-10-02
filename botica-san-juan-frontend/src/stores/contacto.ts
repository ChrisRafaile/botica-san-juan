import { defineStore } from 'pinia'
import { ref } from 'vue'
import contactService from '@/services/contact'
import type { MensajeContacto, ContactoGuardado } from '@/services/contact'

/**
 * Consultas enviadas desde el portal.
 *
 * Antes apuntaba a `contact.php`, del sistema anterior, que la API no sirve:
 * el formulario respondia 404 y el mensaje no llegaba a ninguna parte.
 */
export const useContactStore = defineStore('contacto', () => {
  const inquiries = ref<ContactoGuardado[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const sendContactForm = async (formulario: MensajeContacto): Promise<void> => {
    isLoading.value = true
    error.value = null
    try {
      await contactService.enviar(formulario)
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'No se pudo enviar tu consulta'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  /** Solo para el administrador: la API exige rol para leer los mensajes. */
  const fetchContactInquiries = async (): Promise<void> => {
    isLoading.value = true
    error.value = null
    try {
      inquiries.value = await contactService.listar()
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'No se pudieron cargar las consultas'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const clearError = () => {
    error.value = null
  }

  return { inquiries, isLoading, error, sendContactForm, fetchContactInquiries, clearError }
})
