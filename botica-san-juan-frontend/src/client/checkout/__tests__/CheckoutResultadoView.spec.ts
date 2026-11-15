import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import CheckoutResultadoView from '../CheckoutResultadoView.vue'
import { consultarEstado } from '@/services/pagos'
import { descargarPdfComprobante } from '@/services/comprobantes'

const mockRoute = {
  path: '/checkout/exitoso',
  query: { ref: 'BSJ-405-OK' },
}
const mockRouter = {
  push: vi.fn(),
  replace: vi.fn(),
}

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  useRouter: () => mockRouter,
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'string\' ? to : to.path"><slot /></a>',
  },
}))

vi.mock('@/services/pagos', () => ({
  consultarEstado: vi.fn(),
  describirMedio: () => 'AMEX terminada en 1005',
  formatearMonto: (monto: number, moneda = 'PEN') =>
    `${moneda === 'PEN' ? 'S/' : moneda} ${monto.toFixed(2)}`,
  aEstadoVisual: (estado: string | undefined) => (estado === 'pagado' ? 'PAYMENT_SUCCESS' : 'PAYMENT_ERROR'),
  rutaDelEstado: (estado: string | undefined) => (estado === 'pagado' ? '/checkout/exitoso' : '/checkout/error'),
}))

vi.mock('@/services/comprobantes', () => ({
  descargarPdfComprobante: vi.fn(),
}))

describe('CheckoutResultadoView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockRoute.path = '/checkout/exitoso'
    mockRoute.query = { ref: 'BSJ-405-OK' }
  })

  it('muestra el comprobante y permite descargar la Boleta PDF autenticada en éxito', async () => {
    vi.mocked(consultarEstado).mockResolvedValue({
      pedido_id: 405,
      es_final: true,
      pago: {
        referencia: 'BSJ-405-OK',
        estado: 'pagado',
        metodo_pago: 'card',
        marca_tarjeta: 'AMEX',
        ultimos4: '1005',
        monto: 42.5,
        moneda: 'PEN',
        pagado_en: '2026-11-13 22:10:00',
        mensaje_error: null,
      },
      comprobante: {
        id: 77,
        tipo: 'boleta',
        identificador: 'B001-00000077',
        estado_sunat: 'pendiente',
      },
    })
    vi.mocked(descargarPdfComprobante).mockResolvedValue()

    const wrapper = mount(CheckoutResultadoView)
    await flushPromises()

    expect(wrapper.find('main').classes()).toContain('bg-superficie-fondo')
    expect(wrapper.text()).toContain('¡Compra realizada con éxito!')
    expect(wrapper.text()).toContain('B001-00000077')

    const btnPdf = wrapper.findAll('button').find((b) => b.text().includes('Descargar Boleta PDF'))
    expect(btnPdf).toBeDefined()
    await btnPdf!.trigger('click')
    await flushPromises()

    expect(descargarPdfComprobante).toHaveBeenCalledWith(77, 'B001-00000077')
    expect(wrapper.find('a[href="/client/home"]').exists()).toBe(true)
  })
})
