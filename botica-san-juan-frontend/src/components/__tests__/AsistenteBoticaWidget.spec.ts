import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AsistenteBoticaWidget from '../AsistenteBoticaWidget.vue'
import { renderizarMarkdownSeguro } from '@/utils/markdownSeguro'
import api from '@/services/api'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

describe('AsistenteBoticaWidget (Bloque D)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('escapa HTML malicioso y formatea Markdown seguro', () => {
    const html = renderizarMarkdownSeguro(
      '**Amoxicilina 500mg** <script>alert(1)</script>\n- Stock: `25 uds`',
    )

    expect(html).toContain('<strong>Amoxicilina 500mg</strong>')
    expect(html).toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
    expect(html).not.toContain('<script>')
    expect(html).toContain('<ul')
    expect(html).toContain('<code')
  })

  it('muestra sugerencias distintas por rol y permite enviar y cerrar con Escape', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { habilitado: true } })
    vi.mocked(api.post).mockResolvedValue({
      data: { respuesta: 'Hay **25 unidades** de Amoxicilina.' },
    })

    const wrapperCliente = mount(AsistenteBoticaWidget, {
      props: { rol: 'cliente' },
    })
    await wrapperCliente.find('button[aria-label="Abrir asistente de botica"]').trigger('click')
    await flushPromises()

    expect(wrapperCliente.text()).toContain('¿Tienen amoxicilina 500mg disponible?')
    expect(wrapperCliente.text()).not.toContain('¿Qué lotes vencen en los próximos 30 días?')

    const wrapperAdmin = mount(AsistenteBoticaWidget, {
      props: { rol: 'administrador' },
    })
    await wrapperAdmin.find('button[aria-label="Abrir asistente de botica"]').trigger('click')
    await flushPromises()

    expect(wrapperAdmin.text()).toContain('¿Qué lotes vencen en los próximos 30 días?')

    await wrapperAdmin.find('input[aria-label="Mensaje para el asistente"]').setValue('Stock de amoxicilina')
    await wrapperAdmin.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith(
      '/asistente/mensajes',
      { mensaje: 'Stock de amoxicilina', historial: [] },
      expect.objectContaining({ signal: expect.any(AbortSignal) }),
    )
    expect(wrapperAdmin.text()).toContain('Hay 25 unidades de Amoxicilina.')

    await wrapperAdmin.trigger('keydown', { key: 'Escape' })
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()

    expect(wrapperAdmin.find('[role="dialog"]').exists()).toBe(false)

    wrapperCliente.unmount()
    wrapperAdmin.unmount()
  })

  it('muestra estado deshabilitado cuando el servidor no tiene claves configuradas', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { habilitado: false } })

    const wrapper = mount(AsistenteBoticaWidget, {
      props: { rol: 'cliente' },
    })
    await wrapper.find('button[aria-label="Abrir asistente de botica"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('El asistente está deshabilitado')
    const input = wrapper.find('input[aria-label="Mensaje para el asistente"]')
    expect((input.element as HTMLInputElement).disabled).toBe(true)

    wrapper.unmount()
  })

  it('muestra adjuntar Excel/CSV solo para administrador y permite previsualizar, confirmar y cancelar', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { habilitado: true } })

    const wrapperCliente = mount(AsistenteBoticaWidget, { props: { rol: 'cliente' } })
    await wrapperCliente.find('button[aria-label="Abrir asistente de botica"]').trigger('click')
    await flushPromises()
    expect(wrapperCliente.find('button[aria-label="Adjuntar archivo Excel o CSV"]').exists()).toBe(false)
    wrapperCliente.unmount()

    const wrapperQf = mount(AsistenteBoticaWidget, { props: { rol: 'farmaceutico' } })
    await wrapperQf.find('button[aria-label="Abrir asistente de botica"]').trigger('click')
    await flushPromises()
    expect(wrapperQf.find('button[aria-label="Adjuntar archivo Excel o CSV"]').exists()).toBe(false)
    wrapperQf.unmount()

    vi.mocked(api.post)
      .mockResolvedValueOnce({
        data: {
          formato: 'csv',
          token_confirmacion: 'tok-abc-123',
          expira_en_minutos: 15,
          resumen: {
            total_filas: 2,
            validas: 2,
            con_errores: 0,
            duplicados: 0,
            advertencias: 1,
          },
          filas_validas: [],
          duplicados: [],
          advertencias: [{ fila: 2, mensaje: 'Lote próximo a vencer' }],
          errores: [],
        },
      })
      .mockResolvedValueOnce({
        data: {
          message: 'Importación confirmada',
          resultado: {
            filas_procesadas: 2,
            productos_creados: 1,
            productos_actualizados: 1,
            lotes_creados: 2,
            lotes_actualizados: 0,
            unidades_ingresadas: 80,
            evento_id: 99,
          },
        },
      })

    const wrapperAdmin = mount(AsistenteBoticaWidget, { props: { rol: 'administrador' } })
    await wrapperAdmin.find('button[aria-label="Abrir asistente de botica"]').trigger('click')
    await flushPromises()

    expect(wrapperAdmin.find('button[aria-label="Adjuntar archivo Excel o CSV"]').exists()).toBe(true)

    const fileInput = wrapperAdmin.find('input[type="file"]')
    const archivo = new File(['producto,lote\nAmoxicilina,L1'], 'reposicion.csv', { type: 'text/csv' })
    Object.defineProperty(fileInput.element, 'files', { value: [archivo], configurable: true })
    await fileInput.trigger('change')
    await flushPromises()

    expect(api.post).toHaveBeenNthCalledWith(
      1,
      '/asistente/importaciones/previsualizar',
      expect.any(FormData),
      expect.objectContaining({ signal: expect.any(AbortSignal) }),
    )
    expect(wrapperAdmin.text()).toContain('reposicion.csv')
    expect(wrapperAdmin.text()).toContain('Confirmar importación')

    const btnConfirmar = wrapperAdmin.findAll('button').find((b) => b.text().includes('Confirmar importación'))
    expect(btnConfirmar).toBeDefined()
    await btnConfirmar!.trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenNthCalledWith(
      2,
      '/asistente/importaciones/confirmar',
      { token_confirmacion: 'tok-abc-123' },
      expect.objectContaining({ signal: expect.any(AbortSignal) }),
    )
    expect(wrapperAdmin.text()).toContain('Importación confirmada:')
    expect(wrapperAdmin.text()).toContain('80 unidades ingresadas')
    expect(wrapperAdmin.find('[aria-label="Previsualización de importación"]').exists()).toBe(false)

    // Segundo archivo para probar Cancelar
    vi.mocked(api.post)
      .mockResolvedValueOnce({
        data: {
          formato: 'xlsx',
          token_confirmacion: 'tok-cancel-456',
          expira_en_minutos: 15,
          resumen: { total_filas: 1, validas: 1, con_errores: 0, duplicados: 0, advertencias: 0 },
          filas_validas: [],
          duplicados: [],
          advertencias: [],
          errores: [],
        },
      })
      .mockResolvedValueOnce({ data: { cancelado: true } })

    const archivo2 = new File(['PK'], 'lote.xlsx', {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    Object.defineProperty(fileInput.element, 'files', { value: [archivo2], configurable: true })
    await fileInput.trigger('change')
    await flushPromises()

    const btnCancelar = wrapperAdmin.findAll('button').find((b) => b.text().trim() === 'Cancelar')
    await btnCancelar!.trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenLastCalledWith('/asistente/importaciones/cancelar', {
      token_confirmacion: 'tok-cancel-456',
    })
    expect(wrapperAdmin.find('[aria-label="Previsualización de importación"]').exists()).toBe(false)

    wrapperAdmin.unmount()
  })
})
