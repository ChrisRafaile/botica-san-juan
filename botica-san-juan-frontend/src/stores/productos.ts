import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import productsService from '@/services/products'
import type { Product } from '@/services/products'

export const useProductsStore = defineStore('products', () => {
  const products = ref<Product[]>([])
  const filteredProducts = ref<Product[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const searchQuery = ref('')
  const selectedCategory = ref<string>('')

  const categories = computed(() => {
    const uniqueCategories = new Set(products.value.map(product => product.tipo).filter(Boolean))
    return Array.from(uniqueCategories)
  })

  const loadProducts = async () => {
    try {
      isLoading.value = true
      error.value = null
      const allProducts = await productsService.getAllProducts()
      products.value = allProducts
      filteredProducts.value = allProducts
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Error loading products'
      console.error('Error loading products:', err)
    } finally {
      isLoading.value = false
    }
  }

  const searchProducts = async (query: string) => {
    try {
      isLoading.value = true
      error.value = null
      searchQuery.value = query

      if (query.trim()) {
        const results = await productsService.searchProducts(query)
        filteredProducts.value = results
      } else {
        filteredProducts.value = products.value
      }
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Error searching products'
      console.error('Error searching products:', err)
    } finally {
      isLoading.value = false
    }
  }

  const filterByCategory = (category: string) => {
    selectedCategory.value = category

    if (category) {
      filteredProducts.value = products.value.filter(product => product.tipo === category)
    } else {
      filteredProducts.value = products.value
    }
  }

  const getProductById = (id: number) => {
    return products.value.find(product => product.id === id)
  }

  const clearFilters = () => {
    searchQuery.value = ''
    selectedCategory.value = ''
    filteredProducts.value = products.value
  }

  return {
    products,
    filteredProducts,
    isLoading,
    error,
    searchQuery,
    selectedCategory,
    categories,
    loadProducts,
    searchProducts,
    filterByCategory,
    getProductById,
    clearFilters
  }
})