import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import ProductoDetalleView from '../ProductoDetalleView.vue'
import productsService, { type Product } from '@/services/products'
import { useCartStore } from '@/stores/carrito'

// Mock de scrollTo para jsdom
window.scrollTo = vi.fn()

const mockRoute = {
  params: { id: '101' },
  path: '/catalogo/101',
}
const mockRouter = {
  push: vi.fn(),
  replace: vi.fn(),
}

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  useRouter: () => mockRouter,
  RouterLink: {
    name: 'RouterLink',
    props: ['to'],
    template: '<a :href="typeof to === \'string\' ? to : to.path"><slot /></a>',
  },
}))

// Caso real del dominio: «amoxicilina 500 mg»
const amoxicilina: Product = {
  id: 101,
  nombre: 'Amoxicilina 500 mg',
  concentracion: '500 mg',
  adicional: 'Cápsulas indicadas para el tratamiento de infecciones bacterianas del tracto respiratorio.',
  laboratorio: 'Laboratorios Bagó',
  presentacion: 'Caja x 100 cápsulas',
  tipo: 'Cápsula',
  stock: 24,
  stock_disponible: 24,
  precio: 18.5,
  imagen: 'productos/amoxicilina-500.webp',
  principio_activo: 'Amoxicilina',
  requiere_receta: true,
  laboratorio_fabricante: 'Laboratorios Bagó del Perú S.A.',
}

const productoRecomendado: Product = {
  id: 102,
  nombre: 'Cefalexina 500 mg',
  concentracion: '500 mg',
  adicional: 'Antibiótico cefalosporínico.',
  laboratorio: 'Medifarma',
  presentacion: 'Caja x 50 cápsulas',
  tipo: 'Cápsula',
  stock: 15,
  stock_disponible: 15,
  precio: 22.0,
  imagen: '',
}

describe('ProductoDetalleView.vue (Ficha de Producto en /catalogo/:id)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    mockRoute.params.id = '101'
  })

  function montarComponente() {
    return mount(ProductoDetalleView, {
      global: {
        mocks: {
          $route: mockRoute,
        },
        stubs: {
          Header: true,
          Footer: true,
          RouterLink: {
            props: ['to'],
            template: '<a :href="typeof to === \'string\' ? to : to.path"><slot /></a>',
          },
        },
      },
    })
  }

  it('renderiza la ficha completa con un producto real como «Amoxicilina 500 mg»', async () => {
    vi.spyOn(productsService, 'getProductById').mockResolvedValue(amoxicilina)
    vi.spyOn(productsService, 'listar').mockResolvedValue({
      items: [productoRecomendado],
      total: 1,
      pagina: 1,
      porPagina: 10,
      ultimaPagina: 1,
    })

    const wrapper = montarComponente()
    await flushPromises()

    // Nombre, concentración y laboratorio
    expect(wrapper.find('h1').text()).toBe('Amoxicilina 500 mg')
    expect(wrapper.text()).toContain('500 mg')
    expect(wrapper.text()).toContain('Laboratorios Bagó')

    // Advertencia de receta médica obligatoria
    expect(wrapper.text()).toContain('Venta bajo receta médica')

    // Existencias y precio
    expect(wrapper.text()).toContain('En stock (24 disponibles)')
    expect(wrapper.text()).toContain('S/ 18.50')

    // Desplazamiento accesible al montar/cambiar de producto
    expect(window.scrollTo).toHaveBeenCalledWith({
      top: 0,
      behavior: 'smooth',
    })
  })

  it('permite modificar la cantidad y agregar al carrito con feedback visual', async () => {
    vi.spyOn(productsService, 'getProductById').mockResolvedValue(amoxicilina)
    vi.spyOn(productsService, 'listar').mockResolvedValue({
      items: [],
      total: 0,
      pagina: 1,
      porPagina: 10,
      ultimaPagina: 1,
    })

    const wrapper = montarComponente()
    await flushPromises()

    const cartStore = useCartStore()
    const spyAgregar = vi.spyOn(cartStore, 'agregar')

    // Incrementar cantidad a 3
    const botonAumentar = wrapper.find('button[aria-label="Aumentar cantidad"]')
    await botonAumentar.trigger('click')
    await botonAumentar.trigger('click')

    expect(wrapper.text()).toContain('3')

    // Agregar al carrito
    const botonAgregar = wrapper.find('[data-testid="boton-agregar"]')
    await botonAgregar.trigger('click')

    expect(spyAgregar).toHaveBeenCalledWith(101, 3, 24)
    expect(wrapper.text()).toContain('¡Agregado al carrito!')
  })

  it('acordeón accesible: respeta atributos ARIA y permite abrir y cerrar secciones', async () => {
    vi.spyOn(productsService, 'getProductById').mockResolvedValue(amoxicilina)
    vi.spyOn(productsService, 'listar').mockResolvedValue({
      items: [],
      total: 0,
      pagina: 1,
      porPagina: 10,
      ultimaPagina: 1,
    })

    const wrapper = montarComponente()
    await flushPromises()

    // Botones del acordeón
    const btnDesc = wrapper.find('#acordeon-btn-descripcion')
    const panelDesc = wrapper.find('#acordeon-panel-descripcion')

    expect(btnDesc.attributes('aria-expanded')).toBe('true')
    expect(btnDesc.attributes('aria-controls')).toBe('acordeon-panel-descripcion')
    expect(panelDesc.attributes('role')).toBe('region')
    expect(panelDesc.attributes('aria-labelledby')).toBe('acordeon-btn-descripcion')
    expect(panelDesc.text()).toContain('Cápsulas indicadas para el tratamiento')

    // Sección Principio Activo
    const btnPrincipio = wrapper.find('#acordeon-btn-principio-activo')
    const panelPrincipio = wrapper.find('#acordeon-panel-principio-activo')
    expect(btnPrincipio.attributes('aria-expanded')).toBe('true')
    expect(panelPrincipio.text()).toContain('Amoxicilina')

    // Sección Contraindicaciones (inicia plegada, se expande al click)
    const btnContra = wrapper.find('#acordeon-btn-contraindicaciones')
    const panelContra = wrapper.find('#acordeon-panel-contraindicaciones')
    expect(btnContra.attributes('aria-expanded')).toBe('false')

    await btnContra.trigger('click')
    expect(btnContra.attributes('aria-expanded')).toBe('true')
    expect(panelContra.isVisible()).toBe(true)
    expect(panelContra.text()).toContain('Recomendaciones generales de seguridad')
  })

  it('muestra estados vacíos dignos cuando el producto carece de descripción o principio activo', async () => {
    const productoSinDetalle: Product = {
      id: 202,
      nombre: 'Alcohol en Gel 500 mL',
      concentracion: '70%',
      adicional: '', // Vacío deliberado
      laboratorio: 'Alkofarma',
      presentacion: 'Frasco con dosificador',
      tipo: 'Gel',
      stock: 10,
      stock_disponible: 10,
      precio: 9.9,
      imagen: '',
      principio_activo: '', // Vacío deliberado
      requiere_receta: false,
    }

    vi.spyOn(productsService, 'getProductById').mockResolvedValue(productoSinDetalle)
    vi.spyOn(productsService, 'listar').mockResolvedValue({
      items: [],
      total: 0,
      pagina: 1,
      porPagina: 10,
      ultimaPagina: 1,
    })

    const wrapper = montarComponente()
    await flushPromises()

    // Estado vacío digno de descripción
    expect(wrapper.find('#acordeon-panel-descripcion').text()).toContain(
      'Sin descripción adicional registrada',
    )

    // Estado vacío digno de principio activo
    expect(wrapper.find('#acordeon-panel-principio-activo').text()).toContain(
      'Información de principio activo no especificada',
    )
  })

  it('galería interactiva: cuenta con zoom manual accesible y soporte para ilustraciones', async () => {
    vi.spyOn(productsService, 'getProductById').mockResolvedValue(amoxicilina)
    vi.spyOn(productsService, 'listar').mockResolvedValue({
      items: [],
      total: 0,
      pagina: 1,
      porPagina: 10,
      ultimaPagina: 1,
    })

    const wrapper = montarComponente()
    await flushPromises()

    const botonZoom = wrapper.find('button[aria-label="Ampliar imagen de producto"]')
    expect(botonZoom.exists()).toBe(true)
    expect(botonZoom.attributes('aria-pressed')).toBe('false')

    // Alternar zoom
    await botonZoom.trigger('click')
    expect(botonZoom.attributes('aria-pressed')).toBe('true')
  })

  it('reserva altura compartida entre el skeleton y el detalle, y mantiene placeholder mientras cargan los recomendados', async () => {
    /* El tipo se deriva del constructor de Promise en vez de escribirse como
       `(valor: T) => void`: la regla no-unused-vars del proyecto cuenta el
       nombre del parámetro de un tipo de función como variable sin usar. */
    type Resolver<T> = Parameters<ConstructorParameters<typeof Promise<T>>[0]>[0]
    let resolverProducto!: Resolver<Product>
    let resolverRecomendados!: Resolver<{
      items: Product[]
      total: number
      pagina: number
      porPagina: number
      ultimaPagina: number
    }>

    vi.spyOn(productsService, 'getProductById').mockReturnValue(
      new Promise((resolve) => {
        resolverProducto = resolve
      }),
    )
    vi.spyOn(productsService, 'listar').mockReturnValue(
      new Promise((resolve) => {
        resolverRecomendados = resolve
      }),
    )

    const wrapper = montarComponente()
    await flushPromises()

    // Fase 1: cargando producto dentro del contenedor con altura reservada
    const contenedor = wrapper.find('[data-testid="contenedor-ficha-producto"]')
    expect(contenedor.exists()).toBe(true)
    expect(contenedor.classes().some((c) => c.startsWith('min-h-'))).toBe(true)
    expect(contenedor.find('[data-testid="skeleton-producto"]').exists()).toBe(true)
    expect(contenedor.find('[data-testid="skeleton-producto"] .aspect-square').exists()).toBe(true)

    // Fase 2: llega GET /api/productos/:id, recomendados sigue pendiente
    resolverProducto(amoxicilina)
    await flushPromises()

    const mismoContenedor = wrapper.find('[data-testid="contenedor-ficha-producto"]')
    expect(mismoContenedor.exists()).toBe(true)
    expect(mismoContenedor.find('[data-testid="skeleton-producto"]').exists()).toBe(false)
    expect(mismoContenedor.find('[data-testid="detalle-producto"]').exists()).toBe(true)
    expect(mismoContenedor.find('[data-testid="seccion-recomendados"]').exists()).toBe(true)
    expect(mismoContenedor.find('[data-testid="skeleton-recomendados"]').exists()).toBe(true)

    // Fase 3: llegan los recomendados y reemplazan el placeholder dentro de la misma sección
    resolverRecomendados({
      items: [productoRecomendado],
      total: 1,
      pagina: 1,
      porPagina: 10,
      ultimaPagina: 1,
    })
    await flushPromises()

    expect(wrapper.find('[data-testid="skeleton-recomendados"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="seccion-recomendados"]').text()).toContain('Cefalexina 500 mg')
  })
})
