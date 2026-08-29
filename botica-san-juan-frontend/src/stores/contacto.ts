import { defineStore } from 'pinia'
import { ref } from 'vue'
import contactService, { type ContactForm, type ContactInquiry } from '@/services/contact'

export const useContactStore = defineStore('contacto', () => {
  // State
  const inquiries = ref<ContactInquiry[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  // Actions
  const sendContactForm = async (formData: ContactForm): Promise<void> => {
    isLoading.value = true
    error.value = null

    try {
      await contactService.sendContactForm(formData)
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Error al enviar el formulario de contacto'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const fetchContactInquiries = async (): Promise<void> => {
    isLoading.value = true
    error.value = null

    try {
      inquiries.value = await contactService.getContactInquiries()
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Error al cargar las consultas de contacto'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const clearError = () => {
    error.value = null
  }

  return {
    // State
    inquiries,
    isLoading,
    error,

    // Actions
    sendContactForm,
    fetchContactInquiries,
    clearError
  }
})