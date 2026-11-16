import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import ClientPedidosView from '../ClientPedidosView.vue'
import CheckoutPagoView from '../checkout/CheckoutPagoView.vue'
import api from '@/services/api'
import { cancelarPedido } from '@/services/pedidosCliente'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/carrito'

const mockRouter = {
  push: vi.fn(),
  replace: vi.fn(),
}

const mockRoute = {
  query: {} as Record<string, string>,
}

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  useRouter: () => mockRouter,
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'string\' ? to : (to.path + (to.query?.pedido ? \'?pedido=\' + to.query.pedido : \'\'))"><slot /></a>',
  },
}))

describe('Cancelación e Idempotencia en el Portal de Cliente', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
    mockRoute.query = {}

    const auth = useAuthStore()
    auth.setUser({
      id: 15,
      nombre: 'Rosa Flores',
      email: 'rosa.flores@boticasanjuan.pe',
      dni: '72345678',
      rol: 'cliente',
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('cancelarPedido envía POST a /pedidos/{id}/cancelar con cabecera Idempotency-Key', async () => {
    const spyPost = vi.spyOn(api, 'post').mockResolvedValue({
      data: {
        ok: true,
        pedido: { id: 701, estado: 'anulado', estado_pago: 'cancelado' },
      },
    })

    const res = await cancelarPedido(701, 'clave-test-cancelacion-001')

    expect(spyPost).toHaveBeenCalledWith(
      '/pedidos/701/cancelar',
      {},
      {
        headers: {
          'Idempotency-Key': 'clave-test-cancelacion-001',
        },
      },
    )
    expect(res.ok).toBe(true)
    expect(res.pedido.estado).toBe('anulado')
  })

  it('ClientPedidosView muestra botón Cancelar pedido solo en pedidos abiertos y actualiza estado tras confirmar', async () => {
    vi.spyOn(api, 'get').mockResolvedValue({
      data: [
        {
          id: 701,
          fecha: '2026-11-16',
          estado: 'pendiente',
          estado_pago: 'pendiente',
          total: 31.0,
          requiere_receta: false,
          pedido_detalles: [
            {
              id: 1,
              producto_id: 201,
              cantidad: 2,
              precio: 15.5,
              subtotal: 31.0,
              producto: { id: 201, nombre: 'Amoxicilina 500 mg', stock: 10 },
            },
          ],
        },
        {
          id: 699,
          fecha: '2026-11-10',
          estado: 'completado',
          estado_pago: 'pagado',
          total: 12.0,
          requiere_receta: false,
          pedido_detalles: [],
        },
      ],
    })

    const spyPost = vi.spyOn(api, 'post').mockResolvedValue({
      data: {
        ok: true,
        pedido: { id: 701, estado: 'anulado', estado_pago: 'cancelado' },
      },
    })

    const wrapper = mount(ClientPedidosView, {
      attachTo: document.body,
      global: {
        stubs: {
          DistintivoReceta: true,
          RecetaDelPedido: true,
        },
      },
    })
    await flushPromises()

    // El pedido 701 (pendiente) debe mostrar botón cancelar
    const btnCancelar = wrapper.find('[data-testid="boton-cancelar-pedido-lista"]')
    expect(btnCancelar.exists()).toBe(true)

    // Al hacer clic, se abre el diálogo accesible
    await btnCancelar.trigger('click')
    await flushPromises()

    const dialogoTitulo = document.getElementById('dialogo-cancelar-pedido-lista-titulo')
    expect(dialogoTitulo).not.toBeNull()
    expect(dialogoTitulo?.textContent).toContain('¿Cancelar el pedido #701?')

    // Confirmar cancelación en el diálogo
    const btnConfirmar = Array.from(document.querySelectorAll('button')).find((b) =>
      b.textContent?.includes('Sí, cancelar pedido'),
    )
    expect(btnConfirmar).toBeDefined()
    btnConfirmar!.click()
    await flushPromises()

    // Debe invocar api.post a /pedidos/701/cancelar
    expect(spyPost).toHaveBeenCalledWith(
      '/pedidos/701/cancelar',
      {},
      expect.objectContaining({
        headers: expect.objectContaining({
          'Idempotency-Key': expect.any(String),
        }),
      }),
    )

    // La tarjeta del pedido se actualiza a Cancelado sin recargar la página entera
    expect(wrapper.text()).toContain('Cancelado')
    wrapper.unmount()
  })

  it('CheckoutPagoView muestra reserva válida y botón Cancelar pedido, o alerta si ya venció', async () => {
    mockRoute.query = { pedido: '805' }

    vi.spyOn(api, 'get').mockImplementation((url: string) => {
      if (url.includes('/pedidos/805/mi-pedido')) {
        return Promise.resolve({
          data: {
            id: 805,
            total: 45.0,
            estado: 'pendiente',
            estado_pago: 'pendiente',
            expira_en: new Date(Date.now() + 10 * 60 * 1000).toISOString(), // 10 minutos en el futuro
            pedido_detalles: [
              {
                id: 1,
                cantidad: 3,
                precio: 15.0,
                subtotal: 45.0,
                producto: { nombre: 'Amoxicilina 500 mg' },
              },
            ],
          },
        })
      }
      return Promise.resolve({ data: [] })
    })

    const wrapper = mount(CheckoutPagoView, {
      global: {
        stubs: {
          SelectorTema: true,
          TarjetaBancariaVisual: true,
          Teleport: true,
        },
      },
    })
    await flushPromises()

    // Indicador de reserva vigente
    const indicadorReserva = wrapper.find('[data-testid="indicador-reserva-expira"]')
    expect(indicadorReserva.exists()).toBe(true)
    expect(indicadorReserva.text()).toContain('Reserva válida hasta las')

    // Botón de cancelación presente
    const btnCancelarCheckout = wrapper.find('[data-testid="boton-cancelar-pedido-checkout"]')
    expect(btnCancelarCheckout.exists()).toBe(true)

    // Si el pedido llega con estado anulado o expirado
    mockRoute.query = { pedido: '806' }
    vi.spyOn(api, 'get').mockImplementation((url: string) => {
      if (url.includes('/pedidos/806/mi-pedido')) {
        return Promise.resolve({
          data: {
            id: 806,
            total: 45.0,
            estado: 'anulado',
            estado_pago: 'cancelado',
            expira_en: new Date(Date.now() - 5 * 60 * 1000).toISOString(), // Vencido
            pedido_detalles: [],
          },
        })
      }
      return Promise.resolve({ data: [] })
    })

    const wrapperExpirado = mount(CheckoutPagoView, {
      global: {
        stubs: {
          SelectorTema: true,
          TarjetaBancariaVisual: true,
          Teleport: true,
        },
      },
    })
    await flushPromises()

    const avisoExpirado = wrapperExpirado.find('[data-testid="aviso-pedido-anulado-expirado"]')
    expect(avisoExpirado.exists()).toBe(true)
    expect(avisoExpirado.text()).toContain('Pedido cancelado')
    // No debe mostrar botón de pago
    expect(wrapperExpirado.findAll('button').some((b) => b.text().includes('Pagar'))).toBe(false)
  })

  it('useCartStore envía Idempotency-Key y renueva la clave al modificar líneas del carrito', async () => {
    const carrito = useCartStore()
    carrito.agregar(101, 2)

    const spyPost = vi.spyOn(api, 'post').mockResolvedValue({
      data: {
        pedido: { id: 901, total: 30.0 },
        comprobante: { id: 1, tipo: 'boleta', identificador: 'B001-00000001', estado_sunat: 'aceptada' },
        seguimiento: { pedido_id: 901, invitado: false },
      },
    })

    await carrito.confirmar({ modalidad_entrega: 'recojo', tipo_comprobante: 'boleta' })

    expect(spyPost).toHaveBeenCalledWith(
      '/pedidos/confirmar',
      expect.objectContaining({ items: [{ producto_id: 101, cantidad: 2 }] }),
      expect.objectContaining({
        headers: expect.objectContaining({
          'Idempotency-Key': expect.any(String),
        }),
      }),
    )

    // Tras confirmar con éxito, el carrito está vacío y la clave se reinicia
    expect(carrito.claveIdempotenciaConfirmar).toBeNull()

    // Al agregar nuevos productos, se genera una nueva intención
    carrito.agregar(102, 1)
    expect(carrito.claveIdempotenciaConfirmar).toBeNull()
  })
})
