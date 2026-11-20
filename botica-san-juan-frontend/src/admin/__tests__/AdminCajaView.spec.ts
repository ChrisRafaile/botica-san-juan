import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AdminCajaView from '../views/AdminCajaView.vue'
import api from '@/services/api'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

describe('AdminCajaView (P2)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('permite abrir caja, registrar movimiento y calcular diferencia al cerrar', async () => {
    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url === '/caja/actual') {
        return { data: { sesion: null, arqueo: null } }
      }
      if (url === '/caja/historial') {
        return {
          data: {
            kpis: { total_sesiones: 1, total_ventas_efectivo: 20, diferencia_acumulada: -5 },
            sesiones: [
              {
                id: 1,
                usuario_id: 1,
                cajero: 'María López',
                estado: 'cerrada',
                monto_inicial: 100,
                ventas_efectivo: 20,
                total_ingresos: 30,
                total_egresos: 15,
                monto_esperado: 135,
                monto_contado: 130,
                diferencia: -5,
                observacion_apertura: null,
                observacion_cierre: null,
                abierta_en: '2026-11-18T08:00:00Z',
                cerrada_en: '2026-11-18T18:00:00Z',
              },
            ],
          },
        }
      }
      return { data: {} }
    })

    const sesionAbierta = {
      id: 1,
      usuario_id: 1,
      cajero: 'María López',
      estado: 'abierta',
      monto_inicial: 100,
      ventas_efectivo: 20,
      total_ingresos: 0,
      total_egresos: 0,
      monto_esperado: null,
      monto_contado: null,
      diferencia: null,
      observacion_apertura: 'Turno mañana',
      observacion_cierre: null,
      abierta_en: '2026-11-18T08:00:00Z',
      cerrada_en: null,
      movimientos: [],
    }

    vi.mocked(api.post).mockImplementation(async (url: string) => {
      if (url === '/caja/abrir') {
        return {
          data: {
            sesion: sesionAbierta,
            arqueo: {
              monto_inicial: 100,
              ventas_efectivo: 20,
              ingresos: 0,
              egresos: 0,
              monto_esperado: 120,
              movimientos_count: 1,
            },
          },
        }
      }
      return { data: {} }
    })

    const wrapper = mount(AdminCajaView)
    await flushPromises()

    expect(wrapper.text()).toContain('Abrir sesión de caja')

    await wrapper.find('#monto-inicial').setValue('100')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/caja/abrir', {
      monto_inicial: 100,
      observacion_apertura: null,
    })
    expect(wrapper.text()).toContain('Esperado en caja')
    expect(wrapper.text()).toContain('S/ 120.00')

    // Simular monto contado menor -> diferencia negativa en vivo
    await wrapper.find('#monto-contado').setValue('115')
    expect(wrapper.text()).toContain('S/ -5.00')

    // Cambiar a historial
    const tabHistorial = wrapper.findAll('[role="tab"]').find((el) => el.text().includes('Historial'))
    await tabHistorial!.trigger('click')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/caja/historial')
    expect(wrapper.text()).toContain('María López')
  })
})
