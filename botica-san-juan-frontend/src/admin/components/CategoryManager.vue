<template>
  <div class="bg-superficie-elevada rounded-xl shadow-lg p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
      <div>
        <h2
          class="text-2xl font-bold text-texto-primario mb-1"
        >
          Categorías de Productos
        </h2>
        <p
          class="text-texto-secundario"
        >
          Gestiona las categorías para organizar tus productos
        </p>
      </div>
      <div class="mt-4 sm:mt-0 flex flex-col sm:flex-row gap-3">
        <button
          class="inline-flex items-center px-4 py-2 border border-borde-base rounded-lg text-texto-secundario bg-superficie-elevada hover:bg-superficie-hundida transition-colors"
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
          class="inline-flex items-center px-4 py-2 bg-botica-700 hover:bg-botica-800 text-white rounded-lg font-medium transition-all"
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
            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-texto-terciario w-4 h-4" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar categorías..."
              class="w-full pl-10 pr-4 py-2 border border-borde-base rounded-lg focus:ring-2 focus:ring-botica-500/20 focus:border-transparent"
            />
          </div>
        </div>
        <div class="flex gap-2">
          <select
            v-model="statusFilter"
            class="px-3 py-2 border border-borde-base rounded-lg focus:ring-2 focus:ring-botica-500/20 focus:border-transparent"
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
      <div class="bg-botica-700 rounded-lg p-4 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p
              class="text-botica-100 text-sm"
            >
              Total Categorías
            </p>
            <p
              class="text-2xl font-bold"
            >
              {{ filteredCategories.length }}
            </p>
          </div>
          <Tag class="w-8 h-8 text-botica-200" />
        </div>
      </div>
      <div class="bg-botica-700 rounded-lg p-4 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p
              class="text-exito-50 text-sm"
            >
              Activas
            </p>
            <p
              class="text-2xl font-bold"
            >
              {{ activeCategoriesCount }}
            </p>
          </div>
          <CheckCircle class="w-8 h-8 text-exito-50" />
        </div>
      </div>
      <div class="bg-botica-700 rounded-lg p-4 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p
              class="text-alerta-50 text-sm"
            >
              Inactivas
            </p>
            <p
              class="text-2xl font-bold"
            >
              {{ inactiveCategoriesCount }}
            </p>
          </div>
          <XCircle class="w-8 h-8 text-alerta-50" />
        </div>
      </div>
      <div class="bg-botica-700 rounded-lg p-4 text-white">
        <div class="flex items-center justify-between">
          <div>
            <p
              class="text-botica-100 text-sm"
            >
              Con Productos
            </p>
            <p
              class="text-2xl font-bold"
            >
              {{ categoriesWithProductsCount }}
            </p>
          </div>
          <Package class="w-8 h-8 text-botica-200" />
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
        class="group bg-superficie-elevada border border-borde-sutil rounded-xl p-6 hover:shadow-lg hover:border-botica-300 transition-all duration-200 cursor-pointer"
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
              class="p-1 text-texto-terciario hover:text-texto-marca transition-colors"
              @click.stop="editCategory(category)"
            >
              <Edit class="w-4 h-4" />
            </button>
            <button
              class="p-1 text-texto-terciario hover:text-peligro-600 transition-colors"
              @click.stop="confirmDeleteCategory(category)"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>

        <div class="mb-3">
          <h3
            class="font-semibold text-texto-primario mb-1"
          >
            {{ category.name }}
          </h3>
          <p
            v-if="category.description"
            class="text-sm text-texto-secundario line-clamp-2"
          >
            {{ category.description }}
          </p>
        </div>

        <div class="flex items-center justify-between">
          <span
            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
            :class="category.isActive ? 'bg-exito-50 text-exito-700' : 'bg-superficie-interactiva text-texto-primario'"
          >
            {{ category.isActive ? 'Activa' : 'Inactiva' }}
          </span>
          <span class="text-sm text-texto-terciario">
            {{ category.productsCount }} productos
          </span>
        </div>
      </div>
    </div>

    <!-- Categories List View -->
    <div
      v-else
      class="bg-superficie-elevada border border-borde-sutil rounded-xl overflow-hidden"
    >
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-superficie-hundida">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-texto-terciario uppercase tracking-wider">
                Categoría
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-texto-terciario uppercase tracking-wider">
                Descripción
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-texto-terciario uppercase tracking-wider">
                Estado
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-texto-terciario uppercase tracking-wider">
                Productos
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-texto-terciario uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-superficie-elevada divide-y divide-borde-sutil">
            <tr
              v-for="category in filteredCategories"
              :key="category.id"
              class="hover:bg-superficie-hundida cursor-pointer"
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
                      class="text-sm font-medium text-texto-primario"
                    >
                      {{ category.name }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="text-sm text-texto-primario max-w-xs truncate">
                  {{ category.description || 'Sin descripción' }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="category.isActive ? 'bg-exito-50 text-exito-700' : 'bg-superficie-interactiva text-texto-primario'"
                >
                  {{ category.isActive ? 'Activa' : 'Inactiva' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-texto-primario">
                {{ category.productsCount }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex items-center space-x-2">
                  <button
                    class="text-texto-marca hover:text-botica-900 transition-colors"
                    @click.stop="editCategory(category)"
                  >
                    <Edit class="w-4 h-4" />
                  </button>
                  <button
                    class="text-peligro-600 hover:text-peligro-700 transition-colors"
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
      <div class="mx-auto w-24 h-24 bg-superficie-interactiva rounded-full flex items-center justify-center mb-4">
        <Tag class="w-12 h-12 text-texto-terciario" />
      </div>
      <h3
        class="text-lg font-medium text-texto-primario mb-2"
      >
        No hay categorías
      </h3>
      <p class="text-texto-terciario mb-6">
        {{ searchQuery || statusFilter !== 'all' ? 'No se encontraron categorías con los filtros aplicados.' : 'Comienza creando tu primera categoría.' }}
      </p>
      <button
        class="inline-flex items-center px-4 py-2 bg-botica-700 hover:bg-botica-800 text-white rounded-lg font-medium transition-all"
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
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-botica-600 mr-3"></div>
        <span class="text-texto-secundario">Cargando categorías...</span>
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
    blue: 'bg-botica-50 text-texto-marca',
    green: 'bg-exito-50 text-exito-600',
    red: 'bg-peligro-50 text-peligro-600',
    yellow: 'bg-alerta-50 text-alerta-600',
    purple: 'bg-botica-100 text-botica-600',
    pink: 'bg-peligro-50 text-peligro-600',
    indigo: 'bg-botica-50 text-texto-marca',
    gray: 'bg-superficie-interactiva text-texto-secundario',
    orange: 'bg-alerta-50 text-alerta-600',
    teal: 'bg-botica-100 text-botica-600',
    cyan: 'bg-botica-100 text-botica-600',
    lime: 'bg-exito-50 text-exito-600'
  }
  return colorMap[color] || 'bg-superficie-interactiva text-texto-secundario'
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