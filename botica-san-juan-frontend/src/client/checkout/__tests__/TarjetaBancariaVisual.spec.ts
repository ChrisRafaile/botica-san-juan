import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TarjetaBancariaVisual from '../TarjetaBancariaVisual.vue'

describe('TarjetaBancariaVisual.vue', () => {
  it('renderiza el estado inicial de la tarjeta con placeholders accesibles', () => {
    const wrapper = mount(TarjetaBancariaVisual)

    expect(wrapper.find('[data-testid="logo-desconocida"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="numero-tarjeta-visual"]').text()).toContain('••••')
    expect(wrapper.find('[data-testid="titular-visual"]').text()).toBe('NOMBRE Y APELLIDO')
    expect(wrapper.find('[data-testid="vencimiento-visual"]').text()).toBe('MM/AA')
  })

  it('detecta Visa con prefijo 4 y actualiza el número en tiempo real', async () => {
    const wrapper = mount(TarjetaBancariaVisual)
    const inputNumero = wrapper.find('#tarjeta-numero')

    await inputNumero.setValue('4532015012345678')

    expect(wrapper.find('[data-testid="logo-visa"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="numero-tarjeta-visual"]').text()).toBe('4532 0150 1234 5678')
  })

  it('detecta Mastercard con prefijo 5105 y con rango 2221', async () => {
    const wrapper = mount(TarjetaBancariaVisual)
    const inputNumero = wrapper.find('#tarjeta-numero')

    // Caso Mastercard 5105...
    await inputNumero.setValue('5105123456789012')
    expect(wrapper.find('[data-testid="logo-mastercard"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="numero-tarjeta-visual"]').text()).toBe('5105 1234 5678 9012')

    // Caso Mastercard rango 2221...
    await inputNumero.setValue('2221000011112222')
    expect(wrapper.find('[data-testid="logo-mastercard"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="numero-tarjeta-visual"]').text()).toBe('2221 0000 1111 2222')
  })

  it('detecta American Express con 34 y 37 y formatea en esquema 4-6-5', async () => {
    const wrapper = mount(TarjetaBancariaVisual)
    const inputNumero = wrapper.find('#tarjeta-numero')

    await inputNumero.setValue('378282246310005')
    expect(wrapper.find('[data-testid="logo-amex"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="numero-tarjeta-visual"]').text()).toBe('3782 822463 10005')

    await inputNumero.setValue('340011223344555')
    expect(wrapper.find('[data-testid="logo-amex"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="numero-tarjeta-visual"]').text()).toBe('3400 112233 44555')
  })

  it('refleja titular y vencimiento en mayúsculas y formato MM/AA', async () => {
    const wrapper = mount(TarjetaBancariaVisual)
    const inputTitular = wrapper.find('#tarjeta-titular')
    const inputVencimiento = wrapper.find('#tarjeta-vencimiento')

    await inputTitular.setValue('Carlos San Juan')
    await inputVencimiento.setValue('1228')

    expect(wrapper.find('[data-testid="titular-visual"]').text()).toBe('CARLOS SAN JUAN')
    expect(wrapper.find('[data-testid="vencimiento-visual"]').text()).toBe('12/28')
  })

  it('no almacena datos en localStorage ni altera el almacenamiento persistente', async () => {
    const spySetItem = vi.spyOn(Storage.prototype, 'setItem')
    const wrapper = mount(TarjetaBancariaVisual)

    await wrapper.find('#tarjeta-numero').setValue('4532015012345678')
    await wrapper.find('#tarjeta-titular').setValue('PRUEBA CLIENTE')
    await wrapper.find('#tarjeta-vencimiento').setValue('0927')

    expect(spySetItem).not.toHaveBeenCalled()
    spySetItem.mockRestore()
  })
})
