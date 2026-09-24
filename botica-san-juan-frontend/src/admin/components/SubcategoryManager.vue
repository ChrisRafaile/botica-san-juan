<template>
  <div class="bg-superficie-elevada rounded-xl shadow-lg p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
      <div>
        <h2 class="text-2xl font-bold text-texto-primario mb-1">
          Subcategorias de Productos
        </h2>
        <p class="text-texto-secundario">
          Organiza el catalogo por niveles para mejorar filtros y reportes.
        </p>
      </div>
      <button
        class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 bg-botica-700 hover:bg-botica-800 text-white rounded-lg font-medium transition-all"
        @click="emit('create-subcategory')"
      >
        <Plus class="w-4 h-4 mr-2" />
        Nueva Subcategoria
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-botica-600 rounded-lg p-4 text-white">
        <p class="text-botica-100 text-sm">
          Total
        </p>
        <p class="text-2xl font-bold">
          {{ filteredSubcategories.length }}
        </p>
      </div>
      <div class="bg-exito-700 rounded-lg p-4 text-white">
        <p class="text-exito-50 text-sm">
          Activas
        </p>
        <p class="text-2xl font-bold">
          {{ activeCount }}
        </p>
      </div>
      <div class="bg-alerta-700 rounded-lg p-4 text-white">
        <p class="text-alerta-50 text-sm">
          Inactivas
        </p>
        <p class="text-2xl font-bold">
          {{ inactiveCount }}
        </p>
      </div>
      <div class="bg-botica-600 rounded-lg p-4 text-white">
        <p class="text-botica-100 text-sm">
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
        class="w-full rounded-lg border border-borde-base px-4 py-2 focus:ring-2 focus:ring-botica-500/20 focus:border-transparent"
      />
      <select
        v-model="categoryFilter"
        class="w-full rounded-lg border border-borde-base px-4 py-2 focus:ring-2 focus:ring-botica-500/20 focus:border-transparent"
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
      class="text-center py-10 text-texto-secundario"
    >
      Cargando subcategorias...
    </div>

    <div
      v-else-if="filteredSubcategories.length === 0"
      class="text-center py-10 text-texto-terciario"
    >
      No hay subcategorias para los filtros actuales.
    </div>

    <div
      v-else
      class="overflow-x-auto"
    >
      <table class="w-full">
        <thead class="bg-superficie-hundida">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-texto-terciario uppercase">
              Subcategoria
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-texto-terciario uppercase">
              Categoria
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-texto-terciario uppercase">
              Estado
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-texto-terciario uppercase">
              Productos
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium text-texto-terciario uppercase">
              Acciones
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-borde-sutil">
          <tr
            v-for="subcategory in filteredSubcategories"
            :key="subcategory.id"
            class="hover:bg-superficie-hundida"
          >
            <td class="px-4 py-3">
              <p class="font-semibold text-texto-primario">
                {{ subcategory.name }}
              </p>
              <p class="text-sm text-texto-terciario">
                {{ subcategory.description || 'Sin descripcion' }}
              </p>
            </td>
            <td class="px-4 py-3 text-sm text-texto-secundario">
              {{ subcategory.categoryName }}
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                :class="subcategory.isActive ? 'bg-exito-50 text-exito-700' : 'bg-superficie-interactiva text-texto-secundario'"
              >
                {{ subcategory.isActive ? 'Activa' : 'Inactiva' }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm font-medium text-texto-primario">
              {{ subcategory.productsCount }}
            </td>
            <td class="px-4 py-3 text-right">
              <div class="inline-flex items-center gap-2">
                <button
                  class="text-texto-marca hover:text-botica-900"
                  @click="emit('edit-subcategory', subcategory)"
                >
                  <Edit class="w-4 h-4" />
                </button>
                <button
                  class="text-botica-600 hover:text-botica-900"
                  @click="emit('view-subcategory', subcategory)"
                >
                  <Eye class="w-4 h-4" />
                </button>
                <button
                  class="text-peligro-600 hover:text-peligro-700"
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
