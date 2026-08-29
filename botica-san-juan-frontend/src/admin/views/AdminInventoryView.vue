<template>
  <div class="space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">
          {{ isAlertsRoute ? 'Alertas de Inventario' : isStockRoute ? 'Control de Stock' : 'Gestion de Inventario' }}
        </h1>
        <p class="mt-2 text-gray-600">
          {{ isAlertsRoute
            ? 'Vista enfocada en productos con riesgo de quiebre de stock.'
            : 'Control y seguimiento del stock de productos.' }}
        </p>
      </div>
      <div class="flex flex-col gap-3 sm:flex-row">
        <button
          class="inline-flex items-center justify-center rounded-xl bg-linear-to-r from-blue-600 to-indigo-600 px-4 py-3 text-white shadow-lg shadow-blue-600/20 transition hover:from-blue-700 hover:to-indigo-700"
          @click="refreshInventory"
        >
          <RefreshCw class="mr-2 h-5 w-5" />
          Actualizar
        </button>
        <button
          v-if="!isAlertsRoute"
          class="inline-flex items-center justify-center rounded-xl bg-linear-to-r from-emerald-600 to-green-600 px-4 py-3 text-white shadow-lg shadow-emerald-600/20 transition hover:from-emerald-700 hover:to-green-700"
          @click="openAdjustModal(null)"
        >
          <Plus class="mr-2 h-5 w-5" />
          Ajustar stock
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <div class="rounded-2xl bg-linear-to-r from-red-500 to-red-600 p-6 text-white shadow-xl">
        <p class="text-sm text-red-100">
          Stock critico (pagina)
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ criticalCount }}
        </p>
      </div>
      <div class="rounded-2xl bg-linear-to-r from-amber-500 to-orange-500 p-6 text-white shadow-xl">
        <p class="text-sm text-amber-100">
          Stock bajo (pagina)
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ lowCount }}
        </p>
      </div>
      <div class="rounded-2xl bg-linear-to-r from-emerald-600 to-green-600 p-6 text-white shadow-xl">
        <p class="text-sm text-emerald-100">
          Productos totales
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ visibleProducts.length }}
        </p>
      </div>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-lg ring-1 ring-slate-200/80 sm:p-6">
      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_220px_130px]">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Buscar producto o categoria..."
            class="w-full rounded-xl border border-slate-200 py-3 pl-10 pr-4 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
          />
        </div>
        <select
          v-model="categoryFilter"
          class="rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
        >
          <option value="all">
            Todas las categorias
          </option>
          <option
            v-for="category in categories"
            :key="category.id"
            :value="String(category.id)"
          >
            {{ category.name }}
          </option>
        </select>
        <select
          v-if="!isAlertsRoute"
          v-model="stockFilter"
          class="rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
        >
          <option value="all">
            Todo el stock
          </option>
          <option value="critical">
            Critico
          </option>
          <option value="low">
            Bajo
          </option>
          <option value="normal">
            Normal
          </option>
        </select>
        <select
          v-model.number="perPage"
          class="rounded-xl border border-slate-200 px-4 py-3 text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
        >
          <option :value="10">
            10
          </option>
          <option :value="20">
            20
          </option>
          <option :value="30">
            30
          </option>
        </select>
      </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-slate-200/80">
      <div
        v-if="loading"
        class="flex items-center justify-center py-16 text-slate-600"
      >
        <div class="h-9 w-9 animate-spin rounded-full border-4 border-slate-200 border-t-blue-600" />
        <span class="ml-3">Cargando inventario...</span>
      </div>

      <div
        v-else-if="visibleProducts.length === 0"
        class="px-6 py-16 text-center"
      >
        <Package class="mx-auto h-14 w-14 text-slate-300" />
        <h2 class="mt-4 text-lg font-semibold text-slate-900">
          No hay productos
        </h2>
        <p class="mt-2 text-sm text-slate-500">
          Prueba ajustando los filtros.
        </p>
      </div>

      <div v-else>
        <div class="space-y-3 p-4 md:hidden">
          <article
            v-for="product in visibleProducts"
            :key="product.id"
            class="rounded-2xl border border-slate-200 p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="font-semibold text-slate-900">
                  {{ product.nombre }}
                </p>
                <p class="text-xs text-slate-500">
                  {{ product.codigo }} · {{ product.categoria }}{{ product.subcategoria ? ` / ${product.subcategoria}` : '' }}
                </p>
              </div>
              <span
                :class="getStockStatusClass(product.estado)"
                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
              >
                {{ product.estado }}
              </span>
            </div>

            <div class="mt-3 flex items-center justify-between text-sm">
              <span class="text-slate-500">Stock</span>
              <span class="font-bold text-slate-900">{{ product.stockActual }}</span>
            </div>

            <div class="mt-4 flex justify-end gap-2">
              <button
                v-if="!isAlertsRoute"
                class="rounded-lg p-2 text-blue-600 transition hover:bg-blue-50"
                @click="openAdjustModal(product)"
              >
                <Edit class="h-4 w-4" />
              </button>
              <button
                v-if="!isAlertsRoute"
                class="rounded-lg p-2 text-emerald-600 transition hover:bg-emerald-50"
                @click="changeStock(product, 1)"
              >
                <Plus class="h-4 w-4" />
              </button>
              <button
                v-if="!isAlertsRoute"
                class="rounded-lg p-2 text-rose-600 transition hover:bg-rose-50"
                @click="changeStock(product, -1)"
              >
                <Minus class="h-4 w-4" />
              </button>
            </div>
          </article>
        </div>

        <div class="hidden overflow-x-auto md:block">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Producto
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Codigo / Categoria
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Stock actual
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Estado
                </th>
                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Acciones
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr
                v-for="product in visibleProducts"
                :key="product.id"
                class="transition hover:bg-slate-50"
              >
                <td class="whitespace-nowrap px-6 py-4">
                  <p class="font-semibold text-slate-900">
                    {{ product.nombre }}
                  </p>
                  <p class="text-sm text-slate-500">
                    {{ product.presentacion }}
                  </p>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                  <p class="font-medium text-slate-900">
                    {{ product.codigo }}
                  </p>
                  <p>{{ product.categoria }}{{ product.subcategoria ? ` / ${product.subcategoria}` : '' }}</p>
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <div class="flex items-center gap-3">
                    <span class="text-lg font-bold text-slate-900">{{ product.stockActual }}</span>
                    <span class="text-sm text-slate-500">min. {{ product.stockMinimo }}</span>
                  </div>
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <span
                    :class="getStockStatusClass(product.estado)"
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                  >
                    {{ product.estado }}
                  </span>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right">
                  <div class="inline-flex items-center gap-2">
                    <button
                      v-if="!isAlertsRoute"
                      class="rounded-lg p-2 text-blue-600 transition hover:bg-blue-50"
                      @click="openAdjustModal(product)"
                    >
                      <Edit class="h-4 w-4" />
                    </button>
                    <button
                      v-if="!isAlertsRoute"
                      class="rounded-lg p-2 text-emerald-600 transition hover:bg-emerald-50"
                      @click="changeStock(product, 1)"
                    >
                      <Plus class="h-4 w-4" />
                    </button>
                    <button
                      v-if="!isAlertsRoute"
                      class="rounded-lg p-2 text-rose-600 transition hover:bg-rose-50"
                      @click="changeStock(product, -1)"
                    >
                      <Minus class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div
      v-if="totalPages > 1"
      class="flex flex-col items-center justify-between gap-3 rounded-2xl bg-white px-4 py-3 shadow ring-1 ring-slate-200/80 sm:flex-row"
    >
      <p class="text-sm text-slate-600">
        Mostrando {{ visibleProducts.length }} de {{ totalItems }} productos
      </p>
      <div class="inline-flex items-center gap-2">
        <button
          class="rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="currentPage <= 1"
          @click="changePage(currentPage - 1)"
        >
          Anterior
        </button>
        <span class="text-sm font-semibold text-slate-700">{{ currentPage }} / {{ totalPages }}</span>
        <button
          class="rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="currentPage >= totalPages"
          @click="changePage(currentPage + 1)"
        >
          Siguiente
        </button>
      </div>
    </div>

    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="showModal"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm"
          @click.self="closeModal"
        >
          <div class="w-full max-w-lg rounded-3xl bg-white shadow-2xl ring-1 ring-black/5">
            <div class="border-b border-slate-200 px-6 py-5">
              <h2 class="text-2xl font-bold text-slate-900">
                Ajustar stock
              </h2>
              <p class="mt-1 text-sm text-slate-500">
                Modifica el stock de un producto de manera segura.
              </p>
            </div>

            <form
              class="space-y-5 px-6 py-6"
              @submit.prevent="saveStockAdjustment"
            >
              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Producto</label>
                <select
                  v-model="selectedProductId"
                  class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                >
                  <option
                    v-for="product in visibleProducts"
                    :key="product.id"
                    :value="product.id"
                  >
                    {{ product.nombre }}
                  </option>
                </select>
              </div>
              <div class="grid gap-4 sm:grid-cols-2">
                <div>
                  <label class="mb-2 block text-sm font-medium text-slate-700">Movimiento</label>
                  <select
                    v-model="stockDelta"
                    class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                  >
                    <option :value="1">
                      Entrar +1
                    </option>
                    <option :value="5">
                      Entrar +5
                    </option>
                    <option :value="-1">
                      Salir -1
                    </option>
                    <option :value="-5">
                      Salir -5
                    </option>
                  </select>
                </div>
                <div>
                  <label class="mb-2 block text-sm font-medium text-slate-700">Nuevo stock</label>
                  <input
                    :value="selectedProduct ? Math.max(0, selectedProduct.stockActual + stockDelta) : 0"
                    type="number"
                    disabled
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700"
                  />
                </div>
              </div>

              <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                  type="button"
                  class="rounded-xl border border-slate-200 px-5 py-3 font-semibold text-slate-700 transition hover:bg-slate-50"
                  @click="closeModal"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  class="inline-flex items-center justify-center rounded-xl bg-linear-to-r from-blue-600 to-indigo-600 px-5 py-3 font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:from-blue-700 hover:to-indigo-700"
                >
                  <Save class="mr-2 h-4 w-4" />
                  Guardar
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useAdminToast } from '../composables/useAdminToast'
import { Edit, Minus, Package, Plus, RefreshCw, Save, Search } from 'lucide-vue-next'

interface InventoryProduct {
  id: number
  nombre: string
  presentacion: string
  codigo: string
  categoryId: number | null
  categoria: string
  subcategoria: string
  stockActual: number
  stockMinimo: number
  stockReposicion: number
  estado: string
  ultimaActualizacion: string
}

interface CategoryOption {
  id: number
  name: string
}

interface BackendProduct {
  id: number
  nombre: string
  concentracion: string
  adicional?: string | null
  laboratorio: string
  presentacion: string
  tipo: string
  categoria_id?: number | null
  subcategoria_id?: number | null
  categoria?: {
    id: number
    nombre: string
  } | null
  subcategoria?: {
    id: number
    nombre: string
  } | null
  stock_minimo?: number | null
  stock_reposicion?: number | null
  codigo_barras?: string | null
  stock: number
  precio: string
  imagen?: string | null
  created_at?: string | null
  updated_at?: string | null
}

interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const { notifyError, notifySuccess } = useAdminToast()
const route = useRoute()

const inventoryProducts = ref<InventoryProduct[]>([])
const loading = ref(false)
const searchQuery = ref('')
const categoryFilter = ref<'all' | string>('all')
const stockFilter = ref<'all' | 'critical' | 'low' | 'normal'>('all')
const showModal = ref(false)
const selectedProductId = ref<number | null>(null)
const stockDelta = ref(1)

const currentPage = ref(1)
const perPage = ref(10)
const totalPages = ref(1)
const totalItems = ref(0)

let searchTimeout: number | undefined

const isStockRoute = computed(() => route.path.endsWith('/inventory/stock'))
const isAlertsRoute = computed(() => route.path.endsWith('/inventory/alerts'))

const visibleProducts = computed(() => {
  if (!isAlertsRoute.value) return inventoryProducts.value
  return inventoryProducts.value.filter(product => product.estado === 'Bajo' || product.estado === 'Critico')
})

const categories = computed<CategoryOption[]>(() => {
  const map = new Map<number, string>()
  inventoryProducts.value.forEach(product => {
    if (product.categoryId) {
      map.set(product.categoryId, product.categoria)
    }
  })
  return Array.from(map.entries())
    .map(([id, name]) => ({ id, name }))
    .sort((a, b) => a.name.localeCompare(b.name))
})
const criticalCount = computed(() => inventoryProducts.value.filter(product => product.stockActual <= product.stockMinimo).length)
const lowCount = computed(() => inventoryProducts.value.filter(product => product.stockActual > product.stockMinimo && product.stockActual <= product.stockReposicion).length)
const selectedProduct = computed(() => visibleProducts.value.find(product => product.id === selectedProductId.value) || null)

const getStockStatusClass = (estado: string) => {
  switch (estado) {
    case 'Critico':
      return 'bg-red-100 text-red-800'
    case 'Bajo':
      return 'bg-orange-100 text-orange-800'
    case 'Normal':
      return 'bg-green-100 text-green-800'
    default:
      return 'bg-slate-100 text-slate-800'
  }
}

const mapProducts = (products: BackendProduct[]) => {
  inventoryProducts.value = products.map(product => ({
    id: product.id,
    nombre: product.nombre,
    presentacion: product.presentacion,
    codigo: product.codigo_barras || `${product.tipo.slice(0, 3).toUpperCase()}-${String(product.id).padStart(3, '0')}`,
    categoryId: product.categoria?.id || product.categoria_id || null,
    categoria: product.categoria?.nombre || product.tipo,
    subcategoria: product.subcategoria?.nombre || '',
    stockActual: product.stock,
    stockMinimo: Number(product.stock_minimo ?? 5),
    stockReposicion: Number(product.stock_reposicion ?? 10),
    estado: product.stock <= Number(product.stock_minimo ?? 5)
      ? 'Critico'
      : product.stock <= Number(product.stock_reposicion ?? 10)
        ? 'Bajo'
        : 'Normal',
    ultimaActualizacion: product.updated_at || product.created_at || new Date().toISOString()
  }))
}

const fetchInventory = async (page = currentPage.value) => {
  loading.value = true
  try {
    const effectiveStockFilter = isAlertsRoute.value ? 'low' : stockFilter.value
    const response = await api.get<PaginatedResponse<BackendProduct>>('/productos', {
      params: {
        page,
        per_page: perPage.value,
        q: searchQuery.value.trim() || undefined,
        categoria_id: categoryFilter.value === 'all' ? undefined : categoryFilter.value,
        stock_status: effectiveStockFilter
      }
    })
    mapProducts(response.data.data)
    currentPage.value = response.data.current_page
    totalPages.value = response.data.last_page
    totalItems.value = response.data.total
  } catch (error) {
    console.error('Error loading inventory:', error)
    notifyError('Error de carga', 'No se pudo cargar el inventario.')
  } finally {
    loading.value = false
  }
}

const changePage = async (page: number) => {
  if (page < 1 || page > totalPages.value || page === currentPage.value) return
  await fetchInventory(page)
}

const refreshInventory = async () => {
  await fetchInventory(currentPage.value)
}

const openAdjustModal = (product: InventoryProduct | null) => {
  selectedProductId.value = product?.id ?? visibleProducts.value[0]?.id ?? null
  stockDelta.value = 1
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveStockAdjustment = async () => {
  if (!selectedProduct.value) return
  await changeStock(selectedProduct.value, stockDelta.value)
  showModal.value = false
}

const changeStock = async (product: InventoryProduct, delta: number) => {
  try {
    const backendProduct = await api.get<BackendProduct>(`/productos/${product.id}`)
    const nextStock = Math.max(0, (backendProduct.data.stock ?? product.stockActual) + delta)
    await api.put(`/productos/${product.id}`, {
      nombre: backendProduct.data.nombre,
      concentracion: backendProduct.data.concentracion,
      adicional: backendProduct.data.adicional || '',
      laboratorio: backendProduct.data.laboratorio,
      presentacion: backendProduct.data.presentacion,
      tipo: backendProduct.data.tipo,
      categoria_id: backendProduct.data.categoria_id,
      subcategoria_id: backendProduct.data.subcategoria_id,
      stock_minimo: backendProduct.data.stock_minimo,
      stock_reposicion: backendProduct.data.stock_reposicion,
      codigo_barras: backendProduct.data.codigo_barras,
      stock: nextStock,
      precio: backendProduct.data.precio
    })
    notifySuccess('Stock actualizado', `Nuevo stock para ${product.nombre}: ${nextStock}.`)
    await fetchInventory(currentPage.value)
  } catch (error) {
    console.error('Error updating stock:', error)
    notifyError('No se pudo actualizar', 'Error al guardar el stock del producto.')
  }
}

watch([categoryFilter, stockFilter, perPage], async () => {
  currentPage.value = 1
  await fetchInventory(1)
})

watch(searchQuery, () => {
  if (searchTimeout) {
    window.clearTimeout(searchTimeout)
  }
  searchTimeout = window.setTimeout(async () => {
    currentPage.value = 1
    await fetchInventory(1)
  }, 300)
})

watch(isAlertsRoute, async (isAlerts) => {
  if (isAlerts) {
    stockFilter.value = 'low'
  }
  currentPage.value = 1
  await fetchInventory(1)
})

onMounted(fetchInventory)
</script>
