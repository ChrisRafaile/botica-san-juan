import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import ProfileView from '../ProfileView.vue'
import CartView from '../CartView.vue'
import CheckoutPagoView from '../checkout/CheckoutPagoView.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/carrito'

describe('Perfil (dirección y tema) y flujo reversible de compra', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    document.documentElement.classList.remove('dark')
    vi.clearAllMocks()

    const auth = useAuthStore()
    auth.setUser({
      id: 9,
      nombre: 'Lucía Mendoza',
      email: 'lucia@botica.local',
      dni: '44556677',
      telefono: '999888777',
      direccion_entrega: 'Av. Próceres 1840, SJL',
      rol: 'cliente',
      email_verified_at: '2026-10-01T10:00:00Z',
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('guarda dirección predeterminada de entrega y cambia preferencias de tema en ProfileView', async () => {
    const putSpy = vi.spyOn(api, 'put').mockResolvedValue({
      data: {
        id: 9,
        nombre: 'Lucía Mendoza',
        email: 'lucia@botica.local',
        dni: '44556677',
        telefono: '999888777',
        direccion_entrega: 'Jr. Las Flores 321, SJL',
        rol: 'cliente',
        email_verified_at: '2026-10-01T10:00:00Z',
      },
    })

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/client/perfil', component: ProfileView }],
    })
    await router.push('/client/perfil')
    await router.isReady()

    const w = mount(ProfileView, { global: { plugins: [router] } })
    await flushPromises()

    const inputDireccion = w.find('input[placeholder*="Av. Próceres"]')
    expect((inputDireccion.element as HTMLInputElement).value).toBe('Av. Próceres 1840, SJL')

    await inputDireccion.setValue('Jr. Las Flores 321, SJL')
    await w.find('form').trigger('submit')
    await flushPromises()

    expect(putSpy).toHaveBeenCalledWith(
      '/perfil',
      expect.objectContaining({ direccion_entrega: 'Jr. Las Flores 321, SJL' }),
    )
    expect(useAuthStore().user?.direccion_entrega).toBe('Jr. Las Flores 321, SJL')

    const btnOscuro = w.findAll('[role="group"] button').find((b) => b.text().includes('Oscuro'))
    expect(btnOscuro).toBeDefined()
    await btnOscuro!.trigger('click')
    expect(localStorage.getItem('botica:tema')).toBe('oscuro')
    expect(document.documentElement.classList.contains('dark')).toBe(true)

    w.unmount()
  })

  it('permite navegar de CheckoutPagoView al carrito o tienda sin vaciar el carrito y cambiar de pedido pendiente', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)

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
    await flushPromises()
    expect(localStorage.getItem('botica:carrito')).toContain('101')

    vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
      if (url === '/pedidos/usuario/9') {
        return {
          data: [
            { id: 405, estado: 'pendiente', estado_pago: 'pendiente', total: 28 },
            { id: 406, estado: 'pendiente', estado_pago: 'pendiente', total: 64 },
          ],
        }
      }
      if (url === '/pedidos/405/mi-pedido') {
        return {
          data: {
            id: 405,
            total: 28,
            moneda: 'PEN',
            estado_pago: 'pendiente',
            pedido_detalles: [{ id: 1, cantidad: 2, precio: 14, subtotal: 28, producto: { nombre: 'Amoxicilina 500 mg' } }],
          },
        }
      }
      if (url === '/pedidos/406/mi-pedido') {
        return {
          data: {
            id: 406,
            total: 64,
            moneda: 'PEN',
            estado_pago: 'pendiente',
            pedido_detalles: [{ id: 2, cantidad: 1, precio: 64, subtotal: 64, producto: { nombre: 'Azitromicina 500 mg' } }],
          },
        }
      }
      return { data: {} }
    })

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/checkout/pago', component: CheckoutPagoView },
        { path: '/cart', component: { template: '<p>Cart</p>' } },
        { path: '/products', component: { template: '<p>Products</p>' } },
        { path: '/client/home', component: { template: '<p>Portal</p>' } },
      ],
    })
    await router.push('/checkout/pago?pedido=405')
    await router.isReady()

    const w = mount(CheckoutPagoView, { global: { plugins: [pinia, router] } })
    await flushPromises()

    expect(w.text()).toContain('Amoxicilina 500 mg')
    expect(w.find('a[href="/cart"]').exists()).toBe(true)
    expect(w.find('a[href="/products"]').exists()).toBe(true)
    expect(w.find('a[href="/client/home"]').exists()).toBe(true)

    const select = w.find('#selector-pedido-pendiente')
    expect(select.exists()).toBe(true)
    await select.setValue('406')
    await flushPromises()

    expect(router.currentRoute.value.query.pedido).toBe('406')
    expect(w.text()).toContain('Azitromicina 500 mg')
    expect(useCartStore().totalUnidades).toBe(2)

    w.unmount()
  })

  it('precarga la dirección de entrega predeterminada al abrir el checkout desde CartView', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)

    const auth = useAuthStore()
    auth.setUser({
      id: 9,
      nombre: 'Lucía Mendoza',
      email: 'lucia@botica.local',
      dni: '44556677',
      telefono: '999888777',
      direccion_entrega: 'Av. Próceres 1840, SJL',
      rol: 'cliente',
    })

    const carrito = useCartStore()
    carrito.agregar(101, 1, 5)

    vi.spyOn(api, 'post').mockResolvedValue({
      data: {
        lineas: [
          {
            producto_id: 101,
            nombre: 'Amoxicilina 500 mg',
            precio: 14,
            cantidad: 1,
            subtotal: 14,
            stock_disponible: 5,
            requiere_receta: false,
          },
        ],
        ajustes: [],
        desglose: { subtotal_gravado: 11.86, subtotal_exonerado: 0, subtotal_inafecto: 0, igv: 2.14, tasa_igv: 0.18, total: 14 },
        receta: false,
      },
    })

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { template: '<p>Home</p>' } },
        { path: '/cart', component: CartView },
        { path: '/products', component: { template: '<p>Tienda</p>' } },
        { path: '/client/home', component: { template: '<p>Portal</p>' } },
        { path: '/client/pedidos', component: { template: '<p>Pedidos</p>' } },
      ],
    })
    await router.push('/cart')
    await router.isReady()

    const w = mount(CartView, {
      global: {
        plugins: [pinia, router],
        stubs: {
          Header: true,
          Footer: true,
          ConsultorInteracciones: true,
          DialogoConfirmacion: true,
          RecetaDelPedido: true,
        },
      },
    })
    await flushPromises()

    expect(w.find('a[href="/products"]').exists()).toBe(true)
    expect(w.find('a[href="/client/home"]').exists()).toBe(true)

    const btnCheckout = w.findAll('button').find((b) => b.text().includes('Continuar al checkout'))
    expect(btnCheckout).toBeDefined()
    await btnCheckout!.trigger('click')
    await flushPromises()

    const radioEntrega = w.find('input[name="modalidad_entrega"][value="entrega"]')
    await radioEntrega.setValue(true)
    await flushPromises()

    const inputDireccion = w.find('#co-direccion')
    expect(inputDireccion.exists()).toBe(true)
    expect((inputDireccion.element as HTMLInputElement).value).toBe('Av. Próceres 1840, SJL')

    w.unmount()
  })
})
