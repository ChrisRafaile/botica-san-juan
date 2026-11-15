import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ClientComprobantesView from '../ClientComprobantesView.vue'
import api from '@/services/api'

describe('ClientComprobantesView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
      if (url === '/comprobantes/mios') {
        return {
          data: {
            data: [
              {
                id: 88,
                tipo: 'boleta',
                serie: 'B001',
                numero: '00000012',
                correlativo: 'B001-00000012',
                total: 50,
                fecha_emision: '2026-11-13',
                estado_sunat: 'aceptado',
                pedido: { id: 501, fecha: '2026-11-13', estado: 'completado' },
              },
              {
                id: 89,
                tipo: 'factura',
                serie: 'F001',
                numero: '00000003',
                correlativo: 'F001-00000003',
                total: 118,
                fecha_emision: '2026-11-14',
                estado_sunat: 'aceptado',
                pedido: { id: 502, fecha: '2026-11-14', estado: 'completado' },
              },
            ],
            total: 2,
          },
        }
      }
      if (url === '/comprobantes/89') {
        return {
          data: {
            data: {
              comprobante: {
                tipo: 'factura',
                serie: 'F001',
                numero: '00000003',
                identificador: 'F001-00000003',
                fecha_emision: '2026-11-14',
                estado_sunat: 'aceptado',
              },
              cliente: { nombre: 'Clínica Norte SAC', documento: '20555111222' },
              items: [
                { descripcion: 'Amoxicilina 500 mg', cantidad: 2, precio_unitario: 59, subtotal: 118 },
              ],
              totales: { subtotal_gravado: 100, subtotal_exonerado: 0, subtotal_inafecto: 0, igv: 18, tasa_igv: 0.18, total: 118 },
              leyenda: 'Los precios incluyen IGV.',
            },
          },
        }
      }
      if (url === '/facturacion/documentos/88/pdf') {
        return { data: new Blob(['%PDF-1.4 mock'], { type: 'application/pdf' }) }
      }
      return { data: {} }
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  it('filtra por buscador y tipo, previsualiza el detalle y descarga el PDF como Blob', async () => {
    const crearUrlSpy = vi.fn(() => 'blob:pdf-88')
    const revocarUrlSpy = vi.fn()
    const clickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
    vi.stubGlobal('URL', { createObjectURL: crearUrlSpy, revokeObjectURL: revocarUrlSpy })

    const w = mount(ClientComprobantesView)
    await flushPromises()

    expect(w.text()).toContain('B001-00000012')
    expect(w.text()).toContain('F001-00000003')

    const inputBuscar = w.find('input[type="search"]')
    await inputBuscar.setValue('F001')
    expect(w.text()).not.toContain('B001-00000012')
    expect(w.text()).toContain('F001-00000003')

    const btnVistaPrevia = w.findAll('button').find((b) => b.text().includes('Vista previa'))
    await btnVistaPrevia!.trigger('click')
    await flushPromises()

    const dialog = w.find('[role="dialog"]')
    expect(dialog.exists()).toBe(true)
    expect(dialog.text()).toContain('Clínica Norte SAC')
    expect(dialog.text()).toContain('Amoxicilina 500 mg')

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()
    expect(w.find('[role="dialog"]').exists()).toBe(false)

    await inputBuscar.setValue('')
    const btnPdfBoleta = w.findAll('button').find((b) => b.text().includes('Descargar B001-00000012'))
    expect(btnPdfBoleta).toBeDefined()
    await btnPdfBoleta!.trigger('click')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/facturacion/documentos/88/pdf', { responseType: 'blob' })
    expect(crearUrlSpy).toHaveBeenCalledTimes(1)
    expect(clickSpy).toHaveBeenCalledTimes(1)
    expect(revocarUrlSpy).toHaveBeenCalledWith('blob:pdf-88')
  })
})
