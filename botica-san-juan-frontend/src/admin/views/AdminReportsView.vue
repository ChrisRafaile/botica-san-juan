<template>
  <div class="p-4 sm:p-6 lg:p-8 space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">
          {{ isInventoryRoute ? 'Reporte de Inventario' : 'Reportes y Estadísticas' }}
        </h1>
        <p class="mt-2 text-gray-600">
          {{ isInventoryRoute
            ? 'Vista analítica del stock actual, alertas y valoración aproximada de inventario.'
            : 'Analítica de ventas, pedidos y productos desde datos reales.' }}
        </p>
      </div>
      <div class="flex flex-col sm:flex-row gap-3">
        <button
          class="inline-flex items-center justify-center rounded-xl bg-linear-to-r from-blue-600 to-indigo-600 px-4 py-3 text-white shadow-lg shadow-blue-600/20 transition hover:from-blue-700 hover:to-indigo-700"
          @click="exportCsv"
        >
          <Download class="mr-2 h-5 w-5" /> Exportar CSV
        </button>
        <button
          class="inline-flex items-center justify-center rounded-xl bg-linear-to-r from-emerald-600 to-green-600 px-4 py-3 text-white shadow-lg shadow-emerald-600/20 transition hover:from-emerald-700 hover:to-green-700"
          @click="refreshReports"
        >
          <RefreshCw class="mr-2 h-5 w-5" /> Actualizar
        </button>
      </div>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-lg ring-1 ring-slate-200/80 sm:p-6">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl bg-linear-to-r from-blue-600 to-cyan-600 p-6 text-white">
          <p class="text-sm text-blue-100">
            {{ isInventoryRoute ? 'Productos totales' : 'Ventas' }}
          </p>
          <p class="mt-2 text-3xl font-bold">
            {{ isInventoryRoute ? inventorySummary.totalProducts : `S/ ${summary.sales.toFixed(2)}` }}
          </p>
        </div>
        <div class="rounded-2xl bg-linear-to-r from-violet-600 to-fuchsia-600 p-6 text-white">
          <p class="text-sm text-violet-100">
            {{ isInventoryRoute ? 'Stock critico' : 'Pedidos' }}
          </p>
          <p class="mt-2 text-3xl font-bold">
            {{ isInventoryRoute ? inventorySummary.critical : summary.orders }}
          </p>
        </div>
        <div class="rounded-2xl bg-linear-to-r from-emerald-600 to-green-600 p-6 text-white">
          <p class="text-sm text-emerald-100">
            {{ isInventoryRoute ? 'Stock bajo' : 'Productos vendidos' }}
          </p>
          <p class="mt-2 text-3xl font-bold">
            {{ isInventoryRoute ? inventorySummary.low : summary.units }}
          </p>
        </div>
        <div class="rounded-2xl bg-linear-to-r from-amber-500 to-orange-500 p-6 text-white">
          <p class="text-sm text-amber-100">
            {{ isInventoryRoute ? 'Valor inventario' : 'Ticket promedio' }}
          </p>
          <p class="mt-2 text-3xl font-bold">
            {{ isInventoryRoute
              ? `S/ ${inventorySummary.valuation.toFixed(2)}`
              : `S/ ${summary.average_ticket.toFixed(2)}` }}
          </p>
        </div>
      </div>
    </div>

    <div
      v-if="!isInventoryRoute"
      class="grid grid-cols-1 gap-6 xl:grid-cols-2"
    >
      <div class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-slate-200/80">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-xl font-bold text-slate-900">
              Filtros
            </h2>
            <p class="text-sm text-slate-500">
              Ajusta el rango y recalcula los datos.
            </p>
          </div>
          <button
            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            @click="clearFilters"
          >
            Limpiar
          </button>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Período</label>
            <select
              v-model="period"
              class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            >
              <option value="month">
                Este mes
              </option>
              <option value="quarter">
                Trimestre
              </option>
              <option value="year">
                Año
              </option>
            </select>
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Desde</label>
            <input
              v-model="fromDate"
              type="date"
              class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Hasta</label>
            <input
              v-model="toDate"
              type="date"
              class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            />
          </div>
        </div>
      </div>

      <div class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-slate-200/80">
        <h2 class="text-xl font-bold text-slate-900">
          {{ isInventoryRoute ? 'Productos con menor stock' : 'Top productos' }}
        </h2>
        <div
          v-if="topProducts.length === 0"
          class="py-10 text-center text-slate-500"
        >
          No hay información suficiente.
        </div>
        <div
          v-else
          class="mt-6 space-y-3"
        >
          <div
            v-for="item in topProducts"
            :key="item.nombre"
            class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3"
          >
            <div>
              <p class="font-semibold text-slate-900">
                {{ item.nombre }}
              </p>
              <p class="text-sm text-slate-500">
                {{ item.cantidad }} unidades
              </p>
            </div>
            <span class="font-semibold text-emerald-600">S/ {{ item.total.toFixed(2) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="isInventoryRoute"
      class="grid grid-cols-1 gap-6 xl:grid-cols-2"
    >
      <section class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-slate-200/80">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-xl font-bold text-slate-900">
              Distribucion de stock
            </h2>
            <p class="text-sm text-slate-500">
              Proporcion critico, bajo y normal en tiempo real.
            </p>
          </div>
          <div class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
            {{ inventorySummary.totalProducts }} productos
          </div>
        </div>

        <div class="mt-6 flex flex-col items-center gap-5 md:flex-row md:items-center md:justify-between">
          <div
            class="relative h-44 w-44 rounded-full transition-all duration-300"
            :class="donutActiveClass"
            :style="donutStyle"
            @mousemove="handleDonutHover"
            @mouseleave="clearDonutTooltip"
          >
            <div class="absolute inset-5 rounded-full bg-white" />
            <div class="absolute inset-0 flex items-center justify-center">
              <div class="text-center">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Riesgo
                </p>
                <p class="text-2xl font-bold text-slate-900">
                  {{ riskRateLabel }}
                </p>
              </div>
            </div>
            <div
              v-if="donutTooltip.visible"
              class="pointer-events-none absolute z-10 rounded-lg bg-slate-900 px-3 py-2 text-xs text-white shadow-xl"
              :style="donutTooltipStyle"
            >
              <p class="font-semibold">
                {{ donutTooltip.label }}
              </p>
              <p>
                {{ donutTooltip.value }} productos ({{ donutTooltip.percent }}%)
              </p>
            </div>
          </div>

          <div class="w-full space-y-3 md:max-w-xs">
            <div
              class="flex items-center justify-between rounded-xl bg-rose-50 px-4 py-3 transition-all duration-300"
              :class="{ 'ring-2 ring-rose-300 scale-[1.02]': activeDonutSegment === 'critico' }"
            >
              <p class="text-sm font-medium text-rose-700">
                Critico
              </p>
              <p class="text-lg font-bold text-rose-800">
                {{ inventorySummary.critical }}
              </p>
            </div>
            <div
              class="flex items-center justify-between rounded-xl bg-amber-50 px-4 py-3 transition-all duration-300"
              :class="{ 'ring-2 ring-amber-300 scale-[1.02]': activeDonutSegment === 'bajo' }"
            >
              <p class="text-sm font-medium text-amber-700">
                Bajo
              </p>
              <p class="text-lg font-bold text-amber-800">
                {{ inventorySummary.low }}
              </p>
            </div>
            <div
              class="flex items-center justify-between rounded-xl bg-emerald-50 px-4 py-3 transition-all duration-300"
              :class="{ 'ring-2 ring-emerald-300 scale-[1.02]': activeDonutSegment === 'normal' }"
            >
              <p class="text-sm font-medium text-emerald-700">
                Normal
              </p>
              <p class="text-lg font-bold text-emerald-800">
                {{ inventorySummary.normal }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-slate-200/80">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-xl font-bold text-slate-900">
              Tendencia de salida
            </h2>
            <p class="text-sm text-slate-500">
              Unidades vendidas por dia (mes actual).
            </p>
          </div>
          <div
            class="rounded-full px-3 py-1 text-xs font-semibold"
            :class="trendClass"
          >
            {{ trendLabel }}
          </div>
        </div>

        <div class="mt-5">
          <svg
            viewBox="0 0 560 180"
            class="h-44 w-full"
            preserveAspectRatio="none"
          >
            <defs>
              <linearGradient
                id="trendFill"
                x1="0"
                y1="0"
                x2="0"
                y2="1"
              >
                <stop
                  offset="0%"
                  stop-color="#3b82f6"
                  stop-opacity="0.30"
                />
                <stop
                  offset="100%"
                  stop-color="#3b82f6"
                  stop-opacity="0.02"
                />
              </linearGradient>
            </defs>
            <path
              v-if="trendAreaPath"
              :d="trendAreaPath"
              fill="url(#trendFill)"
            />
            <path
              v-if="trendLinePath"
              :d="trendLinePath"
              fill="none"
              stroke="#2563eb"
              stroke-width="3"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
            <line
              v-if="activeTrendPoint"
              :x1="activeTrendPoint.x"
              :x2="activeTrendPoint.x"
              y1="0"
              y2="180"
              stroke="#60a5fa"
              stroke-width="1.5"
              stroke-dasharray="4 4"
              opacity="0.9"
            />
            <circle
              v-for="point in trendChartPoints"
              :key="point.index"
              :cx="point.x"
              :cy="point.y"
              :r="activeTrendIndex === point.index ? 6.5 : 4.5"
              :fill="activeTrendIndex === point.index ? '#1e40af' : '#1d4ed8'"
              stroke="#ffffff"
              :stroke-width="activeTrendIndex === point.index ? 2.5 : 2"
              class="cursor-pointer transition-all duration-200"
              @mouseenter="showTrendTooltip(point)"
              @mouseleave="hideTrendTooltip"
            />
          </svg>

          <div
            v-if="trendTooltip.visible"
            class="pointer-events-none mt-2 inline-flex rounded-lg bg-slate-900 px-3 py-2 text-xs text-white shadow-lg"
          >
            {{ trendTooltip.label }}: {{ trendTooltip.value }} unidades
          </div>

          <div class="mt-2 grid grid-cols-3 gap-2 text-xs text-slate-500">
            <div>
              Min: <span class="font-semibold text-slate-700">{{ trendStats.min }}</span>
            </div>
            <div class="text-center">
              Prom: <span class="font-semibold text-slate-700">{{ trendStats.avg }}</span>
            </div>
            <div class="text-right">
              Max: <span class="font-semibold text-slate-700">{{ trendStats.max }}</span>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div
      v-if="isInventoryRoute"
      class="overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-slate-200/80"
    >
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Producto
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Categoria
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Stock
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Minimo
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Precio
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Estado
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr
              v-for="row in inventoryRows"
              :key="row.id"
              class="hover:bg-slate-50"
            >
              <td class="px-6 py-4 text-sm font-semibold text-slate-900">
                {{ row.nombre }}
              </td>
              <td class="px-6 py-4 text-sm text-slate-600">
                {{ row.categoria }}
              </td>
              <td class="px-6 py-4 text-sm text-slate-700">
                {{ row.stock }}
              </td>
              <td class="px-6 py-4 text-sm text-slate-700">
                {{ row.stock_minimo }}
              </td>
              <td class="px-6 py-4 text-sm font-semibold text-slate-900">
                S/ {{ row.precio.toFixed(2) }}
              </td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase"
                  :class="stockStateClass(row.estado)"
                >
                  {{ row.estado }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-slate-200/80">
      <div
        v-if="loading"
        class="flex items-center justify-center py-16 text-slate-600"
      >
        <div class="h-9 w-9 animate-spin rounded-full border-4 border-slate-200 border-t-blue-600" />
        <span class="ml-3">Cargando reportes...</span>
      </div>
      <div
        v-else-if="!isInventoryRoute"
        class="overflow-x-auto"
      >
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Fecha
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Pedidos
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Productos
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Ingresos
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr
              v-for="row in reportRows"
              :key="row.fecha"
              class="hover:bg-slate-50"
            >
              <td class="px-6 py-4 text-sm font-medium text-slate-900">
                {{ formatDate(row.fecha) }}
              </td>
              <td class="px-6 py-4 text-sm text-slate-600">
                {{ row.pedidos }}
              </td>
              <td class="px-6 py-4 text-sm text-slate-600">
                {{ row.productos }}
              </td>
              <td class="px-6 py-4 text-sm font-semibold text-emerald-600">
                S/ {{ row.ingresos.toFixed(2) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div
        v-else
        class="px-6 py-10 text-center text-slate-500"
      >
        El detalle de inventario se muestra en la tabla superior.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useAdminToast } from '../composables/useAdminToast'
import { Download, RefreshCw } from 'lucide-vue-next'

interface ReportRow {
  fecha: string
  pedidos: number
  productos: number
  ingresos: number
}

interface TopProduct {
  nombre: string
  cantidad: number
  total: number
}

interface SummaryData {
  sales: number
  orders: number
  units: number
  average_ticket: number
  customers: number
}

interface StatusBreakdown {
  estado: string
  pedidos: number
  total: number
}

interface ReportsResponse {
  filters: {
    period: string
    from: string
    to: string
  }
  summary: SummaryData
  daily_rows: ReportRow[]
  top_products: TopProduct[]
  status_breakdown: StatusBreakdown[]
}

interface InventoryItem {
  id: number
  nombre: string
  precio: number
  stock: number
  stock_minimo: number
  stock_reposicion: number
  categoria?: {
    nombre?: string
  } | null
}

interface InventoryRow {
  id: number
  nombre: string
  categoria: string
  stock: number
  stock_minimo: number
  precio: number
  estado: 'critico' | 'bajo' | 'normal'
}

interface DonutTooltipState {
  visible: boolean
  label: string
  value: number
  percent: number
  x: number
  y: number
}

interface TrendChartPoint {
  index: number
  x: number
  y: number
  label: string
  value: number
}

const loading = ref(false)
const route = useRoute()
const isInventoryRoute = computed(() => route.path.endsWith('/reports/inventory'))
const period = ref<'month' | 'quarter' | 'year'>('month')
const fromDate = ref('')
const toDate = ref('')
const reportRows = ref<ReportRow[]>([])
const topProducts = ref<TopProduct[]>([])
const inventoryRows = ref<InventoryRow[]>([])
const salesTrendPoints = ref<Array<{ label: string; value: number }>>([])
const activeDonutSegment = ref<'critico' | 'bajo' | 'normal' | null>(null)
const activeTrendIndex = ref<number | null>(null)
const donutTooltip = ref<DonutTooltipState>({
  visible: false,
  label: '',
  value: 0,
  percent: 0,
  x: 0,
  y: 0
})
const trendTooltip = ref({
  visible: false,
  label: '',
  value: 0
})
const statusBreakdown = ref<StatusBreakdown[]>([])
const summary = ref<SummaryData>({
  sales: 0,
  orders: 0,
  units: 0,
  average_ticket: 0,
  customers: 0
})
const { notifyError, notifySuccess } = useAdminToast()

const formatDate = (dateValue: string) => new Date(dateValue).toLocaleDateString('es-PE', {
  year: 'numeric',
  month: 'short',
  day: 'numeric'
})

const inventorySummary = computed(() => {
  const totalProducts = inventoryRows.value.length
  const critical = inventoryRows.value.filter(item => item.estado === 'critico').length
  const low = inventoryRows.value.filter(item => item.estado === 'bajo').length
  const normal = inventoryRows.value.filter(item => item.estado === 'normal').length
  const valuation = inventoryRows.value.reduce((sum, item) => sum + (item.stock * item.precio), 0)

  return {
    totalProducts,
    critical,
    low,
    normal,
    valuation
  }
})

const donutStyle = computed(() => {
  const total = Math.max(1, inventorySummary.value.totalProducts)
  const criticalPct = (inventorySummary.value.critical / total) * 100
  const lowPct = (inventorySummary.value.low / total) * 100
  const normalPct = Math.max(0, 100 - criticalPct - lowPct)

  return {
    background: `conic-gradient(#ef4444 0 ${criticalPct}%, #f59e0b ${criticalPct}% ${criticalPct + lowPct}%, #10b981 ${criticalPct + lowPct}% ${criticalPct + lowPct + normalPct}%)`
  }
})

const donutTooltipStyle = computed(() => ({
  left: `${donutTooltip.value.x}px`,
  top: `${donutTooltip.value.y}px`,
  transform: 'translate(-50%, -120%)'
}))

const donutActiveClass = computed(() => {
  if (!activeDonutSegment.value) return 'shadow-none scale-100'
  return 'shadow-xl shadow-slate-300/70 scale-[1.02]'
})

const riskRateLabel = computed(() => {
  const total = Math.max(1, inventorySummary.value.totalProducts)
  const riskRate = ((inventorySummary.value.critical + inventorySummary.value.low) / total) * 100
  return `${Math.round(riskRate)}%`
})

const trendSeriesValues = computed(() => salesTrendPoints.value.map(point => point.value))

const trendChartPoints = computed<TrendChartPoint[]>(() => {
  const values = trendSeriesValues.value
  if (values.length < 2) return []

  const width = 560
  const height = 150
  const maxValue = Math.max(...values, 1)
  const stepX = width / Math.max(1, values.length - 1)

  return values.map((value, index) => ({
    index,
    x: Number((index * stepX).toFixed(2)),
    y: Number((height - (value / maxValue) * (height - 12)).toFixed(2)),
    label: salesTrendPoints.value[index]?.label ?? `Dia ${index + 1}`,
    value
  }))
})

const activeTrendPoint = computed(() => {
  if (activeTrendIndex.value === null) return null
  return trendChartPoints.value.find(point => point.index === activeTrendIndex.value) ?? null
})

const trendStats = computed(() => {
  if (trendSeriesValues.value.length === 0) {
    return { min: 0, avg: 0, max: 0 }
  }

  const min = Math.min(...trendSeriesValues.value)
  const max = Math.max(...trendSeriesValues.value)
  const avg = trendSeriesValues.value.reduce((sum, value) => sum + value, 0) / trendSeriesValues.value.length

  return {
    min,
    avg: Number(avg.toFixed(1)),
    max
  }
})

const trendLabel = computed(() => {
  if (trendSeriesValues.value.length < 2) return 'Sin suficiente data'
  const first = trendSeriesValues.value[0] ?? 0
  const last = trendSeriesValues.value[trendSeriesValues.value.length - 1] ?? 0
  if (last > first) return 'Tendencia al alza'
  if (last < first) return 'Tendencia a la baja'
  return 'Tendencia estable'
})

const trendClass = computed(() => {
  if (trendSeriesValues.value.length < 2) return 'bg-slate-100 text-slate-600'
  const first = trendSeriesValues.value[0] ?? 0
  const last = trendSeriesValues.value[trendSeriesValues.value.length - 1] ?? 0
  if (last > first) return 'bg-rose-100 text-rose-700'
  if (last < first) return 'bg-emerald-100 text-emerald-700'
  return 'bg-slate-100 text-slate-700'
})

const trendLinePath = computed(() => {
  const values = trendSeriesValues.value
  if (values.length < 2) return ''

  const width = 560
  const height = 150
  const maxValue = Math.max(...values, 1)
  const stepX = width / Math.max(1, values.length - 1)

  const points = values.map((value, index) => {
    const x = index * stepX
    const y = height - (value / maxValue) * (height - 12)
    return `${x.toFixed(2)},${y.toFixed(2)}`
  })

  return `M ${points.join(' L ')}`
})

const trendAreaPath = computed(() => {
  const line = trendLinePath.value
  if (!line) return ''
  return `${line} L 560,180 L 0,180 Z`
})

const handleDonutHover = (event: MouseEvent) => {
  const target = event.currentTarget as HTMLDivElement | null
  if (!target) return

  const rect = target.getBoundingClientRect()
  const cx = rect.width / 2
  const cy = rect.height / 2
  const x = event.clientX - rect.left
  const y = event.clientY - rect.top
  const dx = x - cx
  const dy = y - cy
  const radius = Math.sqrt(dx * dx + dy * dy)
  const outerRadius = rect.width / 2
  const innerRadius = outerRadius - 20

  if (radius > outerRadius || radius < innerRadius) {
    clearDonutTooltip()
    return
  }

  const total = Math.max(1, inventorySummary.value.totalProducts)
  const criticalPct = (inventorySummary.value.critical / total) * 100
  const lowPct = (inventorySummary.value.low / total) * 100

  let angle = (Math.atan2(dy, dx) * 180) / Math.PI
  angle = (angle + 90 + 360) % 360
  const anglePct = (angle / 360) * 100

  if (anglePct <= criticalPct) {
    activeDonutSegment.value = 'critico'
    donutTooltip.value = {
      visible: true,
      label: 'Critico',
      value: inventorySummary.value.critical,
      percent: Math.round((inventorySummary.value.critical / total) * 100),
      x,
      y
    }
    return
  }

  if (anglePct <= criticalPct + lowPct) {
    activeDonutSegment.value = 'bajo'
    donutTooltip.value = {
      visible: true,
      label: 'Bajo',
      value: inventorySummary.value.low,
      percent: Math.round((inventorySummary.value.low / total) * 100),
      x,
      y
    }
    return
  }

  activeDonutSegment.value = 'normal'
  donutTooltip.value = {
    visible: true,
    label: 'Normal',
    value: inventorySummary.value.normal,
    percent: Math.round((inventorySummary.value.normal / total) * 100),
    x,
    y
  }
}

const clearDonutTooltip = () => {
  donutTooltip.value.visible = false
  activeDonutSegment.value = null
}

const showTrendTooltip = (point: TrendChartPoint) => {
  activeTrendIndex.value = point.index
  trendTooltip.value = {
    visible: true,
    label: formatDate(point.label),
    value: point.value
  }
}

const hideTrendTooltip = () => {
  trendTooltip.value.visible = false
  activeTrendIndex.value = null
}

const stockStateClass = (estado: InventoryRow['estado']) => {
  if (estado === 'critico') return 'bg-rose-100 text-rose-700'
  if (estado === 'bajo') return 'bg-amber-100 text-amber-700'
  return 'bg-emerald-100 text-emerald-700'
}

const resolveStockState = (item: InventoryItem): InventoryRow['estado'] => {
  const stock = Number(item.stock || 0)
  const minimo = Number(item.stock_minimo || 0)
  const reposicion = Number(item.stock_reposicion || minimo)

  if (stock <= minimo) return 'critico'
  if (stock <= reposicion) return 'bajo'
  return 'normal'
}

const refreshInventoryReport = async () => {
  loading.value = true
  try {
    const [productsResponse, trendResponse] = await Promise.all([
      api.get('/productos', {
        params: {
          per_page: 200
        }
      }),
      api.get<ReportsResponse>('/reportes/ventas', {
        params: {
          period: 'month'
        }
      })
    ])

    const rows = Array.isArray(productsResponse.data)
      ? productsResponse.data
      : (productsResponse.data?.data ?? [])

    inventoryRows.value = rows.map((item: InventoryItem) => ({
      id: item.id,
      nombre: item.nombre,
      categoria: item.categoria?.nombre || 'Sin categoria',
      stock: Number(item.stock || 0),
      stock_minimo: Number(item.stock_minimo || 0),
      precio: Number(item.precio || 0),
      estado: resolveStockState(item)
    }))

    topProducts.value = inventoryRows.value
      .slice()
      .sort((a, b) => a.stock - b.stock)
      .slice(0, 5)
      .map((item) => ({
        nombre: item.nombre,
        cantidad: item.stock,
        total: item.precio * item.stock
      }))

    salesTrendPoints.value = (trendResponse.data.daily_rows || []).map((row) => ({
      label: row.fecha,
      value: Number(row.productos || 0)
    }))
  } catch (error) {
    console.error('Error loading inventory report:', error)
    notifyError('Error de carga', 'No se pudo cargar el reporte de inventario.')
  } finally {
    loading.value = false
  }
}

const refreshReports = async () => {
  if (isInventoryRoute.value) {
    await refreshInventoryReport()
    return
  }

  loading.value = true
  try {
    const response = await api.get<ReportsResponse>('/reportes/ventas', {
      params: {
        period: period.value,
        from: fromDate.value || undefined,
        to: toDate.value || undefined
      }
    })
    summary.value = response.data.summary
    reportRows.value = response.data.daily_rows
    topProducts.value = response.data.top_products
    statusBreakdown.value = response.data.status_breakdown
  } catch (error) {
    console.error('Error loading reports:', error)
    notifyError('Error de carga', 'No se pudieron cargar los reportes.')
  } finally {
    loading.value = false
  }
}

const clearFilters = () => {
  if (isInventoryRoute.value) {
    refreshInventoryReport()
    return
  }

  period.value = 'month'
  fromDate.value = ''
  toDate.value = ''
  refreshReports()
}

const exportCsv = () => {
  if (isInventoryRoute.value) {
    const rows = [
      'producto,categoria,stock,stock_minimo,precio,estado',
      ...inventoryRows.value.map(row => `${row.nombre},${row.categoria},${row.stock},${row.stock_minimo},${row.precio.toFixed(2)},${row.estado}`)
    ]

    const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = 'reporte_inventario.csv'
    link.click()
    URL.revokeObjectURL(link.href)
    notifySuccess('CSV exportado', 'El archivo de inventario fue descargado.')
    return
  }

  const rows = [
    'fecha,pedidos,productos,ingresos',
    ...reportRows.value.map(row => `${row.fecha},${row.pedidos},${row.productos},${row.ingresos.toFixed(2)}`)
  ]
  const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = 'reportes.csv'
  link.click()
  URL.revokeObjectURL(link.href)
  notifySuccess('CSV exportado', 'El archivo de reportes fue descargado.')
}

watch([period, fromDate, toDate], () => refreshReports())
watch(() => route.path, () => refreshReports())
onMounted(refreshReports)
</script>
