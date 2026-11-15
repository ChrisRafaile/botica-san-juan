import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import CheckoutPagoView from '../CheckoutPagoView.vue'
import api from '@/services/api'

const mockRoute = {
  query: { pedido: '405' },
}
const mockRouter = {
  push: vi.fn(),
  replace: vi.fn(),
}

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  useRouter: () => mockRouter,
}))

vi.mock('@/services/izipay', () => ({
  montarFormulario: vi.fn(),
}))

vi.mock('@/services/pagos', () => ({
  formatearMonto: (monto: number, moneda = 'PEN') =>
    `${moneda === 'PEN' ? 'S/' : moneda} ${monto.toFixed(2)}`,
  iniciarPago: vi.fn(),
  validarRetorno: vi.fn(),
  rutaDelEstado: vi.fn(() => '/'),
}))

describe('CheckoutPagoView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.spyOn(api, 'get').mockResolvedValue({
      data: {
        id: 405,
        total: 42.5,
        moneda: 'PEN',
        address: 'Av. Próceres de la Independencia 1840, SJL',
        estado_pago: 'pendiente',
        pedido_detalles: [
          {
            id: 1,
            cantidad: 2,
            precio: 14.0,
            precio_unitario: 14.0,
            subtotal: 28.0,
            producto: { nombre: 'Amoxicilina 500 mg' },
          },
          {
            id: 2,
            cantidad: 1,
            precio: 14.5,
            precio_unitario: 14.5,
            subtotal: 14.5,
            producto: { nombre: 'Paracetamol 500 mg' },
          },
        ],
      },
    })
  })

  it('usa tokens semánticos y variantes dark en el fondo y alinea arriba la columna de resumen', async () => {
    const wrapper = mount(CheckoutPagoView, {
      global: {
        stubs: {
          TarjetaBancariaVisual: true,
        },
      },
    })
    await flushPromises()

    const main = wrapper.find('main')
    expect(main.classes()).toContain('bg-superficie-fondo')
    expect(main.classes()).toContain('dark:from-superficie-fondo')
    expect(main.classes()).toContain('dark:to-superficie-hundida')

    const gridPrincipal = main.find('.grid')
    expect(gridPrincipal.classes()).toContain('items-start')

    const resumen = wrapper.find('[data-testid="seccion-resumen-pedido"]')
    expect(resumen.exists()).toBe(true)
    expect(resumen.classes()).toContain('self-start')
    expect(resumen.text()).toContain('Amoxicilina 500 mg')
    expect(resumen.text()).toContain('Paracetamol 500 mg')
  })
})
