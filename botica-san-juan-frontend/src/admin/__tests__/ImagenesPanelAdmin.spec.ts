import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import api from '@/services/api'
import AdminProductsView from '../views/AdminProductsView.vue'
import AdminInventoryView from '../views/AdminInventoryView.vue'
import AdminOrdersView from '../views/AdminOrdersView.vue'
import ProductModal from '../components/ProductModal.vue'

vi.mock('vue-router', () => ({
  useRoute: () => ({ name: 'admin-inventory', path: '/admin/inventory', query: {}, params: {} }),
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}))

const productoConFoto = {
  id: 69,
  nombre: 'AMOXICILINA 500 mg',
  concentracion: '500 mg',
  adicional: 'Caja x 100',
  laboratorio: 'PORTUGAL',
  presentacion: 'Caja Envase Blister Cápsulas',
  tipo: 'CAPSULA',
  categoria_id: 1,
  categoria: { id: 1, nombre: 'Antibióticos' },
  subcategoria_id: null,
  subcategoria: null,
  stock: 80,
  stock_disponible: 80,
  stock_no_vendible: 0,
  stock_minimo: 10,
  stock_reposicion: 20,
  precio: '1.80',
  imagen: 'storage/productos/38530.webp',
  codigo_barras: '7750000111222',
}

const productoSinFoto = {
  id: 710,
  nombre: 'PARACETAMOL 500 mg',
  concentracion: '500 mg',
  adicional: 'Caja x 100',
  laboratorio: 'MEDIFARMA',
  presentacion: 'Caja Envase Blister Tabletas',
  tipo: 'TABLETA',
  categoria_id: 2,
  categoria: { id: 2, nombre: 'Analgésicos' },
  subcategoria_id: null,
  subcategoria: null,
  stock: 150,
  stock_disponible: 150,
  stock_no_vendible: 0,
  stock_minimo: 10,
  stock_reposicion: 30,
  precio: '0.60',
  imagen: 'images/default_image.png',
  codigo_barras: '7750000333444',
}

describe('Panel admin — miniaturas reales y subida segura de imágenes', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()
  })

  it('AdminProductsView muestra miniatura real lazy con tamaño fijo y evita default_image', async () => {
    vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
      if (url.startsWith('/productos/resumen')) {
        return {
          data: {
            data: { total: 2, agotados: 0, criticos: 0, bajos: 0, normales: 2 },
            umbrales_sospechosos: false,
          },
        } as never
      }
      if (url.startsWith('/productos')) {
        return {
          data: {
            data: [productoConFoto, productoSinFoto],
            current_page: 1,
            last_page: 1,
            total: 2,
          },
        } as never
      }
      if (url.startsWith('/categorias')) {
        return { data: [] } as never
      }
      if (url.startsWith('/subcategorias')) {
        return { data: [] } as never
      }
      return { data: {} } as never
    })

    const wrapper = mount(AdminProductsView, {
      global: { stubs: { Teleport: true, ProductModal: true } },
    })
    await flushPromises()

    const imgReal = wrapper.find('img[src*="38530.webp"]')
    expect(imgReal.exists()).toBe(true)
    expect(imgReal.attributes('loading')).toBe('lazy')
    expect(imgReal.classes()).toContain('h-10')
    expect(imgReal.classes()).toContain('w-10')
    expect(wrapper.find('img[src*="default_image"]').exists()).toBe(false)
  })

  describe('aviso de umbrales sin salto de maquetación', () => {
    const TEXTO_AVISO = 'La mayoría del catálogo aparece por reponer'

    function mockResumen(umbralesSospechosos: boolean) {
      vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
        if (url.startsWith('/productos/resumen')) {
          return {
            data: {
              data: { total: 884, agotados: 300, criticos: 400, bajos: 100, normales: 84 },
              umbrales_sospechosos: umbralesSospechosos,
            },
          } as never
        }
        if (url.startsWith('/productos')) {
          return { data: { data: [productoConFoto], current_page: 1, last_page: 1, total: 1 } } as never
        }
        return { data: [] } as never
      })
    }

    const montar = () =>
      mount(AdminProductsView, { global: { stubs: { Teleport: true, ProductModal: true } } })

    const aviso = (w: ReturnType<typeof montar>) =>
      w.findAll('p').find((p) => p.text().includes(TEXTO_AVISO))

    beforeEach(() => {
      localStorage.clear()
    })

    it('en la primera visita reserva el hueco antes de saber la respuesta y luego lo rellena', async () => {
      mockResumen(true)
      const wrapper = montar()

      // Antes de que responda la red: el párrafo ocupa su sitio, oculto.
      expect(aviso(wrapper)?.classes()).toContain('invisible')
      expect(aviso(wrapper)?.attributes('aria-hidden')).toBe('true')

      await flushPromises()

      expect(aviso(wrapper)?.classes()).not.toContain('invisible')
      expect(localStorage.getItem('botica:admin:umbrales-sospechosos')).toBe('1')
    })

    it('con el valor recordado pinta el aviso desde el primer fotograma', () => {
      localStorage.setItem('botica:admin:umbrales-sospechosos', '1')
      mockResumen(true)

      expect(aviso(montar())?.classes()).not.toContain('invisible')
    })

    it('si se recuerda que no hace falta, no reserva nada', async () => {
      localStorage.setItem('botica:admin:umbrales-sospechosos', '0')
      mockResumen(false)
      const wrapper = montar()

      expect(aviso(wrapper)).toBeUndefined()
      await flushPromises()
      expect(aviso(wrapper)).toBeUndefined()
    })
  })

  it('AdminInventoryView renderiza miniaturas reales en tabla y tarjetas móviles', async () => {
    vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
      if (url.startsWith('/productos/resumen')) {
        return {
          data: {
            data: { total: 2, agotados: 0, criticos: 0, bajos: 0, normales: 2 },
            umbrales_sospechosos: false,
          },
        } as never
      }
      if (url.startsWith('/productos')) {
        return {
          data: {
            data: [productoConFoto, productoSinFoto],
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 2,
          },
        } as never
      }
      if (url.startsWith('/categorias')) {
        return { data: [] } as never
      }
      return { data: {} } as never
    })

    const wrapper = mount(AdminInventoryView)
    await flushPromises()

    const imagenes = wrapper.findAll('img[src*="38530.webp"]')
    expect(imagenes.length).toBe(2) // Una en tarjeta móvil y otra en tabla de escritorio
    for (const img of imagenes) {
      expect(img.attributes('loading')).toBe('lazy')
      expect(img.classes()).toContain('h-10')
      expect(img.classes()).toContain('w-10')
    }
    expect(wrapper.find('img[src*="default_image"]').exists()).toBe(false)
  })

  it('AdminOrdersView muestra la foto de cada producto dentro del detalle del pedido', async () => {
    vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
      if (url.startsWith('/pedidos/resumen')) {
        return {
          data: {
            data: {
              portal: {
                pendientes: { pedidos: 1, importe: 4.2 },
                confirmados: { pedidos: 0, importe: 0 },
                mas_antiguo_dias: 1,
                abiertos_sin_lineas: 0,
              },
              mostrador: { hoy: { pedidos: 0, importe: 0 } },
              total_registros: 1,
            },
          },
        } as never
      }
      if (url.startsWith('/pedidos')) {
        return {
          data: {
            data: [
              {
                id: 501,
                usuario_id: 9,
                origen: 'web',
                fecha_pedido: '2026-11-15T10:00:00Z',
                total: '4.20',
                estado: 'pendiente',
                usuario: { id: 9, nombre: 'Lucía Pérez', email: 'lucia@correo.pe' },
                pedido_detalles: [
                  {
                    id: 1,
                    cantidad: 2,
                    subtotal: '3.60',
                    producto: {
                      id: 69,
                      nombre: 'AMOXICILINA 500 mg',
                      imagen: 'storage/productos/38530.webp',
                    },
                  },
                  {
                    id: 2,
                    cantidad: 1,
                    subtotal: '0.60',
                    producto: {
                      id: 710,
                      nombre: 'PARACETAMOL 500 mg',
                      imagen: 'images/default_image.png',
                    },
                  },
                ],
              },
            ],
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 1,
          },
        } as never
      }
      return { data: {} } as never
    })

    const wrapper = mount(AdminOrdersView, {
      global: { stubs: { Teleport: true } },
    })
    await flushPromises()

    const botonDetalle = wrapper.findAll('button').find(b => b.attributes('aria-label')?.includes('detalle'))
      ?? wrapper.findAll('button').find(b => b.text().includes('Ver'))
    expect(botonDetalle).toBeTruthy()
    await botonDetalle!.trigger('click')
    await flushPromises()

    const imgDetalle = wrapper.find('img[src*="38530.webp"]')
    expect(imgDetalle.exists()).toBe(true)
    expect(imgDetalle.attributes('loading')).toBe('lazy')
    expect(imgDetalle.classes()).toContain('h-12')
    expect(imgDetalle.classes()).toContain('w-12')
    expect(wrapper.find('img[src*="default_image"]').exists()).toBe(false)
  })

  it('ProductModal valida formato/peso y envía POST + _method=PUT sin Content-Type manual', async () => {
    const postSpy = vi.spyOn(api, 'post').mockResolvedValue({ data: productoConFoto } as never)

    const wrapper = mount(ProductModal, {
      props: {
        isOpen: true,
        product: productoConFoto,
        categories: [{ id: 1, name: 'Antibióticos' }],
        subcategories: [],
      },
      global: { stubs: { Teleport: true, PhotoCapture: true } },
    })
    await flushPromises()

    // Vista previa de la foto actual al abrir en edición
    expect(wrapper.find('img[src*="38530.webp"]').exists()).toBe(true)

    // Rechaza archivo no permitido inline sin alert()
    const input = wrapper.find('input[type="file"]')
    const invalido = new File(['texto'], 'malo.txt', { type: 'text/plain' })
    Object.defineProperty(input.element, 'files', { value: [invalido], configurable: true })
    await input.trigger('change')
    expect(wrapper.text()).toContain('Formato no compatible')

    // Rechaza archivo mayor a 2 MB inline
    const pesada = new File([new Uint8Array(2 * 1024 * 1024 + 10)], 'pesada.jpg', { type: 'image/jpeg' })
    Object.defineProperty(input.element, 'files', { value: [pesada], configurable: true })
    await input.trigger('change')
    expect(wrapper.text()).toContain('no puede superar los 2 MB')

    // Sube archivo WebP válido y lo envía con _method=PUT
    const valida = new File(['webp-bytes'], 'caja.webp', { type: 'image/webp' })
    Object.defineProperty(input.element, 'files', { value: [valida], configurable: true })
    await input.trigger('change')
    await wrapper.find('form#formulario-producto').trigger('submit.prevent')
    await flushPromises()

    expect(postSpy).toHaveBeenCalledTimes(1)
    const primeraLlamada = postSpy.mock.calls[0]
    expect(primeraLlamada).toBeDefined()
    expect(primeraLlamada![0]).toBe('/productos/69')
    expect(primeraLlamada![1]).toBeInstanceOf(FormData)
    expect((primeraLlamada![1] as FormData).get('_method')).toBe('PUT')
    expect((primeraLlamada![1] as FormData).get('imagen')).toBe(valida)
    expect(primeraLlamada![2]).toBeUndefined()

    // Quitar imagen marca quitar_imagen=1 al guardar por POST + _method=PUT sin headers manuales
    await wrapper.find('button[aria-label="Quitar imagen"]').trigger('click')
    await wrapper.find('form#formulario-producto').trigger('submit.prevent')
    await flushPromises()

    expect(postSpy).toHaveBeenCalledTimes(2)
    const segundaLlamada = postSpy.mock.calls[1]
    expect(segundaLlamada).toBeDefined()
    const [url, cuerpo, config] = segundaLlamada!
    expect(url).toBe('/productos/69')
    expect(cuerpo).toBeInstanceOf(FormData)
    expect((cuerpo as FormData).get('_method')).toBe('PUT')
    expect((cuerpo as FormData).get('quitar_imagen')).toBe('1')
    expect(config).toBeUndefined()
  })
})
