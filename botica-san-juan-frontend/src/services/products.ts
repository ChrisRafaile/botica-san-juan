import api from './api'

/**
 * Catalogo de productos del portal publico.
 *
 * FALLO CORREGIDO: EL PAGINADOR LLEGABA SIN ABRIR
 *
 * `GET /api/productos` no devuelve un arreglo, devuelve el paginador de
 * Laravel: { current_page, data: [...], total, per_page, ... }. Los metodos
 * de aqui estaban tipados como `Product[]` y hacian `return response.data`,
 * asi que entregaban el OBJETO del paginador donde el resto del codigo
 * esperaba una lista. La pantalla de productos acababa mostrando "No se
 * encontraron productos" con 3 361 productos en la base.
 *
 * El tipo mentia y TypeScript no podia avisar: `AxiosResponse<Product[]>` era
 * una afirmacion nuestra sobre la respuesta, no una comprobacion.
 *
 * DE PASO: LA BUSQUEDA SE HACE EN EL SERVIDOR
 *
 * La vista filtraba en el navegador sobre los 10 productos de la primera
 * pagina, de modo que buscar solo encontraba algo si ya estaba a la vista.
 * El controlador acepta `q`, `tipo` y `per_page`, asi que se le pide a el.
 */

export interface Product {
  id: number
  nombre: string
  concentracion: string
  adicional: string
  laboratorio: string
  presentacion: string
  tipo: string
  stock: number
  precio: number
  imagen: string
}

/** Lo que de verdad devuelve el servidor en los listados. */
interface RespuestaPaginada<T> {
  data: T[]
  total: number
  current_page: number
  per_page: number
  last_page: number
}

/** Resultado de un listado, con el total REAL del catalogo. */
export interface ListadoProductos {
  items: Product[]
  total: number
  pagina: number
  porPagina: number
  ultimaPagina: number
}

export interface OpcionesListado {
  busqueda?: string
  tipo?: string
  laboratorio?: string
  pagina?: number
  porPagina?: number
}

/**
 * Un grid de catalogo con 10 elementos se ve roto; con 3 361 se queda sin
 * memoria el navegador. 48 llena la rejilla y mantiene la respuesta corta.
 */
const POR_PAGINA_POR_DEFECTO = 48

/**
 * Los decimales llegan como CADENA, no como numero.
 *
 * Laravel serializa las columnas decimal como string para no perder
 * precision por el camino: `precio` viaja como "12.50". La vista hacia
 * `producto.precio.toFixed(2)` y eso lanza "toFixed is not a function",
 * que en Vue rompe el render entero y deja la pantalla EN BLANCO, sin
 * ningun mensaje que relacione el fallo con el precio.
 *
 * Se convierten aqui, en el borde del sistema, y no en cada plantilla que
 * los use: si cada vista lo arregla por su cuenta, alguna se olvidara.
 */
function normalizar(p: Product): Product {
  return {
    ...p,
    precio: Number(p.precio ?? 0),
    stock: Number(p.stock ?? 0),
  }
}

/**
 * Acepta las dos formas: el paginador y el arreglo pelado. Asi sigue
 * funcionando si algun endpoint devuelve una lista simple.
 */
function abrirPaginador(cuerpo: unknown): ListadoProductos {
  if (Array.isArray(cuerpo)) {
    const items = (cuerpo as Product[]).map(normalizar)
    return {
      items,
      total: items.length,
      pagina: 1,
      porPagina: items.length,
      ultimaPagina: 1,
    }
  }

  const p = cuerpo as Partial<RespuestaPaginada<Product>>
  const items = (Array.isArray(p?.data) ? p.data : []).map(normalizar)

  return {
    items,
    total: typeof p?.total === 'number' ? p.total : items.length,
    pagina: typeof p?.current_page === 'number' ? p.current_page : 1,
    porPagina: typeof p?.per_page === 'number' ? p.per_page : items.length,
    ultimaPagina: typeof p?.last_page === 'number' ? p.last_page : 1,
  }
}

class ProductsService {
  /** Listado con filtros resueltos en el servidor. */
  async listar(opciones: OpcionesListado = {}): Promise<ListadoProductos> {
    const params: Record<string, string | number> = {
      per_page: opciones.porPagina ?? POR_PAGINA_POR_DEFECTO,
      page: opciones.pagina ?? 1,
    }

    const busqueda = opciones.busqueda?.trim()
    if (busqueda) params.q = busqueda
    if (opciones.tipo) params.tipo = opciones.tipo
    /* `laboratorio` NO se envia: el controlador no lo contempla y mandarlo
       daria la impresion de que filtra cuando el servidor lo ignora. Ese
       filtro se resuelve en la vista sobre la pagina cargada, con sus
       limitaciones, hasta que exista en la API. */

    const respuesta = await api.get('/productos', { params })
    return abrirPaginador(respuesta.data)
  }

  async getAllProducts(): Promise<Product[]> {
    const { items } = await this.listar()
    return items
  }

  async getProductById(id: number): Promise<Product> {
    const respuesta = await api.get(`/productos/${id}`)
    const cuerpo = respuesta.data as Product | { data: Product }
    return normalizar((cuerpo as { data?: Product }).data ?? (cuerpo as Product))
  }

  async searchProducts(query: string): Promise<Product[]> {
    const { items } = await this.listar({ busqueda: query })
    return items
  }

  async getProductsByType(type: string): Promise<Product[]> {
    const { items } = await this.listar({ tipo: type })
    return items
  }

  /** Filtra sobre la pagina cargada: la API todavia no acepta este criterio. */
  async getProductsByLaboratory(laboratory: string): Promise<Product[]> {
    const { items } = await this.listar()
    return items.filter(p => p.laboratorio === laboratory)
  }
}

export default new ProductsService()
