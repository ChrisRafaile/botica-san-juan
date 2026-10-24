import { afterEach, beforeEach, describe, expect, it } from 'vitest'

import { _fijarAlmacen } from '@/offline/almacen'
import { AlmacenMemoria } from '@/offline/almacenMemoria'
import {
  _reiniciarCorrelativo,
  formatearNota,
  reconciliarCorrelativo,
  siguienteNotaContingencia,
} from '@/offline/notaContingencia'
import type { VentaLocal } from '@/offline/tipos'

describe('correlativo de la nota de contingencia', () => {
  let almacen: AlmacenMemoria

  const ventaConNota = (nota: string): VentaLocal => ({
    uuid_local: crypto.randomUUID(),
    nota_contingencia: nota,
    registrada_en: new Date().toISOString(),
    cliente: {},
    items: [],
    pagos: [],
    total: 0,
    estado: 'pendiente',
    intentos: 0,
  })

  beforeEach(async () => {
    almacen = new AlmacenMemoria()
    await almacen.abrir()
    _fijarAlmacen(almacen)
    _reiniciarCorrelativo()
  })

  afterEach(() => {
    _fijarAlmacen(null)
    _reiniciarCorrelativo()
  })

  it('empieza en uno y avanza de uno en uno', async () => {
    expect(await siguienteNotaContingencia()).toBe('NV-000001')
    expect(await siguienteNotaContingencia()).toBe('NV-000002')
    expect(await siguienteNotaContingencia()).toBe('NV-000003')
  })

  it('no reutiliza números que ya están en la cola local', async () => {
    /* El caso real: alguien limpió los datos del navegador y el contador
       volvió a cero, pero las ventas siguen esperando con su papel ya
       entregado al cliente. */
    await almacen.guardarVenta(ventaConNota('NV-000041'))
    await almacen.guardarVenta(ventaConNota('NV-000017'))

    await reconciliarCorrelativo()

    expect(await siguienteNotaContingencia()).toBe('NV-000042')
  })

  it('formatea con seis dígitos', () => {
    expect(formatearNota(7)).toBe('NV-000007')
    expect(formatearNota(123456)).toBe('NV-123456')
  })

  it('sobrevive a un almacén que falla', async () => {
    _fijarAlmacen({
      nombre: 'roto',
      abrir: async () => {},
      guardarVenta: async () => {},
      ventasPendientes: async () => {
        throw new Error('sin almacén')
      },
      marcarVenta: async () => {},
      pendienteMasAntigua: async () => null,
      registrarEvento: async () => {},
      eventos: async () => [],
      guardarCatalogo: async () => {},
      buscarEnCatalogo: async () => [],
    })

    /* Que no haya cola no puede dejar a la caja sin poder emitir el papel:
       se sigue con lo que haya guardado localmente. */
    await expect(siguienteNotaContingencia()).resolves.toMatch(/^NV-\d{6}$/)
  })
})
