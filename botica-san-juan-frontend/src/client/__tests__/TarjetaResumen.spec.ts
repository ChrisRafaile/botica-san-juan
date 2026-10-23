import { describe, expect, it } from 'vitest'
import { h, markRaw } from 'vue'
import { mount } from '@vue/test-utils'
import { RouterLinkStub } from '@vue/test-utils'

import TarjetaResumen from '@/client/components/TarjetaResumen.vue'

/* El icono de prueba pinta un elemento real: con uno que devuelve `null` no
   hay nodo al que se aplique `aria-hidden`, y la prueba estaria comprobando
   el doble en vez del componente. */
const icono = markRaw({ render: () => h('svg') })
const base = { etiqueta: 'Pedidos en curso', valor: 3, detalle: 'Pendientes', icono, cargando: false }

const montar = (extra = {}) =>
  mount(TarjetaResumen, {
    props: { ...base, ...extra },
    global: { stubs: { RouterLink: RouterLinkStub } },
  })

describe('TarjetaResumen', () => {
  it('muestra la cifra y su detalle', () => {
    const w = montar()

    expect(w.text()).toContain('Pedidos en curso')
    expect(w.text()).toContain('3')
    expect(w.text()).toContain('Pendientes')
  })

  it('oculta la cifra mientras carga y no muestra un cero enganyoso', () => {
    /* Pintar `0` mientras llega la respuesta es peor que no pintar nada: el
       cliente lee un dato falso durante un segundo y lo da por bueno. */
    const w = montar({ cargando: true, valor: 0 })

    expect(w.text()).not.toContain('Pendientes')
    expect(w.findAll('.animate-pulse').length).toBeGreaterThan(0)
  })

  it('mantiene la misma altura cargando y cargada', () => {
    /* Es lo que impide que las cuatro tarjetas empujen el historial hacia
       abajo cuando responde la API. */
    const cargando = montar({ cargando: true })
    const lista = montar({ cargando: false })

    expect(cargando.classes()).toContain('h-28')
    expect(lista.classes()).toContain('h-28')
  })

  it('es un enlace solo cuando se le da destino', () => {
    expect(montar().findComponent(RouterLinkStub).exists()).toBe(false)

    const w = montar({ enlace: '/cart' })
    expect(w.findComponent(RouterLinkStub).props('to')).toBe('/cart')
  })

  it('el icono es decorativo', () => {
    /* La etiqueta ya dice de que es la cifra; anunciar ademas el icono solo
       anade ruido en un lector de pantalla. */
    const w = mount(TarjetaResumen, {
      props: base,
      global: { stubs: { RouterLink: RouterLinkStub } },
    })

    expect(w.html()).toContain('aria-hidden="true"')
  })
})
