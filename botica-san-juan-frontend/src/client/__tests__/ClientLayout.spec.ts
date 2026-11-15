import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import ClientLayout from '../layouts/ClientLayout.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/carrito'
import appRouter from '@/router'

function crearRouterPrueba() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/client',
        component: ClientLayout,
        children: [
          { path: 'home', component: { template: '<h1>Inicio</h1>' }, meta: { titulo: 'Inicio' } },
          { path: 'pedidos', component: { template: '<h1>Pedidos</h1>' }, meta: { titulo: 'Mis Pedidos' } },
          { path: 'comprobantes', component: { template: '<h1>Comprobantes</h1>' }, meta: { titulo: 'Mis Comprobantes' } },
          { path: 'perfil', component: { template: '<h1>Perfil</h1>' }, meta: { titulo: 'Mi Perfil' } },
        ],
      },
      { path: '/products', component: { template: '<p>Tienda</p>' } },
      { path: '/cart', component: { template: '<p>Carrito</p>' } },
      { path: '/checkout/pago', component: { template: '<p>Pago</p>' } },
      { path: '/logout', component: { template: '<p>Logout</p>' } },
    ],
  })
}

describe('ClientLayout.vue', () => {
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

    vi.spyOn(api, 'get').mockResolvedValue({
      data: [
        { id: 501, estado: 'pendiente', estado_pago: 'pendiente', total: 48.5 },
        { id: 502, estado: 'completado', estado_pago: 'pagado', total: 22.0 },
      ],
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('muestra navegación, contador de carrito, alertas de pago pendiente y persiste el colapso lateral', async () => {
    const carrito = useCartStore()
    carrito.agregar(101, 3, 10)

    const router = crearRouterPrueba()
    await router.push('/client/home')
    await router.isReady()

    const w = mount(ClientLayout, {
      global: { plugins: [router] },
    })
    await flushPromises()

    expect(w.text()).toContain('Mis Pedidos')
    expect(w.text()).toContain('Mis Comprobantes')
    expect(w.text()).toContain('Mi Perfil')
    expect(w.find('[data-testid="contador-carrito-cliente"]').text()).toBe('3')

    const btnColapsar = w.find('button[aria-label="Colapsar barra lateral"]')
    expect(btnColapsar.exists()).toBe(true)
    await btnColapsar.trigger('click')
    expect(localStorage.getItem('botica:cliente:lateral-colapsada')).toBe('1')

    const btnAlertas = w.find('button[aria-label="Pedidos pendientes de pago: 1"]')
    expect(btnAlertas.exists()).toBe(true)
    await btnAlertas.trigger('click')
    expect(w.text()).toContain('Pedido #501')

    w.unmount()
  })

  it('abre drawer móvil, cierra con Escape y al cambiar de ruta, y confirma antes de cerrar sesión en /logout', async () => {
    const router = crearRouterPrueba()
    await router.push('/client/home')
    await router.isReady()

    const w = mount(ClientLayout, {
      global: { plugins: [router] },
    })
    await flushPromises()

    await w.find('button[aria-label="Abrir menú"]').trigger('click')
    expect(w.find('#drawer-portal-cliente').exists()).toBe(true)

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()
    expect(w.find('#drawer-portal-cliente').exists()).toBe(false)

    await w.find('header button[aria-label="Cerrar sesión"]').trigger('click')
    await flushPromises()
    const confirmarBtn = Array.from(document.body.querySelectorAll('button')).find((b) =>
      b.textContent?.includes('Sí, cerrar sesión'),
    )
    expect(confirmarBtn).toBeDefined()
    confirmarBtn!.click()
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/logout')

    w.unmount()
  })

  it('mantiene /profile redirigiendo a /client/perfil en el router principal', () => {
    const rutaProfile = appRouter.getRoutes().find((r) => r.path === '/profile')
    expect(rutaProfile?.redirect).toBe('/client/perfil')

    const rutasCliente = appRouter.getRoutes().map((r) => r.path)
    expect(rutasCliente).toContain('/client/home')
    expect(rutasCliente).toContain('/client/pedidos')
    expect(rutasCliente).toContain('/client/comprobantes')
    expect(rutasCliente).toContain('/client/perfil')
  })
})
