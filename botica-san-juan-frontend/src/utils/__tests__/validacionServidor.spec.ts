import { describe, expect, it } from 'vitest'

import {
  ErrorDeValidacion,
  erroresPorCampo,
  traducirCampos,
} from '@/utils/validacionServidor'

describe('erroresPorCampo', () => {
  it('extrae el primer mensaje de cada campo', () => {
    const datos = {
      message: 'Los datos no son validos',
      errors: {
        dni: ['El DNI ya esta registrado', 'El DNI debe tener 8 digitos'],
        email: ['El correo ya esta en uso'],
      },
    }

    expect(erroresPorCampo(datos)).toEqual({
      dni: 'El DNI ya esta registrado',
      email: 'El correo ya esta en uso',
    })
  })

  it('se queda con el primero y no apila todas las reglas', () => {
    /* Apilar cuatro mensajes bajo un input desplaza el formulario y abruma a
       quien solo necesita saber qué escribir. */
    const datos = { errors: { password: ['Muy corta', 'Sin mayusculas', 'Sin numeros'] } }

    expect(erroresPorCampo(datos).password).toBe('Muy corta')
  })

  it('admite un mensaje suelto en vez de una lista', () => {
    expect(erroresPorCampo({ errors: { dni: 'DNI invalido' } })).toEqual({ dni: 'DNI invalido' })
  })

  it('no inventa nada cuando la respuesta no trae errores', () => {
    expect(erroresPorCampo({ message: 'Error del servidor' })).toEqual({})
    expect(erroresPorCampo(null)).toEqual({})
    expect(erroresPorCampo('texto')).toEqual({})
    expect(erroresPorCampo({ errors: null })).toEqual({})
  })

  it('descarta mensajes vacios', () => {
    expect(erroresPorCampo({ errors: { dni: ['', '  ', 'Este si'] } })).toEqual({ dni: 'Este si' })
  })
})

describe('traducirCampos', () => {
  const equivalencias = { nombre: 'name', dni: 'dni' }

  it('traduce el nombre del servidor al del formulario', () => {
    const { campos } = traducirCampos({ nombre: 'Escribe tu nombre' }, equivalencias)

    expect(campos).toEqual({ name: 'Escribe tu nombre' })
  })

  it('no pierde los mensajes que no corresponden a ningun campo visible', () => {
    /* Es el punto importante: si se descartaran en silencio, el formulario
       quedaría rechazado SIN un solo mensaje en pantalla, que es la peor
       versión de este fallo porque parece que no ha pasado nada. */
    const { campos, sueltos } = traducirCampos(
      { nombre: 'Falta', acepta_terminos: 'Debes aceptar los terminos' },
      equivalencias,
    )

    expect(campos).toEqual({ name: 'Falta' })
    expect(sueltos).toEqual(['Debes aceptar los terminos'])
  })
})

describe('ErrorDeValidacion', () => {
  it('conserva los campos y sigue siendo un Error', () => {
    const e = new ErrorDeValidacion('Revisa los datos', { dni: 'Ya registrado' })

    expect(e).toBeInstanceOf(Error)
    expect(e.message).toBe('Revisa los datos')
    expect(e.campos).toEqual({ dni: 'Ya registrado' })
  })

  it('se distingue de un Error corriente', () => {
    /* El formulario decide con `instanceof` si tiene errores por campo o solo
       un mensaje general. */
    expect(new Error('fallo') instanceof ErrorDeValidacion).toBe(false)
    expect(new ErrorDeValidacion('x', {}) instanceof ErrorDeValidacion).toBe(true)
  })
})
