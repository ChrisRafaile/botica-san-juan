<template>
  <div class="space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">
          Gestion de Pedidos
        </h1>
        <p class="mt-2 text-gray-600">
          Supervisa el estado de cada orden y su detalle.
        </p>
      </div>
      <button
        class="inline-flex items-center justify-center rounded-xl bg-linear-to-r from-violet-600 to-fuchsia-600 px-4 py-3 text-white shadow-lg shadow-violet-600/20 transition hover:from-violet-700 hover:to-fuchsia-700"
        @click="refreshOrders"
      >
        <RefreshCw class="mr-2 h-5 w-5" />
        Actualizar
      </button>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl bg-linear-to-r from-slate-900 to-slate-700 p-6 text-white shadow-xl">
        <p class="text-sm text-slate-200">
          Total pedidos
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ totalItems }}
        </p>
      </div>
      <div class="rounded-2xl bg-linear-to-r from-amber-500 to-orange-500 p-6 text-white shadow-xl">
        <p class="text-sm text-amber-100">
          Pendientes (pagina)
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ pendingCount }}
        </p>
      </div>
      <div class="rounded-2xl bg-linear-to-r from-blue-600 to-cyan-600 p-6 text-white shadow-xl">
        <p class="text-sm text-blue-100">
          En proceso (pagina)
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ processingCount }}
        </p>
      </div>
      <div class="rounded-2xl bg-linear-to-r from-emerald-600 to-green-600 p-6 text-white shadow-xl">
        <p class="text-sm text-emerald-100">
          Entregados (pagina)
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ deliveredCount }}
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
            placeholder="Buscar por cliente, pedido o producto..."
            class="w-full rounded-xl border border-slate-200 py-3 pl-10 pr-4 outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100"
          />
        </div>
        <select
          v-model="statusFilter"
          class="rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100"
        >
          <option value="all">
            Todos los estados
          </option>
          <option value="pendiente">
            Pendiente
          </option>
          <option value="procesando">
            Procesando
          </option>
          <option value="enviado">
            Enviado
          </option>
          <option value="entregado">
            Entregado
          </option>
          <option value="cancelado">
            Cancelado
          </option>
        </select>
        <input
          v-model="dateFilter"
          type="date"
          class="rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100"
        />
        <select
          v-model.number="perPage"
          class="rounded-xl border border-slate-200 px-4 py-3 text-slate-900 outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100"
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
        <div class="h-9 w-9 animate-spin rounded-full border-4 border-slate-200 border-t-violet-600" />
        <span class="ml-3">Cargando pedidos...</span>
      </div>

      <div
        v-else-if="orders.length === 0"
        class="px-6 py-16 text-center"
      >
        <ShoppingCart class="mx-auto h-14 w-14 text-slate-300" />
        <h2 class="mt-4 text-lg font-semibold text-slate-900">
          No hay pedidos
        </h2>
        <p class="mt-2 text-sm text-slate-500">
          Prueba cambiando los filtros.
        </p>
      </div>

      <div v-else>
        <div class="space-y-3 p-4 md:hidden">
          <article
            v-for="order in orders"
            :key="order.id"
            class="rounded-2xl border border-slate-200 p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="font-semibold text-slate-900">
                  #{{ String(order.id).padStart(4, '0') }}
                </p>
                <p class="text-xs text-slate-500">
                  {{ formatDate(order.fecha_pedido) }}
                </p>
              </div>
              <select
                v-model="order.estado"
                class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide outline-none transition focus:ring-4"
                :class="statusSelectClass(order.estado)"
                @change="updateStatus(order)"
              >
                <option value="pendiente">
                  Pendiente
                </option>
                <option value="procesando">
                  Procesando
                </option>
                <option value="enviado">
                  Enviado
                </option>
                <option value="entregado">
                  Entregado
                </option>
                <option value="cancelado">
                  Cancelado
                </option>
              </select>
            </div>

            <div class="mt-3 space-y-1 text-sm text-slate-700">
              <p>{{ order.usuario?.nombre || 'Sin cliente' }}</p>
              <p class="text-slate-500">
                {{ order.usuario?.email || 'Sin email' }}
              </p>
              <p>{{ order.pedidoDetalles?.length || 0 }} producto(s)</p>
              <p class="font-semibold text-slate-900">
                S/ {{ formatMoney(order.total) }}
              </p>
            </div>

            <div class="mt-4 flex justify-end gap-2">
              <button
                class="rounded-lg p-2 text-blue-600 transition hover:bg-blue-50"
                @click="openDetail(order)"
              >
                <Eye class="h-4 w-4" />
              </button>
              <button
                class="rounded-lg p-2 text-rose-600 transition hover:bg-rose-50"
                @click="askDelete(order)"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </article>
        </div>

        <div class="hidden overflow-x-auto md:block">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Pedido
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Cliente
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Productos
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Total
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
                v-for="order in orders"
                :key="order.id"
                class="transition hover:bg-slate-50"
              >
                <td class="whitespace-nowrap px-6 py-4">
                  <p class="font-semibold text-slate-900">
                    #{{ String(order.id).padStart(4, '0') }}
                  </p>
                  <p class="text-sm text-slate-500">
                    {{ formatDate(order.fecha_pedido) }}
                  </p>
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <p class="font-medium text-slate-900">
                    {{ order.usuario?.nombre || 'Sin cliente' }}
                  </p>
                  <p class="text-sm text-slate-500">
                    {{ order.usuario?.email || 'Sin email' }}
                  </p>
                </td>
                <td class="px-6 py-4">
                  <p class="text-sm font-medium text-slate-900">
                    {{ order.pedidoDetalles?.length || 0 }} producto(s)
                  </p>
                  <p class="max-w-md truncate text-sm text-slate-500">
                    {{ productsSummary(order) }}
                  </p>
                </td>
                <td class="whitespace-nowrap px-6 py-4 font-semibold text-slate-900">
                  S/ {{ formatMoney(order.total) }}
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <select
                    v-model="order.estado"
                    class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide outline-none transition focus:ring-4"
                    :class="statusSelectClass(order.estado)"
                    @change="updateStatus(order)"
                  >
                    <option value="pendiente">
                      Pendiente
                    </option>
                    <option value="procesando">
                      Procesando
                    </option>
                    <option value="enviado">
                      Enviado
                    </option>
                    <option value="entregado">
                      Entregado
                    </option>
                    <option value="cancelado">
                      Cancelado
                    </option>
                  </select>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right">
                  <div class="inline-flex items-center gap-2">
                    <button
                      class="rounded-lg p-2 text-blue-600 transition hover:bg-blue-50"
                      @click="openDetail(order)"
                    >
                      <Eye class="h-4 w-4" />
                    </button>
                    <button
                      class="rounded-lg p-2 text-rose-600 transition hover:bg-rose-50"
                      @click="askDelete(order)"
                    >
                      <Trash2 class="h-4 w-4" />
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
        Mostrando {{ orders.length }} de {{ totalItems }} pedidos
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
          v-if="detailOrder"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm"
          @click.self="detailOrder = null"
        >
          <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white shadow-2xl ring-1 ring-black/5">
            <div class="border-b border-slate-200 px-6 py-5">
              <h2 class="text-2xl font-bold text-slate-900">
                Detalle del pedido #{{ String(detailOrder.id).padStart(4, '0') }}
              </h2>
              <p class="mt-1 text-sm text-slate-500">
                {{ detailOrder.usuario?.nombre }} · {{ formatDate(detailOrder.fecha_pedido) }}
              </p>
            </div>

            <div class="space-y-6 px-6 py-6">
              <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl bg-slate-50 p-4">
                  <p class="text-xs uppercase tracking-wider text-slate-500">
                    Total
                  </p>
                  <p class="mt-2 text-xl font-bold text-slate-900">
                    S/ {{ formatMoney(detailOrder.total) }}
                  </p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                  <p class="text-xs uppercase tracking-wider text-slate-500">
                    Estado
                  </p>
                  <p class="mt-2 text-xl font-bold text-slate-900">
                    {{ detailOrder.estado }}
                  </p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                  <p class="text-xs uppercase tracking-wider text-slate-500">
                    Items
                  </p>
                  <p class="mt-2 text-xl font-bold text-slate-900">
                    {{ detailOrder.pedidoDetalles?.length || 0 }}
                  </p>
                </div>
              </div>

              <div>
                <h3 class="mb-3 text-lg font-semibold text-slate-900">
                  Productos
                </h3>
                <div class="space-y-3">
                  <div
                    v-for="item in detailOrder.pedidoDetalles || []"
                    :key="item.id"
                    class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3"
                  >
                    <div>
                      <p class="font-medium text-slate-900">
                        {{ item.producto?.nombre || 'Producto' }}
                      </p>
                      <p class="text-sm text-slate-500">
                        Cantidad: {{ item.cantidad }}
                      </p>
                    </div>
                    <p class="font-semibold text-slate-900">
                      S/ {{ formatMoney(item.subtotal) }}
                    </p>
                  </div>
                </div>
              </div>

              <div class="flex justify-end">
                <button
                  class="rounded-xl border border-slate-200 px-5 py-3 font-semibold text-slate-700 transition hover:bg-slate-50"
                  @click="detailOrder = null"
                >
                  Cerrar
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import { useAdminToast } from '../composables/useAdminToast'
import { Eye, RefreshCw, Search, ShoppingCart, Trash2 } from 'lucide-vue-next'

interface OrderItem {
  id: number
  cantidad: number
  subtotal: string | number
  producto?: {
    id: number
    nombre: string
  } | null
}

interface OrderUser {
  id: number
  nombre: string
  email: string
}

interface OrderRecord {
  id: number
  usuario_id: number
  fecha_pedido: string
  total: string | number
  estado: string
  usuario?: OrderUser | null
  pedidoDetalles?: OrderItem[]
}

interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const { notifyError, notifySuccess } = useAdminToast()

const orders = ref<OrderRecord[]>([])
const loading = ref(false)
const searchQuery = ref('')
const statusFilter = ref<'all' | 'pendiente' | 'procesando' | 'enviado' | 'entregado' | 'cancelado'>('all')
const dateFilter = ref('')
const detailOrder = ref<OrderRecord | null>(null)

const currentPage = ref(1)
const perPage = ref(10)
const totalPages = ref(1)
const totalItems = ref(0)

let searchTimeout: number | undefined

const pendingCount = computed(() => orders.value.filter(order => order.estado === 'pendiente').length)
const processingCount = computed(() => orders.value.filter(order => order.estado === 'procesando').length)
const deliveredCount = computed(() => orders.value.filter(order => order.estado === 'entregado').length)

const formatMoney = (value: string | number | undefined) => Number(value ?? 0).toFixed(2)
const formatDate = (dateValue: string) => new Date(dateValue).toLocaleDateString('es-PE', {
  year: 'numeric',
  month: 'short',
  day: 'numeric'
})
const productsSummary = (order: OrderRecord) => (order.pedidoDetalles || []).map(item => item.producto?.nombre || 'Producto').join(', ') || 'Sin productos'

const statusSelectClass = (status: string) => {
  switch (status) {
    case 'pendiente':
      return 'bg-amber-100 text-amber-800 border-amber-200'
    case 'procesando':
      return 'bg-blue-100 text-blue-800 border-blue-200'
    case 'enviado':
      return 'bg-orange-100 text-orange-800 border-orange-200'
    case 'entregado':
      return 'bg-emerald-100 text-emerald-800 border-emerald-200'
    case 'cancelado':
      return 'bg-rose-100 text-rose-800 border-rose-200'
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200'
  }
}

const fetchOrders = async (page = currentPage.value) => {
  loading.value = true
  try {
    const response = await api.get<PaginatedResponse<OrderRecord>>('/pedidos', {
      params: {
        paginate: 1,
        page,
        per_page: perPage.value,
        q: searchQuery.value.trim() || undefined,
        status: statusFilter.value,
        date: dateFilter.value || undefined
      }
    })
    orders.value = response.data.data
    currentPage.value = response.data.current_page
    totalPages.value = response.data.last_page
    totalItems.value = response.data.total
  } catch (error) {
    console.error('Error loading orders:', error)
    notifyError('Error de carga', 'No se pudieron cargar los pedidos.')
  } finally {
    loading.value = false
  }
}

const changePage = async (page: number) => {
  if (page < 1 || page > totalPages.value || page === currentPage.value) return
  await fetchOrders(page)
}

const refreshOrders = async () => {
  await fetchOrders(currentPage.value)
}

const updateStatus = async (order: OrderRecord) => {
  try {
    await api.put(`/pedidos/${order.id}`, {
      estado: order.estado,
      usuario_id: order.usuario_id,
      fecha_pedido: order.fecha_pedido,
      total: Number(order.total)
    })
    notifySuccess('Estado actualizado', `Pedido #${String(order.id).padStart(4, '0')} actualizado.`)
  } catch (error) {
    console.error('Error updating order status:', error)
    notifyError('No se pudo actualizar', 'Se recargara la pagina de pedidos.')
    await fetchOrders()
  }
}

const openDetail = (order: OrderRecord) => {
  detailOrder.value = order
}

const askDelete = async (order: OrderRecord) => {
  if (!window.confirm(`Eliminar el pedido #${String(order.id).padStart(4, '0')}?`)) return
  try {
    await api.delete(`/pedidos/${order.id}`)
    notifySuccess('Pedido eliminado', `Pedido #${String(order.id).padStart(4, '0')} eliminado.`)
    await fetchOrders(currentPage.value)
  } catch (error) {
    console.error('Error deleting order:', error)
    notifyError('No se pudo eliminar', 'El pedido no pudo ser eliminado.')
  }
}

watch([statusFilter, dateFilter, perPage], async () => {
  currentPage.value = 1
  await fetchOrders(1)
})

watch(searchQuery, () => {
  if (searchTimeout) {
    window.clearTimeout(searchTimeout)
  }
  searchTimeout = window.setTimeout(async () => {
    currentPage.value = 1
    await fetchOrders(1)
  }, 300)
})

onMounted(fetchOrders)
</script>
