import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const solicitar = vi.fn()
vi.mock('@/services/correos', () => ({
  solicitarEnlace: (...args: unknown[]) => solicitar(...args),
}))

import ForgotPasswordView from '../ForgotPasswordView.vue'

const MENSAJE_GENERICO =
  'Si existe una cuenta con ese correo, te enviamos un enlace para restablecer la contraseña.'

function montar() {
  return mount(ForgotPasswordView, {
    attachTo: document.body,
    global: {
      stubs: {
        RouterLink: { template: '<a><slot /></a>' },
      },
    },
  })
}

describe('ForgotPasswordView', () => {
  beforeEach(() => {
    solicitar.mockReset()
    /* Solo los intervalos: flushPromises se apoya en setTimeout y con todos
       los relojes falsos nunca resolvería. */
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] })
  })

  afterEach(() => {
    vi.useRealTimers()
    document.body.innerHTML = ''
  })

  it('no llama al endpoint con un correo mal escrito y explica por qué', async () => {
    const w = montar()
    await w.find('input[type="email"]').setValue('maria.lopez@gmail')
    await w.find('form').trigger('submit')

    expect(solicitar).not.toHaveBeenCalled()
    expect(w.find('#error-correo-olvide').text()).toContain('no parece válido')
    expect(w.find('input').attributes('aria-invalid')).toBe('true')
  })

  it('muestra el mensaje genérico del backend tal cual, sin prometer el envío', async () => {
    solicitar.mockResolvedValue(MENSAJE_GENERICO)
    const w = montar()
    await w.find('input[type="email"]').setValue('maria.lopez@gmail.com')
    await w.find('form').trigger('submit')
    await flushPromises()

    expect(solicitar).toHaveBeenCalledWith('maria.lopez@gmail.com')
    expect(w.text()).toContain('Revisa tu correo')
    expect(w.text()).toContain(MENSAJE_GENERICO)
  })

  it('bloquea el reenvío 60 segundos y lo habilita al terminar', async () => {
    solicitar.mockResolvedValue(MENSAJE_GENERICO)
    const w = montar()
    await w.find('input[type="email"]').setValue('jorge.mendoza@hotmail.com')
    await w.find('form').trigger('submit')
    await flushPromises()

    const boton = () => w.findAll('button').find((b) => b.text().includes('Reenviar'))!
    expect(boton().text()).toContain('1:00')
    expect(boton().attributes('disabled')).toBeDefined()

    vi.advanceTimersByTime(60_000)
    await w.vm.$nextTick()
    expect(boton().text()).toBe('Reenviar enlace')
    expect(boton().attributes('disabled')).toBeUndefined()

    await boton().trigger('click')
    await flushPromises()
    expect(solicitar).toHaveBeenCalledTimes(2)
  })

  it('distingue el límite de peticiones (429) de un error genérico', async () => {
    solicitar.mockRejectedValue({ response: { status: 429 } })
    const w = montar()
    await w.find('input[type="email"]').setValue('rosa.quispe@gmail.com')
    await w.find('form').trigger('submit')
    await flushPromises()

    expect(w.find('[role="alert"]').text()).toContain('Espera unos minutos')
    expect(w.text()).not.toContain('Revisa tu correo')
  })

  it('limpia el temporizador al desmontar', async () => {
    solicitar.mockResolvedValue(MENSAJE_GENERICO)
    const limpiar = vi.spyOn(globalThis, 'clearInterval')
    const w = montar()
    await w.find('input[type="email"]').setValue('luis.castro@gmail.com')
    await w.find('form').trigger('submit')
    await flushPromises()

    w.unmount()
    expect(limpiar).toHaveBeenCalled()
  })
})
