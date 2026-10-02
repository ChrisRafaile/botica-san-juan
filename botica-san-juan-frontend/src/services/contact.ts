import api from './api'

/**
 * Formulario de contacto del portal.
 *
 * FALLO CORREGIDO: APUNTABA A UN SISTEMA QUE YA NO EXISTE
 *
 * Este servicio llamaba a `contact.php` y `ver_consultas.php`, scripts del
 * sistema PHP anterior. La API de Laravel nunca los sirvio, asi que el
 * formulario respondia 404 en silencio: el visitante escribia su consulta,
 * pulsaba enviar y el mensaje no llegaba a ninguna parte.
 *
 * La API expone POST /contacto, publico y con limitador, y la lectura de los
 * mensajes queda reservada al administrador.
 */

export interface MensajeContacto {
  nombre: string
  email: string
  telefono?: string
  motivo?: string
  mensaje: string
}

export interface ContactoGuardado extends MensajeContacto {
  id: number
  created_at: string
}

class ContactService {
  /** Envia una consulta. No exige sesion: lo normal es que quien escribe aun no sea cliente. */
  async enviar(mensaje: MensajeContacto): Promise<ContactoGuardado> {
    const respuesta = await api.post('/contacto', mensaje)
    const cuerpo = respuesta.data as ContactoGuardado | { data: ContactoGuardado }
    return (cuerpo as { data?: ContactoGuardado }).data ?? (cuerpo as ContactoGuardado)
  }

  /** Mensajes recibidos. Solo administrador. */
  async listar(): Promise<ContactoGuardado[]> {
    const respuesta = await api.get('/contacto')
    const cuerpo = respuesta.data as ContactoGuardado[] | { data: ContactoGuardado[] }
    return Array.isArray(cuerpo) ? cuerpo : (cuerpo?.data ?? [])
  }
}

export default new ContactService()
