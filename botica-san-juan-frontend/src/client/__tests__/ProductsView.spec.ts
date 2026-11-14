import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import ProductsView from '../ProductsView.vue'
import productsService, { type Product } from '@/services/products'

vi.mock('vue-router', () => ({
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'string\' ? to : \'#\'"><slot /></a>',
  },
  useRoute: () => ({ query: {}, params: {} }),
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}))

const amoxicilinaConFoto: Product = {
  id: 69,
  nombre: 'AMOXICILINA',
  concentracion: '500 mg',
  adicional: 'Caja x 100',
  laboratorio: 'PORTUGAL',
  presentacion: 'Caja Envase Blister Cápsulas',
  tipo: 'CAPSULA',
  precio: 1.8,
  stock: 80,
  imagen: 'storage/productos/38530.webp',
  requiere_receta: true,
}

const paracetamolSinFoto: Product = {
  id: 710,
  nombre: 'PARACETAMOL',
  concentracion: '500 mg',
  adicional: 'Caja x 100',
  laboratorio: 'MEDIFARMA',
  presentacion: 'Caja Envase Blister Tabletas',
  tipo: 'TABLETA',
  precio: 0.6,
  stock: 150,
  imagen: 'images/default_image.png',
  requiere_receta: false,
}

describe('ProductsView — imágenes reales del catálogo y respaldo', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()

    vi.spyOn(productsService, 'listar').mockResolvedValue({
      items: [amoxicilinaConFoto, paracetamolSinFoto],
      total: 2,
      pagina: 1,
      porPagina: 24,
      ultimaPagina: 1,
    })
    vi.spyOn(productsService, 'facetas').mockResolvedValue({
      tipos: [],
      laboratorios: [],
      categorias: [],
      total: 2,
      sinStock: 0,
    })
  })

  function montarCatalogo() {
    return mount(ProductsView, {
      global: {
        stubs: {
          Header: true,
          Footer: true,
          'router-link': { props: ['to'], template: '<a :href="typeof to === \'string\' ? to : \'#\'"><slot /></a>' },
        },
        mocks: {
          $route: { path: '/products', query: {}, params: {} },
        },
      },
    })
  }

  it('muestra la foto real optimizada en WebP y usa SVG directo cuando falta fotografía', async () => {
    const wrapper = montarCatalogo()
    await flushPromises()

    const fotoReal = wrapper.find('img[src*="38530.webp"]')
    expect(fotoReal.exists()).toBe(true)
    expect(fotoReal.attributes('alt')).toBe('AMOXICILINA')
    expect(fotoReal.attributes('width')).toBe('400')
    expect(fotoReal.attributes('height')).toBe('400')
    expect(fotoReal.classes()).toContain('object-contain')

    // El producto con `images/default_image.png` no dispara un <img> hacia /images/default_image.png
    expect(wrapper.find('img[src*="default_image"]').exists()).toBe(false)

    // El segundo producto muestra la ilustración de tableta directamente
    const respaldo = wrapper.find('img[src*="/formas/"]')
    expect(respaldo.exists()).toBe(true)
  })

  it('cambia al respaldo SVG de forma farmacéutica si una foto real falla al cargar', async () => {
    const wrapper = montarCatalogo()
    await flushPromises()

    const fotoReal = wrapper.find('img[src*="38530.webp"]')
    expect(fotoReal.exists()).toBe(true)

    await fotoReal.trigger('error')
    await flushPromises()

    expect(wrapper.find('img[src*="38530.webp"]').exists()).toBe(false)
    expect(wrapper.findAll('img[src*="/formas/"]').length).toBe(2)
  })
})
