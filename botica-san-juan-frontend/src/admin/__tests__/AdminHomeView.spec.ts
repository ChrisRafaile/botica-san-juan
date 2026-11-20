import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AdminHomeView from '../AdminHomeView.vue'
import api from '@/services/api'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
  },
}))

const tableroMock = {
  ventas: {
    hoy: { importe: 250.5, comprobantes: 8 },
    ayer: { importe: 200.0, comprobantes: 6 },
    diferencia: 50.5,
  },
  vencimientos: {
    vencidos: { lotes: 1, unidades: 10 },
    en_30_dias: { lotes: 2, unidades: 30 },
    en_90_dias: { lotes: 3, unidades: 45 },
    lotes_con_fecha: 6,
    lotes_totales: 10,
  },
  reposicion: { agotados: 2, criticos: 4, bajos: 6 },
  catalogo: { productos: 100, lotes: 25 },
  ultimas: [
    {
      id: 101,
      fecha: '2026-11-18T14:30:00Z',
      total: 45.0,
      medio_pago: 'yape',
      origen: 'pos',
      cliente: 'Cliente eventual',
    },
  ],
  mas_vendidos: [
    { id: 1, nombre: 'Amoxicilina', concentracion: '500mg', unidades: 32, importe: 320.0 },
  ],
  comparativa_semanal: {
    dias: [
      { etiqueta: 'Jue', fecha: '2026-11-12', actual: 100, anterior: 80 },
      { etiqueta: 'Vie', fecha: '2026-11-13', actual: 120, anterior: 90 },
      { etiqueta: 'Sáb', fecha: '2026-11-14', actual: 150, anterior: 110 },
      { etiqueta: 'Dom', fecha: '2026-11-15', actual: 90, anterior: 70 },
      { etiqueta: 'Lun', fecha: '2026-11-16', actual: 140, anterior: 100 },
      { etiqueta: 'Mar', fecha: '2026-11-17', actual: 200, anterior: 160 },
      { etiqueta: 'Mié', fecha: '2026-11-18', actual: 250.5, anterior: 180 },
    ],
    total_actual: 1050.5,
    total_anterior: 790.0,
    variacion_absoluta: 260.5,
  },
  metodos_pago: [
    { medio: 'efectivo', comprobantes: 15, importe: 600.0, porcentaje: 60.0 },
    { medio: 'yape', comprobantes: 10, importe: 400.0, porcentaje: 40.0 },
  ],
}

describe('AdminHomeView (P5)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    vi.mocked(api.get).mockResolvedValue({ data: tableroMock })
  })

  it('muestra comparativa semanal, Top 5 productos, métodos de pago y oculta montos', async () => {
    const wrapper = mount(AdminHomeView, {
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
          },
        },
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Últimos 7 días vs. semana anterior')
    expect(wrapper.text()).toContain('Top 5 productos')
    expect(wrapper.text()).toContain('Amoxicilina')
    expect(wrapper.text()).toContain('Métodos de pago (30 días)')
    expect(wrapper.text()).toContain('60%')
    expect(wrapper.text()).toContain('250.50')

    const botonOcultar = wrapper.find('button[aria-pressed]')
    expect(botonOcultar.text()).toContain('Ocultar montos')
    await botonOcultar.trigger('click')

    expect(localStorage.getItem('botica_ocultar_montos')).toBe('1')
    expect(wrapper.text()).toContain('Mostrar montos')
    expect(wrapper.text()).toContain('S/ ••••')
    expect(wrapper.text()).not.toContain('250.50')
  })
})
