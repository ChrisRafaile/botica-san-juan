import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import CartView from '../CartView.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/carrito'

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
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()

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
    return mount(CartView, {
      global: {
        stubs: {
          Header: true,
          Footer: true,
          ConsultorInteracciones: true,
        },
      },
    })
  }

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

    expect(api.post).toHaveBeenCalledWith('/pedidos/confirmar', {
      items: [{ producto_id: 101, cantidad: 2 }],
      modalidad_entrega: 'entrega',
      tipo_comprobante: 'factura',
      direccion: 'Av. Los Pinos 450, SJL',
      cliente_nombre: 'Comercial Salud EIRL',
      cliente_documento: '20999888777',
      cliente_telefono: undefined,
    })

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

    expect(api.post).toHaveBeenCalledWith('/pedidos/confirmar', {
      items: [{ producto_id: 101, cantidad: 2 }],
      modalidad_entrega: 'recojo',
      tipo_comprobante: 'boleta',
      direccion: undefined,
      cliente_nombre: undefined,
      cliente_documento: undefined,
      cliente_telefono: undefined,
    })

    expect(wrapper.text()).toContain('Pedido registrado')
    expect(wrapper.text()).toContain('#612')
    expect(wrapper.find('a[href="/client/home"]').exists()).toBe(true)
  })
})
