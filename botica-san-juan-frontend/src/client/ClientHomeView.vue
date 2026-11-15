<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import type { Component } from 'vue'
import { RouterLink } from 'vue-router'
import {
  AlertCircle,
  ArrowRight,
  CheckCircle2,
  Clock,
  CreditCard,
  Download,
  FileText,
  LoaderCircle,
  PackageCheck,
  PackageOpen,
  Plus,
  Receipt,
  Search,
  ShoppingCart,
  Sparkles,
  XCircle,
} from 'lucide-vue-next'

import { descargarPdfComprobante, listarMisComprobantes, type ComprobanteCliente } from '@/services/comprobantes'
import {
  cargarPedidosCliente,
  cargarProductosDestacados,
  cargarResumenCliente,
  etiquetaModalidad,
  formatearFecha,
  formatearMoneda,
  pedidoRequierePago,
  type PedidoCliente,
  type ProductoDestacado,
  type ResumenCliente,
} from '@/services/pedidosCliente'
import type { EstadoReceta } from '@/services/recetas'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/carrito'
import TarjetaResumen from './components/TarjetaResumen.vue'
import DistintivoReceta from '@/components/DistintivoReceta.vue'
import RecetaDelPedido from '@/components/RecetaDelPedido.vue'
import ImagenConRespaldo from '@/components/ImagenConRespaldo.vue'

const auth = useAuthStore()
const carrito = useCartStore()

const resumen = ref<ResumenCliente | null>(null)
const pedidos = ref<PedidoCliente[]>([])
const comprobantes = ref<ComprobanteCliente[]>([])
const recomendados = ref<ProductoDestacado[]>([])
const cargando = ref(true)
const error = ref('')
const descargandoPdfId = ref<number | null>(null)
const errorPdf = ref('')
const recetaAbierta = ref<number | null>(null)
const agregadoRecienteId = ref<number | null>(null)
let timerAgregado: number | undefined

const ROLES: Record<string, string> = {
  administrador: 'Administrador',
  vendedor: 'Vendedor',
  cliente: 'Cliente',
}

const nombreCorto = computed(() => auth.user?.nombre?.split(' ')[0] ?? 'cliente')
const rol = computed(() => ROLES[auth.user?.rol ?? ''] ?? 'Cliente')

interface EstiloEstado {
  rotulo: string
  fondo: string
  texto: string
  icono: Component
}

const ESTADOS: Record<string, EstiloEstado> = {
  pendiente: {
    rotulo: 'Pendiente',
    fondo: 'bg-alerta-50 text-alerta-700 dark:bg-alerta-500/15 dark:text-alerta-500',
    texto: 'text-alerta-700 dark:text-alerta-500',
    icono: Clock,
  },
  confirmado: {
    rotulo: 'Confirmado',
    fondo: 'bg-botica-50 text-texto-marca dark:bg-botica-500/15 dark:text-botica-300',
    texto: 'text-texto-marca',
    icono: PackageCheck,
  },
  completado: {
    rotulo: 'Entregado',
    fondo: 'bg-exito-50 text-exito-700 dark:bg-exito-500/15 dark:text-exito-500',
    texto: 'text-exito-700 dark:text-exito-500',
    icono: CheckCircle2,
  },
  anulado: {
    rotulo: 'Cancelado',
    fondo: 'bg-peligro-50 text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-500',
    texto: 'text-peligro-700 dark:text-peligro-500',
    icono: XCircle,
  },
}

const SIN_ESTADO: EstiloEstado = {
  rotulo: 'En proceso',
  fondo: 'bg-superficie-hundida text-texto-secundario',
  texto: 'text-texto-secundario',
  icono: Clock,
}

const estilo = (estado: string): EstiloEstado => ESTADOS[estado] ?? SIN_ESTADO

const pedidosEnCurso = computed(() =>
  pedidos.value
    .filter((p) => p.estado === 'pendiente' || p.estado === 'confirmado')
    .slice(0, 5),
)

const pedidosAMostrar = computed(() =>
  pedidosEnCurso.value.length > 0 ? pedidosEnCurso.value : pedidos.value.slice(0, 4),
)

function alternarReceta(pedido: PedidoCliente): void {
  recetaAbierta.value = recetaAbierta.value === pedido.id ? null : pedido.id
}

function agregarAlCarrito(producto: ProductoDestacado): void {
  carrito.agregar(producto.id, 1)
  agregadoRecienteId.value = producto.id
  if (timerAgregado !== undefined) {
    window.clearTimeout(timerAgregado)
  }
  timerAgregado = window.setTimeout(() => {
    agregadoRecienteId.value = null
  }, 1600)
}

async function descargarPdf(c: ComprobanteCliente): Promise<void> {
  errorPdf.value = ''
  descargandoPdfId.value = c.id
  try {
    await descargarPdfComprobante(c.id, c.correlativo)
  } catch {
    errorPdf.value = `No pudimos descargar el comprobante ${c.correlativo}. Vuelve a intentarlo en un momento.`
  } finally {
    descargandoPdfId.value = null
  }
}

onMounted(async () => {
  const usuarioId = auth.user?.id
  const [res, peds, comps, dests] = await Promise.allSettled([
    cargarResumenCliente(),
    usuarioId ? cargarPedidosCliente(usuarioId) : Promise.resolve([]),
    listarMisComprobantes(),
    cargarProductosDestacados(8),
  ])

  if (res.status === 'fulfilled') resumen.value = res.value
  if (peds.status === 'fulfilled') {
    pedidos.value = peds.value
    const pendiente = peds.value.find(
      (p) => p.requiere_receta && (!p.receta_estado || p.receta_estado === 'rechazada'),
    )
    if (pendiente) recetaAbierta.value = pendiente.id
  }
  if (comps.status === 'fulfilled') comprobantes.value = comps.value.slice(0, 5)
  if (dests.status === 'fulfilled') recomendados.value = dests.value

  if (
    res.status === 'rejected' &&
    peds.status === 'rejected' &&
    comps.status === 'rejected'
  ) {
    error.value = 'No pudimos cargar tu resumen en este momento. Inténtalo de nuevo más tarde.'
  }

  cargando.value = false
})

onUnmounted(() => {
  if (timerAgregado !== undefined) {
    window.clearTimeout(timerAgregado)
  }
})
</script>

<template>
  <section class="space-y-8" aria-labelledby="titulo-portal-inicio">
    <!-- Cabecera de bienvenida y accesos rápidos -->
    <header class="flex flex-col justify-between gap-4 rounded-2xl border border-borde-sutil bg-superficie-elevada p-6 sm:flex-row sm:items-center">
      <div>
        <div class="flex flex-wrap items-center gap-2">
          <span class="inline-flex items-center rounded-full bg-botica-50 px-2.5 py-0.5 text-xs font-semibold text-texto-marca dark:bg-botica-500/15 dark:text-botica-300">
            {{ rol }}
          </span>
          <span v-if="auth.user?.dni" class="cifras-tabulares text-xs text-texto-terciario">
            DNI {{ auth.user.dni }}
          </span>
        </div>
        <h1
          id="titulo-portal-inicio"
          class="mt-1.5 text-2xl font-bold tracking-tight text-texto-primario sm:text-3xl"
        >
          Hola, {{ nombreCorto }}
        </h1>
        <p class="mt-1 text-sm text-texto-secundario">
          Revisa tus pedidos en curso, descarga tus comprobantes y repón tus productos en segundos.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <RouterLink
          v-if="carrito.totalUnidades > 0"
          to="/cart"
          class="inline-flex min-h-11 items-center gap-2 rounded-full bg-botica-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-botica-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
        >
          <ShoppingCart class="size-4" aria-hidden="true" />
          Finalizar compra ({{ carrito.totalUnidades }})
        </RouterLink>

        <RouterLink
          to="/products"
          class="inline-flex min-h-11 items-center gap-2 rounded-full border border-borde-control bg-superficie-elevada px-4 py-2.5 text-sm font-semibold text-texto-primario transition hover:bg-superficie-interactiva focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
        >
          <Search class="size-4" aria-hidden="true" />
          Ver catálogo
        </RouterLink>
      </div>
    </header>

    <!-- KPIs renovados -->
    <section aria-label="Resumen de tu cuenta">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <TarjetaResumen
          etiqueta="Pedidos en curso"
          :valor="String(resumen?.pedidos.abiertos ?? 0)"
          :detalle="resumen?.pedidos.abiertos === 1 ? '1 pedido activo' : 'Pendientes o confirmados'"
          :icono="Clock"
          :cargando="cargando"
          enlace="/client/pedidos"
        />

        <TarjetaResumen
          etiqueta="Pedidos totales"
          :valor="String(resumen?.pedidos.total ?? 0)"
          :detalle="`${resumen?.pedidos.completados ?? 0} entregados`"
          :icono="PackageCheck"
          :cargando="cargando"
          enlace="/client/pedidos"
        />

        <TarjetaResumen
          etiqueta="Comprobantes"
          :valor="String(resumen?.comprobantes ?? 0)"
          detalle="Boletas y facturas disponibles"
          :icono="Receipt"
          :cargando="cargando"
          enlace="/client/comprobantes"
        />

        <TarjetaResumen
          etiqueta="Tu carrito"
          :valor="String(carrito.totalUnidades)"
          :detalle="carrito.totalUnidades > 0 ? 'Listo para finalizar compra' : 'Sin productos añadidos'"
          :icono="ShoppingCart"
          :cargando="false"
          enlace="/cart"
        />
      </div>
    </section>

    <!-- Recomendados y más buscados -->
    <section
      v-if="cargando || recomendados.length > 0"
      aria-labelledby="titulo-recomendados"
      class="space-y-4"
    >
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <Sparkles class="size-5 text-texto-marca" aria-hidden="true" />
          <h2 id="titulo-recomendados" class="text-lg font-bold text-texto-primario">
            Recomendados y más buscados
          </h2>
        </div>
        <RouterLink
          to="/products"
          class="inline-flex items-center gap-1 text-sm font-semibold text-texto-marca hover:underline"
        >
          Explorar todo
          <ArrowRight class="size-4" aria-hidden="true" />
        </RouterLink>
      </div>

      <div
        v-if="cargando"
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
      >
        <div
          v-for="n in 4"
          :key="n"
          class="h-64 animate-pulse rounded-2xl border border-borde-sutil bg-superficie-elevada"
        />
      </div>

      <div
        v-else
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
      >
        <article
          v-for="producto in recomendados"
          :key="producto.id"
          class="flex flex-col overflow-hidden rounded-2xl border border-borde-sutil bg-superficie-elevada transition hover:border-botica-500/40"
        >
          <RouterLink :to="`/catalogo/${producto.id}`" class="block aspect-4/3 w-full overflow-hidden">
            <ImagenConRespaldo
              :src="producto.imagen ?? null"
              :alt="producto.nombre"
              clase-contenedor="h-full w-full"
              clase-imagen="h-full w-full object-cover transition-transform duration-200 hover:scale-105"
            />
          </RouterLink>

          <div class="flex flex-1 flex-col justify-between p-4">
            <div>
              <div class="flex items-start justify-between gap-2">
                <RouterLink
                  :to="`/catalogo/${producto.id}`"
                  class="line-clamp-2 text-sm font-semibold text-texto-primario hover:text-texto-marca"
                >
                  {{ producto.nombre }}
                </RouterLink>
              </div>
              <p v-if="producto.concentracion || producto.presentacion" class="mt-0.5 text-xs text-texto-terciario">
                {{ [producto.concentracion, producto.presentacion].filter(Boolean).join(' · ') }}
              </p>
              <span
                v-if="producto.requiere_receta"
                class="mt-2 inline-flex rounded-full bg-alerta-50 px-2 py-0.5 text-2xs font-medium text-alerta-700 dark:bg-alerta-500/15 dark:text-alerta-400"
              >
                Requiere receta
              </span>
            </div>

            <div class="mt-4 flex items-center justify-between gap-2 pt-2 border-t border-borde-sutil">
              <span class="cifras-tabulares text-base font-bold text-texto-primario">
                {{ formatearMoneda(producto.precio) }}
              </span>
              <button
                type="button"
                :disabled="!producto.disponible"
                class="inline-flex min-h-9 items-center gap-1 rounded-full bg-botica-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-botica-700 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
                @click="agregarAlCarrito(producto)"
              >
                <Plus class="size-3.5" aria-hidden="true" />
                {{
                  !producto.disponible
                    ? 'Agotado'
                    : agregadoRecienteId === producto.id
                      ? 'Agregado'
                      : 'Agregar'
                }}
              </button>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- Estado de pedidos en curso + últimos comprobantes -->
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-5">
      <section class="lg:col-span-3" aria-labelledby="titulo-pedidos-curso">
        <div class="flex items-center justify-between gap-2">
          <h2 id="titulo-pedidos-curso" class="text-lg font-bold text-texto-primario">
            {{ pedidosEnCurso.length > 0 ? 'Pedidos en curso' : 'Tus pedidos recientes' }}
          </h2>
          <RouterLink
            to="/client/pedidos"
            class="inline-flex items-center gap-1 text-sm font-semibold text-texto-marca hover:underline"
          >
            Ver todos
            <ArrowRight class="size-4" aria-hidden="true" />
          </RouterLink>
        </div>

        <div class="mt-3 overflow-hidden rounded-2xl border border-borde-sutil bg-superficie-elevada">
          <ul v-if="cargando">
            <li
              v-for="n in 3"
              :key="n"
              class="flex h-20 items-center gap-4 border-b border-borde-sutil px-4 last:border-0"
            >
              <div class="size-9 animate-pulse rounded-full bg-superficie-hundida" />
              <div class="flex-1 space-y-2">
                <div class="h-3.5 w-32 animate-pulse rounded bg-superficie-hundida" />
                <div class="h-3 w-48 animate-pulse rounded bg-superficie-hundida" />
              </div>
              <div class="h-4 w-16 animate-pulse rounded bg-superficie-hundida" />
            </li>
          </ul>

          <div
            v-else-if="pedidosAMostrar.length === 0"
            class="flex h-44 flex-col items-center justify-center gap-2 px-6 text-center"
          >
            <PackageOpen class="size-8 text-texto-terciario" aria-hidden="true" />
            <p class="text-sm text-texto-secundario">Aún no realizaste ningún pedido.</p>
            <RouterLink to="/products" class="text-sm font-semibold text-texto-marca hover:underline">
              Explorar medicamentos y productos
            </RouterLink>
          </div>

          <ul v-else>
            <li
              v-for="pedido in pedidosAMostrar"
              :key="pedido.id"
              class="border-b border-borde-sutil px-4 last:border-0"
            >
              <div class="flex min-h-20 flex-wrap items-center gap-4 py-3">
                <span
                  class="flex size-9 shrink-0 items-center justify-center rounded-full"
                  :class="estilo(pedido.estado).fondo"
                  aria-hidden="true"
                >
                  <component :is="estilo(pedido.estado).icono" class="size-4" />
                </span>

                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="truncate text-sm font-semibold text-texto-primario">
                      Pedido #{{ pedido.id }}
                    </p>
                    <span
                      v-if="pedido.estado_pago === 'pagado'"
                      class="rounded-full bg-exito-50 px-2 py-0.5 text-2xs font-semibold text-exito-700 dark:bg-exito-500/15 dark:text-exito-500"
                    >
                      Pagado
                    </span>
                    <span
                      v-else-if="pedido.estado !== 'anulado'"
                      class="rounded-full bg-alerta-50 px-2 py-0.5 text-2xs font-semibold text-alerta-700 dark:bg-alerta-500/15 dark:text-alerta-500"
                    >
                      Pago pendiente
                    </span>
                  </div>
                  <p class="text-xs text-texto-secundario">
                    {{ formatearFecha(pedido.fecha || pedido.fecha_pedido) }} · {{ etiquetaModalidad(pedido.observacion) }}
                  </p>
                </div>

                <div class="flex items-center gap-3">
                  <RouterLink
                    v-if="pedidoRequierePago(pedido)"
                    :to="{ path: '/checkout/pago', query: { pedido: String(pedido.id) } }"
                    class="inline-flex min-h-9 items-center gap-1.5 rounded-full bg-botica-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-botica-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
                  >
                    <CreditCard class="size-3.5" aria-hidden="true" />
                    Pagar
                  </RouterLink>

                  <div class="text-right">
                    <p class="cifras-tabulares text-sm font-semibold text-texto-primario">
                      {{ formatearMoneda(pedido.total) }}
                    </p>
                    <span class="text-xs font-medium" :class="estilo(pedido.estado).texto">
                      {{ estilo(pedido.estado).rotulo }}
                    </span>
                  </div>
                </div>
              </div>

              <div v-if="pedido.requiere_receta" class="pb-4">
                <button
                  type="button"
                  class="flex w-full items-center gap-2 rounded-lg px-1 py-1.5 text-left transition-colors hover:bg-superficie-hundida"
                  :aria-expanded="recetaAbierta === pedido.id"
                  @click="alternarReceta(pedido)"
                >
                  <DistintivoReceta :estado="(pedido.receta_estado as EstadoReceta | null) ?? null" compacto />
                  <span class="text-xs text-texto-secundario">
                    {{ recetaAbierta === pedido.id ? 'Ocultar' : 'Gestionar receta' }}
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
        </div>
      </section>

      <section class="lg:col-span-2" aria-labelledby="titulo-comprobantes">
        <div class="flex items-center justify-between gap-2">
          <h2 id="titulo-comprobantes" class="text-lg font-bold text-texto-primario">
            Últimos comprobantes
          </h2>
          <RouterLink
            to="/client/comprobantes"
            class="inline-flex items-center gap-1 text-sm font-semibold text-texto-marca hover:underline"
          >
            Ver todos
            <ArrowRight class="size-4" aria-hidden="true" />
          </RouterLink>
        </div>

        <div class="mt-3 overflow-hidden rounded-2xl border border-borde-sutil bg-superficie-elevada">
          <ul v-if="cargando">
            <li
              v-for="n in 3"
              :key="n"
              class="flex h-16 items-center gap-3 border-b border-borde-sutil px-4 last:border-0"
            >
              <div class="h-3 w-full animate-pulse rounded bg-superficie-hundida" />
            </li>
          </ul>

          <p
            v-else-if="comprobantes.length === 0"
            class="flex h-32 flex-col items-center justify-center gap-2 px-4 text-center text-sm text-texto-secundario"
          >
            <FileText class="size-7 text-texto-terciario" aria-hidden="true" />
            Aquí aparecerán tus boletas y facturas.
          </p>

          <ul v-else>
            <li
              v-for="c in comprobantes"
              :key="c.id"
              class="flex min-h-16 items-center justify-between gap-3 border-b border-borde-sutil px-4 py-2 last:border-0"
            >
              <div class="min-w-0">
                <p class="cifras-tabulares truncate text-sm font-semibold text-texto-primario">
                  {{ c.correlativo }}
                </p>
                <p class="cifras-tabulares text-xs text-texto-secundario">
                  {{ formatearFecha(c.fecha_emision) }} · {{ formatearMoneda(c.total) }}
                </p>
              </div>

              <button
                type="button"
                :disabled="descargandoPdfId === c.id"
                class="inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-full border border-borde-control px-3.5 py-2 text-xs font-semibold text-texto-primario transition hover:bg-superficie-interactiva disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
                @click="descargarPdf(c)"
              >
                <LoaderCircle
                  v-if="descargandoPdfId === c.id"
                  class="size-3.5 animate-spin"
                  aria-hidden="true"
                />
                <Download v-else class="size-3.5" aria-hidden="true" />
                <span aria-hidden="true">{{ descargandoPdfId === c.id ? 'Descargando…' : 'PDF' }}</span>
                <span class="sr-only">Descargar el comprobante {{ c.correlativo }} en PDF</span>
              </button>
            </li>
          </ul>
        </div>

        <p
          v-if="errorPdf"
          class="mt-3 flex items-start gap-2 rounded-xl bg-peligro-50 px-3.5 py-2.5 text-xs text-peligro-700 dark:bg-peligro-500/10 dark:text-peligro-500"
          role="alert"
        >
          <AlertCircle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
          {{ errorPdf }}
        </p>
      </section>
    </div>

    <p
      v-if="error"
      class="flex items-start gap-2 rounded-xl bg-peligro-50 px-4 py-3 text-sm text-peligro-700 dark:bg-peligro-500/10 dark:text-peligro-500"
      role="alert"
    >
      <AlertCircle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
      {{ error }}
    </p>
  </section>
</template>
