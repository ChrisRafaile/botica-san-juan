import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import ProfileView from '../ProfileView.vue'
import { useAuthStore } from '@/stores/auth'
import type { User } from '@/services/auth'

/**
 * La tarjeta de «Acceso con Google» en el perfil.
 * ---------------------------------------------------------------------------
 * Lo que hay que demostrar aquí no es que el botón exista, sino que NO
 * aparezca cuando pulsarlo dejaría a la persona fuera de su cuenta, y que en
 * su lugar se explique qué falta.
 *
 * Un botón desactivado no serviría: no dice nada. Y dejar pulsar para que el
 * backend responda 422 convertiría la única forma de enterarse en un error.
 */
vi.mock('@/services/perfil', () => ({
  obtenerPerfil: vi.fn(() => Promise.resolve(null)),
  guardarDatosPersonales: vi.fn(),
  subirFoto: vi.fn(),
  eliminarFoto: vi.fn(),
  cambiarContrasena: vi.fn(),
  desvincularGoogle: vi.fn(),
}))

vi.mock('@/services/correos', () => ({
  reenviarVerificacion: vi.fn(),
}))

const base: User = {
  id: 1,
  nombre: 'Cliente de Prueba',
  email: 'cliente@prueba.test',
  dni: '10000011',
  rol: 'cliente',
  email_verified_at: '2026-11-01T10:00:00Z',
}

function montarCon(usuario: Partial<User>) {
  const auth = useAuthStore()
  auth.setUser({ ...base, ...usuario } as User)

  return mount(ProfileView, {
    global: {
      stubs: {
        ZonaDeFoto: true,
        MedidorDeContrasena: true,
        DialogoConfirmacion: true,
      },
    },
  })
}

describe('tarjeta de acceso con Google', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('no se muestra si la cuenta nunca usó Google', () => {
    /* A quien entra solo con DNI, una tarjeta sobre Google no le dice nada. */
    const vista = montarCon({ tiene_google: false })

    expect(vista.text()).not.toContain('Acceso con Google')
  })

  it('ofrece desvincular cuando hay contraseña propia y correo verificado', () => {
    const vista = montarCon({
      tiene_google: true,
      tiene_contrasena: true,
      puede_desvincular_google: true,
    })

    expect(vista.text()).toContain('Acceso con Google')
    expect(vista.text()).toContain('Desvincular Google')
  })

  it('una cuenta nacida con Google no ve el botón, ve la explicación', () => {
    /* Este es el caso que importa: sin contraseña propia, quitar Google deja
       la cuenta sin ninguna vía de entrada. */
    const vista = montarCon({
      tiene_google: true,
      tiene_contrasena: false,
      puede_desvincular_google: false,
    })

    expect(vista.text()).toContain('Todavía no puedes desvincular Google')

    /* Se busca entre los BOTONES y no en el texto de la vista: el texto lleva
       también el del aviso, que contiene la palabra «desvincular», así que una
       comprobación sobre `text()` pasaría siempre y no demostraría nada. */
    const botones = vista.findAll('button').map((b) => b.text())

    expect(botones.some((texto) => texto.includes('Desvincular'))).toBe(false)
  })

  it('la explicación nombra la salida real', () => {
    /* «No puedes» a secas deja a alguien atascado. La salida es pedir la
       contraseña por correo, y el texto tiene que decirlo. */
    const vista = montarCon({
      tiene_google: true,
      tiene_contrasena: false,
      puede_desvincular_google: false,
    })

    expect(vista.text()).toContain('olvidé mi contraseña')
  })

  it('con contraseña pero sin correo verificado explica eso otro', () => {
    const vista = montarCon({
      tiene_google: true,
      tiene_contrasena: true,
      puede_desvincular_google: false,
      email_verified_at: null,
    })

    expect(vista.text()).toContain('confirmar tu correo')
  })

  it('el botón abre la confirmación en lugar de desvincular directamente', async () => {
    /* Perder una vía de acceso no debe ocurrir por un clic accidental. */
    const { desvincularGoogle } = await import('@/services/perfil')

    const vista = montarCon({
      tiene_google: true,
      tiene_contrasena: true,
      puede_desvincular_google: true,
    })

    const boton = vista
      .findAll('button')
      .find((b) => b.text().includes('Desvincular Google'))

    expect(boton).toBeDefined()
    await boton!.trigger('click')

    expect(desvincularGoogle).not.toHaveBeenCalled()
  })
})
