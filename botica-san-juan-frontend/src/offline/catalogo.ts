import api from '@/services/api'
import { obtenerAlmacen } from './almacen'
import { CODIGOS, bitacora } from './bitacora'
import type { ProductoLocal } from './tipos'
import type { FormaVenta, ProductoPos } from '@/admin/composables/usePuntoVenta'

/**
 * Catálogo maestro de la caja.
 * ---------------------------------------------------------------------------
 * Sin esto, la contingencia servía de poco: la venta se guardaba bien, pero el
 * vendedor no podía encontrar el producto para armarla. El ensayo del 24/10 lo
 * dejó claro.
 *
 * LA CIFRA QUE NO SE GUARDA
 *
 * El catálogo local trae nombre, presentaciones y precio. **No trae stock.**
 * El stock cambia con cada venta de cualquier caja y un número guardado hace
 * horas es peor que ninguno: invita a prometer lo que ya no hay. Sin conexión
 * la pantalla marca «sin verificar», el vendedor mira el anaquel —que es la
 * fuente de verdad cuando no hay sistema— y la diferencia se resuelve como
 * incidencia al sincronizar.
 *
 * Por eso los resultados locales llevan `stock_disponible: -1`, que la
 * pantalla sabe leer. No es cero: cero significaría «no hay», y lo que
 * ocurre es que no se sabe.
 */

const CLAVE_CORTE = 'botica:catalogo-sincronizado-en'

/** Tope de resultados, el mismo que aplica el servidor. */
const LIMITE = 20

interface RespuestaCatalogo {
  data: ProductoLocal[]
  total: number
  sincronizado_en: string
}

function leerCorte(): string | null {
  try {
    return localStorage.getItem(CLAVE_CORTE)
  } catch {
    return null
  }
}

function guardarCorte(valor: string): void {
  try {
    localStorage.setItem(CLAVE_CORTE, valor)
  } catch {
    /* Sin marca de corte la próxima descarga será completa. Es más tráfico,
       no un error. */
  }
}

/**
 * Descarga el catálogo y lo guarda en local.
 *
 * Incremental a partir de la segunda vez: solo baja lo que cambió desde el
 * último corte. Devuelve cuántos productos se guardaron.
 */
export async function sincronizarCatalogo(forzarCompleto = false): Promise<number> {
  const desde = forzarCompleto ? null : leerCorte()

  const { data } = await api.get<RespuestaCatalogo>('/pos/catalogo', {
    params: desde ? { desde } : {},
  })

  if (data.data.length > 0) {
    const almacen = await obtenerAlmacen()
    await almacen.guardarCatalogo(data.data)
  }

  guardarCorte(data.sincronizado_en)

  await bitacora.info(
    CODIGOS.CATALOGO_DESCARGADO,
    desde
      ? `Catálogo actualizado: ${data.total} productos con cambios.`
      : `Catálogo descargado completo: ${data.total} productos.`,
    { contexto: { incremental: Boolean(desde), total: data.total } },
  )

  return data.total
}

/**
 * Busca en el catálogo local y devuelve el mismo tipo que la API.
 *
 * Que devuelva `ProductoPos` y no `ProductoLocal` no es un capricho: permite
 * que la pantalla de caja no sepa de dónde vino el resultado. Un camino
 * distinto por origen habría acabado con dos maneras de pintar la misma
 * fila, y una de las dos se habría quedado atrás.
 */
export async function buscarEnCatalogoLocal(termino: string): Promise<ProductoPos[]> {
  const almacen = await obtenerAlmacen()
  const encontrados = await almacen.buscarEnCatalogo(termino, LIMITE)

  return encontrados.map(aProductoPos)
}

function aProductoPos(p: ProductoLocal): ProductoPos {
  return {
    id: p.id,
    nombre: p.nombre,
    concentracion: p.concentracion ?? null,
    presentacion: p.presentacion ?? null,
    laboratorio: p.laboratorio ?? null,
    principio_activo: p.principio_activo ?? null,
    codigo_barras: p.codigo_barras ?? null,
    requiere_receta: p.requiere_receta,
    afecto_igv: p.afecto_igv,
    tipo_afectacion_igv: p.tipo_afectacion_igv,
    afectacion: p.afectacion,
    /* -1 es «no se sabe», distinto de 0 que seria «no hay». */
    stock_contable: -1,
    stock_disponible: -1,
    venta_fraccionada: p.venta_fraccionada,
    formas_venta: formasVenta(p),
  }
}

/**
 * Reproduce el cálculo de presentaciones del servidor.
 *
 * Es la única duplicación de lógica que esta capa se permite, y está acotada
 * a una función de quince líneas. La alternativa —guardar las presentaciones
 * ya calculadas por el servidor— habría guardado también su `maximo_vendible`
 * y su `disponible`, que dependen del stock y quedan obsoletos en cuanto otra
 * caja vende. Un dato correcto recalculado vale más que uno caducado.
 */
function formasVenta(p: ProductoLocal): FormaVenta[] {
  const formas: FormaVenta[] = [
    { unidad_venta: 'unidad', factor_unidades: 1, precio: redondear(p.precio), maximo_vendible: 0, disponible: true },
  ]

  const porBlister = p.unidades_por_blister ?? 0

  if (porBlister > 0) {
    formas.push({
      unidad_venta: 'blister',
      factor_unidades: porBlister,
      precio: redondear(p.precio_blister ?? p.precio * porBlister),
      maximo_vendible: 0,
      disponible: true,
    })

    const blisters = p.blisters_por_caja ?? 0

    if (blisters > 0) {
      const porCaja = porBlister * blisters
      formas.push({
        unidad_venta: 'caja',
        factor_unidades: porCaja,
        precio: redondear(p.precio_caja ?? p.precio * porCaja),
        maximo_vendible: 0,
        disponible: true,
      })
    }
  }

  return formas
}

function redondear(valor: number): number {
  return Math.round(valor * 100) / 100
}

/** Solo para las pruebas. */
export function _olvidarCorte(): void {
  try {
    localStorage.removeItem(CLAVE_CORTE)
  } catch {
    /* nada que limpiar */
  }
}
