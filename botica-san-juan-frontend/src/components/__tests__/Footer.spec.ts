import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Footer from '../Footer.vue'

// Mock de vue-router para RouterLink
vi.mock('vue-router', () => ({
  RouterLink: {
    name: 'RouterLink',
    props: ['to'],
    template: '<a :href="typeof to === \'string\' ? to : to.path"><slot /></a>',
  },
}))

describe('Footer.vue', () => {
  it('renderiza los métodos de pago aceptados con sus iconos SVG accesibles', () => {
    const wrapper = mount(Footer)

    expect(wrapper.text()).toContain('Métodos de pago aceptados')
    expect(wrapper.find('[title="Visa"]').exists()).toBe(true)
    expect(wrapper.find('[title="Mastercard"]').exists()).toBe(true)
    expect(wrapper.find('[title="American Express"]').exists()).toBe(true)
    expect(wrapper.find('[title="Yape"]').exists()).toBe(true)
    expect(wrapper.find('[title="Plin"]').exists()).toBe(true)
  })

  it('renderiza el enlace al Libro de Reclamaciones con su ruta correcta', () => {
    const wrapper = mount(Footer)

    const enlaces = wrapper.findAll('a[href="/libro-de-reclamaciones"]')
    expect(enlaces.length).toBeGreaterThan(0)
    const bannerReclamaciones = wrapper.find('a[aria-label="Ir al Libro de Reclamaciones Virtual"]')
    expect(bannerReclamaciones.exists()).toBe(true)
  })

  it('renderiza el botón flotante de WhatsApp con aria-label descriptivo', () => {
    const wrapper = mount(Footer)

    const botonWa = wrapper.find('a[aria-label="Contactar a Botica San Juan por WhatsApp (abre en nueva ventana)"]')
    expect(botonWa.exists()).toBe(true)
    expect(botonWa.attributes('target')).toBe('_blank')
    expect(botonWa.attributes('rel')).toContain('noopener')
  })
})
