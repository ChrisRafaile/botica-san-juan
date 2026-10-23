<!--
  Portal del cliente.
  ===========================================================================
  QUÉ SUSTITUYE

  La versión anterior eran 93 líneas con cuatro tarjetas de colores que no
  llevaban a ninguna parte y una rejilla de «productos disponibles» con texto
  de relleno. Parecía un panel y no mostraba ni un dato real del cliente.

  DE DÓNDE SALE CADA CIFRA, Y POR QUÉ IMPORTA

  · Pedidos, gasto y comprobantes vienen de `GET /api/pedidos/mi-resumen`,
    agregados en SQL. No se cuentan aquí sobre la lista recibida: esa es la
    quinta repetición del error que produjo el «2 en stock» de la pantalla de
    productos, donde la vista contaba su página y la presentaba como total.

  · El carrito lo sabe el almacén del navegador y NO el servidor. En el portal
    el carrito vive en `localStorage` —para que se pueda armar sin cuenta— y
    el servidor no conoce su contenido hasta que se pide una cotización. Así
    que esa tarjeta se alimenta del store, que es quien tiene la verdad.

  SOBRE EL SALTO DE MAQUETACIÓN

  Las tarjetas y las filas del historial reservan su altura antes de que
  lleguen los datos, con un esqueleto del mismo tamaño que el contenido
  final. Sin eso, la página entera salta cuando responde la API, que es
  exactamente lo que llevó el CLS del catálogo a 0.44.
-->
<template>
  <main class="min-h-dvh bg-superficie-base px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">
      <!-- Saludo -->
      <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-texto-primario sm:text-3xl">
            Hola, {{ nombreCorto }}
          </h1>
          <p class="mt-1 flex items-center gap-2 text-sm text-texto-secundario">
            <span
              class="inline-flex items-center rounded-full bg-botica-50 px-2.5 py-0.5 text-xs font-semibold text-texto-marca"
            >
              {{ rol }}
            </span>
            Tu actividad en Botica San Juan
          </p>
        </div>

        <RouterLink
          to="/products"
          class="inline-flex items-center gap-2 rounded-full bg-botica-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-botica-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
        >
          <Search class="size-4" />
          Ver catálogo
        </RouterLink>
      </header>

      <!-- Tarjetas de resumen -->
      <section
        class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        aria-label="Resumen de tu actividad"
      >
        <TarjetaResumen
          :cargando="cargando"
          etiqueta="Pedidos en curso"
          :valor="resumen?.pedidos.abiertos ?? 0"
          detalle="Pendientes o confirmados"
          :icono="Clock"
        />
        <TarjetaResumen
          :cargando="cargando"
          etiqueta="Pedidos completados"
          :valor="resumen?.pedidos.completados ?? 0"
          detalle="Entregados"
          :icono="PackageCheck"
        />
        <TarjetaResumen
          :cargando="cargando"
          etiqueta="Total comprado"
          :valor="dinero(resumen?.gasto.total ?? 0)"
          :detalle="`${dinero(resumen?.gasto.ultimos_30_dias ?? 0)} en 30 días`"
          :icono="Receipt"
        />
        <TarjetaResumen
          :cargando="false"
          etiqueta="En tu carrito"
          :valor="carrito.totalUnidades"
          detalle="Unidades guardadas"
          :icono="ShoppingCart"
          enlace="/cart"
        />
      </section>

      <div class="mt-8 grid gap-6 lg:grid-cols-5">
        <!-- Historial de pedidos -->
        <section
          class="lg:col-span-3"
          aria-labelledby="titulo-pedidos"
        >
          <h2
            id="titulo-pedidos"
            class="text-lg font-bold text-texto-primario"
          >
            Tus últimos pedidos
          </h2>

          <div class="mt-3 overflow-hidden rounded-2xl border border-borde-sutil bg-superficie-elevada">
            <!-- Esqueleto con el alto de las filas futuras. -->
            <ul v-if="cargando">
              <li
                v-for="n in 3"
                :key="n"
                class="flex h-20 items-center gap-4 border-b border-borde-sutil px-4 last:border-0"
              >
                <div class="h-9 w-9 animate-pulse rounded-full bg-superficie-hundida" />
                <div class="flex-1 space-y-2">
                  <div class="h-3 w-24 animate-pulse rounded bg-superficie-hundida" />
                  <div class="h-3 w-40 animate-pulse rounded bg-superficie-hundida" />
                </div>
              </li>
            </ul>

            <p
              v-else-if="pedidos.length === 0"
              class="flex h-32 flex-col items-center justify-center gap-2 px-4 text-center text-sm text-texto-secundario"
            >
              <PackageOpen
                class="size-7 text-texto-terciario"
                aria-hidden="true"
              />
              Todavía no tienes pedidos. Cuando hagas el primero, aparecerá aquí.
            </p>

            <ul v-else>
              <li
                v-for="pedido in pedidos"
                :key="pedido.id"
                class="flex h-20 items-center gap-4 border-b border-borde-sutil px-4 last:border-0"
              >
                <span
                  class="flex size-9 shrink-0 items-center justify-center rounded-full"
                  :class="estilo(pedido.estado).fondo"
                  aria-hidden="true"
                >
                  <component
                    :is="estilo(pedido.estado).icono"
                    class="size-4"
                  />
                </span>

                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-semibold text-texto-primario">
                    Pedido #{{ pedido.id }}
                  </p>
                  <p class="text-xs text-texto-secundario">
                    {{ fecha(pedido.fecha) }}
                  </p>
                </div>

                <div class="text-right">
                  <p class="text-sm font-semibold text-texto-primario">
                    {{ dinero(Number(pedido.total)) }}
                  </p>
                  <span
                    class="text-xs font-medium"
                    :class="estilo(pedido.estado).texto"
                  >
                    {{ estilo(pedido.estado).rotulo }}
                  </span>
                </div>
              </li>
            </ul>
          </div>
        </section>

        <!-- Comprobantes -->
        <section
          class="lg:col-span-2"
          aria-labelledby="titulo-comprobantes"
        >
          <h2
            id="titulo-comprobantes"
            class="text-lg font-bold text-texto-primario"
          >
            Tus comprobantes
          </h2>

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
              <FileText
                class="size-7 text-texto-terciario"
                aria-hidden="true"
              />
              Aquí aparecerán tus boletas y facturas.
            </p>

            <ul v-else>
              <li
                v-for="c in comprobantes"
                :key="c.id"
                class="flex h-16 items-center justify-between gap-3 border-b border-borde-sutil px-4 last:border-0"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-texto-primario">
                    {{ c.correlativo }}
                  </p>
                  <p class="text-xs text-texto-secundario">
                    {{ fecha(c.fecha_emision) }} · {{ dinero(c.total) }}
                  </p>
                </div>

                <a
                  :href="urlPdf(c.id)"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-borde-control px-3 py-1.5 text-xs font-semibold text-texto-primario transition hover:bg-superficie-interactiva focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
                >
                  <Download
                    class="size-3.5"
                    aria-hidden="true"
                  />
                  <!-- El texto accesible dice CUÁL se descarga: cinco enlaces
                       que solo pongan «PDF» son indistinguibles en la lista de
                       enlaces de un lector de pantalla. -->
                  <span aria-hidden="true">PDF</span>
                  <span class="sr-only">Descargar el comprobante {{ c.correlativo }} en PDF</span>
                </a>
              </li>
            </ul>
          </div>
        </section>
      </div>

      <!-- Un fallo de carga se dice, no se deja en blanco. -->
      <p
        v-if="error"
        class="mt-6 flex items-start gap-2 rounded-xl bg-peligro-50 px-4 py-3 text-sm text-peligro-700 dark:bg-peligro-500/10 dark:text-peligro-500"
        role="alert"
      >
        <AlertCircle class="mt-0.5 size-4 shrink-0" />
        {{ error }}
      </p>
    </div>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import type { Component } from 'vue'
import { RouterLink } from 'vue-router'
import {
  AlertCircle,
  CheckCircle2,
  Clock,
  Download,
  FileText,
  PackageCheck,
  PackageOpen,
  Receipt,
  Search,
  ShoppingCart,
  XCircle,
} from 'lucide-vue-next'

import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/carrito'
import TarjetaResumen from './components/TarjetaResumen.vue'

interface Resumen {
  pedidos: { abiertos: number; completados: number; anulados: number; total: number }
  gasto: { total: number; ultimos_30_dias: number }
  ultimo_pedido: { id: number; fecha: string; estado: string; total: number } | null
  comprobantes: number
}

interface PedidoFila {
  id: number
  fecha: string
  estado: string
  total: number | string
}

interface ComprobanteFila {
  id: number
  correlativo: string
  total: number
  fecha_emision: string
}

const auth = useAuthStore()
const carrito = useCartStore()

const resumen = ref<Resumen | null>(null)
const pedidos = ref<PedidoFila[]>([])
const comprobantes = ref<ComprobanteFila[]>([])
const cargando = ref(true)
const error = ref('')

const ROLES: Record<string, string> = {
  administrador: 'Administrador',
  vendedor: 'Vendedor',
  cliente: 'Cliente',
}

const nombreCorto = computed(() => auth.user?.nombre?.split(' ')[0] ?? 'cliente')
const rol = computed(() => ROLES[auth.user?.rol ?? ''] ?? 'Cliente')

const dinero = (valor: number) =>
  new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(valor)

const fecha = (valor: string) => {
  if (!valor) return ''
  const d = new Date(valor)

  return Number.isNaN(d.getTime())
    ? ''
    : d.toLocaleDateString('es-PE', { day: '2-digit', month: 'short', year: 'numeric' })
}

/** El color NO es el único portador del estado: va acompañado de su rótulo. */
interface EstiloEstado {
  rotulo: string
  fondo: string
  texto: string
  icono: Component
}

const ESTADOS: Record<string, EstiloEstado> = {
  pendiente: {
    rotulo: 'Pendiente',
    fondo: 'bg-alerta-50 text-alerta-700',
    texto: 'text-alerta-700',
    icono: Clock,
  },
  confirmado: {
    rotulo: 'Confirmado',
    fondo: 'bg-botica-50 text-texto-marca',
    texto: 'text-texto-marca',
    icono: PackageCheck,
  },
  completado: {
    rotulo: 'Entregado',
    fondo: 'bg-exito-50 text-exito-700',
    texto: 'text-exito-700',
    icono: CheckCircle2,
  },
  anulado: {
    rotulo: 'Anulado',
    fondo: 'bg-peligro-50 text-peligro-700',
    texto: 'text-peligro-700',
    icono: XCircle,
  },
}

/* El valor por defecto es una constante y no `ESTADOS.pendiente`: con
   `noUncheckedIndexedAccess` el acceso por clave puede ser indefinido, de modo
   que el respaldo tambien lo seria y el tipo nunca quedaria resuelto. */
const SIN_ESTADO: EstiloEstado = {
  rotulo: 'En proceso',
  fondo: 'bg-superficie-hundida text-texto-secundario',
  texto: 'text-texto-secundario',
  icono: Clock,
}

const estilo = (estado: string): EstiloEstado => ESTADOS[estado] ?? SIN_ESTADO

/* La descarga va contra la API, que decide con la política si el comprobante
   es de quien lo pide. El enlace no es la protección: la protección está en
   el servidor. */
const urlPdf = (id: number) => {
  const base = (import.meta.env.VITE_API_URL as string | undefined) ?? '/api'

  return `${base.replace(/\/$/, '')}/facturacion/documentos/${id}/pdf`
}

onMounted(async () => {
  try {
    /* En paralelo: son tres peticiones independientes y encadenarlas
       triplicaría la espera sin ganar nada. */
    const [r, p, c] = await Promise.all([
      api.get('/pedidos/mi-resumen'),
      api.get(`/pedidos/usuario/${auth.user?.id}`),
      api.get('/comprobantes/mios'),
    ])

    resumen.value = r.data.data
    /* Solo las cinco más recientes: el portal es un resumen, no un archivo. */
    pedidos.value = (Array.isArray(p.data) ? p.data : (p.data?.data ?? []))
      .slice()
      .sort((a: PedidoFila, b: PedidoFila) => b.id - a.id)
      .slice(0, 5)
    comprobantes.value = (c.data?.data ?? []).slice(0, 5)
  } catch {
    error.value = 'No pudimos cargar tu actividad. Vuelve a intentarlo en un momento.'
  } finally {
    cargando.value = false
  }
})
</script>
