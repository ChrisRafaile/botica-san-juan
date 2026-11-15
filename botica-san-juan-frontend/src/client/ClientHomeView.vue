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

        <!--
          El portal se quedó sin salida al reescribirlo: la versión anterior
          tenía «Cerrar Sesión» y se perdió. Un panel al que se entra y del
          que no se puede salir obliga a escribir la URL a mano.
        -->
        <div class="flex flex-wrap items-center gap-2">
          <RouterLink
            v-if="carrito.totalUnidades > 0"
            to="/cart"
            class="inline-flex min-h-11 items-center gap-2 rounded-full bg-botica-700 px-4 py-2.5 text-sm font-semibold text-neutro-0 transition hover:bg-botica-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
          >
            <ShoppingCart class="size-4" aria-hidden="true" />
            Finalizar compra ({{ carrito.totalUnidades }})
          </RouterLink>

          <RouterLink
            to="/products"
            class="inline-flex min-h-11 items-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
            :class="carrito.totalUnidades > 0
              ? 'border border-borde-control text-texto-primario hover:bg-superficie-interactiva'
              : 'bg-botica-700 text-neutro-0 hover:bg-botica-800'"
          >
            <Search class="size-4" aria-hidden="true" />
            Ver catálogo
          </RouterLink>

          <button
            type="button"
            class="inline-flex min-h-11 items-center gap-2 rounded-full border border-borde-control px-4 py-2.5 text-sm font-semibold text-texto-primario transition hover:bg-superficie-interactiva focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
            @click="confirmandoSalida = true"
          >
            <LogOut
              class="size-4"
              aria-hidden="true"
            />
            Cerrar sesión
          </button>
        </div>
      </header>

      <DialogoConfirmacion
        :abierto="confirmandoSalida"
        titulo="¿Cerrar sesión?"
        texto="Se cerrará tu sesión en este equipo. Tu carrito se conserva."
        etiqueta-confirmar="Cerrar sesión"
        etiqueta-cancelar="Seguir trabajando"
        peligroso
        id="salir-cliente"
        @confirmar="salir"
        @cancelar="confirmandoSalida = false"
      />

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
                class="border-b border-borde-sutil px-4 last:border-0"
              >
                <div class="flex min-h-20 flex-wrap items-center gap-4 py-3">
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
                      {{ fecha(pedido.fecha) }} · {{ etiquetaModalidad(pedido.observacion) }}
                    </p>
                  </div>

                  <div class="flex items-center gap-3">
                    <RouterLink
                      v-if="pedido.estado_pago !== 'pagado' && pedido.estado !== 'anulado'"
                      :to="{ path: '/checkout/pago', query: { pedido: String(pedido.id) } }"
                      class="inline-flex min-h-9 items-center gap-1.5 rounded-full bg-botica-600 px-3 py-1.5 text-xs font-semibold text-neutro-0 transition hover:bg-botica-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
                    >
                      <CreditCard class="size-3.5" aria-hidden="true" />
                      Pagar
                    </RouterLink>

                    <div class="text-right">
                      <p class="cifras-tabulares text-sm font-semibold text-texto-primario">
                        {{ dinero(Number(pedido.total)) }}
                      </p>
                      <span
                        class="text-xs font-medium"
                        :class="estilo(pedido.estado).texto"
                      >
                        {{ estilo(pedido.estado).rotulo }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Receta. Solo en los pedidos que la necesitan: un bloque
                     que diga «no necesita receta» obliga a leer para
                     descubrir que no hay nada que hacer.

                     Plegado por omisión salvo cuando la pelota está en el
                     tejado del cliente —falta adjuntarla o se la rechazaron—,
                     que es justo cuando no debe pasar desapercibida. -->
                <div
                  v-if="pedido.requiere_receta"
                  class="pb-4"
                >
                  <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-lg px-1 py-1.5 text-left transition-colors hover:bg-superficie-hundida"
                    :aria-expanded="recetaAbierta === pedido.id"
                    @click="alternarReceta(pedido)"
                  >
                    <DistintivoReceta
                      :estado="pedido.receta_estado ?? null"
                      compacto
                    />
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
                class="flex min-h-16 items-center justify-between gap-3 border-b border-borde-sutil px-4 py-2 last:border-0"
              >
                <div class="min-w-0">
                  <p class="cifras-tabulares truncate text-sm font-semibold text-texto-primario">
                    {{ c.correlativo }}
                  </p>
                  <p class="cifras-tabulares text-xs text-texto-secundario">
                    {{ fecha(c.fecha_emision) }} · {{ dinero(c.total) }}
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
                  <Download
                    v-else
                    class="size-3.5"
                    aria-hidden="true"
                  />
                  <!-- El texto accesible dice CUÁL se descarga: cinco botones
                       que solo pongan «PDF» son indistinguibles para un lector
                       de pantalla. -->
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
import { RouterLink, useRouter } from 'vue-router'
import {
  AlertCircle,
  CheckCircle2,
  Clock,
  CreditCard,
  Download,
  FileText,
  LoaderCircle,
  LogOut,
  PackageCheck,
  PackageOpen,
  Receipt,
  Search,
  ShoppingCart,
  XCircle,
} from 'lucide-vue-next'

import api from '@/services/api'
import { descargarPdfComprobante } from '@/services/comprobantes'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/carrito'
import TarjetaResumen from './components/TarjetaResumen.vue'
import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'
import DistintivoReceta from '@/components/DistintivoReceta.vue'
import RecetaDelPedido from '@/components/RecetaDelPedido.vue'
import type { EstadoReceta } from '@/services/recetas'

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
  estado_pago?: string | null
  observacion?: string | null
  total: number | string
  /** Lo calcula el servidor sobre los productos del pedido. */
  requiere_receta?: boolean
  /** `null` o ausente si todavía no se ha adjuntado ninguna. */
  receta_estado?: EstadoReceta | null
}

interface ComprobanteFila {
  id: number
  correlativo: string
  total: number
  fecha_emision: string
}

const router = useRouter()
const auth = useAuthStore()
const carrito = useCartStore()

const resumen = ref<Resumen | null>(null)
const pedidos = ref<PedidoFila[]>([])

/* Qué receta está desplegada. Uno a la vez: con varias abiertas la lista se
   vuelve una columna de formularios y se pierde de vista a qué pedido
   pertenece cada uno. */
const recetaAbierta = ref<number | null>(null)

function alternarReceta(pedido: PedidoFila) {
  recetaAbierta.value = recetaAbierta.value === pedido.id ? null : pedido.id
}
const comprobantes = ref<ComprobanteFila[]>([])
const cargando = ref(true)
const confirmandoSalida = ref(false)
const error = ref('')
const descargandoPdfId = ref<number | null>(null)
const errorPdf = ref('')

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
    rotulo: 'Anulado',
    fondo: 'bg-peligro-50 text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-500',
    texto: 'text-peligro-700 dark:text-peligro-500',
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

/* La salida pasa por /logout, igual que en la cabecera publica y en el panel
   de administracion: un solo sitio donde se cierra la sesion. */
const salir = () => {
  confirmandoSalida.value = false
  router.push('/logout')
}

const etiquetaModalidad = (observacion?: string | null) => {
  const texto = (observacion ?? '').trim()
  if (!texto || texto === 'Recojo en botica') return 'Recojo en botica'
  return `Envío: ${texto}`
}

/* La descarga va por Axios adjuntando el token Bearer para generar un Blob en el
   navegador: un enlace directo sin cabecera de autorizacion fallaba con 401. */
async function descargarPdf(comprobante: ComprobanteFila) {
  if (descargandoPdfId.value !== null) return
  descargandoPdfId.value = comprobante.id
  errorPdf.value = ''

  try {
    await descargarPdfComprobante(comprobante.id, comprobante.correlativo)
  } catch {
    errorPdf.value = `No pudimos descargar el comprobante ${comprobante.correlativo}. Inténtalo nuevamente.`
  } finally {
    descargandoPdfId.value = null
  }
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
