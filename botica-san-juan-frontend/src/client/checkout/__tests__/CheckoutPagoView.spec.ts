import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import CheckoutPagoView from '../CheckoutPagoView.vue'
import api from '@/services/api'
import { iniciarPago, simularPago } from '@/services/pagos'

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
  simularPago: vi.fn(),
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
        observacion: 'Av. Próceres de la Independencia 1840, SJL',
        estado_pago: 'pendiente',
        comprobante_electronico: {
          id: 19,
          tipo_comprobante: 'factura',
          serie: 'F001',
          numero: 42,
          estado_sunat: 'pendiente',
        },
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

  it('usa tokens semánticos, variantes dark y carga el pedido desde mi-pedido con entrega y comprobante', async () => {
    const wrapper = mount(CheckoutPagoView, {
      global: {
        stubs: {
          TarjetaBancariaVisual: true,
        },
      },
    })
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/pedidos/405/mi-pedido')

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
    expect(resumen.text()).toContain('Factura F001-00000042')
    expect(resumen.text()).toContain('Av. Próceres de la Independencia 1840, SJL')
  })

  it('exige completar la tarjeta 3D en modo simulado y procesa el cobro sin enviar PAN ni CVV', async () => {
    vi.mocked(iniciarPago).mockResolvedValue({
      ok: true,
      message: 'Operación creada en modo simulado.',
      pago: {
        referencia: 'BSJ-405-SIM',
        estado: 'procesando',
        metodo_pago: null,
        marca_tarjeta: null,
        ultimos4: null,
        monto: 42.5,
        moneda: 'PEN',
        pagado_en: null,
        mensaje_error: null,
      },
      checkout: {
        form_token: 'simulado:abc',
        public_key: 'llave-simulada',
        modo: 'simulado',
        client_url: '',
        client_theme: 'neon',
      },
    })

    vi.mocked(simularPago).mockResolvedValue({
      ok: true,
      message: 'Notificacion aplicada.',
      pedido_id: 405,
      es_final: true,
      pago: {
        referencia: 'BSJ-405-SIM',
        estado: 'pagado',
        metodo_pago: 'card',
        marca_tarjeta: 'AMEX',
        ultimos4: '1005',
        monto: 42.5,
        moneda: 'PEN',
        pagado_en: '2026-11-13 22:00:00',
        mensaje_error: null,
      },
    })

    const wrapper = mount(CheckoutPagoView)
    await flushPromises()

    // Primer intento sin datos en tarjeta: muestra guía y no simula cobro
    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(simularPago).not.toHaveBeenCalled()
    expect(wrapper.find('[role="alert"]').text()).toContain('Completa los datos en la tarjeta 3D')

    // Rellenar tarjeta Amex completa en memoria
    await wrapper.find('#tarjeta-numero').setValue('378282246310005')
    await wrapper.find('#tarjeta-titular').setValue('LUCIA MENDOZA')
    await wrapper.find('#tarjeta-vencimiento').setValue('0829')
    await wrapper.find('#tarjeta-cvv').setValue('4921')
    await flushPromises()

    const botonPagar = wrapper.findAll('button').find((b) => b.text().includes('Pagar'))
    expect(botonPagar).toBeDefined()
    await botonPagar!.trigger('click')
    await flushPromises()

    expect(simularPago).toHaveBeenCalledWith('BSJ-405-SIM', {
      resultado: 'aprobar',
      marca_tarjeta: 'amex',
    })
    expect(mockRouter.push).toHaveBeenCalledWith({
      path: '/checkout/procesando',
      query: { ref: 'BSJ-405-SIM' },
    })
  })
})
