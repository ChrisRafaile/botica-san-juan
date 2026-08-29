<template>
  <div class="min-h-screen bg-gray-50 pt-16">
    <!-- Header Section -->
    <section class="bg-white shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center">
          <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
            Nuestros Productos
          </h1>
          <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            Encuentra todos los medicamentos y productos de salud que necesitas.
            Calidad garantizada y precios competitivos.
          </p>
        </div>
      </div>
    </section>

    <!-- Filters and Search -->
    <section class="bg-white border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col lg:flex-row gap-4 items-center justify-between">
          <!-- Search -->
          <div class="relative flex-1 max-w-md">
            <SearchIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar productos..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            />
          </div>

          <!-- Filters -->
          <div class="flex flex-wrap gap-4">
            <!-- Tipo Filter -->
            <select
              v-model="selectedTipo"
              class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            >
              <option value="">
                Todos los tipos
              </option>
              <option
                v-for="tipo in tipos"
                :key="tipo"
                :value="tipo"
              >
                {{ tipo }}
              </option>
            </select>

            <!-- Laboratorio Filter -->
            <select
              v-model="selectedLaboratorio"
              class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            >
              <option value="">
                Todos los laboratorios
              </option>
              <option
                v-for="lab in laboratorios"
                :key="lab"
                :value="lab"
              >
                {{ lab }}
              </option>
            </select>

            <!-- Sort -->
            <select
              v-model="sortBy"
              class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            >
              <option value="nombre">
                Ordenar por nombre
              </option>
              <option value="precio-asc">
                Precio: menor a mayor
              </option>
              <option value="precio-desc">
                Precio: mayor a menor
              </option>
              <option value="stock">
                Stock disponible
              </option>
            </select>
          </div>
        </div>
      </div>
    </section>

    <!-- Products Grid -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <div
          v-for="product in filteredProducts"
          :key="product.id"
          class="card group"
        >
          <!-- Product Image -->
          <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden mb-4">
            <img
              :src="product.imagen"
              :alt="product.nombre"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
          </div>

          <!-- Product Info -->
          <div class="space-y-2">
            <h3 class="text-lg font-semibold text-gray-900 line-clamp-2">
              {{ product.nombre }}
            </h3>

            <div class="text-sm text-gray-600 space-y-1">
              <p v-if="product.concentracion">
                <span class="font-medium">Concentración:</span> {{ product.concentracion }}
              </p>
              <p v-if="product.laboratorio">
                <span class="font-medium">Laboratorio:</span> {{ product.laboratorio }}
              </p>
              <p v-if="product.presentacion">
                <span class="font-medium">Presentación:</span> {{ product.presentacion }}
              </p>
              <p v-if="product.tipo">
                <span class="font-medium">Tipo:</span> {{ product.tipo }}
              </p>
            </div>

            <!-- Stock and Price -->
            <div class="flex items-center justify-between pt-2">
              <div class="flex items-center space-x-2">
                <span
                  class="text-sm font-medium"
                  :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'"
                >
                  {{ product.stock > 0 ? 'En stock' : 'Agotado' }}
                </span>
                <span class="text-sm text-gray-500">
                  ({{ product.stock }})
                </span>
              </div>
            </div>

            <div class="flex items-center justify-between pt-2">
              <span class="text-2xl font-bold text-primary">
                S/ {{ product.precio.toFixed(2) }}
              </span>

              <button
                v-if="product.stock > 0"
                class="btn-primary text-sm px-4 py-2"
                @click="addToCart(product)"
              >
                <ShoppingCartIcon class="w-4 h-4 mr-2" />
                Agregar
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- No products found -->
      <div
        v-if="filteredProducts.length === 0"
        class="text-center py-12"
      >
        <PackageIcon class="w-16 h-16 text-gray-400 mx-auto mb-4" />
        <h3 class="text-lg font-medium text-gray-900 mb-2">
          No se encontraron productos
        </h3>
        <p class="text-gray-600">
          Intenta con otros términos de búsqueda o filtros.
        </p>
      </div>
    </section>

    <!-- Loading State -->
    <div
      v-if="productsStore.isLoading"
      class="flex items-center justify-center py-12"
    >
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useCartStore } from '@/stores/carrito'
import { useProductsStore } from '@/stores/productos'
import type { Product } from '@/services/products'
import {
  SearchIcon,
  ShoppingCartIcon,
  PackageIcon
} from 'lucide-vue-next'

const cartStore = useCartStore()
const productsStore = useProductsStore()

// Reactive data
const searchQuery = ref('')
const selectedTipo = ref('')
const selectedLaboratorio = ref('')
const sortBy = ref('nombre')

// Computed properties
const tipos = computed(() => {
  return productsStore.categories
})

const laboratorios = computed(() => {
  const unique = [...new Set(productsStore.products.map(p => p.laboratorio))].filter(Boolean)
  return unique
})

const filteredProducts = computed(() => {
  let filtered = productsStore.filteredProducts.filter(product => {
    const matchesTipo = !selectedTipo.value || product.tipo === selectedTipo.value
    const matchesLaboratorio = !selectedLaboratorio.value || product.laboratorio === selectedLaboratorio.value

    return matchesTipo && matchesLaboratorio
  })

  // Sort
  filtered.sort((a, b) => {
    switch (sortBy.value) {
      case 'precio-asc':
        return a.precio - b.precio
      case 'precio-desc':
        return b.precio - a.precio
      case 'stock':
        return b.stock - a.stock
      default:
        return a.nombre.localeCompare(b.nombre)
    }
  })

  return filtered
})

// Methods
const addToCart = async (product: Product) => {
  try {
    await cartStore.addItem(product.id, 1)
  } catch (error) {
    console.error('Error adding to cart:', error)
    // Handle error (show toast, etc.)
  }
}

// Watchers
watch(searchQuery, (newQuery) => {
  productsStore.searchProducts(newQuery)
})

watch(selectedTipo, (newTipo) => {
  productsStore.filterByCategory(newTipo)
})

// Lifecycle
onMounted(async () => {
  await productsStore.loadProducts()
  await cartStore.loadCart()
})
</script>