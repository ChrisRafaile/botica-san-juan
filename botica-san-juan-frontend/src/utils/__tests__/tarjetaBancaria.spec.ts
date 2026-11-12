import { describe, it, expect } from 'vitest'
import {
  detectarMarcaTarjeta,
  formatearNumeroTarjeta,
  formatearVencimiento,
  esVencimientoValido,
} from '../tarjetaBancaria'

describe('detector y formateador de tarjetas bancarias (función pura)', () => {
  describe('detectarMarcaTarjeta', () => {
    it('detecta Visa con prefijo 4', () => {
      expect(detectarMarcaTarjeta('4')).toBe('visa')
      expect(detectarMarcaTarjeta('4532 0150 1234 5678')).toBe('visa')
      expect(detectarMarcaTarjeta('4000123456789010')).toBe('visa')
    })

    it('detecta Mastercard con rango clásico 51 a 55', () => {
      // Caso solicitado específicamente: Mastercard 5105...
      expect(detectarMarcaTarjeta('5105123456789012')).toBe('mastercard')
      expect(detectarMarcaTarjeta('5212345678901234')).toBe('mastercard')
      expect(detectarMarcaTarjeta('5399999999999999')).toBe('mastercard')
      expect(detectarMarcaTarjeta('5400000000000000')).toBe('mastercard')
      expect(detectarMarcaTarjeta('5500000000000000')).toBe('mastercard')
    })

    it('detecta Mastercard con rango extendido 2221 a 2720', () => {
      // Límites y valores intermedios del rango 2-series de Mastercard
      expect(detectarMarcaTarjeta('2221000000000000')).toBe('mastercard')
      expect(detectarMarcaTarjeta('2500123456789012')).toBe('mastercard')
      expect(detectarMarcaTarjeta('2720999999999999')).toBe('mastercard')
      // Parcial durante la escritura (ej. '22')
      expect(detectarMarcaTarjeta('22')).toBe('mastercard')
    })

    it('detecta American Express con prefijo 34 y 37', () => {
      expect(detectarMarcaTarjeta('340012345678901')).toBe('amex')
      expect(detectarMarcaTarjeta('378282246310005')).toBe('amex')
      expect(detectarMarcaTarjeta('37')).toBe('amex')
    })

    it('identifica como desconocida ante prefijos no soportados o cadenas vacías', () => {
      expect(detectarMarcaTarjeta('')).toBe('desconocida')
      expect(detectarMarcaTarjeta('   ')).toBe('desconocida')
      // Discover (6011...)
      expect(detectarMarcaTarjeta('6011123456789012')).toBe('desconocida')
      // JCB (35...)
      expect(detectarMarcaTarjeta('3528123456789012')).toBe('desconocida')
      // Fuera del rango 2221-2720 (ej: 2220 o 2721)
      expect(detectarMarcaTarjeta('2220999999999999')).toBe('desconocida')
      expect(detectarMarcaTarjeta('2721000000000000')).toBe('desconocida')
    })
  })

  describe('formatearNumeroTarjeta', () => {
    it('formatea números Visa y Mastercard en bloques 4-4-4-4', () => {
      expect(formatearNumeroTarjeta('4532015012345678')).toBe('4532 0150 1234 5678')
      expect(formatearNumeroTarjeta('5105123456789012')).toBe('5105 1234 5678 9012')
      expect(formatearNumeroTarjeta('2221000011112222')).toBe('2221 0000 1111 2222')
    })

    it('formatea números Amex en bloques 4-6-5', () => {
      expect(formatearNumeroTarjeta('378282246310005')).toBe('3782 822463 10005')
      expect(formatearNumeroTarjeta('340011223344555')).toBe('3400 112233 44555')
    })

    it('soporta entrada parcial progresiva', () => {
      expect(formatearNumeroTarjeta('41')).toBe('41')
      expect(formatearNumeroTarjeta('41112')).toBe('4111 2')
      expect(formatearNumeroTarjeta('3782822')).toBe('3782 822')
    })

    it('limita la cantidad máxima de dígitos según la red emisora', () => {
      // Visa/Mastercard: 16 dígitos
      expect(formatearNumeroTarjeta('45320150123456789999')).toBe('4532 0150 1234 5678')
      // Amex: 15 dígitos
      expect(formatearNumeroTarjeta('3782822463100059999')).toBe('3782 822463 10005')
    })
  })

  describe('formatearVencimiento y validación', () => {
    it('formatea mes y año añadiendo la barra inclinada', () => {
      expect(formatearVencimiento('1228')).toBe('12/28')
      expect(formatearVencimiento('0527')).toBe('05/27')
      expect(formatearVencimiento('12')).toBe('12/')
    })

    it('pre-rellena con 0 si se ingresa un mes de un solo dígito mayor a 1', () => {
      expect(formatearVencimiento('5')).toBe('05/')
    })

    it('corrige meses mayores a 12 a 12', () => {
      expect(formatearVencimiento('9928')).toBe('12/28')
    })

    it('valida estructura MM/AA', () => {
      expect(esVencimientoValido('12/28')).toBe(true)
      expect(esVencimientoValido('01/26')).toBe(true)
      expect(esVencimientoValido('13/28')).toBe(false)
      expect(esVencimientoValido('00/28')).toBe(false)
      expect(esVencimientoValido('abc')).toBe(false)
    })
  })
})
