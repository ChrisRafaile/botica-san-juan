/**
 * Utilidades puras para detección y formateo de tarjetas bancarias.
 *
 * PRIVACIDAD Y SEGURIDAD ESTRICTA:
 * Esta función es 100 % pura y en memoria volátil. No almacena, no envía,
 * no registra ni persiste datos en localStorage, cookies, logs ni analítica.
 * Cumple con PCI-DSS al no guardar jamás datos de tarjeta en el cliente.
 */

export type MarcaTarjeta = 'visa' | 'mastercard' | 'amex' | 'desconocida'

/**
 * Detecta la marca de una tarjeta bancaria a partir de sus dígitos iniciales (BIN/IIN).
 *
 * Reglas de detección:
 * - Visa: empieza con 4 (longitud estándar 16, admite 13-19).
 * - Mastercard:
 *   - Prefijos 51 al 55 (rango tradicional).
 *   - Prefijos 2221 al 2720 (rango 2-series asignado por Mastercard).
 * - American Express (Amex): prefijos 34 o 37 (longitud 15).
 * - Desconocida: cualquier otro prefijo o valor vacío.
 *
 * @param numero Número de tarjeta sin formatear o parcialmente formateado.
 * @returns Marca identificada ('visa' | 'mastercard' | 'amex' | 'desconocida').
 */
export function detectarMarcaTarjeta(numero: string): MarcaTarjeta {
  if (!numero) return 'desconocida'

  // Se limpian espacios y guiones para evaluar exclusivamente los dígitos.
  const digitos = numero.replace(/\D/g, '')
  if (digitos.length === 0) return 'desconocida'

  // Visa: empieza con 4
  if (digitos.startsWith('4')) {
    return 'visa'
  }

  // American Express: empieza con 34 o 37
  if (/^3[47]/.test(digitos)) {
    return 'amex'
  }

  // Mastercard:
  // 1. Rango 51 a 55
  if (/^5[1-5]/.test(digitos)) {
    return 'mastercard'
  }

  // 2. Rango 2221 a 2720 (evaluación de los primeros 4 dígitos)
  if (digitos.length >= 2) {
    const dosPrimeros = Number(digitos.slice(0, 2))
    // Si solo hay 2 dígitos y están entre 22 y 27, podría ser un Mastercard en proceso de tipeo
    if (digitos.length < 4) {
      if (dosPrimeros >= 22 && dosPrimeros <= 27) {
        return 'mastercard'
      }
    } else {
      const cuatroPrimeros = Number(digitos.slice(0, 4))
      if (cuatroPrimeros >= 2221 && cuatroPrimeros <= 2720) {
        return 'mastercard'
      }
    }
  }

  return 'desconocida'
}

/**
 * Formatea un número de tarjeta con el espaciado característico de cada red emisora:
 * - American Express: 4-6-5 dígitos (máximo 15 dígitos: #### ###### #####).
 * - Visa / Mastercard / Desconocida: 4-4-4-4 dígitos (máximo 16 dígitos: #### #### #### ####).
 *
 * @param valor Cadena con dígitos o texto introducido por el usuario.
 * @param marcaForzada Opcional si ya se conoce la marca; si no, se detecta automáticamente.
 * @returns Número formateado con espacios.
 */
export function formatearNumeroTarjeta(valor: string, marcaForzada?: MarcaTarjeta): string {
  if (!valor) return ''

  const digitos = valor.replace(/\D/g, '')
  const marca = marcaForzada ?? detectarMarcaTarjeta(digitos)

  if (marca === 'amex') {
    // Amex: máximo 15 dígitos con esquema 4-6-5
    const recortado = digitos.slice(0, 15)
    const partes: string[] = []

    if (recortado.length > 0) {
      partes.push(recortado.slice(0, 4))
    }
    if (recortado.length > 4) {
      partes.push(recortado.slice(4, 10))
    }
    if (recortado.length > 10) {
      partes.push(recortado.slice(10, 15))
    }

    return partes.join(' ')
  }

  // Estándar 16 dígitos con esquema 4-4-4-4 (Visa, Mastercard, otras)
  const recortado = digitos.slice(0, 16)
  const partes: string[] = []

  for (let i = 0; i < recortado.length; i += 4) {
    partes.push(recortado.slice(i, i + 4))
  }

  return partes.join(' ')
}

/**
 * Formatea una fecha de vencimiento a formato MM/AA.
 *
 * @param valor Texto o dígitos de entrada.
 * @returns Cadena con formato "MM/AA" o parcial "MM" / "MM/".
 */
export function formatearVencimiento(valor: string): string {
  if (!valor) return ''

  const digitos = valor.replace(/\D/g, '').slice(0, 4)

  if (digitos.length === 0) return ''

  if (digitos.length === 1) {
    // Si el primer dígito es mayor a 1 (ej: 2..9), se asume mes 02..09
    if (Number(digitos) > 1) {
      return `0${digitos}/`
    }
    return digitos
  }

  const mes = digitos.slice(0, 2)
  const anio = digitos.slice(2, 4)

  // El mes no puede ser superior a 12 ni inferior a 01 si ya se escribieron dos dígitos
  let mesAjustado = mes
  const mesNum = Number(mes)
  if (mesNum > 12) {
    mesAjustado = '12'
  } else if (mesNum === 0) {
    mesAjustado = '01'
  }

  if (digitos.length > 2) {
    return `${mesAjustado}/${anio}`
  }

  return `${mesAjustado}/`
}

/**
 * Valida si un mes/año de vencimiento es coherente (mes entre 1 y 12).
 */
export function esVencimientoValido(vencimiento: string): boolean {
  if (!/^\d{2}\/\d{2}$/.test(vencimiento)) return false
  const [mesStr] = vencimiento.split('/')
  const mes = Number(mesStr)
  return mes >= 1 && mes <= 12
}
