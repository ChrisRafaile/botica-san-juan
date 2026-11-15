import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import ClientPedidosView from '../ClientPedidosView.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/carrito'

const mockRoute = { query: {} as Record<string, string> }
vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  RouterLink: {
    props: ['to'],
    template:
      '<a :href="typeof to === \'string\' ? to : (to.path + (to.query?.pedido ? \'?pedido=\' + to.query.pedido : \'\'))"><slot /></a>',
  },
}))

describe('ClientPedidosView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
    mockRoute.query = {}

    const auth = useAuthStore()
    auth.setUser({
      id: 9,
      nombre: 'Lucía Mendoza',
      email: 'lucia@botica.local',
      dni: '44556677',
      rol: 'cliente',
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  function mockPedidos() {
    vi.spyOn(api, 'get').mockResolvedValue({
      data: [
        {
          id: 501,
          fecha: '2026-11-13',
          estado: 'pendiente',
          estado_pago: 'pendiente',
          observacion: 'Av. Próceres 1840, SJL',
          total: 28,
          requiere_receta: true,
          receta_estado: null,
          pedido_detalles: [
            {
              id: 1,
              producto_id: 101,
              cantidad: 2,
              precio: 14,
              subtotal: 28,
              producto: {
                id: 101,
                nombre: 'Amoxicilina 500 mg',
                concentracion: '500 mg',
                stock: 12,
                imagen: 'storage/productos/03210.webp',
              },
            },
          ],
        },
        {
          id: 499,
          fecha: '2026-11-01',
          estado: 'completado',
          estado_pago: 'pagado',
          observacion: 'Recojo en botica',
          total: 15,
          requiere_receta: false,
          pedido_detalles: [
            {
              id: 2,
              producto_id: 102,
              cantidad: 3,
              precio: 5,
              subtotal: 15,
              producto: { id: 102, nombre: 'Paracetamol 500 mg', stock: 5 },
            },
          ],
        },
      ],
    })
  }

  it('filtra pedidos por estado y muestra Reanudar pago solo para pedidos impagos', async () => {
    mockPedidos()
    const w = mount(ClientPedidosView, {
      global: { stubs: { DistintivoReceta: true, RecetaDelPedido: true } },
    })
    await flushPromises()

    expect(w.text()).toContain('Pedido #501')
    expect(w.text()).toContain('Pedido #499')
    expect(w.text()).toContain('Amoxicilina 500 mg')

    // El ítem con foto la pinta; el que no tiene se queda con la ilustración.
    const foto = w.find('img[alt="Amoxicilina 500 mg"]')
    expect(foto.exists()).toBe(true)
    expect(foto.attributes('src')).toContain('storage/productos/03210.webp')
    expect(w.find('img[alt="Paracetamol 500 mg"]').exists()).toBe(false)

    expect(w.find('a[href="/checkout/pago?pedido=501"]').exists()).toBe(true)
    expect(w.find('a[href="/checkout/pago?pedido=499"]').exists()).toBe(false)

    const btnEntregado = w.findAll('button').find((b) => b.text().includes('Entregado'))
    expect(btnEntregado).toBeDefined()
    await btnEntregado!.trigger('click')

    expect(w.text()).not.toContain('Pedido #501')
    expect(w.text()).toContain('Pedido #499')
  })

  it('repite un pedido con Volver a pedir y advierte claramente si algún producto tiene ajuste de stock', async () => {
    mockPedidos()
    vi.spyOn(api, 'post').mockResolvedValue({
      data: {
        lineas: [
          {
            producto_id: 102,
            nombre: 'Paracetamol 500 mg',
            precio: 5,
            cantidad: 1,
            subtotal: 5,
            stock_disponible: 1,
            requiere_receta: false,
          },
        ],
        ajustes: [
          {
            producto_id: 102,
            nombre: 'Paracetamol 500 mg',
            motivo: 'stock_insuficiente',
            mensaje: 'Paracetamol 500 mg: ajustado de 3 a 1 unidad por stock disponible.',
          },
        ],
        desglose: { subtotal_gravado: 4.24, subtotal_exonerado: 0, subtotal_inafecto: 0, igv: 0.76, tasa_igv: 0.18, total: 5 },
        receta: false,
      },
    })

    const w = mount(ClientPedidosView, {
      global: { stubs: { DistintivoReceta: true, RecetaDelPedido: true } },
    })
    await flushPromises()

    const botonesReorden = w.findAll('button').filter((b) => b.text().includes('Volver a pedir'))
    expect(botonesReorden).toHaveLength(2)
    await botonesReorden[1]!.trigger('click')
    await flushPromises()

    const carrito = useCartStore()
    expect(carrito.totalUnidades).toBe(1)

    const alerta = w.find('[role="alert"]')
    expect(alerta.exists()).toBe(true)
    expect(alerta.text()).toContain('Paracetamol 500 mg: ajustado de 3 a 1 unidad')
    expect(w.find('a[href="/cart"]').exists()).toBe(true)
  })
})
