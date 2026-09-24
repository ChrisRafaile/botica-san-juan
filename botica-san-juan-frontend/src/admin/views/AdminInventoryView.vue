<script setup lang="ts">
/**
 * AdminInventoryView · Inventario, Control de stock y Alertas
 * ---------------------------------------------------------------------------
 * Una misma vista sirve tres rutas; cambia el encuadre, no los datos.
 *
 * Rediseño sobre el design system. Qué cambia y por qué:
 *
 * - Las tarjetas de indicador dejan de ser degradados a pantalla completa. Un
 *   bloque rojo saturado grita igual con 0 que con 340, así que se deja de
 *   mirar. Ahora el color se reserva para el número y un acento discreto.
 * - Los botones de icono tenían cero etiqueta accesible: un lector de pantalla
 *   anunciaba "botón" sin más, tres veces por fila. Ahora cada uno dice qué
 *   hace y sobre qué producto.
 * - El estado de carga pasa de "spinner centrado" a esqueleto con la forma del
 *   contenido: la espera se percibe más corta y la página no salta al llegar
 *   los datos.
 * - El estado vacío distingue "no hay nada" de "tus filtros no encontraron
 *   nada", porque la acción que resuelve cada caso es distinta.
 * - Se añade una barra de stock: ver 4 no dice nada; ver 4 con el mínimo en 5
 *   se entiende de un vistazo.
 *
 * La lógica de datos (paginación, filtros, búsqueda con rebote, ajuste de
 * stock) se mantiene intacta respecto a la versión anterior.
 */

import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useAdminToast } from '../composables/useAdminToast'
import {
  Minus,
  PackageOpen,
  Plus,
  RefreshCw,
  Save,
  Search,
  SlidersHorizontal,
  TriangleAlert,
  Boxes,
  CircleCheck,
  X,
} from 'lucide-vue-next'
import BoticaBadge from '../components/ui/BoticaBadge.vue'
import BoticaStat from '../components/ui/BoticaStat.vue'
import BoticaButton from '../components/ui/BoticaButton.vue'
import BoticaEstadoVacio from '../components/ui/BoticaEstadoVacio.vue'

interface InventoryProduct {
  id: number
  nombre: string
  presentacion: string
  codigo: string
  categoryId: number | null
  categoria: string
  subcategoria: string
  stockActual: number
  /** Unidades realmente vendibles: excluye lotes vencidos y retirados. */
  stockDisponible: number
  /** Diferencia entre el stock contable y el vendible. */
  stockNoVendible: number
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
  categoria?: { id: number; nombre: string } | null
  subcategoria?: { id: number; nombre: string } | null
  stock_minimo?: number | null
  stock_reposicion?: number | null
  codigo_barras?: string | null
  stock: number
  stock_disponible?: number
  stock_no_vendible?: number
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
/** Ids con una operación de stock en curso, para bloquear sólo esa fila. */
const enProceso = ref<Set<number>>(new Set())

const currentPage = ref(1)
const perPage = ref(10)
const totalPages = ref(1)
const totalItems = ref(0)

let searchTimeout: number | undefined

const isStockRoute = computed(() => route.path.endsWith('/inventory/stock'))
const isAlertsRoute = computed(() => route.path.endsWith('/inventory/alerts'))

const titulo = computed(() =>
  isAlertsRoute.value
    ? 'Alertas de inventario'
    : isStockRoute.value
      ? 'Control de stock'
      : 'Gestión de inventario',
)

const descripcion = computed(() =>
  isAlertsRoute.value
    ? 'Productos que han bajado del nivel de reposición y necesitan pedido.'
    : 'Seguimiento del stock disponible por producto.',
)

const visibleProducts = computed(() => {
  if (!isAlertsRoute.value) return inventoryProducts.value
  return inventoryProducts.value.filter(
    (product) => product.estado === 'Bajo' || product.estado === 'Critico',
  )
})

const categories = computed<CategoryOption[]>(() => {
  const map = new Map<number, string>()
  inventoryProducts.value.forEach((product) => {
    if (product.categoryId) map.set(product.categoryId, product.categoria)
  })
  return Array.from(map.entries())
    .map(([id, name]) => ({ id, name }))
    .sort((a, b) => a.name.localeCompare(b.name))
})

const criticalCount = computed(
  () => inventoryProducts.value.filter((p) => p.stockDisponible <= p.stockMinimo).length,
)
const lowCount = computed(
  () =>
    inventoryProducts.value.filter(
      (p) => p.stockDisponible > p.stockMinimo && p.stockDisponible <= p.stockReposicion,
    ).length,
)
/** Unidades que estan en el anaquel pero no se pueden vender (vencidas). */
const noVendibleTotal = computed(
  () => inventoryProducts.value.reduce((suma, p) => suma + p.stockNoVendible, 0),
)
const selectedProduct = computed(
  () => visibleProducts.value.find((p) => p.id === selectedProductId.value) || null,
)

/** Distingue "sin datos" de "los filtros no devuelven nada". */
const hayFiltrosActivos = computed(
  () =>
    searchQuery.value.trim() !== '' ||
    categoryFilter.value !== 'all' ||
    (!isAlertsRoute.value && stockFilter.value !== 'all'),
)

function limpiarFiltros() {
  searchQuery.value = ''
  categoryFilter.value = 'all'
  if (!isAlertsRoute.value) stockFilter.value = 'all'
}

/** Traduce el estado del negocio al tono de la insignia. */
function tonoEstado(estado: string) {
  if (estado === 'Critico') return 'critico' as const
  if (estado === 'Bajo') return 'bajo' as const
  return 'normal' as const
}

function etiquetaEstado(estado: string) {
  return estado === 'Critico' ? 'Crítico' : estado
}

/**
 * Proporción de stock respecto al nivel de reposición, acotada al 100 %.
 * Da contexto inmediato: un 4 no dice nada; un 4 sobre un mínimo de 5, sí.
 */
function porcentajeStock(p: InventoryProduct) {
  const techo = Math.max(p.stockReposicion, p.stockMinimo, 1)
  return Math.min(100, Math.round((p.stockDisponible / techo) * 100))
}

function colorBarra(estado: string) {
  if (estado === 'Critico') return 'bg-stock-critico'
  if (estado === 'Bajo') return 'bg-stock-bajo'
  return 'bg-stock-optimo'
}

const mapProducts = (products: BackendProduct[]) => {
  inventoryProducts.value = products.map((product) => ({
    id: product.id,
    nombre: product.nombre,
    presentacion: product.presentacion,
    codigo:
      product.codigo_barras ||
      `${product.tipo.slice(0, 3).toUpperCase()}-${String(product.id).padStart(3, '0')}`,
    categoryId: product.categoria?.id || product.categoria_id || null,
    categoria: product.categoria?.nombre || product.tipo,
    subcategoria: product.subcategoria?.nombre || '',
    stockActual: product.stock,
    stockDisponible: Number(product.stock_disponible ?? product.stock),
    stockNoVendible: Number(product.stock_no_vendible ?? 0),
    stockMinimo: Number(product.stock_minimo ?? 5),
    stockReposicion: Number(product.stock_reposicion ?? 10),
    /* El estado se calcula sobre el stock VENDIBLE. Un producto con 40
       unidades vencidas no esta "Normal": no se puede vender ninguna. */
    estado: (() => {
      const vendible = Number(product.stock_disponible ?? product.stock)
      if (vendible <= Number(product.stock_minimo ?? 5)) return 'Critico'
      if (vendible <= Number(product.stock_reposicion ?? 10)) return 'Bajo'
      return 'Normal'
    })(),
    ultimaActualizacion:
      product.updated_at || product.created_at || new Date().toISOString(),
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
        stock_status: effectiveStockFilter,
      },
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
  if (enProceso.value.has(product.id)) return
  enProceso.value = new Set(enProceso.value).add(product.id)
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
      precio: backendProduct.data.precio,
    })
    notifySuccess('Stock actualizado', `Nuevo stock para ${product.nombre}: ${nextStock}.`)
    await fetchInventory(currentPage.value)
  } catch (error) {
    console.error('Error updating stock:', error)
    notifyError('No se pudo actualizar', 'Error al guardar el stock del producto.')
  } finally {
    const copia = new Set(enProceso.value)
    copia.delete(product.id)
    enProceso.value = copia
  }
}

watch([categoryFilter, stockFilter, perPage], async () => {
  currentPage.value = 1
  await fetchInventory(1)
})

watch(searchQuery, () => {
  if (searchTimeout) window.clearTimeout(searchTimeout)
  searchTimeout = window.setTimeout(async () => {
    currentPage.value = 1
    await fetchInventory(1)
  }, 300)
})

watch(isAlertsRoute, async (isAlerts) => {
  if (isAlerts) stockFilter.value = 'low'
  currentPage.value = 1
  await fetchInventory(1)
})

onMounted(fetchInventory)
</script>

<template>
  <div class="mx-auto max-w-[110rem] space-y-5">
    <!-- Encabezado -->
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div class="min-w-0">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-texto-primario">
          {{ titulo }}
        </h1>
        <p class="mt-1 text-sm text-texto-secundario">
          {{ descripcion }}
        </p>
      </div>

      <div class="flex shrink-0 flex-wrap gap-2">
        <BoticaButton
          :icono="RefreshCw"
          :cargando="loading"
          @click="refreshInventory"
        >
          Actualizar
        </BoticaButton>
        <BoticaButton
          v-if="!isAlertsRoute"
          variante="primario"
          :icono="Plus"
          @click="openAdjustModal(null)"
        >
          Ajustar stock
        </BoticaButton>
      </div>
    </header>

    <!-- Indicadores -->
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <BoticaStat
        etiqueta="Stock crítico"
        :valor="criticalCount"
        :icono="TriangleAlert"
        tono="critico"
        detalle="En o bajo el mínimo · esta página"
        :cargando="loading"
      />
      <BoticaStat
        etiqueta="Stock bajo"
        :valor="lowCount"
        :icono="Boxes"
        tono="alerta"
        detalle="Bajo el nivel de reposición · esta página"
        :cargando="loading"
      />
      <BoticaStat
        etiqueta="Productos listados"
        :valor="totalItems"
        :icono="CircleCheck"
        tono="marca"
        :detalle="`Mostrando ${visibleProducts.length} en esta página`"
        :cargando="loading"
      />
      <!-- Solo aparece si hay algo que no se puede vender: una tarjeta en cero
           todo el tiempo deja de mirarse. -->
      <BoticaStat
        v-if="noVendibleTotal > 0"
        etiqueta="No vendible"
        :valor="noVendibleTotal"
        :icono="TriangleAlert"
        tono="critico"
        detalle="Unidades vencidas o retiradas · esta página"
        :cargando="loading"
      />
    </div>

    <!-- Filtros -->
    <section
      class="rounded-xl border border-borde-sutil bg-superficie-elevada p-3 sm:p-4"
      aria-label="Filtros de inventario"
    >
      <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_200px_200px_110px]">
        <div class="relative">
          <Search
            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-texto-terciario"
            aria-hidden="true"
          />
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Buscar producto o categoría…"
            aria-label="Buscar producto o categoría"
            class="h-10 w-full rounded-lg border border-borde-base bg-superficie-hundida pl-9 pr-3 text-sm text-texto-primario outline-none transition placeholder:text-texto-terciario focus:border-borde-marca focus:ring-2 focus:ring-botica-500/20"
          />
        </div>

        <select
          v-model="categoryFilter"
          aria-label="Filtrar por categoría"
          class="h-10 rounded-lg border border-borde-base bg-superficie-hundida px-3 text-sm text-texto-primario outline-none transition focus:border-borde-marca focus:ring-2 focus:ring-botica-500/20"
        >
          <option value="all">
            Todas las categorías
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
          aria-label="Filtrar por estado de stock"
          class="h-10 rounded-lg border border-borde-base bg-superficie-hundida px-3 text-sm text-texto-primario outline-none transition focus:border-borde-marca focus:ring-2 focus:ring-botica-500/20"
        >
          <option value="all">
            Todo el stock
          </option>
          <option value="critical">
            Crítico
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
          aria-label="Productos por página"
          class="h-10 rounded-lg border border-borde-base bg-superficie-hundida px-3 text-sm text-texto-primario outline-none transition focus:border-borde-marca focus:ring-2 focus:ring-botica-500/20"
        >
          <option :value="10">
            10 por página
          </option>
          <option :value="20">
            20 por página
          </option>
          <option :value="30">
            30 por página
          </option>
        </select>
      </div>

      <div
        v-if="hayFiltrosActivos"
        class="mt-3 flex items-center gap-2 border-t border-borde-sutil pt-3"
      >
        <SlidersHorizontal
          class="size-3.5 text-texto-terciario"
          aria-hidden="true"
        />
        <span class="text-xs text-texto-secundario">Filtros activos</span>
        <BoticaButton
          tamano="sm"
          variante="fantasma"
          :icono="X"
          @click="limpiarFiltros"
        >
          Limpiar
        </BoticaButton>
      </div>
    </section>

    <!-- Resultados -->
    <section
      class="overflow-hidden rounded-xl border border-borde-sutil bg-superficie-elevada"
      aria-label="Listado de inventario"
    >
      <!-- Anuncia el resultado a lectores de pantalla sin robar el foco -->
      <p
        class="sr-only"
        role="status"
        aria-live="polite"
      >
        {{ loading ? 'Cargando inventario' : `${visibleProducts.length} productos mostrados de ${totalItems}` }}
      </p>

      <!-- Esqueleto de carga: misma forma que el contenido real -->
      <div
        v-if="loading"
        class="divide-y divide-borde-sutil"
        aria-hidden="true"
      >
        <div
          v-for="n in 5"
          :key="n"
          class="flex items-center gap-4 px-4 py-4 sm:px-6"
        >
          <div class="flex-1 space-y-2">
            <div class="h-3.5 w-1/3 animate-pulse rounded bg-superficie-interactiva" />
            <div class="h-3 w-1/4 animate-pulse rounded bg-superficie-hundida" />
          </div>
          <div class="hidden h-3.5 w-20 animate-pulse rounded bg-superficie-interactiva sm:block" />
          <div class="h-6 w-16 animate-pulse rounded-full bg-superficie-interactiva" />
        </div>
      </div>

      <!-- Sin resultados -->
      <BoticaEstadoVacio
        v-else-if="visibleProducts.length === 0"
        :icono="PackageOpen"
        :titulo="hayFiltrosActivos ? 'Ningún producto coincide' : 'Sin productos en inventario'"
        :descripcion="
          hayFiltrosActivos
            ? 'Prueba con otros términos o quita algún filtro.'
            : isAlertsRoute
              ? 'Ningún producto está por debajo del nivel de reposición. Todo en orden.'
              : 'Cuando registres productos, aparecerán aquí.'
        "
      >
        <template
          v-if="hayFiltrosActivos"
          #accion
        >
          <BoticaButton
            :icono="X"
            @click="limpiarFiltros"
          >
            Limpiar filtros
          </BoticaButton>
        </template>
      </BoticaEstadoVacio>

      <template v-else>
        <!-- Móvil: tarjetas. Una tabla de 5 columnas es ilegible por debajo
             de 768px, y esta pantalla se usa con el teléfono en mano. -->
        <ul class="divide-y divide-borde-sutil md:hidden">
          <li
            v-for="product in visibleProducts"
            :key="product.id"
            class="p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate font-medium text-texto-primario">
                  {{ product.nombre }}
                </p>
                <p class="mt-0.5 truncate text-xs text-texto-terciario">
                  {{ product.codigo }} · {{ product.categoria
                  }}{{ product.subcategoria ? ` / ${product.subcategoria}` : '' }}
                </p>
              </div>
              <BoticaBadge
                :tono="tonoEstado(product.estado)"
                punto
              >
                {{ etiquetaEstado(product.estado) }}
              </BoticaBadge>
            </div>

            <div class="mt-3">
              <div class="flex items-baseline justify-between">
                <span class="cifras-tabulares text-lg font-semibold text-texto-primario">
                  {{ product.stockDisponible }}
                </span>
                <span class="text-xs text-texto-terciario">
                  mín. {{ product.stockMinimo }} · rep. {{ product.stockReposicion }}
                  <template v-if="product.stockNoVendible > 0">
                    · <span class="font-medium text-venc-critico">{{ product.stockNoVendible }} no vendible</span>
                  </template>
                </span>
              </div>
              <div
                class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-superficie-hundida"
                role="progressbar"
                :aria-valuenow="product.stockDisponible"
                :aria-valuemin="0"
                :aria-valuemax="Math.max(product.stockReposicion, product.stockMinimo, 1)"
                :aria-label="`Stock de ${product.nombre}`"
              >
                <div
                  class="h-full rounded-full transition-[width] duration-300"
                  :class="colorBarra(product.estado)"
                  :style="{ width: `${porcentajeStock(product)}%` }"
                />
              </div>
            </div>

            <div
              v-if="!isAlertsRoute"
              class="mt-3 flex justify-end gap-1.5"
            >
              <BoticaButton
                tamano="sm"
                :icono="Minus"
                :disabled="enProceso.has(product.id) || product.stockDisponible === 0"
                :etiqueta-accesible="`Restar una unidad a ${product.nombre}`"
                @click="changeStock(product, -1)"
              />
              <BoticaButton
                tamano="sm"
                :icono="Plus"
                :disabled="enProceso.has(product.id)"
                :etiqueta-accesible="`Sumar una unidad a ${product.nombre}`"
                @click="changeStock(product, 1)"
              />
              <BoticaButton
                tamano="sm"
                variante="fantasma"
                :icono="SlidersHorizontal"
                :etiqueta-accesible="`Ajustar stock de ${product.nombre}`"
                @click="openAdjustModal(product)"
              />
            </div>
          </li>
        </ul>

        <!-- Escritorio: tabla -->
        <div class="hidden overflow-x-auto md:block">
          <table class="min-w-full text-sm">
            <caption class="sr-only">
              Inventario de productos con su stock actual y estado
            </caption>
            <thead>
              <tr class="border-b border-borde-sutil bg-superficie-hundida">
                <th
                  scope="col"
                  class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-texto-terciario"
                >
                  Producto
                </th>
                <th
                  scope="col"
                  class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-texto-terciario"
                >
                  Código / Categoría
                </th>
                <th
                  scope="col"
                  class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-texto-terciario"
                >
                  Stock
                </th>
                <th
                  scope="col"
                  class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-texto-terciario"
                >
                  Estado
                </th>
                <th
                  v-if="!isAlertsRoute"
                  scope="col"
                  class="px-5 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-texto-terciario"
                >
                  Acciones
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-borde-sutil">
              <tr
                v-for="product in visibleProducts"
                :key="product.id"
                class="transition-colors duration-100 hover:bg-superficie-interactiva"
              >
                <td class="px-5 py-3">
                  <p class="font-medium text-texto-primario">
                    {{ product.nombre }}
                  </p>
                  <p class="text-xs text-texto-terciario">
                    {{ product.presentacion }}
                  </p>
                </td>
                <td class="px-5 py-3">
                  <p class="cifras-tabulares text-texto-secundario">
                    {{ product.codigo }}
                  </p>
                  <p class="text-xs text-texto-terciario">
                    {{ product.categoria
                    }}{{ product.subcategoria ? ` / ${product.subcategoria}` : '' }}
                  </p>
                </td>
                <td class="px-5 py-3">
                  <div class="flex items-baseline gap-2">
                    <span class="cifras-tabulares text-base font-semibold text-texto-primario">
                      {{ product.stockDisponible }}
                    </span>
                    <span class="text-xs text-texto-terciario">
                      / mín. {{ product.stockMinimo }}
                    </span>
                    <span
                      v-if="product.stockNoVendible > 0"
                      class="text-xs font-medium text-venc-critico"
                      :title="`${product.stockNoVendible} unidades vencidas o retiradas, no vendibles`"
                    >+{{ product.stockNoVendible }} no vendible</span>
                  </div>
                  <div
                    class="mt-1 h-1 w-28 overflow-hidden rounded-full bg-superficie-hundida"
                    role="progressbar"
                    :aria-valuenow="product.stockDisponible"
                    :aria-valuemin="0"
                    :aria-valuemax="Math.max(product.stockReposicion, product.stockMinimo, 1)"
                    :aria-label="`Stock de ${product.nombre}`"
                  >
                    <div
                      class="h-full rounded-full transition-[width] duration-300"
                      :class="colorBarra(product.estado)"
                      :style="{ width: `${porcentajeStock(product)}%` }"
                    />
                  </div>
                </td>
                <td class="px-5 py-3">
                  <BoticaBadge
                    :tono="tonoEstado(product.estado)"
                    punto
                  >
                    {{ etiquetaEstado(product.estado) }}
                  </BoticaBadge>
                </td>
                <td
                  v-if="!isAlertsRoute"
                  class="px-5 py-3 text-right"
                >
                  <div class="inline-flex gap-1.5">
                    <BoticaButton
                      tamano="sm"
                      :icono="Minus"
                      :disabled="enProceso.has(product.id) || product.stockDisponible === 0"
                      :etiqueta-accesible="`Restar una unidad a ${product.nombre}`"
                      @click="changeStock(product, -1)"
                    />
                    <BoticaButton
                      tamano="sm"
                      :icono="Plus"
                      :disabled="enProceso.has(product.id)"
                      :etiqueta-accesible="`Sumar una unidad a ${product.nombre}`"
                      @click="changeStock(product, 1)"
                    />
                    <BoticaButton
                      tamano="sm"
                      variante="fantasma"
                      :icono="SlidersHorizontal"
                      :etiqueta-accesible="`Ajustar stock de ${product.nombre}`"
                      @click="openAdjustModal(product)"
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </section>

    <!-- Paginación -->
    <nav
      v-if="totalPages > 1"
      class="flex flex-col items-center justify-between gap-3 rounded-xl border border-borde-sutil bg-superficie-elevada px-4 py-3 sm:flex-row"
      aria-label="Paginación del inventario"
    >
      <p class="text-sm text-texto-secundario">
        Mostrando
        <span class="cifras-tabulares font-medium text-texto-primario">{{ visibleProducts.length }}</span>
        de
        <span class="cifras-tabulares font-medium text-texto-primario">{{ totalItems }}</span>
        productos
      </p>
      <div class="flex items-center gap-2">
        <BoticaButton
          tamano="sm"
          :disabled="currentPage <= 1"
          @click="changePage(currentPage - 1)"
        >
          Anterior
        </BoticaButton>
        <span class="cifras-tabulares px-1 text-sm text-texto-secundario">
          {{ currentPage }} / {{ totalPages }}
        </span>
        <BoticaButton
          tamano="sm"
          :disabled="currentPage >= totalPages"
          @click="changePage(currentPage + 1)"
        >
          Siguiente
        </BoticaButton>
      </div>
    </nav>

    <!-- Modal de ajuste -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showModal"
          class="fixed inset-0 z-50 flex items-end justify-center bg-neutro-950/60 p-0 backdrop-blur-sm sm:items-center sm:p-6"
          role="dialog"
          aria-modal="true"
          aria-labelledby="titulo-ajuste-stock"
          @click.self="closeModal"
          @keydown.esc="closeModal"
        >
          <div
            class="w-full max-w-md rounded-t-2xl border border-borde-sutil bg-superficie-flotante shadow-2xl sm:rounded-2xl"
          >
            <div class="flex items-start justify-between gap-4 border-b border-borde-sutil px-5 py-4">
              <div>
                <h2
                  id="titulo-ajuste-stock"
                  class="font-display text-lg font-semibold text-texto-primario"
                >
                  Ajustar stock
                </h2>
                <p class="mt-0.5 text-xs text-texto-secundario">
                  El movimiento queda registrado en el histórico del producto.
                </p>
              </div>
              <BoticaButton
                tamano="sm"
                variante="fantasma"
                :icono="X"
                etiqueta-accesible="Cerrar"
                @click="closeModal"
              />
            </div>

            <form
              class="space-y-4 px-5 py-5"
              @submit.prevent="saveStockAdjustment"
            >
              <div>
                <label
                  for="ajuste-producto"
                  class="mb-1.5 block text-sm font-medium text-texto-secundario"
                >Producto</label>
                <select
                  id="ajuste-producto"
                  v-model="selectedProductId"
                  class="h-10 w-full rounded-lg border border-borde-base bg-superficie-hundida px-3 text-sm text-texto-primario outline-none focus:border-borde-marca focus:ring-2 focus:ring-botica-500/20"
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
                  <label
                    for="ajuste-movimiento"
                    class="mb-1.5 block text-sm font-medium text-texto-secundario"
                  >Movimiento</label>
                  <select
                    id="ajuste-movimiento"
                    v-model.number="stockDelta"
                    class="h-10 w-full rounded-lg border border-borde-base bg-superficie-hundida px-3 text-sm text-texto-primario outline-none focus:border-borde-marca focus:ring-2 focus:ring-botica-500/20"
                  >
                    <option :value="1">
                      Entrada +1
                    </option>
                    <option :value="5">
                      Entrada +5
                    </option>
                    <option :value="-1">
                      Salida −1
                    </option>
                    <option :value="-5">
                      Salida −5
                    </option>
                  </select>
                </div>
                <div>
                  <label
                    for="ajuste-resultado"
                    class="mb-1.5 block text-sm font-medium text-texto-secundario"
                  >Stock resultante</label>
                  <input
                    id="ajuste-resultado"
                    :value="selectedProduct ? Math.max(0, selectedProduct.stockActual + stockDelta) : 0"
                    type="number"
                    disabled
                    class="cifras-tabulares h-10 w-full rounded-lg border border-borde-sutil bg-superficie-hundida px-3 text-sm font-semibold text-texto-primario"
                  />
                </div>
              </div>

              <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                <BoticaButton @click="closeModal">
                  Cancelar
                </BoticaButton>
                <BoticaButton
                  type="submit"
                  variante="primario"
                  :icono="Save"
                  :disabled="!selectedProduct"
                >
                  Guardar movimiento
                </BoticaButton>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
