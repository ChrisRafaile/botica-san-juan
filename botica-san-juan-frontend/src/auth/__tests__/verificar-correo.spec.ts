import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

let queryActual: Record<string, string> = {}
vi.mock('vue-router', () => ({
  useRoute: () => ({ query: queryActual }),
}))

import VerificarCorreoView from '../VerificarCorreoView.vue'

/**
 * Pantalla de resultado de la verificación.
 *
 * No comprueba nada: la API ya decidió y redirigió aquí con `?resultado=`.
 * Lo que hay que proteger es que lea ese parámetro con cuidado, y sobre todo
 * que ante cualquier cosa rara NO diga que el correo quedó confirmado. Un
 * «listo» falso es peor que un «este enlace no sirve» de más: la persona se
 * va creyendo que terminó.
 */
function montar(query: Record<string, string>) {
  queryActual = query

  return mount(VerificarCorreoView, {
    global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } },
  })
}

describe('VerificarCorreoView', () => {
  it('ensena el exito cuando la API confirma', () => {
    const vista = montar({ resultado: 'confirmado' })

    expect(vista.text()).toContain('Correo confirmado')
    expect(vista.text()).toContain('Ir a iniciar sesión')
  })

  it('pulsar dos veces no se presenta como un error', () => {
    const vista = montar({ resultado: 'ya-confirmado' })

    expect(vista.text()).toContain('Ya estaba confirmado')
    expect(vista.text()).not.toContain('no sirve')
  })

  it('un enlace rechazado se explica y ofrece pedir otro', () => {
    const vista = montar({ resultado: 'invalido' })

    expect(vista.text()).toContain('Este enlace no sirve')
    expect(vista.text()).toContain('pide otro')
  })

  it('sin parametro no se da por confirmado', () => {
    /* Llegar aquí escribiendo la dirección a mano no puede parecerse a haber
       confirmado el correo. */
    const vista = montar({})

    expect(vista.text()).toContain('Este enlace no sirve')
  })

  it('un resultado desconocido tampoco se da por confirmado', () => {
    // Por si algún día la API añade un resultado que esta pantalla no conoce.
    const vista = montar({ resultado: 'algo-que-no-existe' })

    expect(vista.text()).toContain('Este enlace no sirve')
  })
})
