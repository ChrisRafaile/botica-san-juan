/**
 * Datos operativos de la botica. Fuente única.
 *
 * POR QUÉ UN MÓDULO Y NO EL DATO ESCRITO EN CADA PANTALLA
 *
 * Antes de centralizarlo, el portal ofrecía **cuatro números de WhatsApp
 * distintos** a la vez:
 *
 *   - `wa.me/016772892`  en Servicios, Cobertura y la cabecera de Contacto
 *     — que además es el fijo, y un fijo no tiene WhatsApp: ese enlace no
 *       llevaba a ninguna conversación.
 *   - `wa.me/51999999999` en el pie — relleno.
 *   - `+51 967 654 321`  en la ficha de Contacto, sólo como texto.
 *   - `+51 987 654 321`  en los ajustes del panel.
 *
 * Ninguno podía ser el bueno al mismo tiempo. Un dato de contacto repetido a
 * mano en siete archivos no se mantiene: se corrige en uno y se olvida en los
 * otros seis, y el cliente acaba escribiendo a un número que nadie lee.
 *
 * DATOS POR CONFIRMAR CON EL DUEÑO
 *
 * La dirección, el fijo y el horario salen del material del negocio. El número
 * de WhatsApp **no está confirmado**: por eso se lee de `VITE_BOTICA_WHATSAPP`
 * y, si no está definida, las acciones de WhatsApp se ocultan en lugar de
 * enlazar a un número inventado. Es preferible una vía de contacto menos que
 * una que no contesta.
 */

/** Teléfono fijo de la botica, tal como se marca. */
export const TELEFONO_FIJO = '(01) 677-2892'

/** El mismo fijo en formato marcable para el atributo `href="tel:"`. */
export const TELEFONO_FIJO_E164 = '+5116772892'

/**
 * WhatsApp en formato internacional sin signos (lo que espera wa.me).
 *
 * `null` mientras no se confirme. Toda la interfaz debe comprobarlo antes de
 * pintar un botón de WhatsApp.
 */
export const WHATSAPP: string | null =
  (import.meta.env.VITE_BOTICA_WHATSAPP as string | undefined)?.replace(/\D/g, '') || null

/**
 * Correo de atención.
 *
 * Es el que figura en el pie del portal y el único verificable. La ficha de
 * Contacto publicaba otros tres —info@, ventas@ y soporte@ en un dominio
 * `boticasan-juan.com` que no es el del sistema— y los tres rebotaban.
 */
export const CORREO = 'Boticassanjuan16@gmail.com'

export const DIRECCION = {
  calle: 'Av. Sta. Rosa de Lima 103',
  distrito: 'San Juan de Lurigancho',
  codigoPostal: '15423',
  ciudad: 'Lima',
  pais: 'Perú',
  /** Una línea, para enlaces y mensajes. */
  completa: 'Av. Sta. Rosa de Lima 103, San Juan de Lurigancho 15423, Lima, Perú',
}

/**
 * Horario comercial.
 *
 * `desde` y `hasta` van en minutos desde medianoche porque es lo que permite
 * compararlos con la hora actual sin analizar cadenas. `dias` usa la numeración
 * de `Date.getDay()`: 0 = domingo.
 */
export interface FranjaHoraria {
  etiqueta: string
  dias: number[]
  desde: number
  hasta: number
  texto: string
}

const h = (hora: number, minuto = 0) => hora * 60 + minuto

export const HORARIO: FranjaHoraria[] = [
  {
    etiqueta: 'Lunes a viernes',
    dias: [1, 2, 3, 4, 5],
    desde: h(7),
    hasta: h(22),
    texto: '7:00 a. m. – 10:00 p. m.',
  },
  {
    etiqueta: 'Sábados',
    dias: [6],
    desde: h(8),
    hasta: h(21),
    texto: '8:00 a. m. – 9:00 p. m.',
  },
  {
    etiqueta: 'Domingos y feriados',
    dias: [0],
    desde: h(9),
    hasta: h(18),
    texto: '9:00 a. m. – 6:00 p. m.',
  },
]

/**
 * ¿Está abierta la botica ahora mismo?
 *
 * Se calcula con la hora del dispositivo de quien mira. No es exacto para quien
 * tenga el reloj en otro huso, pero el error es suyo y conocido; traer la hora
 * del servidor para esto costaría una petición y no cambia la decisión de nadie.
 *
 * Los feriados no se contemplan: se tratan como domingo, que es lo que hace la
 * botica en la práctica.
 */
export function estadoAtencion(ahora: Date = new Date()): {
  abierta: boolean
  franja: FranjaHoraria | null
  texto: string
} {
  const dia = ahora.getDay()
  const minutos = ahora.getHours() * 60 + ahora.getMinutes()
  const franja = HORARIO.find((f) => f.dias.includes(dia)) ?? null

  if (!franja) return { abierta: false, franja: null, texto: 'Cerrado' }

  const abierta = minutos >= franja.desde && minutos < franja.hasta

  /* La hora de cierre se saca del propio texto de la franja para no tener el
     dato escrito dos veces y que se separen. Si el formato cambiara y el corte
     fallara, se cae al texto completo en vez de mostrar "undefined". */
  const cierre = franja.texto.split('–')[1]?.trim() ?? franja.texto

  return {
    abierta,
    franja,
    texto: abierta ? `Abierto ahora · hasta ${cierre}` : 'Cerrado ahora',
  }
}

/** Enlace a WhatsApp con un mensaje ya escrito. `null` si no hay número. */
export function enlaceWhatsApp(mensaje?: string): string | null {
  if (!WHATSAPP) return null
  const base = `https://wa.me/${WHATSAPP}`
  return mensaje ? `${base}?text=${encodeURIComponent(mensaje)}` : base
}

/** Mapa de la sede. Se usa en Contacto; se declara aquí para no repetirlo. */
export const MAPA_EMBED =
  'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3903.1093089078936!2d-76.99819362405982!3d-11.966933240421248!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9105c5919fa13ef3%3A0xba67ea4c5d045e15!2sAv.%20Sta.%20Rosa%20de%20Lima%20103%2C%20San%20Juan%20de%20Lurigancho%2015423!5e0!3m2!1ses-419!2spe!4v1760860710905!5m2!1ses-419!2spe'

export const MAPA_COMO_LLEGAR =
  'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(DIRECCION.completa)
