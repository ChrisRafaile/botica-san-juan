import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, nextTick } from 'vue'
import SelectorTema from '../SelectorTema.vue'
import { _reiniciarEstadoTemaParaPruebas } from '@/composables/useTheme'

interface OyenteMedia {
  (evento: MediaQueryListEvent): void
}

function crearMatchMediaSimulado(inicialOscuro: boolean) {
  let matches = inicialOscuro
  const oyentes = new Set<OyenteMedia>()

  const mediaQuery = {
    get matches() {
      return matches
    },
    media: '(prefers-color-scheme: dark)',
    onchange: null,
    addEventListener: vi.fn((evento: string, cb: OyenteMedia) => {
      if (evento === 'change') oyentes.add(cb)
    }),
    removeEventListener: vi.fn((evento: string, cb: OyenteMedia) => {
      if (evento === 'change') oyentes.delete(cb)
    }),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn(),
  }

  return {
    mediaQuery,
    emitirCambio(nuevoOscuro: boolean) {
      matches = nuevoOscuro
      const evento = { matches: nuevoOscuro, media: '(prefers-color-scheme: dark)' } as MediaQueryListEvent
      for (const cb of oyentes) cb(evento)
    },
    cantidadOyentes: () => oyentes.size,
  }
}

describe('SelectorTema y useTheme', () => {
  const originalMatchMedia = window.matchMedia

  beforeEach(() => {
    localStorage.removeItem('botica:tema')
    document.documentElement.classList.remove('dark')
    document.documentElement.style.colorScheme = ''
  })

  afterEach(() => {
    window.matchMedia = originalMatchMedia
    localStorage.removeItem('botica:tema')
    document.documentElement.classList.remove('dark')
  })

  it('recorre los tres estados (sistema -> claro -> oscuro -> sistema) y persiste en localStorage', async () => {
    const sim = crearMatchMediaSimulado(false)
    window.matchMedia = vi.fn().mockReturnValue(sim.mediaQuery)
    _reiniciarEstadoTemaParaPruebas()

    const w = mount(SelectorTema)
    expect(w.text()).toContain('Según el sistema')
    expect(document.documentElement.classList.contains('dark')).toBe(false)

    await w.find('button').trigger('click')
    expect(localStorage.getItem('botica:tema')).toBe('claro')
    expect(w.text()).toContain('Tema claro')
    expect(document.documentElement.classList.contains('dark')).toBe(false)

    await w.find('button').trigger('click')
    expect(localStorage.getItem('botica:tema')).toBe('oscuro')
    expect(w.text()).toContain('Tema oscuro')
    expect(document.documentElement.classList.contains('dark')).toBe(true)

    await w.find('button').trigger('click')
    expect(localStorage.getItem('botica:tema')).toBe('sistema')
    expect(w.text()).toContain('Según el sistema')
    expect(document.documentElement.classList.contains('dark')).toBe(false)

    w.unmount()
  })

  it('sigue prefers-color-scheme en vivo cuando está en modo sistema y limpia el listener al desmontar', async () => {
    const sim = crearMatchMediaSimulado(false)
    window.matchMedia = vi.fn().mockReturnValue(sim.mediaQuery)
    _reiniciarEstadoTemaParaPruebas()

    const w = mount(SelectorTema)
    expect(document.documentElement.classList.contains('dark')).toBe(false)
    expect(sim.cantidadOyentes()).toBe(1)

    sim.emitirCambio(true)
    await nextTick()
    expect(document.documentElement.classList.contains('dark')).toBe(true)

    sim.emitirCambio(false)
    await nextTick()
    expect(document.documentElement.classList.contains('dark')).toBe(false)

    w.unmount()
    expect(sim.cantidadOyentes()).toBe(0)
  })

  it('sincroniza dos instancias (cabecera pública y portal cliente) y soporta modo segmentado', async () => {
    const sim = crearMatchMediaSimulado(false)
    window.matchMedia = vi.fn().mockReturnValue(sim.mediaQuery)
    _reiniciarEstadoTemaParaPruebas()

    const Host = defineComponent({
      components: { SelectorTema },
      template: `
        <div>
          <SelectorTema data-testid="cabecera" />
          <SelectorTema modo="segmentado" data-testid="preferencias" />
        </div>
      `,
    })

    const w = mount(Host)
    const btnOscuro = w.find('[data-testid="preferencias"]').findAll('button')[1]
    expect(btnOscuro).toBeDefined()
    await btnOscuro!.trigger('click')

    expect(localStorage.getItem('botica:tema')).toBe('oscuro')
    expect(document.documentElement.classList.contains('dark')).toBe(true)
    expect(w.find('[data-testid="cabecera"]').text()).toContain('Tema oscuro')

    w.unmount()
  })
})
