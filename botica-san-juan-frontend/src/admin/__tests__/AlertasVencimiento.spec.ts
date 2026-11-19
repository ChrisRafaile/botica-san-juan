import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import PanelAlertasVencimiento from '../components/PanelAlertasVencimiento.vue'
import { etiquetaVencimiento, tonoVencimiento } from '../utils/vencimientos'
import api from '@/services/api'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
  },
}))

const respuestaMock = {
  resumen: {
    total: 3,
    vencido: 1,
    '<=30': 1,
    '<=60': 0,
    '<=90': 1,
    vigente: 0,
    valor_en_riesgo_90d: 145.5,
    valor_vencido: 32.0,
  },
  lotes: [
    {
      id: 1,
      producto_id: 10,
      codigo_lote: 'AMOX-001',
      fecha_vencimiento: '2026-11-10',
      dias_para_vencer: -8,
      bucket: 'vencido',
      nivel_vencimiento: 'vencido',
      cantidad_actual: 16,
      costo_unitario: 2.0,
      valor_en_riesgo: 32.0,
      estado: 'activo',
      producto: {
        id: 10,
        nombre: 'Amoxicilina 500mg',
        concentracion: '500mg',
        presentacion: 'Cápsula',
        laboratorio: 'Portugal',
      },
    },
    {
      id: 2,
      producto_id: 11,
      codigo_lote: 'PARA-018',
      fecha_vencimiento: '2026-12-06',
      dias_para_vencer: 18,
      bucket: '<=30',
      nivel_vencimiento: 'critico',
      cantidad_actual: 20,
      costo_unitario: 5.0,
      valor_en_riesgo: 100.0,
      estado: 'activo',
      producto: {
        id: 11,
        nombre: 'Paracetamol 500mg',
        concentracion: '500mg',
        presentacion: 'Tableta',
        laboratorio: 'Genfar',
      },
    },
  ],
}

describe('Alertas de vencimiento (P1)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(api.get).mockResolvedValue({ data: respuestaMock })
  })

  it('mapea días y niveles al semáforo de vencimiento', () => {
    expect(etiquetaVencimiento(-2)).toBe('VENCIDO')
    expect(etiquetaVencimiento(18)).toBe('18d')
    expect(tonoVencimiento(-2, 'vencido')).toBe('vence-critico')
    expect(tonoVencimiento(18, 'critico')).toBe('vence-critico')
    expect(tonoVencimiento(44, 'alto')).toBe('vence-alto')
    expect(tonoVencimiento(76, 'medio')).toBe('vence-medio')
    expect(tonoVencimiento(180, 'vigente')).toBe('vigente')
  })

  it('muestra KPI Valor en riesgo, badges y filtra al hacer clic en un bucket', async () => {
    const wrapper = mount(PanelAlertasVencimiento)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/inventario/vencimientos', { params: undefined })
    expect(wrapper.text()).toContain('Valor en riesgo (90d)')
    expect(wrapper.text()).toContain('S/ 145.50')
    expect(wrapper.text()).toContain('VENCIDO')
    expect(wrapper.text()).toContain('18d')
    expect(wrapper.text()).toContain('Amoxicilina 500mg')

    const tab30 = wrapper.findAll('[role="tab"]').find((el) => el.text().includes('≤ 30 días'))
    expect(tab30).toBeDefined()
    await tab30!.trigger('click')
    await flushPromises()

    expect(api.get).toHaveBeenLastCalledWith('/inventario/vencimientos', {
      params: { bucket: '<=30' },
    })
  })
})
