import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { _fijarAlmacen } from '@/offline/almacen'
import { AlmacenMemoria } from '@/offline/almacenMemoria'
import type { ProductoLocal } from '@/offline/tipos'

const get = vi.fn()
vi.mock('@/services/api', () => ({ default: { get: (...args: unknown[]) => get(...args) } }))

import { _olvidarCorte, buscarEnCatalogoLocal, sincronizarCatalogo } from '@/offline/catalogo'

/**
 * Catálogo guardado en la caja.
 *
 * Lo que importa comprobar: que la búsqueda local devuelve el mismo tipo que
 * la API —para que la pantalla no tenga que saber de dónde vino—, que el
 * stock se declara «no verificable» en vez de inventarse, y que la segunda
 * descarga solo pide lo que cambió.
 */
describe('catálogo local', () => {
  let almacen: AlmacenMemoria

  const producto = (extra: Partial<ProductoLocal> = {}): ProductoLocal => ({
    id: 1,
    nombre: 'PARACETAMOL 500 mg',
    concentracion: '500 mg',
    presentacion: 'Caja x 100',
    laboratorio: 'MEDIFARMA',
    principio_activo: 'Paracetamol',
    codigo_barras: '7751234567890',
    requiere_receta: false,
    afecto_igv: true,
    tipo_afectacion_igv: '10',
    afectacion: 'Gravado',
    precio: 0.5,
    precio_blister: 4.5,
    precio_caja: 40,
    unidades_por_blister: 10,
    blisters_por_caja: 10,
    venta_fraccionada: true,
    actualizado_en: '2026-10-24T10:00:00-05:00',
    ...extra,
  })

  beforeEach(async () => {
    almacen = new AlmacenMemoria()
    await almacen.abrir()
    _fijarAlmacen(almacen)
    _olvidarCorte()
    get.mockReset()
  })

  afterEach(() => {
    _fijarAlmacen(null)
    _olvidarCorte()
  })

  // ------------------------------------------------------------ descarga

  it('la primera descarga pide el catálogo entero', async () => {
    get.mockResolvedValue({
      data: { data: [producto()], total: 1, sincronizado_en: '2026-10-24T12:00:00-05:00' },
    })

    const total = await sincronizarCatalogo()

    expect(total).toBe(1)
    const [, opciones] = get.mock.calls[0] as [string, { params: Record<string, unknown> }]
    expect(opciones.params).toEqual({})
  })

  it('la segunda descarga solo pide lo que cambió', async () => {
    get.mockResolvedValue({
      data: { data: [producto()], total: 1, sincronizado_en: '2026-10-24T12:00:00-05:00' },
    })

    await sincronizarCatalogo()
    await sincronizarCatalogo()

    const [, opciones] = get.mock.calls[1] as [string, { params: { desde?: string } }]
    expect(opciones.params.desde).toBe('2026-10-24T12:00:00-05:00')
  })

  // ------------------------------------------------------------ búsqueda

  it('encuentra por nombre, por principio activo y por código de barras', async () => {
    await almacen.guardarCatalogo([producto()])

    expect(await buscarEnCatalogoLocal('paracetamol')).toHaveLength(1)
    expect(await buscarEnCatalogoLocal('PARACETAMOL')).toHaveLength(1)
    expect(await buscarEnCatalogoLocal('7751234567890')).toHaveLength(1)
    expect(await buscarEnCatalogoLocal('ibuprofeno')).toHaveLength(0)
  })

  it('no busca con menos de dos caracteres', async () => {
    await almacen.guardarCatalogo([producto()])

    expect(await buscarEnCatalogoLocal('p')).toHaveLength(0)
  })

  it('declara el stock como no verificable en vez de inventarlo', async () => {
    await almacen.guardarCatalogo([producto()])

    const [encontrado] = await buscarEnCatalogoLocal('paracetamol')

    /* -1 es «no se sabe». Un cero significaría «no hay», que es una
       afirmación distinta y que la caja no está en condiciones de hacer. */
    expect(encontrado?.stock_disponible).toBe(-1)
    expect(encontrado?.stock_contable).toBe(-1)
  })

  it('reconstruye las tres presentaciones con sus factores', async () => {
    await almacen.guardarCatalogo([producto()])

    const [encontrado] = await buscarEnCatalogoLocal('paracetamol')

    expect(encontrado?.formas_venta).toEqual([
      { unidad_venta: 'unidad', factor_unidades: 1, precio: 0.5, maximo_vendible: 0, disponible: true },
      { unidad_venta: 'blister', factor_unidades: 10, precio: 4.5, maximo_vendible: 0, disponible: true },
      { unidad_venta: 'caja', factor_unidades: 100, precio: 40, maximo_vendible: 0, disponible: true },
    ])
  })

  it('un producto sin blíster solo ofrece la unidad', async () => {
    await almacen.guardarCatalogo([
      producto({ id: 2, nombre: 'JARABE X', unidades_por_blister: null, blisters_por_caja: null }),
    ])

    const [encontrado] = await buscarEnCatalogoLocal('jarabe')

    expect(encontrado?.formas_venta).toHaveLength(1)
    expect(encontrado?.formas_venta[0]?.unidad_venta).toBe('unidad')
  })

  it('respeta el tope de resultados', async () => {
    const muchos = Array.from({ length: 40 }, (_, i) =>
      producto({ id: i + 1, nombre: `PARACETAMOL variante ${i}`, codigo_barras: null }),
    )
    await almacen.guardarCatalogo(muchos)

    expect(await buscarEnCatalogoLocal('paracetamol')).toHaveLength(20)
  })

  it('conserva el tratamiento tributario del producto', async () => {
    await almacen.guardarCatalogo([
      producto({ id: 3, nombre: 'LECHE MATERNIZADA', tipo_afectacion_igv: '20', afectacion: 'Exonerado' }),
    ])

    const [encontrado] = await buscarEnCatalogoLocal('leche')

    expect(encontrado?.tipo_afectacion_igv).toBe('20')
    expect(encontrado?.afectacion).toBe('Exonerado')
  })
})
