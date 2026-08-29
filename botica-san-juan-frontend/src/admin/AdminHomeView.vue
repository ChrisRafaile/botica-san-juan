<template>
  <div class="p-4 sm:p-6 lg:p-8 space-y-6">
    <div>
      <h1 class="text-3xl font-bold text-gray-900">
        Dashboard
      </h1>
      <p class="mt-2 text-gray-600">
        Bienvenido al panel de administración, {{ user?.nombre || 'Administrador' }}.
      </p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl bg-linear-to-r from-blue-600 to-indigo-600 p-6 text-white shadow-xl">
        <p class="text-sm text-blue-100">
          Productos
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ productsCount }}
        </p>
        <p class="mt-1 text-sm text-blue-50">
          Activos
        </p>
      </div>
      <div class="rounded-2xl bg-linear-to-r from-emerald-600 to-green-600 p-6 text-white shadow-xl">
        <p class="text-sm text-emerald-100">
          Pedidos
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ ordersCount }}
        </p>
        <p class="mt-1 text-sm text-emerald-50">
          Registrados
        </p>
      </div>
      <div class="rounded-2xl bg-linear-to-r from-violet-600 to-fuchsia-600 p-6 text-white shadow-xl">
        <p class="text-sm text-violet-100">
          Clientes
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ clientsCount }}
        </p>
        <p class="mt-1 text-sm text-violet-50">
          Con cuenta
        </p>
      </div>
      <div class="rounded-2xl bg-linear-to-r from-amber-500 to-orange-500 p-6 text-white shadow-xl">
        <p class="text-sm text-amber-100">
          Ventas
        </p>
        <p class="mt-2 text-3xl font-bold">
          S/ {{ totalSales.toFixed(2) }}
        </p>
        <p class="mt-1 text-sm text-amber-50">
          Acumuladas
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
      <div class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-slate-200/80">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h2 class="text-xl font-bold text-slate-900">
              Pedidos recientes
            </h2>
            <p class="mt-1 text-sm text-slate-500">
              Últimas órdenes registradas en el sistema.
            </p>
          </div>
          <button
            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            @click="refreshData"
          >
            Actualizar
          </button>
        </div>

        <div
          v-if="recentOrders.length === 0"
          class="py-10 text-center text-slate-500"
        >
          No hay pedidos para mostrar.
        </div>
        <div
          v-else
          class="mt-6 space-y-3"
        >
          <div
            v-for="order in recentOrders"
            :key="order.id"
            class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3"
          >
            <div>
              <p class="font-semibold text-slate-900">
                #{{ String(order.id).padStart(4, '0') }}
              </p>
              <p class="text-sm text-slate-500">
                {{ order.usuario?.nombre || 'Sin cliente' }} · {{ formatDate(order.fecha_pedido) }}
              </p>
            </div>
            <span
              class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
              :class="statusBadge(order.estado)"
            >
              {{ order.estado }}
            </span>
          </div>
        </div>
      </div>

      <div class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-slate-200/80">
        <h2 class="text-xl font-bold text-slate-900">
          Productos más vendidos
        </h2>
        <p class="mt-1 text-sm text-slate-500">
          Resumen calculado a partir de los pedidos existentes.
        </p>

        <div
          v-if="topProducts.length === 0"
          class="py-10 text-center text-slate-500"
        >
          No hay datos suficientes para generar ranking.
        </div>
        <div
          v-else
          class="mt-6 space-y-3"
        >
          <div
            v-for="item in topProducts"
            :key="item.nombre"
            class="rounded-2xl bg-slate-50 px-4 py-3"
          >
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="font-semibold text-slate-900">
                  {{ item.nombre }}
                </p>
                <p class="text-sm text-slate-500">
                  {{ item.cantidad }} unidades
                </p>
              </div>
              <p class="font-semibold text-emerald-600">
                S/ {{ item.total.toFixed(2) }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-slate-200/80">
      <h2 class="text-xl font-bold text-slate-900">
        Acciones rápidas
      </h2>
      <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
        <button
          class="rounded-2xl bg-blue-50 p-4 text-left transition hover:bg-blue-100"
          @click="$router.push('/admin/products/add')"
        >
          <Package class="h-7 w-7 text-blue-600" />
          <p class="mt-3 text-sm font-semibold text-slate-800">
            Nuevo producto
          </p>
        </button>
        <button
          class="rounded-2xl bg-violet-50 p-4 text-left transition hover:bg-violet-100"
          @click="$router.push('/admin/clients')"
        >
          <Users class="h-7 w-7 text-violet-600" />
          <p class="mt-3 text-sm font-semibold text-slate-800">
            Ver clientes
          </p>
        </button>
        <button
          class="rounded-2xl bg-emerald-50 p-4 text-left transition hover:bg-emerald-100"
          @click="$router.push('/admin/orders')"
        >
          <ShoppingCart class="h-7 w-7 text-emerald-600" />
          <p class="mt-3 text-sm font-semibold text-slate-800">
            Ver pedidos
          </p>
        </button>
        <button
          class="rounded-2xl bg-amber-50 p-4 text-left transition hover:bg-amber-100"
          @click="$router.push('/admin/reports')"
        >
          <BarChart3 class="h-7 w-7 text-amber-600" />
          <p class="mt-3 text-sm font-semibold text-slate-800">
            Reportes
          </p>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import { BarChart3, Package, ShoppingCart, Users } from 'lucide-vue-next'
import { useAuthStore } from '../stores/auth'

interface DashboardUser {
  id: number
  nombre: string
  rol?: string | null
}

interface DashboardOrder {
  id: number
  fecha_pedido: string
  total: string | number
  estado: string
  usuario?: DashboardUser | null
  pedidoDetalles?: Array<{
    cantidad: number
    subtotal: string | number
    producto?: { nombre?: string } | null
  }>
}

const authStore = useAuthStore()
const user = computed(() => authStore.user)

const productsCount = ref(0)
const ordersCount = ref(0)
const clientsCount = ref(0)
const totalSales = ref(0)
const recentOrders = ref<DashboardOrder[]>([])
const topProducts = ref<Array<{ nombre: string; cantidad: number; total: number }>>([])

const formatDate = (dateValue: string) => new Date(dateValue).toLocaleDateString('es-PE', {
  year: 'numeric',
  month: 'short',
  day: 'numeric'
})

const statusBadge = (status: string) => {
  switch (status) {
    case 'pendiente':
      return 'bg-amber-100 text-amber-800'
    case 'procesando':
      return 'bg-blue-100 text-blue-800'
    case 'enviado':
      return 'bg-orange-100 text-orange-800'
    case 'entregado':
      return 'bg-emerald-100 text-emerald-800'
    default:
      return 'bg-slate-100 text-slate-800'
  }
}

const refreshData = async () => {
  try {
    const [productsResponse, usersResponse, ordersResponse] = await Promise.all([
      api.get<{ data: Array<{ id: number }> }>('/productos?per_page=200'),
      api.get<DashboardUser[]>('/usuarios'),
      api.get<DashboardOrder[]>('/pedidos')
    ])

    productsCount.value = productsResponse.data.data.length
    clientsCount.value = usersResponse.data.filter(currentUser => currentUser.rol === 'cliente').length
    ordersCount.value = ordersResponse.data.length
    recentOrders.value = ordersResponse.data
      .slice()
      .sort((left, right) => new Date(right.fecha_pedido).getTime() - new Date(left.fecha_pedido).getTime())
      .slice(0, 5)
    totalSales.value = ordersResponse.data.reduce((sum, order) => sum + Number(order.total || 0), 0)

    const productSummary = new Map<string, { nombre: string; cantidad: number; total: number }>()
    ordersResponse.data.forEach(order => {
      (order.pedidoDetalles || []).forEach(detail => {
        const nombre = detail.producto?.nombre || 'Producto'
        const current = productSummary.get(nombre) || { nombre, cantidad: 0, total: 0 }
        current.cantidad += detail.cantidad || 0
        current.total += Number(detail.subtotal || 0)
        productSummary.set(nombre, current)
      })
    })
    topProducts.value = Array.from(productSummary.values())
      .sort((left, right) => right.cantidad - left.cantidad)
      .slice(0, 4)
  } catch (error) {
    console.error('Error loading dashboard data:', error)
  }
}

onMounted(refreshData)
</script>
