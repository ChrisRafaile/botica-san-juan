import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

const restablecer = vi.fn()
vi.mock('@/services/correos', () => ({
  restablecerContrasena: (...args: unknown[]) => restablecer(...args),
}))

/* `useRoute()` lee la query. Se sustituye el composable y no se monta un
   router de verdad: lo que se comprueba aquí es la pantalla, no el
   enrutamiento. */
let queryActual: Record<string, string> = {}
vi.mock('vue-router', () => ({
  useRoute: () => ({ query: queryActual }),
}))

import RestablecerContrasenaView from '../RestablecerContrasenaView.vue'

/**
 * Pantalla de contraseña nueva.
 *
 * Lo que vale la pena comprobar no es que el formulario envíe —eso es lo
 * fácil— sino los tres sitios donde una pantalla de contraseñas engaña:
 *
 *   1. Que con un enlace a medias no ofrezca un formulario que va a fallar.
 *   2. Que no envíe dos contraseñas distintas.
 *   3. Que el token no acabe en el almacén del navegador.
 */
function montar(query: Record<string, string>) {
  queryActual = query

  return mount(RestablecerContrasenaView, {
    global: {
      stubs: {
        RouterLink: { template: '<a><slot /></a>' },
        MedidorDeContrasena: true,
      },
    },
  })
}

describe('RestablecerContrasenaView', () => {
  it('sin token ni correo no ensena formulario', () => {
    const vista = montar({})

    /* Un formulario que al enviarse va a fallar seguro es peor que decir
       claramente que el enlace está incompleto. */
    expect(vista.find('input[type="password"]').exists()).toBe(false)
    expect(vista.text()).toContain('incompleto')
  })

  it('con enlace completo ensena el formulario y el correo de destino', () => {
    const vista = montar({ token: 'abc', correo: 'ana@prueba.test' })

    expect(vista.findAll('input[type="password"]')).toHaveLength(2)
    expect(vista.text()).toContain('ana@prueba.test')
  })

  it('no permite enviar si las dos contrasenas no coinciden', async () => {
    const vista = montar({ token: 'abc', correo: 'ana@prueba.test' })
    const campos = vista.findAll('input[type="password"]')

    await campos[0]!.setValue('clave-larga-123')
    await campos[1]!.setValue('otra-clave-123')

    expect(vista.text()).toContain('no coinciden')
    expect((vista.find('button[type="submit"]').element as HTMLButtonElement).disabled).toBe(true)
    expect(restablecer).not.toHaveBeenCalled()
  })

  it('no permite enviar una contrasena mas corta que el minimo del servidor', async () => {
    const vista = montar({ token: 'abc', correo: 'ana@prueba.test' })
    const campos = vista.findAll('input[type="password"]')

    await campos[0]!.setValue('corta')
    await campos[1]!.setValue('corta')

    /* El servidor exige 8. Dejar enviar para que responda 422 gasta un viaje
       y, si el token era de un solo uso, confunde sobre qué falló. */
    expect((vista.find('button[type="submit"]').element as HTMLButtonElement).disabled).toBe(true)
  })

  it('envia el token y el correo del enlace, no lo que se teclee', async () => {
    restablecer.mockResolvedValue('Tu contraseña quedó actualizada.')

    const vista = montar({ token: 'token-del-correo', correo: 'ana@prueba.test' })
    const campos = vista.findAll('input[type="password"]')

    await campos[0]!.setValue('clave-nueva-12345')
    await campos[1]!.setValue('clave-nueva-12345')
    await vista.find('form').trigger('submit')

    expect(restablecer).toHaveBeenCalledWith({
      token: 'token-del-correo',
      email: 'ana@prueba.test',
      password: 'clave-nueva-12345',
      password_confirmation: 'clave-nueva-12345',
    })
  })

  it('el token no se guarda en el navegador', async () => {
    restablecer.mockResolvedValue('Listo.')

    const vista = montar({ token: 'token-secreto', correo: 'ana@prueba.test' })
    const campos = vista.findAll('input[type="password"]')

    await campos[0]!.setValue('clave-nueva-12345')
    await campos[1]!.setValue('clave-nueva-12345')
    await vista.find('form').trigger('submit')
    await vista.vm.$nextTick()

    /* Mientras está vigente, el token equivale a la contraseña de la cuenta.
       Nada de dejarlo escrito donde sobreviva a la pestaña. */
    const volcado = JSON.stringify({ ...localStorage, ...sessionStorage })
    expect(volcado).not.toContain('token-secreto')
  })

  it('tras el exito manda a iniciar sesion y avisa de que se cerraron las sesiones', async () => {
    restablecer.mockResolvedValue('Tu contraseña quedó actualizada.')

    const vista = montar({ token: 'abc', correo: 'ana@prueba.test' })
    const campos = vista.findAll('input[type="password"]')

    await campos[0]!.setValue('clave-nueva-12345')
    await campos[1]!.setValue('clave-nueva-12345')
    await vista.find('form').trigger('submit')
    await vista.vm.$nextTick()

    expect(vista.text()).toContain('Contraseña actualizada')
    /* El backend revoca todos los tokens. Si la pantalla no lo dijera, la
       persona pensaría que su sesión en el móvil sigue abierta. */
    expect(vista.text()).toContain('cerramos las sesiones')
  })

  it('un fallo del servidor deja pedir otro enlace y limpia los campos', async () => {
    restablecer.mockRejectedValue({
      response: { data: { message: 'El enlace ya no es válido.' } },
    })

    const vista = montar({ token: 'abc', correo: 'ana@prueba.test' })
    const campos = vista.findAll('input[type="password"]')

    await campos[0]!.setValue('clave-nueva-12345')
    await campos[1]!.setValue('clave-nueva-12345')
    await vista.find('form').trigger('submit')
    await vista.vm.$nextTick()

    expect(vista.text()).toContain('ya no es válido')
    expect(vista.text()).toContain('Pedir un enlace nuevo')
    expect((campos[0]!.element as HTMLInputElement).value).toBe('')
  })
})
