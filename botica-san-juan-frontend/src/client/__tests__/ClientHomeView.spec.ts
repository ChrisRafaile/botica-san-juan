import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import ClientHomeView from '../ClientHomeView.vue'
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

describe('ClientHomeView.vue', () => {
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

    vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
      if (url === '/pedidos/mi-resumen') {
        return {
          data: {
            data: {
              pedidos: { abiertos: 1, completados: 0, anulados: 0, total: 1 },
              gasto: { total: 50, ultimos_30_dias: 50 },
              ultimo_pedido: { id: 501, fecha: '2026-11-13', estado: 'pendiente', total: 50 },
              comprobantes: 1,
            },
          },
        }
      }
      if (url === '/pedidos/usuario/9') {
        return {
          data: [
            {
              id: 501,
              fecha: '2026-11-13',
              estado: 'pendiente',
              estado_pago: 'pagado',
              observacion: 'Av. Próceres 1840, SJL',
              total: 50,
            },
          ],
        }
      }
      if (url === '/comprobantes/mios') {
        return {
          data: {
            data: [
              {
                id: 88,
                correlativo: 'B001-00000012',
                total: 50,
                fecha_emision: '2026-11-13',
              },
            ],
          },
        }
      }
      if (url === '/facturacion/documentos/88/pdf') {
        return {
          data: new Blob(['%PDF-1.4 mock'], { type: 'application/pdf' }),
        }
      }
      return { data: {} }
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  function montar() {
    return mount(ClientHomeView, {
      global: {
        stubs: {
          DialogoConfirmacion: true,
          DistintivoReceta: true,
          RecetaDelPedido: true,
          TarjetaResumen: {
            props: ['etiqueta', 'valor'],
            template: '<div><span>{{ etiqueta }}</span><span>{{ valor }}</span></div>',
          },
        },
      },
    })
  }

  it('muestra acceso directo al carrito, lista últimos pedidos y descarga la Boleta PDF mediante Blob autenticado', async () => {
    const carrito = useCartStore()
    carrito.agregar(101, 2, 10)

    const crearUrlSpy = vi.fn(() => 'blob:mock-pdf-88')
    const revocarUrlSpy = vi.fn()
    // Evita el aviso de jsdom ("Not implemented: navigation") al hacer click sobre <a download>
    const clickEnlaceSpy = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(() => {})
    vi.stubGlobal('URL', {
      createObjectURL: crearUrlSpy,
      revokeObjectURL: revocarUrlSpy,
    })

    const wrapper = montar()
    await flushPromises()

    expect(wrapper.text()).toContain('Finalizar compra (2)')
    expect(wrapper.text()).toContain('Pedido #501')
    expect(wrapper.text()).toContain('Pagado')
    expect(wrapper.text()).toContain('Envío: Av. Próceres 1840, SJL')
    expect(wrapper.text()).toContain('B001-00000012')

    // Ya no es un enlace directo <a href=".../pdf"> sin token
    expect(wrapper.find('a[href*="/facturacion/documentos/"]').exists()).toBe(false)

    const botonPdf = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Descargar el comprobante B001-00000012 en PDF'))
    expect(botonPdf).toBeDefined()

    await botonPdf!.trigger('click')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/facturacion/documentos/88/pdf', {
      responseType: 'blob',
    })
    expect(crearUrlSpy).toHaveBeenCalledTimes(1)
    expect(clickEnlaceSpy).toHaveBeenCalledTimes(1)
    expect(revocarUrlSpy).toHaveBeenCalledWith('blob:mock-pdf-88')
  })

  it('informa con role="alert" si falla la descarga del PDF', async () => {
    vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
      if (url === '/pedidos/mi-resumen') {
        return {
          data: {
            data: {
              pedidos: { abiertos: 0, completados: 0, anulados: 0, total: 0 },
              gasto: { total: 0, ultimos_30_dias: 0 },
              ultimo_pedido: null,
              comprobantes: 1,
            },
          },
        }
      }
      if (url === '/pedidos/usuario/9') return { data: [] }
      if (url === '/comprobantes/mios') {
        return {
          data: {
            data: [{ id: 88, correlativo: 'B001-00000012', total: 50, fecha_emision: '2026-11-13' }],
          },
        }
      }
      if (url === '/facturacion/documentos/88/pdf') {
        throw new Error('HTTP 401')
      }
      return { data: {} }
    })

    const wrapper = montar()
    await flushPromises()

    const botonPdf = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Descargar el comprobante B001-00000012 en PDF'))
    await botonPdf!.trigger('click')
    await flushPromises()

    const alerta = wrapper.find('[role="alert"]')
    expect(alerta.exists()).toBe(true)
    expect(alerta.text()).toContain('B001-00000012')
  })
})
