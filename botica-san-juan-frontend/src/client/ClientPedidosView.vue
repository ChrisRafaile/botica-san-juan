<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  AlertCircle,
  AlertTriangle,
  CheckCircle2,
  CreditCard,
  PackageOpen,
  RotateCcw,
  ShoppingCart,
} from 'lucide-vue-next'

import {
  cargarPedidosCliente,
  claseEstadoPedido,
  etiquetaEstadoPedido,
  etiquetaModalidad,
  formatearFecha,
  formatearMoneda,
  pedidoRequierePago,
  type EstadoFiltroPedido,
  type PedidoCliente,
} from '@/services/pedidosCliente'
import type { EstadoReceta } from '@/services/recetas'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/carrito'
import DistintivoReceta from '@/components/DistintivoReceta.vue'
import RecetaDelPedido from '@/components/RecetaDelPedido.vue'
import ImagenConRespaldo from '@/components/ImagenConRespaldo.vue'

const FILTROS: { valor: EstadoFiltroPedido; etiqueta: string }[] = [
  { valor: 'todos', etiqueta: 'Todos' },
  { valor: 'pendiente', etiqueta: 'Pendiente' },
  { valor: 'confirmado', etiqueta: 'Confirmado' },
  { valor: 'completado', etiqueta: 'Entregado' },
  { valor: 'anulado', etiqueta: 'Cancelado' },
]

const route = useRoute()
const auth = useAuthStore()
const carrito = useCartStore()

const pedidos = ref<PedidoCliente[]>([])
const cargando = ref(true)
const error = ref('')
const filtro = ref<EstadoFiltroPedido>('todos')
const recetaAbierta = ref<number | null>(null)
const reordenandoId = ref<number | null>(null)

const feedbackReorden = ref<{
  pedidoId: number
  tipo: 'exito' | 'advertencia' | 'error'
  mensaje: string
  ajustes: string[]
} | null>(null)

function aplicarFiltroDesdeRuta(): void {
  const q = route.query.estado
  if (typeof q === 'string' && FILTROS.some((f) => f.valor === q)) {
    filtro.value = q as EstadoFiltroPedido
  }
}

const conteos = computed<Record<EstadoFiltroPedido, number>>(() => {
  const mapa: Record<EstadoFiltroPedido, number> = {
    todos: pedidos.value.length,
    pendiente: 0,
    confirmado: 0,
    completado: 0,
    anulado: 0,
  }
  for (const p of pedidos.value) {
    if (p.estado === 'pendiente' || p.estado === 'confirmado' || p.estado === 'completado' || p.estado === 'anulado') {
      mapa[p.estado] += 1
    }
  }
  return mapa
})

const pedidosFiltrados = computed(() => {
  if (filtro.value === 'todos') return pedidos.value
  return pedidos.value.filter((p) => p.estado === filtro.value)
})

function alternarReceta(pedidoId: number): void {
  recetaAbierta.value = recetaAbierta.value === pedidoId ? null : pedidoId
}

async function volverAPedir(pedido: PedidoCliente): Promise<void> {
  const detalles = pedido.pedido_detalles ?? []
  if (detalles.length === 0) {
    feedbackReorden.value = {
      pedidoId: pedido.id,
      tipo: 'advertencia',
      mensaje: `El pedido #${pedido.id} no contiene productos disponibles para volver a pedir.`,
      ajustes: [],
    }
    return
  }

  reordenandoId.value = pedido.id
  feedbackReorden.value = null

  const agotadosLocales: string[] = []
  let lineasAgregadas = 0

  for (const item of detalles) {
    const nombreItem = item.producto?.nombre ?? `Producto #${item.producto_id}`
    const stockConocido = item.producto?.stock
    if (stockConocido !== null && stockConocido !== undefined && Number(stockConocido) <= 0) {
      agotadosLocales.push(`${nombreItem}: sin stock disponible actualmente.`)
      continue
    }
    const cant = Math.max(1, Number(item.cantidad) || 1)
    carrito.agregar(item.producto_id, cant)
    lineasAgregadas += 1
  }

  if (lineasAgregadas > 0) {
    await carrito.cotizar()
  }

  const mensajesAjustes = [
    ...agotadosLocales,
    ...carrito.ajustes.map(
      (a) => a.mensaje || `${a.nombre ?? `Producto #${a.producto_id}`}: cantidad ajustada por stock.`,
    ),
  ]

  if (carrito.error) {
    feedbackReorden.value = {
      pedidoId: pedido.id,
      tipo: 'error',
      mensaje: carrito.error,
      ajustes: mensajesAjustes,
    }
  } else if (mensajesAjustes.length > 0) {
    feedbackReorden.value = {
      pedidoId: pedido.id,
      tipo: 'advertencia',
      mensaje:
        lineasAgregadas > 0 && carrito.totalUnidades > 0
          ? `Se cargó el pedido #${pedido.id} en tu carrito con ajustes de disponibilidad:`
          : `No hay stock disponible para los productos del pedido #${pedido.id}:`,
      ajustes: mensajesAjustes,
    }
  } else {
    feedbackReorden.value = {
      pedidoId: pedido.id,
      tipo: 'exito',
      mensaje: `Se agregaron los productos del pedido #${pedido.id} a tu carrito.`,
      ajustes: [],
    }
  }

  reordenandoId.value = null
}

watch(() => route.query.estado, aplicarFiltroDesdeRuta)

onMounted(async () => {
  aplicarFiltroDesdeRuta()
  const usuarioId = auth.user?.id
  if (!usuarioId) {
    cargando.value = false
    return
  }

  try {
    pedidos.value = await cargarPedidosCliente(usuarioId)
  } catch {
    error.value = 'No pudimos cargar tu historial de pedidos. Inténtalo nuevamente en unos instantes.'
  } finally {
    cargando.value = false
  }
})
</script>

<template>
  <section class="space-y-6" aria-labelledby="titulo-mis-pedidos">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
      <div>
        <h1 id="titulo-mis-pedidos" class="text-2xl font-bold tracking-tight text-texto-primario sm:text-3xl">
          Mis pedidos
        </h1>
        <p class="mt-1 text-sm text-texto-secundario">
          Consulta el estado de cada compra, gestiona tus recetas o vuelve a pedir en un clic.
        </p>
      </div>

      <RouterLink
        to="/products"
        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-full bg-botica-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-botica-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
      >
        Nuevo pedido
      </RouterLink>
    </header>

    <!-- Filtros de estado -->
    <nav aria-label="Filtrar pedidos por estado" class="flex flex-wrap gap-2">
      <button
        v-for="item in FILTROS"
        :key="item.valor"
        type="button"
        :aria-pressed="filtro === item.valor"
        :class="[
          'foco-dentro inline-flex min-h-10 items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-medium transition-colors',
          filtro === item.valor
            ? 'border-botica-600 bg-botica-600 text-white'
            : 'border-borde-control bg-superficie-elevada text-texto-secundario hover:bg-superficie-interactiva hover:text-texto-primario',
        ]"
        @click="filtro = item.valor"
      >
        <span>{{ item.etiqueta }}</span>
        <span
          :class="[
            'cifras-tabulares rounded-full px-1.5 py-0.5 text-xs',
            filtro === item.valor
              ? 'bg-white/20 text-white'
              : 'bg-superficie-hundida text-texto-terciario',
          ]"
        >
          {{ conteos[item.valor] }}
        </span>
      </button>
    </nav>

    <!-- Aviso al volver a pedir -->
    <div
      v-if="feedbackReorden"
      :role="feedbackReorden.tipo === 'exito' ? 'status' : 'alert'"
      :class="[
        'flex flex-col justify-between gap-4 rounded-2xl border p-4 sm:flex-row sm:items-center',
        feedbackReorden.tipo === 'exito'
          ? 'border-botica-500/30 bg-botica-50 text-botica-800 dark:bg-botica-500/15 dark:text-botica-200'
          : feedbackReorden.tipo === 'advertencia'
            ? 'border-ambar-500/40 bg-ambar-50 text-ambar-800 dark:bg-ambar-500/15 dark:text-ambar-200'
            : 'border-peligro-500/40 bg-peligro-50 text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-300',
      ]"
    >
      <div class="flex items-start gap-3">
        <CheckCircle2
          v-if="feedbackReorden.tipo === 'exito'"
          class="mt-0.5 size-5 shrink-0"
          aria-hidden="true"
        />
        <AlertTriangle
          v-else
          class="mt-0.5 size-5 shrink-0"
          aria-hidden="true"
        />
        <div class="text-sm">
          <p class="font-semibold">{{ feedbackReorden.mensaje }}</p>
          <ul v-if="feedbackReorden.ajustes.length > 0" class="mt-1.5 list-disc space-y-0.5 pl-4 text-xs">
            <li v-for="(aviso, idx) in feedbackReorden.ajustes" :key="idx">
              {{ aviso }}
            </li>
          </ul>
        </div>
      </div>

      <RouterLink
        v-if="carrito.totalUnidades > 0"
        to="/cart"
        class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-full bg-botica-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-botica-700"
      >
        <ShoppingCart class="size-4" aria-hidden="true" />
        Ir al carrito ({{ carrito.totalUnidades }})
      </RouterLink>
    </div>

    <!-- Error de carga -->
    <p
      v-if="error"
      role="alert"
      class="flex items-start gap-2 rounded-2xl border border-peligro-500/30 bg-peligro-50 p-4 text-sm text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-300"
    >
      <AlertCircle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
      {{ error }}
    </p>

    <!-- Esqueletos de carga -->
    <div v-else-if="cargando" class="space-y-4">
      <div
        v-for="n in 3"
        :key="n"
        class="h-44 animate-pulse rounded-2xl border border-borde-sutil bg-superficie-elevada"
      />
    </div>

    <!-- Estado vacío -->
    <div
      v-else-if="pedidosFiltrados.length === 0"
      class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-borde-sutil bg-superficie-elevada px-6 py-14 text-center"
    >
      <PackageOpen class="size-10 text-texto-terciario" aria-hidden="true" />
      <p class="text-base font-semibold text-texto-primario">
        {{ filtro === 'todos' ? 'Todavía no tienes pedidos registrados' : `No hay pedidos con estado «${etiquetaEstadoPedido(filtro)}»` }}
      </p>
      <p class="text-sm text-texto-secundario">
        {{ filtro === 'todos' ? 'Cuando compres en la tienda verás aquí el detalle de cada entrega.' : 'Prueba seleccionando otro filtro o revisa todo tu historial.' }}
      </p>
      <button
        v-if="filtro !== 'todos'"
        type="button"
        class="mt-1 rounded-full border border-borde-control px-4 py-2 text-sm font-semibold text-texto-primario hover:bg-superficie-interactiva"
        @click="filtro = 'todos'"
      >
        Ver todos los pedidos
      </button>
    </div>

    <!-- Lista completa de pedidos -->
    <ul v-else class="space-y-4">
      <li
        v-for="pedido in pedidosFiltrados"
        :key="pedido.id"
        class="overflow-hidden rounded-2xl border border-borde-sutil bg-superficie-elevada"
      >
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-borde-sutil px-5 py-4">
          <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-base font-bold text-texto-primario">Pedido #{{ pedido.id }}</h2>
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                  claseEstadoPedido(pedido.estado),
                ]"
              >
                {{ etiquetaEstadoPedido(pedido.estado) }}
              </span>
              <span
                v-if="pedido.estado_pago === 'pagado'"
                class="rounded-full bg-exito-50 px-2.5 py-0.5 text-xs font-semibold text-exito-700 dark:bg-exito-500/15 dark:text-exito-400"
              >
                Pagado
              </span>
              <span
                v-else-if="pedido.estado !== 'anulado'"
                class="rounded-full bg-alerta-50 px-2.5 py-0.5 text-xs font-semibold text-alerta-700 dark:bg-alerta-500/15 dark:text-alerta-400"
              >
                Pago pendiente
              </span>
            </div>
            <p class="text-xs text-texto-secundario">
              {{ formatearFecha(pedido.fecha || pedido.fecha_pedido || pedido.created_at) }}
              · {{ etiquetaModalidad(pedido.observacion) }}
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2.5">
            <span class="cifras-tabulares mr-2 text-lg font-bold text-texto-primario">
              {{ formatearMoneda(pedido.total) }}
            </span>

            <RouterLink
              v-if="pedidoRequierePago(pedido)"
              :to="{ path: '/checkout/pago', query: { pedido: String(pedido.id) } }"
              class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-botica-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-botica-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
            >
              <CreditCard class="size-3.5" aria-hidden="true" />
              Reanudar pago
            </RouterLink>

            <button
              v-if="(pedido.pedido_detalles?.length ?? 0) > 0"
              type="button"
              :disabled="reordenandoId === pedido.id"
              class="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-borde-control bg-superficie-elevada px-4 py-2 text-xs font-semibold text-texto-primario transition hover:bg-superficie-interactiva disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
              @click="volverAPedir(pedido)"
            >
              <RotateCcw class="size-3.5" aria-hidden="true" />
              {{ reordenandoId === pedido.id ? 'Agregando…' : 'Volver a pedir' }}
            </button>
          </div>
        </div>

        <!-- Productos del pedido con foto -->
        <div v-if="(pedido.pedido_detalles?.length ?? 0) > 0" class="divide-y divide-borde-sutil px-5">
          <div
            v-for="detalle in pedido.pedido_detalles"
            :key="detalle.id"
            class="flex items-center gap-3 py-3"
          >
            <ImagenConRespaldo
              :ruta="detalle.producto?.imagen ?? null"
              :alt="detalle.producto?.nombre ?? `Producto #${detalle.producto_id}`"
              clase-contenedor="size-12 shrink-0 rounded-xl border border-borde-sutil"
              clase-imagen="size-12 rounded-xl object-cover"
            />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-texto-primario">
                {{ detalle.producto?.nombre ?? `Producto #${detalle.producto_id}` }}
              </p>
              <p class="text-xs text-texto-terciario">
                {{ detalle.cantidad }} × {{ formatearMoneda(detalle.precio_unitario ?? detalle.precio) }}
                <template v-if="detalle.producto?.concentracion">
                  · {{ detalle.producto.concentracion }}
                </template>
              </p>
            </div>
            <p class="cifras-tabulares shrink-0 text-sm font-semibold text-texto-primario">
              {{
                formatearMoneda(
                  detalle.subtotal ?? Number(detalle.precio_unitario ?? detalle.precio) * detalle.cantidad,
                )
              }}
            </p>
          </div>
        </div>

        <!-- Bloque de receta médica si aplica -->
        <div v-if="pedido.requiere_receta" class="border-t border-borde-sutil bg-superficie-hundida/50 px-5 py-3">
          <button
            type="button"
            class="flex w-full items-center gap-2 rounded-lg px-1 py-1 text-left transition-colors hover:bg-superficie-interactiva"
            :aria-expanded="recetaAbierta === pedido.id"
            @click="alternarReceta(pedido.id)"
          >
            <DistintivoReceta :estado="(pedido.receta_estado as EstadoReceta | null) ?? null" compacto />
            <span class="text-xs font-medium text-texto-secundario">
              {{ recetaAbierta === pedido.id ? 'Ocultar detalle de receta' : 'Gestionar receta' }}
            </span>
          </button>

          <RecetaDelPedido
            v-if="recetaAbierta === pedido.id"
            :key="pedido.id"
            :pedido-id="pedido.id"
            class="mt-3"
            @cambio="(r) => (pedido.receta_estado = r?.estado ?? null)"
          />
        </div>
      </li>
    </ul>
  </section>
</template>
