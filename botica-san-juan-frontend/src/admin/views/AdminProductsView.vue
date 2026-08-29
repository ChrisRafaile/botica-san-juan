<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">
          {{ pageTitle }}
        </h1>
        <p class="text-gray-600">
          {{ pageDescription }}
        </p>
      </div>
      <div class="flex gap-3">
        <button
          class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold flex items-center space-x-2 transition-colors"
          :class="{ 'hidden': route.path === '/admin/products/add' }"
          @click="$router.push('/admin/products/bulk-upload')"
        >
          <Upload class="w-5 h-5" />
          <span>Carga Masiva</span>
        </button>
        <button
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold flex items-center space-x-2 transition-colors"
          :class="{ 'hidden': route.path === '/admin/products/add' || isCategoryRoute || isSubcategoryRoute }"
          @click="openCreateModal"
        >
          <Plus class="w-5 h-5" />
          <span>Nuevo Producto</span>
        </button>
      </div>
    </div>

    <!-- Filtros y búsqueda -->
    <div
      v-if="!isCategoryRoute && !isSubcategoryRoute"
      class="bg-white rounded-xl shadow-lg p-6 mb-6"
    >
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
          <input
            v-model="filters.search"
            type="text"
            placeholder="Buscar por nombre, laboratorio..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @input="debouncedSearch"
          />
        </div>
        <div>
          <select
            v-model="filters.categoria_id"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @change="fetchProducts()"
          >
            <option :value="''">
              Todas las categorías
            </option>
            <option
              v-for="category in categoryObjects"
              :key="category.id"
              :value="String(category.id)"
            >
              {{ category.name }}
            </option>
          </select>
        </div>
        <div>
          <select
            v-model="filters.stock_status"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            @change="fetchProducts()"
          >
            <option value="">
              Todos los estados
            </option>
            <option value="in_stock">
              En Stock
            </option>
            <option value="low_stock">
              Stock Bajo
            </option>
            <option value="out_of_stock">
              Agotado
            </option>
          </select>
        </div>
      </div>

      <!-- Filtros activos -->
      <div
        v-if="hasActiveFilters"
        class="flex flex-wrap gap-2 mt-4"
      >
        <span class="text-sm text-gray-600">Filtros activos:</span>
        <button
          v-if="filters.search"
          class="inline-flex items-center px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full"
          @click="clearFilter('search')"
        >
          Búsqueda: {{ filters.search }}
          <X class="w-3 h-3 ml-1" />
        </button>
        <button
          v-if="filters.categoria_id"
          class="inline-flex items-center px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full"
          @click="clearFilter('categoria_id')"
        >
          Categoría: {{ selectedCategoryName }}
          <X class="w-3 h-3 ml-1" />
        </button>
        <button
          v-if="filters.stock_status"
          class="inline-flex items-center px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full"
          @click="clearFilter('stock_status')"
        >
          Estado: {{ filters.stock_status }}
          <X class="w-3 h-3 ml-1" />
        </button>
        <button
          class="inline-flex items-center px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full hover:bg-gray-200"
          @click="clearAllFilters"
        >
          Limpiar todos
        </button>
      </div>
    </div>

    <!-- Estadísticas rápidas -->
    <div
      v-if="!isCategoryRoute && !isSubcategoryRoute"
      class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6"
    >
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center">
          <div class="p-3 bg-blue-100 rounded-lg">
            <Package class="w-6 h-6 text-blue-600" />
          </div>
          <div class="ml-4">
            <p
              class="text-sm font-medium text-gray-600"
            >
              Total Productos
            </p>
            <p
              class="text-2xl font-bold text-gray-900"
            >
              {{ stats.total }}
            </p>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center">
          <div class="p-3 bg-green-100 rounded-lg">
            <CheckCircle class="w-6 h-6 text-green-600" />
          </div>
          <div class="ml-4">
            <p
              class="text-sm font-medium text-gray-600"
            >
              En Stock
            </p>
            <p
              class="text-2xl font-bold text-gray-900"
            >
              {{ stats.inStock }}
            </p>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center">
          <div class="p-3 bg-yellow-100 rounded-lg">
            <AlertTriangle class="w-6 h-6 text-yellow-600" />
          </div>
          <div class="ml-4">
            <p
              class="text-sm font-medium text-gray-600"
            >
              Stock Bajo
            </p>
            <p
              class="text-2xl font-bold text-gray-900"
            >
              {{ stats.lowStock }}
            </p>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center">
          <div class="p-3 bg-red-100 rounded-lg">
            <XCircle class="w-6 h-6 text-red-600" />
          </div>
          <div class="ml-4">
            <p
              class="text-sm font-medium text-gray-600"
            >
              Agotados
            </p>
            <p
              class="text-2xl font-bold text-gray-900"
            >
              {{ stats.outOfStock }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Mensaje de error -->
    <div
      v-if="error"
      class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center justify-between"
    >
      <span>{{ error }}</span>
      <button
        class="text-red-700 hover:text-red-900"
        @click="error = ''"
      >
        <X class="w-4 h-4" />
      </button>
    </div>

    <!-- Vista de Categorías -->
    <CategoryManager
      v-if="isCategoryRoute"
      :categories="categoryObjects"
      :loading="loadingCategories"
      @create-category="openCreateCategoryModal"
      @edit-category="openEditCategoryModal"
      @delete-category="confirmDeleteCategory"
      @view-category="viewCategoryDetails"
    />

    <SubcategoryManager
      v-if="isSubcategoryRoute"
      :subcategories="subCategoryObjects"
      :categories="categoryObjects"
      :loading="loadingSubcategories"
      @create-subcategory="openCreateSubcategoryModal"
      @edit-subcategory="openEditSubcategoryModal"
      @delete-subcategory="confirmDeleteSubcategory"
      @view-subcategory="viewSubcategoryDetails"
    />

    <!-- Tabla de productos -->
    <div
      v-if="!isCategoryRoute && !isSubcategoryRoute"
      class="bg-white rounded-xl shadow-lg overflow-hidden"
    >
      <div class="overflow-x-auto">
        <!-- Loading state -->
        <div
          v-if="loading"
          class="px-6 py-8 text-center"
        >
          <div class="inline-flex items-center">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mr-2"></div>
            Cargando productos...
          </div>
        </div>

        <div
          v-else-if="products.length === 0"
          class="px-6 py-8 text-center text-gray-500"
        >
          <Package class="w-12 h-12 mx-auto mb-4 text-gray-300" />
          <p class="text-lg font-medium">
            No hay productos disponibles
          </p>
          <p class="text-sm">
            Comienza creando tu primer producto
          </p>
        </div>

        <table
          v-else
          class="w-full"
        >
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Producto
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Laboratorio
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Categoría
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Precio
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Stock
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Estado
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Unidades multiples
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr
              v-for="product in products"
              :key="product.id"
              class="hover:bg-gray-50"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="shrink-0 h-10 w-10">
                    <img
                      v-if="product.imagen"
                      :src="product.imagen"
                      alt="Producto"
                      class="h-10 w-10 rounded-lg object-cover"
                    />
                    <div
                      v-else
                      class="h-10 w-10 rounded-lg bg-blue-100 flex items-center justify-center"
                    >
                      <Pill class="w-5 h-5 text-blue-600" />
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">
                      {{ product.nombre }}
                    </div>
                    <div class="text-sm text-gray-500">
                      {{ product.concentracion }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ product.laboratorio }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                  {{ product.categoria?.nombre || product.tipo || 'Sin categoría' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                S/ {{ product.precio }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  :class="{
                    'bg-green-100 text-green-800': product.stock > 10,
                    'bg-yellow-100 text-yellow-800': product.stock > 0 && product.stock <= 10,
                    'bg-red-100 text-red-800': product.stock === 0
                  }"
                >
                  {{ product.stock }} unidades
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  :class="{
                    'bg-green-100 text-green-800': getEstado(product.stock) === 'activo',
                    'bg-gray-100 text-gray-800': getEstado(product.stock) === 'agotado'
                  }"
                >
                  {{ getEstado(product.stock) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  :class="product.venta_fraccionada ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-700'"
                >
                  {{ product.venta_fraccionada ? 'Fraccionada' : 'Simple' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
                  <button
                    class="text-blue-600 hover:text-blue-900 p-1 rounded"
                    title="Editar"
                    @click="openEditModal(product)"
                  >
                    <Edit class="w-4 h-4" />
                  </button>
                  <button
                    class="text-purple-600 hover:text-purple-900 p-1 rounded"
                    title="Ver detalles"
                    @click="viewProduct(product)"
                  >
                    <Eye class="w-4 h-4" />
                  </button>
                  <button
                    class="text-red-600 hover:text-red-900 p-1 rounded"
                    title="Eliminar"
                    @click="confirmDelete(product)"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div
        v-if="pagination.total > 0"
        class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6"
      >
        <div class="flex-1 flex justify-between sm:hidden">
          <button
            :disabled="!pagination.prev_page_url"
            class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="changePage(pagination.current_page - 1)"
          >
            Anterior
          </button>
          <button
            :disabled="!pagination.next_page_url"
            class="ml-3 relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="changePage(pagination.current_page + 1)"
          >
            Siguiente
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700">
              Mostrando
              <span class="font-medium">{{ pagination.from || 0 }}</span>
              a
              <span class="font-medium">{{ pagination.to || 0 }}</span>
              de
              <span class="font-medium">{{ pagination.total }}</span>
              resultados
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
              <button
                :disabled="!pagination.prev_page_url"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="changePage(pagination.current_page - 1)"
              >
                <ChevronLeft class="w-5 h-5" />
              </button>

              <template
                v-for="page in visiblePages"
                :key="page"
              >
                <button
                  v-if="typeof page === 'number'"
                  class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50"
                  :class="{
                    'bg-blue-50 border-blue-500 text-blue-600': page === pagination.current_page
                  }"
                  @click="changePage(page)"
                >
                  {{ page }}
                </button>
                <span
                  v-else
                  class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700"
                >
                  {{ page }}
                </span>
              </template>

              <button
                :disabled="!pagination.next_page_url"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="changePage(pagination.current_page + 1)"
              >
                <ChevronRight class="w-5 h-5" />
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de producto -->
    <ProductModal
      :is-open="isModalOpen"
      :product="selectedProduct"
      :categories="categoryObjects"
      :subcategories="subCategoryObjects"
      :readonly="isModalReadonly"
      @close="closeModal"
      @saved="onProductSaved"
    />

    <!-- Category Modal -->
    <CategoryModal
      :is-open="isCategoryModalOpen"
      :is-editing="isEditingCategory"
      :initial-data="selectedCategory ? { name: selectedCategory.name, description: selectedCategory.description, color: selectedCategory.color, isActive: selectedCategory.isActive } : undefined"
      @close="closeCategoryModal"
      @submit="onCategorySaved"
    />

    <SubcategoryModal
      :is-open="isSubcategoryModalOpen"
      :is-editing="isEditingSubcategory"
      :categories="categoryObjects"
      :initial-data="selectedSubcategory ? { categoryId: selectedSubcategory.categoria_id, name: selectedSubcategory.name, description: selectedSubcategory.description, isActive: selectedSubcategory.isActive } : undefined"
      @close="closeSubcategoryModal"
      @submit="onSubcategorySaved"
    />

    <!-- Modal de confirmación de eliminación -->
    <div
      v-if="deleteModal.show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm"
      @click.self="deleteModal.show = false"
    >
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="text-center">
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
            <AlertTriangle class="h-6 w-6 text-red-600" />
          </div>
          <h3 class="text-lg font-medium text-gray-900 mb-2">
            Eliminar Producto
          </h3>
          <p class="text-sm text-gray-500 mb-6">
            ¿Estás seguro de que quieres eliminar "{{ deleteModal.product?.nombre }}"?
            Esta acción no se puede deshacer.
          </p>
          <div class="flex gap-3">
            <button
              class="flex-1 px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
              @click="deleteModal.show = false"
            >
              Cancelar
            </button>
            <button
              class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
              @click="deleteProduct"
            >
              Eliminar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
  Plus,
  Pill,
  Edit,
  Trash2,
  Eye,
  ChevronLeft,
  ChevronRight,
  X,
  Package,
  CheckCircle,
  AlertTriangle,
  XCircle,
  Upload
} from 'lucide-vue-next'
import api from '@/services/api'
import ProductModal from '@/admin/components/ProductModal.vue'
import CategoryModal from '@/admin/components/CategoryModal.vue'
import CategoryManager from '@/admin/components/CategoryManager.vue'
import SubcategoryManager from '@/admin/components/SubcategoryManager.vue'
import SubcategoryModal from '@/admin/components/SubcategoryModal.vue'

// Get router instance
const router = useRouter()
const route = useRoute()

// Definir la interfaz de categoría
interface Category {
  id: number
  name: string
  description: string
  color: string
  isActive: boolean
  productsCount: number
  createdAt: string
  updatedAt: string
}

interface SubCategory {
  id: number
  categoria_id: number
  name: string
  description: string
  isActive: boolean
  productsCount: number
  categoryName: string
}

// Definir la interfaz del producto
interface Product {
  id: number
  nombre: string
  concentracion: string
  adicional: string
  laboratorio: string
  presentacion: string
  tipo: string
  codigo_digemid?: string | null
  principio_activo?: string | null
  requiere_receta?: boolean
  laboratorio_fabricante?: string | null
  unidad_base?: 'unidad' | 'blister' | 'caja'
  venta_fraccionada?: boolean
  unidades_por_blister?: number | null
  blisters_por_caja?: number | null
  precio_blister?: string | number | null
  precio_caja?: string | number | null
  stock: number
  precio: string
  categoria_id?: number | null
  subcategoria_id?: number | null
  stock_minimo?: number
  stock_reposicion?: number
  codigo_barras?: string
  categoria?: {
    id: number
    nombre: string
  }
  imagen?: string
  created_at?: string
  updated_at?: string
}

// Definir la interfaz de paginación
interface Pagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
  prev_page_url?: string
  next_page_url?: string
}

// Estado de la aplicación
const products = ref<Product[]>([])
const loading = ref(false)
const loadingCategories = ref(false)
const loadingSubcategories = ref(false)
const error = ref('')


// Filtros
const filters = reactive({
  search: '',
  categoria_id: '',
  stock_status: ''
})

// Paginación
const pagination = reactive<Pagination>({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: 0,
  to: 0
})

// Estadísticas
const stats = reactive({
  total: 0,
  inStock: 0,
  lowStock: 0,
  outOfStock: 0
})

// Estado de categorías
const categoryObjects = ref<Category[]>([])
const subCategoryObjects = ref<SubCategory[]>([])

// Estado del modal de categoría
const isModalOpen = ref(false)
const isCategoryModalOpen = ref(false)
const isEditingCategory = ref(false)
const selectedCategory = ref<Category | null>(null)
const isSubcategoryModalOpen = ref(false)
const isEditingSubcategory = ref(false)
const selectedSubcategory = ref<SubCategory | null>(null)
const selectedProduct = ref<Product | null>(null)
const isModalReadonly = ref(false)

// Modal de eliminación
const deleteModal = reactive({
  show: false,
  product: null as Product | null
})

// Debounce para búsqueda
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const debouncedSearch = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout as unknown as number)
  }
  searchTimeout = setTimeout(() => {
    fetchProducts()
  }, 500)
}

// Computed properties
const hasActiveFilters = computed(() => {
  return filters.search || filters.categoria_id || filters.stock_status
})

const selectedCategoryName = computed(() => {
  if (!filters.categoria_id) return 'Todas'
  const category = categoryObjects.value.find(cat => String(cat.id) === filters.categoria_id)
  return category?.name || `ID ${filters.categoria_id}`
})

const visiblePages = computed(() => {
  const current = pagination.current_page
  const last = pagination.last_page
  const delta = 2
  const range: number[] = []
  const rangeWithDots: (number | string)[] = []

  for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
    range.push(i)
  }

  if (current - delta > 2) {
    rangeWithDots.push(1, '...')
  } else {
    rangeWithDots.push(1)
  }

  rangeWithDots.push(...range)

  if (current + delta < last - 1) {
    rangeWithDots.push('...', last)
  } else if (last > 1) {
    rangeWithDots.push(last)
  }

  return rangeWithDots.filter(item => item !== '...' || rangeWithDots.indexOf(item) === rangeWithDots.lastIndexOf(item))
})

const pageTitle = computed(() => {
  if (isCategoryRoute.value) {
    return 'Gestión de Categorías'
  }
  if (isSubcategoryRoute.value) {
    return 'Gestión de Subcategorías'
  }
  return 'Gestión de Productos'
})

const pageDescription = computed(() => {
  if (isCategoryRoute.value) {
    return 'Administra las categorías de productos de la botica'
  }
  if (isSubcategoryRoute.value) {
    return 'Administra subcategorías y su relación con el catálogo principal'
  }
  return 'Administra el catálogo de productos de la botica'
})

const isCategoryRoute = computed(() => route.path.endsWith('/categories'))
const isSubcategoryRoute = computed(() => route.path.endsWith('/subcategories'))

// Función para obtener productos desde la API
const fetchProducts = async (page = 1) => {
  loading.value = true
  error.value = ''

  try {
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: pagination.per_page.toString()
    })

    if (filters.search) params.append('search', filters.search)
    if (filters.categoria_id) params.append('categoria_id', filters.categoria_id)
    if (filters.stock_status) params.append('stock_status', filters.stock_status)

    const response = await api.get(`/productos?${params}`)
    products.value = response.data.data || response.data
    Object.assign(pagination, response.data.meta || response.data)

    // Calcular estadísticas
    calculateStats()

    // Actualizar catálogo si estamos en vistas de gestión
    if (isCategoryRoute.value) {
      await fetchCategories()
    }
    if (isSubcategoryRoute.value) {
      await fetchSubcategories()
    }
  } catch (err: unknown) {
    console.error('Error fetching products:', err)

    if (err instanceof Error && err.message.includes('401')) {
      error.value = 'Error de autenticación. Las rutas de productos deberían ser públicas.'
    } else {
      error.value = 'Error al cargar productos. Verifica que el servidor backend esté ejecutándose.'
    }
  } finally {
    loading.value = false
  }
}

// Calcular estadísticas
const calculateStats = () => {
  stats.total = pagination.total
  stats.inStock = products.value.filter(p => p.stock > 10).length
  stats.lowStock = products.value.filter(p => p.stock > 0 && p.stock <= 10).length
  stats.outOfStock = products.value.filter(p => p.stock === 0).length
}

// Función para determinar el estado basado en el stock
const getEstado = (stock: number) => {
  return stock > 0 ? 'activo' : 'agotado'
}

// Funciones de filtros
const clearFilter = (filterKey: keyof typeof filters) => {
  filters[filterKey] = ''
  fetchProducts()
}

const clearAllFilters = () => {
  filters.search = ''
  filters.categoria_id = ''
  filters.stock_status = ''
  fetchProducts()
}

// Funciones de paginación
const changePage = (page: number) => {
  if (page >= 1 && page <= pagination.last_page) {
    fetchProducts(page)
  }
}

// Funciones del modal
const openCreateModal = () => {
  selectedProduct.value = null
  isModalReadonly.value = false
  isModalOpen.value = true
}

const openEditModal = (product: Product) => {
  selectedProduct.value = product
  isModalReadonly.value = false
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
  selectedProduct.value = null
  isModalReadonly.value = false
}

const onProductSaved = async () => {
  // Recargar la lista de productos
  await fetchProducts(pagination.current_page)
  
  // Si se agregó un producto desde la ruta /add, redirigir a la lista
  if (route.path === '/admin/products/add') {
    router.push('/admin/products')
  }
}

// Funciones de eliminación
const confirmDelete = (product: Product) => {
  deleteModal.product = product
  deleteModal.show = true
}

const deleteProduct = async () => {
  if (!deleteModal.product) return

  try {
    await api.delete(`/productos/${deleteModal.product.id}`)
    deleteModal.show = false
    deleteModal.product = null
    await fetchProducts(pagination.current_page)
  } catch (err: unknown) {
    error.value = 'Error al eliminar el producto'
    console.error('Error deleting product:', err)
  }
}

// Función para ver detalles del producto
const viewProduct = (product: Product) => {
  selectedProduct.value = product
  isModalReadonly.value = true
  isModalOpen.value = true
}

// Funciones de categorías
const mapCategoryFromApi = (item: any): Category => ({
  id: item.id,
  name: item.nombre,
  description: item.descripcion || '',
  color: item.color || 'blue',
  isActive: Boolean(item.activa),
  productsCount: Number(item.productos_count || 0),
  createdAt: item.created_at,
  updatedAt: item.updated_at
})

const mapSubcategoryFromApi = (item: any): SubCategory => ({
  id: item.id,
  categoria_id: item.categoria_id,
  name: item.nombre,
  description: item.descripcion || '',
  isActive: Boolean(item.activa),
  productsCount: Number(item.productos_count || 0),
  categoryName: item.categoria?.nombre || 'Sin categoría'
})

const fetchCategories = async () => {
  loadingCategories.value = true
  try {
    const response = await api.get('/categorias?with_counts=1')
    const data = Array.isArray(response.data) ? response.data : (response.data.data || [])
    categoryObjects.value = data.map(mapCategoryFromApi)
  } catch (err) {
    console.error('Error fetching categories:', err)
    error.value = 'No se pudieron cargar las categorías.'
  } finally {
    loadingCategories.value = false
  }
}

const fetchSubcategories = async () => {
  loadingSubcategories.value = true
  try {
    const response = await api.get('/subcategorias?with_counts=1')
    const data = Array.isArray(response.data) ? response.data : (response.data.data || [])
    subCategoryObjects.value = data.map(mapSubcategoryFromApi)
  } catch (err) {
    console.error('Error fetching subcategories:', err)
    error.value = 'No se pudieron cargar las subcategorías.'
  } finally {
    loadingSubcategories.value = false
  }
}

const openCreateCategoryModal = () => {
  isEditingCategory.value = false
  selectedCategory.value = null
  isCategoryModalOpen.value = true
}

const openEditCategoryModal = (category: Category) => {
  isEditingCategory.value = true
  selectedCategory.value = { ...category }
  isCategoryModalOpen.value = true
}

const closeCategoryModal = () => {
  isCategoryModalOpen.value = false
  isEditingCategory.value = false
  selectedCategory.value = null
}

const onCategorySaved = async (categoryData: { name: string; description: string; color: string; isActive: boolean }) => {
  try {
    if (isEditingCategory.value && selectedCategory.value) {
      await api.put(`/categorias/${selectedCategory.value.id}`, {
        nombre: categoryData.name,
        descripcion: categoryData.description,
        color: categoryData.color,
        activa: categoryData.isActive
      })
    } else {
      await api.post('/categorias', {
        nombre: categoryData.name,
        descripcion: categoryData.description,
        color: categoryData.color,
        activa: categoryData.isActive
      })
    }

    await fetchCategories()
    closeCategoryModal()
  } catch (err) {
    console.error('Error saving category:', err)
    error.value = 'No se pudo guardar la categoría.'
  }
}

const confirmDeleteCategory = (category: Category) => {
  if (confirm(`¿Está seguro de que desea eliminar la categoría "${category.name}"? Esta acción no se puede deshacer.`)) {
    deleteCategory(category)
  }
}

const deleteCategory = async (category: Category) => {
  try {
    await api.delete(`/categorias/${category.id}`)
    await fetchCategories()
  } catch (err) {
    console.error('Error deleting category:', err)
    error.value = 'No se pudo eliminar la categoría.'
  }
}

const viewCategoryDetails = (category: Category) => {
  alert(`Categoría: ${category.name}\nDescripción: ${category.description || 'Sin descripción'}\nEstado: ${category.isActive ? 'Activa' : 'Inactiva'}\nProductos asociados: ${category.productsCount}`)
}

const openCreateSubcategoryModal = () => {
  isEditingSubcategory.value = false
  selectedSubcategory.value = null
  isSubcategoryModalOpen.value = true
}

const openEditSubcategoryModal = (subcategory: SubCategory) => {
  isEditingSubcategory.value = true
  selectedSubcategory.value = { ...subcategory }
  isSubcategoryModalOpen.value = true
}

const closeSubcategoryModal = () => {
  isSubcategoryModalOpen.value = false
  isEditingSubcategory.value = false
  selectedSubcategory.value = null
}

const onSubcategorySaved = async (subcategoryData: { categoryId: number; name: string; description: string; isActive: boolean }) => {
  try {
    if (isEditingSubcategory.value && selectedSubcategory.value) {
      await api.put(`/subcategorias/${selectedSubcategory.value.id}`, {
        categoria_id: subcategoryData.categoryId,
        nombre: subcategoryData.name,
        descripcion: subcategoryData.description,
        activa: subcategoryData.isActive
      })
    } else {
      await api.post('/subcategorias', {
        categoria_id: subcategoryData.categoryId,
        nombre: subcategoryData.name,
        descripcion: subcategoryData.description,
        activa: subcategoryData.isActive
      })
    }

    await fetchSubcategories()
    closeSubcategoryModal()
  } catch (err) {
    console.error('Error saving subcategory:', err)
    error.value = 'No se pudo guardar la subcategoría.'
  }
}

const confirmDeleteSubcategory = (subcategory: SubCategory) => {
  if (confirm(`¿Está seguro de que desea eliminar la subcategoría "${subcategory.name}"? Esta acción no se puede deshacer.`)) {
    deleteSubcategory(subcategory)
  }
}

const deleteSubcategory = async (subcategory: SubCategory) => {
  try {
    await api.delete(`/subcategorias/${subcategory.id}`)
    await fetchSubcategories()
  } catch (err) {
    console.error('Error deleting subcategory:', err)
    error.value = 'No se pudo eliminar la subcategoría.'
  }
}

const viewSubcategoryDetails = (subcategory: SubCategory) => {
  alert(`Subcategoría: ${subcategory.name}\nCategoría: ${subcategory.categoryName}\nDescripción: ${subcategory.description || 'Sin descripción'}\nEstado: ${subcategory.isActive ? 'Activa' : 'Inactiva'}\nProductos asociados: ${subcategory.productsCount}`)
}

// Cargar productos al montar el componente
onMounted(async () => {
  await fetchCategories()
  await fetchSubcategories()
  await fetchProducts()
  
  // Si la ruta es /admin/products/add, abrir el modal de agregar producto
  if (route.path === '/admin/products/add') {
    openCreateModal()
  }

})
</script>