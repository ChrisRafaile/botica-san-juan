import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import productsService from '@/services/products'
import type { Product, FacetasCatalogo, OrdenCatalogo } from '@/services/products'

/**
 * Catálogo del portal público.
 *
 * TODO SE RESUELVE EN EL SERVIDOR
 *
 * Filtrar y paginar en el navegador exige haber descargado antes el catálogo
 * entero. Con 3 361 productos eso es una descarga enorme para enseñar doce, y
 * además miente: la versión anterior cargaba una página y filtraba sobre ella,
 * así que buscar "paracetamol" sólo lo encontraba si ya estaba entre los
 * descargados, y el desplegable de tipos ofrecía los tipos de esa página.
 *
 * Es el patrón que ya apareció en el tablero y en inventario: contar o filtrar
 * sobre lo cargado en vez de preguntárselo a quien tiene todos los datos.
 *
 * Aquí la consulta entera —búsqueda, tipo, laboratorio, categoría,
 * disponibilidad, orden y página— viaja a la API, y las facetas de los
 * desplegables vienen de un endpoint que las cuenta sobre la tabla completa.
 */
export const useProductsStore = defineStore('products', () => {
  const products = ref<Product[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  /* --- Criterios actuales ------------------------------------------------ */
  const busqueda = ref('')
  const tipo = ref('')
  const laboratorio = ref('')
  const categoriaId = ref<number | null>(null)
  const soloDisponibles = ref(false)
  const orden = ref<OrdenCatalogo>('nombre')

  /* --- Paginación -------------------------------------------------------- */
  const pagina = ref(1)
  const porPagina = ref(24)
  const totalFiltrado = ref(0)
  const ultimaPagina = ref(1)

  /* --- Facetas ----------------------------------------------------------- */
  const facetas = ref<FacetasCatalogo | null>(null)

  const hayFiltrosActivos = computed(
    () =>
      busqueda.value.trim() !== '' ||
      tipo.value !== '' ||
      laboratorio.value !== '' ||
      categoriaId.value !== null ||
      soloDisponibles.value,
  )

  const desde = computed(() => (totalFiltrado.value === 0 ? 0 : (pagina.value - 1) * porPagina.value + 1))
  const hasta = computed(() => Math.min(pagina.value * porPagina.value, totalFiltrado.value))

  /**
   * Cada consulta lleva su número de orden y sólo se acepta el resultado de la
   * última. Sin esto, escribir rápido en el buscador deja que una respuesta
   * lenta de hace tres letras pise a la que corresponde a lo escrito ahora:
   * la lista acaba mostrando resultados de una búsqueda que ya no existe.
   */
  let consultaEnCurso = 0

  async function consultar() {
    const mia = ++consultaEnCurso
    isLoading.value = true
    error.value = null

    try {
      const resultado = await productsService.listar({
        busqueda: busqueda.value,
        tipo: tipo.value || undefined,
        laboratorio: laboratorio.value || undefined,
        categoriaId: categoriaId.value,
        soloDisponibles: soloDisponibles.value,
        orden: orden.value,
        pagina: pagina.value,
        porPagina: porPagina.value,
      })

      if (mia !== consultaEnCurso) return

      products.value = resultado.items
      totalFiltrado.value = resultado.total
      ultimaPagina.value = Math.max(1, resultado.ultimaPagina)

      /* Si un filtro deja menos páginas que la que se estaba viendo, se vuelve
         a la última válida en vez de mostrar una página vacía. */
      if (pagina.value > ultimaPagina.value) {
        pagina.value = ultimaPagina.value
        await consultar()
      }
    } catch (err) {
      if (mia !== consultaEnCurso) return
      error.value = err instanceof Error ? err.message : 'No se pudo cargar el catálogo'
      products.value = []
      totalFiltrado.value = 0
      ultimaPagina.value = 1
    } finally {
      if (mia === consultaEnCurso) isLoading.value = false
    }
  }

  /** Las facetas se piden una vez: no cambian al filtrar. */
  async function cargarFacetas() {
    if (facetas.value) return
    try {
      facetas.value = await productsService.facetas()
    } catch {
      /* Sin facetas la pantalla sigue siendo usable: se queda sin desplegables
         pero la búsqueda y la paginación funcionan igual. */
      facetas.value = null
    }
  }

  /** Cualquier cambio de criterio vuelve a la página 1. */
  async function aplicarFiltros() {
    pagina.value = 1
    await consultar()
  }

  async function irAPagina(n: number) {
    const destino = Math.min(Math.max(1, n), ultimaPagina.value)
    if (destino === pagina.value) return
    pagina.value = destino
    await consultar()
  }

  async function limpiarFiltros() {
    busqueda.value = ''
    tipo.value = ''
    laboratorio.value = ''
    categoriaId.value = null
    soloDisponibles.value = false
    orden.value = 'nombre'
    await aplicarFiltros()
  }

  async function inicializar() {
    await Promise.all([consultar(), cargarFacetas()])
  }

  return {
    products,
    isLoading,
    error,
    busqueda,
    tipo,
    laboratorio,
    categoriaId,
    soloDisponibles,
    orden,
    pagina,
    porPagina,
    totalFiltrado,
    ultimaPagina,
    facetas,
    hayFiltrosActivos,
    desde,
    hasta,
    inicializar,
    consultar,
    aplicarFiltros,
    irAPagina,
    limpiarFiltros,
  }
})
