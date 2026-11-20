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
      props: { rol: 'administrador' },
    })
    await wrapper.find('button[aria-label="Abrir asistente de botica"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('El asistente está deshabilitado')
    const input = wrapper.find('input[aria-label="Mensaje para el asistente"]')
    expect((input.element as HTMLInputElement).disabled).toBe(true)

    wrapper.unmount()
  })
})
