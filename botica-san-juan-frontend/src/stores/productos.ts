import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import productsService from '@/services/products'
import type { Product } from '@/services/products'

/**
 * Catalogo del portal publico.
 *
 * LOS FILTROS SE RESUELVEN EN EL SERVIDOR
 *
 * La version anterior cargaba una pagina y filtraba sobre ella en el
 * navegador. Con 3 361 productos en el catalogo eso significa que buscar
 * "paracetamol" solo lo encontraba si ya estaba entre los que se habian
 * descargado, y que el desplegable de tipos solo ofrecia los tipos presentes
 * en esa pagina. Es el mismo error que ya aparecio en el tablero y en la
 * pantalla de inventario del administrador: contar o filtrar sobre lo
 * cargado en vez de preguntarle a quien tiene todos los datos.
 *
 * `total` guarda cuantos productos hay DE VERDAD, para que la pantalla pueda
 * decir "48 de 3 361" en lugar de dar a entender que eso es todo el catalogo.
 */
export const useProductsStore = defineStore('products', () => {
  const products = ref<Product[]>([])
  const filteredProducts = ref<Product[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const searchQuery = ref('')
  const selectedCategory = ref<string>('')

  /** Total real del catalogo segun el servidor, no el de la pagina cargada. */
  const total = ref(0)
  /** Cuantos coinciden con el filtro actual, tambien segun el servidor. */
  const totalFiltrado = ref(0)

  const categories = computed(() => {
    const unicas = new Set(products.value.map(p => p.tipo).filter(Boolean))
    return Array.from(unicas).sort()
  })

  /** Hay mas resultados de los que se estan mostrando. */
  const hayMasResultados = computed(() => filteredProducts.value.length < totalFiltrado.value)

  async function consultar(opciones: { busqueda?: string; tipo?: string }) {
    isLoading.value = true
    error.value = null
    try {
      const { items, total: cuantos } = await productsService.listar(opciones)
      filteredProducts.value = items
      totalFiltrado.value = cuantos
      return items
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'No se pudo cargar el catálogo'
      console.error('Error al consultar el catálogo:', err)
      filteredProducts.value = []
      totalFiltrado.value = 0
      return []
    } finally {
      isLoading.value = false
    }
  }

  const loadProducts = async () => {
    const items = await consultar({})
    products.value = items
    total.value = totalFiltrado.value
  }

  const searchProducts = async (query: string) => {
    searchQuery.value = query
    await consultar({ busqueda: query, tipo: selectedCategory.value })
  }

  const filterByCategory = async (category: string) => {
    selectedCategory.value = category
    await consultar({ busqueda: searchQuery.value, tipo: category })
  }

  const getProductById = (id: number) => {
    return products.value.find(p => p.id === id)
  }

  const clearFilters = async () => {
    searchQuery.value = ''
    selectedCategory.value = ''
    await loadProducts()
  }

  return {
    products,
    filteredProducts,
    isLoading,
    error,
    searchQuery,
    selectedCategory,
    categories,
    total,
    totalFiltrado,
    hayMasResultados,
    loadProducts,
    searchProducts,
    filterByCategory,
    getProductById,
    clearFilters,
  }
})
