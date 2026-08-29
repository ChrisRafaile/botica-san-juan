<template>
  <div class="bg-white rounded-xl shadow-lg p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
      <div>
        <h2
          class="text-2xl font-bold text-gray-800 mb-1"
        >
          Categorías de Productos
        </h2>
        <p
          class="text-gray-600"
        >
          Gestiona las categorías para organizar tus productos
        </p>
      </div>
      <div class="mt-4 sm:mt-0 flex flex-col sm:flex-row gap-3">
        <button
          class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors"
          @click="toggleViewMode"
        >
          <Grid3X3
            v-if="viewMode === 'grid'"
            class="w-4 h-4 mr-2"
          />
          <List
            v-else
            class="w-4 h-4 mr-2"
          />
          {{ viewMode === 'grid' ? 'Vista Lista' : 'Vista Cuadrícula' }}
        </button>
        <button
          class="inline-flex items-center px-4 py-2 bg-linear-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white rounded-lg font-medium transition-all"
          @click="openCreateModal"
        >
          <Plus class="w-4 h-4 mr-2" />
          Nueva Categoría
        </button>
      </div>
    </div>

    <!-- Search and Filters -->
    <div class="mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <div class="relative">
            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar categorías..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
        </div>
        <div class="flex gap-2">
          <select
            v-model="statusFilter"
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="all">
              Todos los estados
            </option>
            <option value="active">
              Activas
            </option>
            <option value="inactive">
              Inactivas
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-linear-to-r from-blue-500 to-blue-600 rounded-lg p-4 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p
              class="text-blue-100 text-sm"
            >
              Total Categorías
            </p>
            <p
              class="text-2xl font-bold"
            >
              {{ filteredCategories.length }}
            </p>
          </div>
          <Tag class="w-8 h-8 text-blue-200" />
        </div>
      </div>
      <div class="bg-linear-to-r from-green-500 to-green-600 rounded-lg p-4 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p
              class="text-green-100 text-sm"
            >
              Activas
            </p>
            <p
              class="text-2xl font-bold"
            >
              {{ activeCategoriesCount }}
            </p>
          </div>
          <CheckCircle class="w-8 h-8 text-green-200" />
        </div>
      </div>
      <div class="bg-linear-to-r from-yellow-500 to-yellow-600 rounded-lg p-4 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p
              class="text-yellow-100 text-sm"
            >
              Inactivas
            </p>
            <p
              class="text-2xl font-bold"
            >
              {{ inactiveCategoriesCount }}
            </p>
          </div>
          <XCircle class="w-8 h-8 text-yellow-200" />
        </div>
      </div>
      <div class="bg-linear-to-r from-purple-500 to-purple-600 rounded-lg p-4 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p
              class="text-purple-100 text-sm"
            >
              Con Productos
            </p>
            <p
              class="text-2xl font-bold"
            >
              {{ categoriesWithProductsCount }}
            </p>
          </div>
          <Package class="w-8 h-8 text-purple-200" />
        </div>
      </div>
    </div>

    <!-- Categories Grid/List View -->
    <div
      v-if="viewMode === 'grid'"
      class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"
    >
      <div
        v-for="category in filteredCategories"
        :key="category.id"
        class="group bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg hover:border-blue-300 transition-all duration-200 cursor-pointer"
        @click="viewCategoryDetails(category)"
      >
        <div class="flex items-start justify-between mb-4">
          <div
            class="w-12 h-12 rounded-lg flex items-center justify-center"
            :class="getCategoryColorClasses(category.color)"
          >
            <Tag class="w-6 h-6" />
          </div>
          <div class="flex items-center space-x-1">
            <button
              class="p-1 text-gray-400 hover:text-blue-600 transition-colors"
              @click.stop="editCategory(category)"
            >
              <Edit class="w-4 h-4" />
            </button>
            <button
              class="p-1 text-gray-400 hover:text-red-600 transition-colors"
              @click.stop="confirmDeleteCategory(category)"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>

        <div class="mb-3">
          <h3
            class="font-semibold text-gray-900 mb-1"
          >
            {{ category.name }}
          </h3>
          <p
            v-if="category.description"
            class="text-sm text-gray-600 line-clamp-2"
          >
            {{ category.description }}
          </p>
        </div>

        <div class="flex items-center justify-between">
          <span
            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
            :class="category.isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
          >
            {{ category.isActive ? 'Activa' : 'Inactiva' }}
          </span>
          <span class="text-sm text-gray-500">
            {{ category.productsCount }} productos
          </span>
        </div>
      </div>
    </div>

    <!-- Categories List View -->
    <div
      v-else
      class="bg-white border border-gray-200 rounded-xl overflow-hidden"
    >
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Categoría
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Descripción
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Estado
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Productos
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr
              v-for="category in filteredCategories"
              :key="category.id"
              class="hover:bg-gray-50 cursor-pointer"
              @click="viewCategoryDetails(category)"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div
                    class="w-8 h-8 rounded-lg flex items-center justify-center mr-3"
                    :class="getCategoryColorClasses(category.color)"
                  >
                    <Tag class="w-4 h-4" />
                  </div>
                  <div>
                    <div
                      class="text-sm font-medium text-gray-900"
                    >
                      {{ category.name }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="text-sm text-gray-900 max-w-xs truncate">
                  {{ category.description || 'Sin descripción' }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="category.isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                >
                  {{ category.isActive ? 'Activa' : 'Inactiva' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ category.productsCount }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex items-center space-x-2">
                  <button
                    class="text-blue-600 hover:text-blue-900 transition-colors"
                    @click.stop="editCategory(category)"
                  >
                    <Edit class="w-4 h-4" />
                  </button>
                  <button
                    class="text-red-600 hover:text-red-900 transition-colors"
                    @click.stop="confirmDeleteCategory(category)"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-if="filteredCategories.length === 0 && !loading"
      class="text-center py-12"
    >
      <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
        <Tag class="w-12 h-12 text-gray-400" />
      </div>
      <h3
        class="text-lg font-medium text-gray-900 mb-2"
      >
        No hay categorías
      </h3>
      <p class="text-gray-500 mb-6">
        {{ searchQuery || statusFilter !== 'all' ? 'No se encontraron categorías con los filtros aplicados.' : 'Comienza creando tu primera categoría.' }}
      </p>
      <button
        class="inline-flex items-center px-4 py-2 bg-linear-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white rounded-lg font-medium transition-all"
        @click="openCreateModal"
      >
        <Plus class="w-4 h-4 mr-2" />
        Crear Primera Categoría
      </button>
    </div>

    <!-- Loading State -->
    <div
      v-if="loading"
      class="text-center py-12"
    >
      <div class="inline-flex items-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mr-3"></div>
        <span class="text-gray-600">Cargando categorías...</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Plus, Tag, Edit, Trash2, Search, Grid3X3, List, CheckCircle, XCircle, Package } from 'lucide-vue-next'

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

interface Props {
  categories: Category[]
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false
})

const emit = defineEmits<{
  'create-category': []
  'edit-category': [category: Category]
  'delete-category': [category: Category]
  'view-category': [category: Category]
}>()

const viewMode = ref<'grid' | 'list'>('grid')
const searchQuery = ref('')
const statusFilter = ref<'all' | 'active' | 'inactive'>('all')

const filteredCategories = computed(() => {
  let filtered = props.categories

  // Filter by search query
  if (searchQuery.value) {
    filtered = filtered.filter(category =>
      category.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      category.description.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
  }

  // Filter by status
  if (statusFilter.value !== 'all') {
    filtered = filtered.filter(category =>
      statusFilter.value === 'active' ? category.isActive : !category.isActive
    )
  }

  return filtered
})

const activeCategoriesCount = computed(() =>
  props.categories.filter(cat => cat.isActive).length
)

const inactiveCategoriesCount = computed(() =>
  props.categories.filter(cat => !cat.isActive).length
)

const categoriesWithProductsCount = computed(() =>
  props.categories.filter(category => category.productsCount > 0).length
)

const getCategoryColorClasses = (color: string) => {
  const colorMap: Record<string, string> = {
    blue: 'bg-blue-100 text-blue-600',
    green: 'bg-green-100 text-green-600',
    red: 'bg-red-100 text-red-600',
    yellow: 'bg-yellow-100 text-yellow-600',
    purple: 'bg-purple-100 text-purple-600',
    pink: 'bg-pink-100 text-pink-600',
    indigo: 'bg-indigo-100 text-indigo-600',
    gray: 'bg-gray-100 text-gray-600',
    orange: 'bg-orange-100 text-orange-600',
    teal: 'bg-teal-100 text-teal-600',
    cyan: 'bg-cyan-100 text-cyan-600',
    lime: 'bg-lime-100 text-lime-600'
  }
  return colorMap[color] || 'bg-gray-100 text-gray-600'
}

const toggleViewMode = () => {
  viewMode.value = viewMode.value === 'grid' ? 'list' : 'grid'
}

const openCreateModal = () => {
  emit('create-category')
}

const editCategory = (category: Category) => {
  emit('edit-category', category)
}

const confirmDeleteCategory = (category: Category) => {
  emit('delete-category', category)
}

const viewCategoryDetails = (category: Category) => {
  emit('view-category', category)
}
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-clamp: 2;
}
</style>