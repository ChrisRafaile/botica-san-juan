import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AboutTestimonials from '../AboutTestimonials.vue'

const visible = (w: ReturnType<typeof mount>) =>
  w.findAll('article').find((a) => a.attributes('aria-hidden') === 'false')!

describe('AboutTestimonials', () => {
  it('apila las tarjetas: la activa recta y delante, las vecinas giradas ±4°', () => {
    const w = mount(AboutTestimonials)
    const tarjetas = w.findAll('article')

    expect(tarjetas[0]!.attributes('style')).toContain('rotate(0deg)')
    expect(tarjetas[0]!.attributes('style')).toContain('z-index: 30')
    expect(tarjetas[1]!.attributes('style')).toContain('rotate(4deg)')
    expect(tarjetas.at(-1)!.attributes('style')).toContain('rotate(-4deg)')
  })

  it('avanza y retrocede con los botones, dando la vuelta al final', async () => {
    const w = mount(AboutTestimonials)
    const primero = visible(w).text()

    await w.find('button[aria-label="Testimonio anterior"]').trigger('click')
    expect(w.text()).toContain(`5 / 5`)

    await w.find('button[aria-label="Testimonio siguiente"]').trigger('click')
    expect(visible(w).text()).toBe(primero)
  })

  it('sin foto pinta las iniciales, y cada reseña nombra una zona de SJL', () => {
    const w = mount(AboutTestimonials)
    expect(w.findAll('article img')).toHaveLength(0)
    expect(visible(w).text()).toContain('RQ')
    expect(visible(w).text()).toContain('Canto Grande, SJL')
  })
})
