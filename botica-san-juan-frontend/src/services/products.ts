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
  /**
   * Stock REALMENTE VENDIBLE: excluye los lotes vencidos.
   *
   * `stock` es el contable: cuenta lo que fisicamente esta en el anaquel,
   * vencido incluido. Mostrarlo en el portal promete unidades que el mostrador
   * no puede entregar, asi que el controlador calcula aparte lo disponible y lo
   * agrega a cada item del listado.
   *
   * Es OPCIONAL en el tipo porque no todos los endpoints lo adjuntan (el
   * `show` de un producto, por ejemplo). `normalizar` le pone el valor de
   * `stock` como respaldo para que la vista nunca tenga que decidir si el
   * campo existe; declararlo obligatorio seria afirmar algo que la respuesta
   * no garantiza, y ese tipo de mentira en el tipo ya costo una pantalla en
   * blanco en este archivo.
   */
  stock_disponible?: number
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

export type OrdenCatalogo = 'nombre' | 'precio_asc' | 'precio_desc' | 'recientes'

export interface OpcionesListado {
  busqueda?: string
  tipo?: string
  laboratorio?: string
  categoriaId?: number | null
  /** Sólo productos con existencias. */
  soloDisponibles?: boolean
  orden?: OrdenCatalogo
  pagina?: number
  porPagina?: number
}

/** Un valor posible de filtro, con cuántos productos tiene detrás. */
export interface Faceta {
  valor: string
  etiqueta?: string
  total: number
}

export interface FacetasCatalogo {
  tipos: Faceta[]
  laboratorios: Faceta[]
  categorias: Faceta[]
  total: number
  sinStock: number
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
    /* Respaldo a `stock` si el endpoint no adjunta lo disponible: es peor
       quedarse sin dato —y pintar todo como agotado— que mostrar el contable. */
    stock_disponible: Number(p.stock_disponible ?? p.stock ?? 0),
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
    /* `laboratorio` YA SE ENVIA. Antes no: el controlador no lo contemplaba y
       mandarlo habria dado la impresion de filtrar sin hacerlo. Ahora existe
       en la API, con sus 83 valores reales. */
    if (opciones.laboratorio) params.laboratorio = opciones.laboratorio
    if (opciones.categoriaId) params.categoria_id = opciones.categoriaId
    if (opciones.soloDisponibles) params.stock_status = 'in_stock'
    /* El catalogo publico se ordena por nombre: "lo ultimo que se edito en el
       almacen", que es el orden por omision del servidor, no significa nada
       para quien viene a comprar. */
    params.orden = opciones.orden ?? 'nombre'

    const respuesta = await api.get('/productos', { params })
    return abrirPaginador(respuesta.data)
  }

  /**
   * Valores posibles de cada filtro, contados sobre TODO el catalogo.
   *
   * La pantalla construia sus desplegables con lo que viniera en la pagina
   * cargada, asi que de 83 laboratorios ofrecia los pocos que hubieran caido
   * en esos 48 productos y el resto del catalogo quedaba inalcanzable.
   */
  async facetas(): Promise<FacetasCatalogo> {
    const { data } = await api.get('/productos/facetas')
    const lista = (x: unknown): Faceta[] =>
      Array.isArray(x)
        ? x.map((f) => ({
            valor: String((f as Faceta).valor ?? ''),
            etiqueta: (f as Faceta).etiqueta,
            total: Number((f as Faceta).total ?? 0),
          })).filter((f) => f.valor !== '')
        : []

    return {
      tipos: lista(data?.tipos),
      laboratorios: lista(data?.laboratorios),
      categorias: lista(data?.categorias),
      total: Number(data?.total ?? 0),
      sinStock: Number(data?.sin_stock ?? 0),
    }
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
