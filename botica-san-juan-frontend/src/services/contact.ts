import api from './api'
import type { AxiosResponse } from 'axios'

// Contact form interface
export interface ContactForm {
  nombre: string
  email: string
  telefono?: string
  motivo?: string
  mensaje?: string
}

// Contact inquiry interface
export interface ContactInquiry {
  nombre: string
  email: string
  telefono?: string
  motivo?: string
  mensaje?: string
  fecha: string
}

// Contact response types
export interface ContactResponse {
  success: boolean
  message?: string
}

class ContactService {
  // Send contact form
  async sendContactForm(contactData: ContactForm): Promise<void> {
    try {
      const response: AxiosResponse<ContactResponse> = await api.post('/contact.php', contactData)

      if (!response.data.success) {
        throw new Error(response.data.message || 'Failed to send contact form')
      }
    } catch (error) {
      console.error('Error sending contact form:', error)
      throw error
    }
  }

  // Get contact inquiries (admin only)
  async getContactInquiries(): Promise<ContactInquiry[]> {
    try {
      const response: AxiosResponse<{ success: boolean; data: ContactInquiry[]; message?: string }> =
        await api.get('/ver_consultas.php')

      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to fetch contact inquiries')
      }
    } catch (error) {
      console.error('Error fetching contact inquiries:', error)
      throw error
    }
  }
}

export default new ContactService()