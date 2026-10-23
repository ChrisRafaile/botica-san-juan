import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'

const props = {
  abierto: true,
  titulo: '¿Cerrar sesion?',
  texto: 'Se cerrara tu sesion en este equipo.',
  etiquetaConfirmar: 'Cerrar sesion',
  etiquetaCancelar: 'Seguir trabajando',
  id: 'salir',
}

/* `Teleport to="body"` saca el panel del componente, asi que las aserciones
   van contra el documento y hay que desmontar para no dejar restos entre
   pruebas. */
function montar(extra: Record<string, unknown> = {}) {
  return mount(DialogoConfirmacion, { props: { ...props, ...extra }, attachTo: document.body })
}

describe('DialogoConfirmacion', () => {
  it('se anuncia como dialogo modal y con su titulo y su texto', () => {
    const w = montar()
    const dialogo = document.querySelector('[role="dialog"]')!

    expect(dialogo.getAttribute('aria-modal')).toBe('true')
    expect(dialogo.getAttribute('aria-labelledby')).toBe('salir-titulo')
    expect(dialogo.getAttribute('aria-describedby')).toBe('salir-texto')
    expect(document.getElementById('salir-titulo')?.textContent).toContain('¿Cerrar sesion?')

    w.unmount()
  })

  it('el foco entra en la opcion segura, no en la destructiva', async () => {
    /* Quien pulsa Intro por inercia debe quedarse donde esta. Es la diferencia
       entre un diálogo que protege y uno que solo añade un clic. */
    const w = montar({ abierto: false })
    await w.setProps({ abierto: true })
    await new Promise((r) => setTimeout(r, 0))

    expect((document.activeElement as HTMLElement)?.textContent?.trim()).toBe('Seguir trabajando')

    w.unmount()
  })

  it('devuelve el foco a quien lo abrio', async () => {
    const abridor = document.createElement('button')
    abridor.textContent = 'Cerrar sesion'
    document.body.appendChild(abridor)
    abridor.focus()

    const w = montar({ abierto: false })
    await w.setProps({ abierto: true })
    await new Promise((r) => setTimeout(r, 0))
    await w.setProps({ abierto: false })
    await new Promise((r) => setTimeout(r, 0))

    expect(document.activeElement).toBe(abridor)

    w.unmount()
    abridor.remove()
  })

  it('emite confirmar y cancelar por separado', async () => {
    const w = montar()
    const botones = document.querySelectorAll('[role="dialog"] button')

    await (botones[1] as HTMLButtonElement).click()
    expect(w.emitted('confirmar')).toHaveLength(1)

    await (botones[0] as HTMLButtonElement).click()
    expect(w.emitted('cancelar')).toHaveLength(1)

    w.unmount()
  })

  it('no deja nada en el documento cuando esta cerrado', () => {
    const w = montar({ abierto: false })

    expect(document.querySelector('[role="dialog"]')).toBeNull()

    w.unmount()
  })
})
