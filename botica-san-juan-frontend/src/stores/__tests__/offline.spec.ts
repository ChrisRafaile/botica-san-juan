import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { _fijarAlmacen } from '@/offline/almacen'
import { AlmacenMemoria } from '@/offline/almacenMemoria'
import type { VentaLocal } from '@/offline/tipos'

/* El store importa el cliente HTTP real; aquí se sustituye entero para que
   las pruebas no dependan de axios ni de una API levantada. */
const post = vi.fn()
vi.mock('@/services/api', () => ({ default: { post: (...args: unknown[]) => post(...args) } }))

import { useOfflineStore } from '@/stores/offline'

/**
 * Cola de ventas sin conexión.
 *
 * Lo que de verdad importa comprobar aquí son las propiedades de las que
 * depende que el vendedor pueda confiar en la caja:
 *
 *   - una venta cobrada queda guardada aunque el envío falle;
 *   - `duplicada` se trata como éxito, no como error;
 *   - una venta rechazada sale de la cola en vez de reintentarse para siempre;
 *   - el atraso se mide desde la venta más antigua y se anota una sola vez.
 */
describe('store offline', () => {
  let almacen: AlmacenMemoria

  const ventaBase = (sobrescribir: Partial<VentaLocal> = {}) => ({
    nota_contingencia: 'NV-000001',
    registrada_en: new Date().toISOString(),
    cliente: { nombre: 'Consumidor final', tipo_documento: 'sin_documento' as const },
    observacion: null,
    items: [
      { producto_id: 1, unidad_venta: 'unidad' as const, cantidad: 2, nombre: 'Paracetamol', precio_unitario: 2.5 },
    ],
    pagos: [{ medio: 'efectivo', monto: 5 }],
    total: 5,
    ...sobrescribir,
  })

  beforeEach(async () => {
    setActivePinia(createPinia())
    almacen = new AlmacenMemoria()
    await almacen.abrir()
    _fijarAlmacen(almacen)
    post.mockReset()
    vi.stubGlobal('crypto', { randomUUID: () => '11111111-2222-4333-8444-555555555555' })
  })

  afterEach(() => {
    _fijarAlmacen(null)
    vi.unstubAllGlobals()
    vi.useRealTimers()
  })

  it('guarda la venta en local antes de intentar enviarla', async () => {
    const store = useOfflineStore()
    store.enLinea = false

    const venta = await store.encolarVenta(ventaBase())

    expect(venta.uuid_local).toBe('11111111-2222-4333-8444-555555555555')
    expect(venta.estado).toBe('pendiente')
    expect(await almacen.ventasPendientes()).toHaveLength(1)
    expect(post).not.toHaveBeenCalled()
  })

  it('la venta sobrevive a un fallo de red y queda para reintentar', async () => {
    const store = useOfflineStore()
    store.enLinea = true
    post.mockRejectedValue(new Error('Network Error'))

    await store.encolarVenta(ventaBase())
    await store.sincronizar()

    const pendientes = await almacen.ventasPendientes()
    expect(pendientes).toHaveLength(1)
    expect(pendientes[0]?.intentos).toBeGreaterThan(0)
    expect(store.ultimoError).toContain('Network Error')
  })

  it('trata «duplicada» como sincronizada y no la reintenta', async () => {
    const store = useOfflineStore()

    /* Se encola sin red para que el envío no salga solo: lo que se está
       comprobando es cómo se interpreta la respuesta, no el disparo
       automático, que tiene su propia prueba más abajo. */
    store.enLinea = false
    await store.encolarVenta(ventaBase())
    store.enLinea = true

    post.mockResolvedValue({
      data: {
        resultados: [
          {
            uuid_local: '11111111-2222-4333-8444-555555555555',
            estado: 'duplicada',
            pedido_id: 77,
            comprobante: 'B001-00000042',
          },
        ],
      },
    })

    await store.sincronizar()

    expect(await almacen.ventasPendientes()).toHaveLength(0)
    expect(store.hayPendientes).toBe(false)
  })

  it('una venta rechazada sale de la cola con su motivo', async () => {
    const store = useOfflineStore()

    store.enLinea = false
    await store.encolarVenta(ventaBase())
    store.enLinea = true

    post.mockResolvedValue({
      data: {
        resultados: [
          {
            uuid_local: '11111111-2222-4333-8444-555555555555',
            estado: 'rechazada',
            motivo: 'El producto 1 no existe en el catálogo.',
          },
        ],
      },
    })

    await store.sincronizar()

    expect(await almacen.ventasPendientes()).toHaveLength(0)

    const eventos = await almacen.eventos()
    expect(eventos.some((e) => e.codigo === 'CAJA_VENTA_RECHAZADA' && e.nivel === 'error')).toBe(true)
  })

  it('no envía nada si ya hay una sincronización en curso', async () => {
    const store = useOfflineStore()
    store.enLinea = true
    await store.encolarVenta(ventaBase())

    post.mockImplementation(
      () => new Promise((resolver) => setTimeout(() => resolver({ data: { resultados: [] } }), 20)),
    )

    const primera = store.sincronizar()
    await store.sincronizar()
    await primera

    expect(post).toHaveBeenCalledTimes(1)
  })

  it('solo manda al servidor los campos del contrato, no el precio local', async () => {
    const store = useOfflineStore()
    store.enLinea = true
    post.mockResolvedValue({ data: { resultados: [] } })

    await store.encolarVenta(ventaBase())
    await store.sincronizar()

    const [, cuerpo] = post.mock.calls[0] as [string, { ventas: Array<{ items: unknown[] }> }]
    expect(cuerpo.ventas[0]?.items[0]).toEqual({
      producto_id: 1,
      unidad_venta: 'unidad',
      cantidad: 2,
    })
  })

  // ------------------------------------------------------------ atraso

  it('mide el atraso desde la venta más antigua', async () => {
    const store = useOfflineStore()
    store.enLinea = false

    await store.encolarVenta(
      ventaBase({ registrada_en: new Date(Date.now() - 30 * 3_600_000).toISOString() }),
    )

    expect(store.horasDeAtraso).toBeGreaterThanOrEqual(30)
    expect(store.nivelDeAtraso).toBe('aviso')
  })

  it('pasa a alerta a partir de las 48 horas', async () => {
    const store = useOfflineStore()
    store.enLinea = false

    await store.encolarVenta(
      ventaBase({ registrada_en: new Date(Date.now() - 50 * 3_600_000).toISOString() }),
    )

    expect(store.nivelDeAtraso).toBe('alerta')
  })

  it('sin pendientes no hay atraso que avisar', async () => {
    const store = useOfflineStore()
    expect(store.nivelDeAtraso).toBe('ninguno')
    expect(store.horasDeAtraso).toBe(0)
  })

  // ---------------------------------------------------------- vigilancia

  it('al volver la red dispara la sincronización y la suelta al parar', async () => {
    const store = useOfflineStore()
    store.enLinea = false
    post.mockResolvedValue({ data: { resultados: [] } })

    await store.encolarVenta(ventaBase())

    const parar = store.vigilar()
    window.dispatchEvent(new Event('online'))
    await vi.waitFor(() => expect(post).toHaveBeenCalled())

    expect(store.enLinea).toBe(true)

    parar()
    post.mockClear()
    window.dispatchEvent(new Event('online'))
    await new Promise((r) => setTimeout(r, 10))

    expect(post).not.toHaveBeenCalled()
  })

  it('anota en la bitácora la caída de la red', async () => {
    const store = useOfflineStore()
    const parar = store.vigilar()

    window.dispatchEvent(new Event('offline'))
    await vi.waitFor(async () => {
      const eventos = await almacen.eventos()
      expect(eventos.some((e) => e.codigo === 'CAJA_RED_CAIDA')).toBe(true)
    })

    expect(store.enLinea).toBe(false)
    parar()
  })
})
