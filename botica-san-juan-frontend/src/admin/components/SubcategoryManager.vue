<template>
  <div class="bg-white rounded-xl shadow-lg p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-800 mb-1">
          Subcategorias de Productos
        </h2>
        <p class="text-gray-600">
          Organiza el catalogo por niveles para mejorar filtros y reportes.
        </p>
      </div>
      <button
        class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 bg-linear-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white rounded-lg font-medium transition-all"
        @click="emit('create-subcategory')"
      >
        <Plus class="w-4 h-4 mr-2" />
        Nueva Subcategoria
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-indigo-600 rounded-lg p-4 text-white">
        <p class="text-indigo-100 text-sm">
          Total
        </p>
        <p class="text-2xl font-bold">
          {{ filteredSubcategories.length }}
        </p>
      </div>
      <div class="bg-green-600 rounded-lg p-4 text-white">
        <p class="text-green-100 text-sm">
          Activas
        </p>
        <p class="text-2xl font-bold">
          {{ activeCount }}
        </p>
      </div>
      <div class="bg-amber-500 rounded-lg p-4 text-white">
        <p class="text-amber-100 text-sm">
          Inactivas
        </p>
        <p class="text-2xl font-bold">
          {{ inactiveCount }}
        </p>
      </div>
      <div class="bg-cyan-600 rounded-lg p-4 text-white">
        <p class="text-cyan-100 text-sm">
          Con productos
        </p>
        <p class="text-2xl font-bold">
          {{ withProductsCount }}
        </p>
      </div>
    </div>

    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <input
        v-model="search"
        type="text"
        placeholder="Buscar subcategoria..."
        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
      />
      <select
        v-model="categoryFilter"
        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
      >
        <option :value="0">
          Todas las categorias
        </option>
        <option
          v-for="category in categories"
          :key="category.id"
          :value="category.id"
        >
          {{ category.name }}
        </option>
      </select>
    </div>

    <div
      v-if="loading"
      class="text-center py-10 text-gray-600"
    >
      Cargando subcategorias...
    </div>

    <div
      v-else-if="filteredSubcategories.length === 0"
      class="text-center py-10 text-gray-500"
    >
      No hay subcategorias para los filtros actuales.
    </div>

    <div
      v-else
      class="overflow-x-auto"
    >
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
              Subcategoria
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
              Categoria
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
              Estado
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
              Productos
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
              Acciones
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr
            v-for="subcategory in filteredSubcategories"
            :key="subcategory.id"
            class="hover:bg-gray-50"
          >
            <td class="px-4 py-3">
              <p class="font-semibold text-gray-900">
                {{ subcategory.name }}
              </p>
              <p class="text-sm text-gray-500">
                {{ subcategory.description || 'Sin descripcion' }}
              </p>
            </td>
            <td class="px-4 py-3 text-sm text-gray-700">
              {{ subcategory.categoryName }}
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                :class="subcategory.isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
              >
                {{ subcategory.isActive ? 'Activa' : 'Inactiva' }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm font-medium text-gray-900">
              {{ subcategory.productsCount }}
            </td>
            <td class="px-4 py-3 text-right">
              <div class="inline-flex items-center gap-2">
                <button
                  class="text-blue-600 hover:text-blue-900"
                  @click="emit('edit-subcategory', subcategory)"
                >
                  <Edit class="w-4 h-4" />
                </button>
                <button
                  class="text-purple-600 hover:text-purple-900"
                  @click="emit('view-subcategory', subcategory)"
                >
                  <Eye class="w-4 h-4" />
                </button>
                <button
                  class="text-red-600 hover:text-red-900"
                  @click="emit('delete-subcategory', subcategory)"
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
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Plus, Edit, Eye, Trash2 } from 'lucide-vue-next'

interface Category {
  id: number
  name: string
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

interface Props {
  subcategories: SubCategory[]
  categories: Category[]
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false
})

const emit = defineEmits<{
  'create-subcategory': []
  'edit-subcategory': [subcategory: SubCategory]
  'delete-subcategory': [subcategory: SubCategory]
  'view-subcategory': [subcategory: SubCategory]
}>()

const search = ref('')
const categoryFilter = ref(0)

const filteredSubcategories = computed(() => {
  return props.subcategories.filter(subcategory => {
    const searchMatch = !search.value ||
      subcategory.name.toLowerCase().includes(search.value.toLowerCase()) ||
      subcategory.description.toLowerCase().includes(search.value.toLowerCase())

    const categoryMatch = categoryFilter.value === 0 || subcategory.categoria_id === categoryFilter.value

    return searchMatch && categoryMatch
  })
})

const activeCount = computed(() => filteredSubcategories.value.filter(item => item.isActive).length)
const inactiveCount = computed(() => filteredSubcategories.value.filter(item => !item.isActive).length)
const withProductsCount = computed(() => filteredSubcategories.value.filter(item => item.productsCount > 0).length)
</script>
