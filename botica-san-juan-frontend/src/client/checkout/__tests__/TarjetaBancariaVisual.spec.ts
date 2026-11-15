import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import TarjetaBancariaVisual from '../TarjetaBancariaVisual.vue'

describe('TarjetaBancariaVisual.vue', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('renderiza el estado inicial de la tarjeta con placeholders accesibles y máscara de cuatro puntos por bloque', () => {
    const wrapper = mount(TarjetaBancariaVisual)

    expect(wrapper.find('[data-testid="logo-desconocida"]').exists()).toBe(true)
    // Máscara vacía por omisión fijada en exactamente cuatro puntos por bloque
    expect(wrapper.find('[data-testid="numero-tarjeta-visual"]').text()).toBe('•••• •••• •••• ••••')
    expect(wrapper.find('[data-testid="titular-visual"]').text()).toBe('NOMBRE Y APELLIDO')
    expect(wrapper.find('[data-testid="vencimiento-visual"]').text()).toBe('MM/AA')

    // Verifica que la transición se limite a propiedades compuestas por GPU y sin transition-all ni overflow en preserve-3d
    const cuerpoTarjeta = wrapper.find('[data-testid="tarjeta-cuerpo"]')
    expect(cuerpoTarjeta.classes()).toContain('transition-[transform,opacity]')
    expect(cuerpoTarjeta.classes()).not.toContain('overflow-hidden')
    expect(wrapper.find('[data-testid="cara-frontal"]').classes()).toContain('overflow-hidden')
    expect(wrapper.find('[data-testid="cara-trasera"]').classes()).toContain('overflow-hidden')
    expect(wrapper.html()).not.toContain('transition-all')
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

  it('gira a la cara trasera al enfocar el CVV y vuelve a la cara frontal al desenfocar', async () => {
    const wrapper = mount(TarjetaBancariaVisual)
    const cuerpo = wrapper.find('[data-testid="tarjeta-cuerpo"]')
    const caraFrontal = wrapper.find('[data-testid="cara-frontal"]')
    const caraTrasera = wrapper.find('[data-testid="cara-trasera"]')
    const inputCvv = wrapper.find('#tarjeta-cvv')

    expect(cuerpo.classes()).not.toContain('esta-volteada')
    expect(caraFrontal.attributes('aria-hidden')).toBeUndefined()
    expect(caraTrasera.attributes('aria-hidden')).toBe('true')

    await inputCvv.trigger('focus')

    expect(cuerpo.classes()).toContain('esta-volteada')
    expect(caraFrontal.attributes('aria-hidden')).toBe('true')
    expect(caraTrasera.attributes('aria-hidden')).toBeUndefined()

    await inputCvv.trigger('blur')

    expect(cuerpo.classes()).not.toContain('esta-volteada')
    expect(caraFrontal.attributes('aria-hidden')).toBeUndefined()
    expect(caraTrasera.attributes('aria-hidden')).toBe('true')

    // Caso American Express: NO gira al enfocar CVV porque el CID va en el anverso
    await wrapper.find('#tarjeta-numero').setValue('378282246310005')
    await inputCvv.trigger('focus')

    expect(cuerpo.classes()).not.toContain('esta-volteada')
    expect(caraFrontal.attributes('aria-hidden')).toBeUndefined()
    expect(caraTrasera.attributes('aria-hidden')).toBe('true')
  })

  it('mantiene el CVV/CID enmascarado con puntos en el reverso (Visa/MC) o en el anverso (Amex)', async () => {
    const wrapper = mount(TarjetaBancariaVisual)

    await wrapper.find('#tarjeta-numero').setValue('4532015012345678')
    await wrapper.find('#tarjeta-titular').setValue('María Fernanda Quispe')
    await wrapper.find('#tarjeta-vencimiento').setValue('0829')

    const inputCvv = wrapper.find('#tarjeta-cvv')
    await inputCvv.trigger('focus')
    await inputCvv.setValue('847')

    const cvvVisual = wrapper.find('[data-testid="cvv-visual"]')
    const caraFrontal = wrapper.find('[data-testid="cara-frontal"]')
    const caraTrasera = wrapper.find('[data-testid="cara-trasera"]')

    expect(cvvVisual.text()).toBe('•••')
    expect(cvvVisual.text()).not.toMatch(/\d/)
    expect(caraTrasera.text()).not.toContain('847')
    expect(caraTrasera.text()).not.toMatch(/\d/)
    expect(wrapper.find('[data-testid="cvv-frontal-visual"]').exists()).toBe(false)

    // Caso Amex: CID de 4 dígitos enmascarado en el anverso, nunca en la cara trasera
    await wrapper.find('#tarjeta-numero').setValue('378282246310005')
    await inputCvv.trigger('focus')
    await inputCvv.setValue('4921')

    const cvvFrontalVisual = wrapper.find('[data-testid="cvv-frontal-visual"]')
    expect(cvvFrontalVisual.exists()).toBe(true)
    expect(cvvFrontalVisual.text()).toBe('••••')
    expect(cvvFrontalVisual.text()).not.toMatch(/\d/)
    expect(caraFrontal.text()).not.toContain('4921')
    expect(caraTrasera.text()).not.toContain('4921')
    expect(caraTrasera.text()).not.toMatch(/\d/)
  })

  it('activa y posiciona el marco luminoso por campo frontal, lo oculta al girar y sigue el CID en Amex', async () => {
    const wrapper = mount(TarjetaBancariaVisual)
    const marco = wrapper.find('[data-testid="marco-foco"]')

    expect(marco.attributes('data-visible')).toBe('false')
    expect(marco.attributes('data-campo-activo')).toBe('ninguno')
    expect((marco.element as HTMLElement).style.opacity).toBe('0')

    await wrapper.find('#tarjeta-numero').trigger('focus')
    expect(marco.attributes('data-visible')).toBe('true')
    expect(marco.attributes('data-campo-activo')).toBe('numero')
    expect((marco.element as HTMLElement).style.opacity).toBe('1')
    expect((marco.element as HTMLElement).style.transform).toContain('translate3d(')
    expect((marco.element as HTMLElement).style.transform).toContain('scale(')

    await wrapper.find('#tarjeta-titular').trigger('focus')
    expect(marco.attributes('data-visible')).toBe('true')
    expect(marco.attributes('data-campo-activo')).toBe('titular')

    await wrapper.find('#tarjeta-vencimiento').trigger('focus')
    expect(marco.attributes('data-visible')).toBe('true')
    expect(marco.attributes('data-campo-activo')).toBe('vencimiento')

    // En tarjetas estándar al pasar al CVV el marco frontal se oculta porque gira
    await wrapper.find('#tarjeta-cvv').trigger('focus')
    expect(marco.attributes('data-visible')).toBe('false')
    expect(marco.attributes('data-campo-activo')).toBe('ninguno')
    expect((marco.element as HTMLElement).style.opacity).toBe('0')

    // En Amex el CID está en el anverso: el marco sigue al campo CVV/CID
    await wrapper.find('#tarjeta-numero').setValue('378282246310005')
    await wrapper.find('#tarjeta-cvv').trigger('focus')
    expect(marco.attributes('data-visible')).toBe('true')
    expect(marco.attributes('data-campo-activo')).toBe('cvv')
    expect((marco.element as HTMLElement).style.opacity).toBe('1')

    // Al volver a un campo frontal y luego perder el foco, se activa y luego se oculta
    await wrapper.find('#tarjeta-titular').trigger('focus')
    expect(marco.attributes('data-visible')).toBe('true')
    await wrapper.find('#tarjeta-titular').trigger('blur')
    expect(marco.attributes('data-visible')).toBe('false')
    expect((marco.element as HTMLElement).style.opacity).toBe('0')
  })

  it('cambia la opacidad de las capas de fondo según cada franquicia detectada', async () => {
    const wrapper = mount(TarjetaBancariaVisual)
    const inputNumero = wrapper.find('#tarjeta-numero')

    const verificarFondoActivo = (franquiciaActiva: 'desconocida' | 'visa' | 'mastercard' | 'amex') => {
      const franquicias = ['desconocida', 'visa', 'mastercard', 'amex'] as const
      for (const franquicia of franquicias) {
        const capa = wrapper.find(`[data-testid="fondo-${franquicia}"]`)
        if (franquicia === franquiciaActiva) {
          expect(capa.attributes('data-activo')).toBe('true')
          expect(capa.classes()).toContain('opacity-100')
          expect(capa.classes()).not.toContain('opacity-0')
        } else {
          expect(capa.attributes('data-activo')).toBe('false')
          expect(capa.classes()).toContain('opacity-0')
          expect(capa.classes()).not.toContain('opacity-100')
        }
      }
    }

    // Genérica inicial
    verificarFondoActivo('desconocida')

    // Visa
    await inputNumero.setValue('4532015012345678')
    verificarFondoActivo('visa')

    // Mastercard
    await inputNumero.setValue('5105123456789012')
    verificarFondoActivo('mastercard')

    // American Express
    await inputNumero.setValue('378282246310005')
    verificarFondoActivo('amex')

    // Genérica con prefijo no reconocido
    await inputNumero.setValue('6011000011112222')
    verificarFondoActivo('desconocida')
  })

  it('observa cambios de tamaño si ResizeObserver está disponible y lo desconecta al desmontar', () => {
    const observeMock = vi.fn()
    const disconnectMock = vi.fn()

    class ResizeObserverSimulado {
      observe = observeMock
      unobserve = vi.fn()
      disconnect = disconnectMock
    }

    vi.stubGlobal('ResizeObserver', ResizeObserverSimulado)

    const wrapper = mount(TarjetaBancariaVisual)
    expect(observeMock).toHaveBeenCalled()

    wrapper.unmount()
    expect(disconnectMock).toHaveBeenCalledTimes(1)
  })

  it('no almacena datos en localStorage ni altera el almacenamiento persistente', async () => {
    const spySetItem = vi.spyOn(Storage.prototype, 'setItem')
    const wrapper = mount(TarjetaBancariaVisual)

    await wrapper.find('#tarjeta-numero').setValue('4532015012345678')
    await wrapper.find('#tarjeta-titular').setValue('PRUEBA CLIENTE')
    await wrapper.find('#tarjeta-vencimiento').setValue('0927')
    await wrapper.find('#tarjeta-cvv').setValue('731')

    expect(spySetItem).not.toHaveBeenCalled()
  })

  it('envuelve las transiciones de titular, vencimiento y CVV en contenedores relativos y asigna rol accesible al formulario', async () => {
    const wrapper = mount(TarjetaBancariaVisual)

    const grupoFormulario = wrapper.find('[aria-label="Datos de la tarjeta"]')
    expect(grupoFormulario.exists()).toBe(true)
    expect(grupoFormulario.attributes('role')).toBe('group')

    await wrapper.find('#tarjeta-numero').setValue('378282246310005')
    await wrapper.find('#tarjeta-titular').setValue('MARIA PEREZ')
    await wrapper.find('#tarjeta-vencimiento').setValue('0829')
    await wrapper.find('#tarjeta-cvv').setValue('1234')

    expect(wrapper.find('[data-testid="titular-visual"] .relative').exists()).toBe(true)
    expect(wrapper.find('[data-testid="vencimiento-visual"] .relative').exists()).toBe(true)
    expect(wrapper.find('[data-testid="cvv-visual"] .relative').exists()).toBe(true)
    expect(wrapper.find('[data-testid="cvv-frontal-visual"] .relative').exists()).toBe(true)
  })
})
