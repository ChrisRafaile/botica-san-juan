import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import CartView from '../CartView.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/carrito'
import { usePedidosPendientes } from '@/composables/usePedidosPendientes'

const mockRouter = {
  push: vi.fn(),
  replace: vi.fn(),
}

vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'string\' ? to : to.path"><slot /></a>',
  },
}))

describe('CartView.vue (Checkout cliente)', () => {
  const wrappersMontados: VueWrapper[] = []

  afterEach(() => {
    while (wrappersMontados.length > 0) {
      wrappersMontados.pop()?.unmount()
    }
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
    // El estado de pendientes es de módulo: sin esto una prueba hereda el aviso de otra.
    usePedidosPendientes().olvidar()

    const auth = useAuthStore()
    auth.setUser({
      id: 9,
      nombre: 'Lucía Mendoza',
      email: 'lucia@botica.local',
      dni: '44556677',
      rol: 'cliente',
    })

    const carrito = useCartStore()
    carrito.agregar(101, 2, 10)

    vi.spyOn(api, 'post').mockImplementation(async (url: string) => {
      if (url === '/carrito/cotizar') {
        return {
          data: {
            lineas: [
              {
                producto_id: 101,
                nombre: 'Amoxicilina 500 mg',
                concentracion: '500 mg',
                presentacion: 'Caja',
                tipo: 'Cápsula',
                imagen: null,
                precio: 18.5,
                cantidad: 2,
                subtotal: 37.0,
                stock_disponible: 10,
                requiere_receta: false,
                tipo_afectacion_igv: '10',
              },
            ],
            ajustes: [],
            unidades: 2,
            receta: false,
            desglose: {
              subtotal_gravado: 31.36,
              subtotal_exonerado: 0,
              subtotal_inafecto: 0,
              igv: 5.64,
              tasa_igv: 0.18,
              total: 37.0,
            },
          },
        }
      }
      if (url === '/pedidos/confirmar') {
        return {
          data: {
            pedido: { id: 612, total: 37.0 },
            modalidad_entrega: 'entrega',
            comprobante: {
              id: 91,
              tipo: 'factura',
              identificador: 'F001-00000091',
              estado_sunat: 'pendiente',
            },
            seguimiento: { pedido_id: 612, invitado: false },
          },
        }
      }
      return { data: {} }
    })
  })

  function montar() {
    const wrapper = mount(CartView, {
      attachTo: document.body,
      global: {
        stubs: {
          Header: true,
          Footer: true,
          ConsultorInteracciones: true,
        },
      },
    })
    wrappersMontados.push(wrapper)
    return wrapper
  }

  describe('pedidos esperando pago', () => {
    function mockHistorial() {
      vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
        if (url === '/pedidos/usuario/9') {
          return {
            data: [
              { id: 222, fecha: '2026-11-15', estado: 'pendiente', estado_pago: 'pendiente', total: 82 },
              { id: 210, fecha: '2026-11-02', estado: 'completado', estado_pago: 'pagado', total: 37 },
            ],
          }
        }
        return { data: {} }
      })
    }

    it('enseña el pedido impago y cómo retomarlo aunque el carrito esté vacío', async () => {
      mockHistorial()
      // Es el estado en que queda el carrito justo después de confirmar.
      useCartStore().vaciar()

      const wrapper = montar()
      await flushPromises()

      const aviso = wrapper.find('[data-testid="pedidos-pendientes-carrito"]')
      expect(aviso.exists()).toBe(true)
      expect(aviso.text()).toContain('Tienes un pedido esperando pago')
      expect(aviso.text()).toContain('Pedido #222')
      // Intl separa el símbolo con un espacio de no separación.
      expect(aviso.text()).toMatch(/S\/\s82\.00/)
      // El pedido ya pagado no es un pendiente y no debe ofrecer cobro.
      expect(aviso.text()).not.toContain('Pedido #210')
      expect(aviso.find('a[href="/checkout/pago"]').text()).toContain('Reanudar pago')
      expect(wrapper.text()).toContain('Tu carrito está vacío')
    })

    it('retira el aviso al cerrar la sesión con la página abierta', async () => {
      mockHistorial()
      const wrapper = montar()
      await flushPromises()
      expect(wrapper.find('[data-testid="pedidos-pendientes-carrito"]').exists()).toBe(true)

      useAuthStore().$patch({ user: null })
      await flushPromises()

      expect(wrapper.find('[data-testid="pedidos-pendientes-carrito"]').exists()).toBe(false)
    })
  })

  it('valida dirección y RUC para Factura antes de enviar y redirige a la pasarela con Tarjeta 3D', async () => {
    const wrapper = montar()
    await flushPromises()

    expect(wrapper.text()).toContain('Amoxicilina 500 mg')

    const btnCheckout = wrapper.findAll('button').find((b) => b.text().includes('Continuar al checkout'))
    expect(btnCheckout).toBeDefined()
    await btnCheckout!.trigger('click')
    await flushPromises()

    // Selecciona Entrega a domicilio y Factura
    await wrapper.find('input[name="modalidad_entrega"][value="entrega"]').setValue(true)
    await wrapper.find('input[name="tipo_comprobante"][value="factura"]').setValue(true)
    await flushPromises()

    // RUC inválido y dirección corta deben bloquear envío sin golpear el backend
    await wrapper.find('#co-direccion').setValue('abc')
    await wrapper.find('#co-doc').setValue('12345678')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(api.post).not.toHaveBeenCalledWith('/pedidos/confirmar', expect.anything())
    expect(wrapper.text()).toContain('El RUC debe tener exactamente 11 dígitos numéricos.')

    // Datos válidos: confirma pedido real y navega a /checkout/pago?pedido=612
    await wrapper.find('#co-direccion').setValue('Av. Los Pinos 450, SJL')
    await wrapper.find('#co-nombre').setValue('Comercial Salud EIRL')
    await wrapper.find('#co-doc').setValue('20999888777')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith(
      '/pedidos/confirmar',
      expect.objectContaining({
        items: [{ producto_id: 101, cantidad: 2 }],
        modalidad_entrega: 'entrega',
        tipo_comprobante: 'factura',
        metodo_pago: 'tarjeta',
        direccion: 'Av. Los Pinos 450, SJL',
        cliente_nombre: 'Comercial Salud EIRL',
        cliente_documento: '20999888777',
        cliente_telefono: undefined,
      }),
      expect.objectContaining({
        headers: expect.objectContaining({
          'Idempotency-Key': expect.any(String),
        }),
      }),
    )

    expect(mockRouter.push).toHaveBeenCalledWith({
      path: '/checkout/pago',
      query: { pedido: '612' },
    })
  })

  it('permite confirmar con Boleta para recojo y pago contraentrega ofreciendo Ver mis pedidos', async () => {
    const wrapper = montar()
    await flushPromises()

    const btnCheckout = wrapper.findAll('button').find((b) => b.text().includes('Continuar al checkout'))
    await btnCheckout!.trigger('click')
    await flushPromises()

    await wrapper.find('input[name="metodo_pago"][value="contraentrega"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith(
      '/pedidos/confirmar',
      expect.objectContaining({
        items: [{ producto_id: 101, cantidad: 2 }],
        modalidad_entrega: 'recojo',
        tipo_comprobante: 'boleta',
        metodo_pago: 'contra_entrega',
        direccion: undefined,
        cliente_nombre: undefined,
        cliente_documento: undefined,
        cliente_telefono: undefined,
      }),
      expect.objectContaining({
        headers: expect.objectContaining({
          'Idempotency-Key': expect.any(String),
        }),
      }),
    )

    expect(wrapper.text()).toContain('Pedido registrado')
    expect(wrapper.text()).toContain('#612')
    expect(wrapper.find('a[href="/client/home"]').exists()).toBe(true)

    const dialogoConfirmado = wrapper.find('[role="dialog"][aria-labelledby="titulo-confirmado"]')
    expect(dialogoConfirmado.exists()).toBe(true)
    expect(dialogoConfirmado.element.contains(document.activeElement)).toBe(true)

    // Tab en el último elemento enfocable del modal confirmado vuelve al primero
    const enfocables = Array.from(
      dialogoConfirmado.element.querySelectorAll<HTMLElement>('a[href], button:not([disabled])'),
    )
    expect(enfocables.length).toBeGreaterThanOrEqual(2)
    enfocables[enfocables.length - 1]!.focus()
    await dialogoConfirmado.trigger('keydown', { key: 'Tab' })
    expect(document.activeElement).toBe(enfocables[0])

    // Escape cierra el modal de pedido confirmado
    await dialogoConfirmado.trigger('keydown', { key: 'Escape' })
    await flushPromises()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it('atrapa el foco en el modal de checkout, cierra con Escape y lo devuelve al disparador', async () => {
    const wrapper = montar()
    await flushPromises()

    const btnCheckout = wrapper.findAll('button').find((b) => b.text().includes('Continuar al checkout'))!
    ;(btnCheckout.element as HTMLElement).focus()
    await btnCheckout.trigger('click')
    await flushPromises()

    const dialogo = wrapper.find('[role="dialog"][aria-labelledby="titulo-checkout"]')
    expect(dialogo.exists()).toBe(true)
    expect(dialogo.element.contains(document.activeElement)).toBe(true)

    const botonCerrar = dialogo.find('button[aria-label="Cerrar checkout"]').element as HTMLElement
    const botonEnviar = dialogo.find('button[type="submit"]').element as HTMLElement

    // Tab desde el último control vuelve al primero
    botonEnviar.focus()
    await dialogo.trigger('keydown', { key: 'Tab' })
    expect(document.activeElement).toBe(botonCerrar)

    // Shift+Tab desde el primero vuelve al último
    await dialogo.trigger('keydown', { key: 'Tab', shiftKey: true })
    expect(document.activeElement).toBe(botonEnviar)

    // Escape cierra el checkout y devuelve el foco al botón que lo abrió
    await dialogo.trigger('keydown', { key: 'Escape' })
    await flushPromises()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(document.activeElement).toBe(btnCheckout.element)
  })

  it('limpia el temporizador de copiarPedido y los escuchadores al desmontar', async () => {
    vi.useFakeTimers()
    const clearTimeoutSpy = vi.spyOn(globalThis, 'clearTimeout')
    const removeListenerSpy = vi.spyOn(window, 'removeEventListener')
    const descriptorOriginal = Object.getOwnPropertyDescriptor(navigator, 'clipboard')

    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText: vi.fn().mockResolvedValue(undefined) },
    })

    try {
      const wrapper = montar()
      await flushPromises()

      const btnCheckout = wrapper.findAll('button').find((b) => b.text().includes('Continuar al checkout'))!
      await btnCheckout.trigger('click')
      await flushPromises()
      await wrapper.find('button[aria-label="Cerrar checkout"]').trigger('click')
      await flushPromises()

      const btnCopiar = wrapper.findAll('button').find((b) => b.text().includes('Copiar pedido'))!
      await btnCopiar.trigger('click')
      await flushPromises()
      expect(wrapper.text()).toContain('Pedido copiado')

      wrapper.unmount()

      expect(clearTimeoutSpy).toHaveBeenCalled()
      expect(removeListenerSpy).toHaveBeenCalledWith('keydown', expect.any(Function))
    } finally {
      if (descriptorOriginal) {
        Object.defineProperty(navigator, 'clipboard', descriptorOriginal)
      }
    }
  })
})
