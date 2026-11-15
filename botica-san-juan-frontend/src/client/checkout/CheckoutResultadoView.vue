<template>
  <main
    class="flex min-h-screen items-center justify-center bg-superficie-fondo bg-linear-to-br from-clinico-50 to-clinico-100 px-4 py-12 text-texto-primario dark:from-superficie-fondo dark:to-superficie-hundida"
  >
    <section
      class="w-full max-w-xl rounded-2xl border border-borde-sutil bg-superficie-elevada/95 p-10 shadow-xl backdrop-blur-sm"
      aria-labelledby="titulo-resultado"
    >
      <div
        v-if="cargando"
        class="text-center text-texto-terciario"
        aria-live="polite"
      >
        Verificando el estado de tu pedido…
      </div>

      <template v-else>
        <div class="text-center">
          <div
            class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full"
            :class="aspecto.fondoIcono"
          >
            <svg
              class="h-10 w-10"
              :class="aspecto.colorIcono"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <polyline
                v-if="visual === 'PAYMENT_SUCCESS'"
                class="trazo"
                points="4 12.5 9.5 18 20 6.5"
              />
              <g v-else-if="visual === 'PAYMENT_ERROR'">
                <line x1="6" y1="6" x2="18" y2="18" />
                <line x1="18" y1="6" x2="6" y2="18" />
              </g>
              <g v-else>
                <circle cx="12" cy="12" r="9" />
                <line x1="12" y1="7.5" x2="12" y2="12.5" />
                <line x1="12" y1="16" x2="12" y2="16.2" />
              </g>
            </svg>
          </div>

          <h1
            id="titulo-resultado"
            class="text-2xl font-bold text-texto-primario mb-2"
            aria-live="polite"
          >
            {{ aspecto.titulo }}
          </h1>
          <p class="text-texto-secundario">
            {{ aspecto.detalle }}
          </p>
        </div>

        <dl
          v-if="pago"
          class="mt-8 divide-y divide-borde-sutil rounded-xl border border-borde-sutil bg-superficie-hundida/60"
        >
          <div class="flex justify-between px-5 py-3">
            <dt class="text-sm text-texto-terciario">
              Pedido
            </dt>
            <dd class="text-sm font-semibold text-texto-primario">
              #{{ pedidoId }}
            </dd>
          </div>
          <div class="flex justify-between px-5 py-3">
            <dt class="text-sm text-texto-terciario">
              Referencia
            </dt>
            <dd class="text-sm font-mono text-texto-primario">
              {{ pago.referencia }}
            </dd>
          </div>
          <div class="flex justify-between px-5 py-3">
            <dt class="text-sm text-texto-terciario">
              Total
            </dt>
            <dd class="cifras-tabulares text-sm font-semibold text-texto-primario">
              {{ formatearMonto(pago.monto, pago.moneda) }}
            </dd>
          </div>
          <div
            v-if="comprobante"
            class="flex justify-between px-5 py-3"
          >
            <dt class="text-sm text-texto-terciario">
              {{ comprobante.tipo === 'factura' ? 'Factura electrónica' : 'Boleta de venta' }}
            </dt>
            <dd class="cifras-tabulares text-sm font-semibold text-texto-primario">
              {{ comprobante.identificador }}
            </dd>
          </div>
          <div
            v-if="visual === 'PAYMENT_SUCCESS'"
            class="flex justify-between px-5 py-3"
          >
            <dt class="text-sm text-texto-terciario">
              Medio de pago
            </dt>
            <dd class="text-sm text-texto-primario">
              {{ describirMedio(pago) }}
            </dd>
          </div>
          <div
            v-if="pago.pagado_en"
            class="flex justify-between px-5 py-3"
          >
            <dt class="text-sm text-texto-terciario">
              Fecha
            </dt>
            <dd class="text-sm text-texto-primario">
              {{ pago.pagado_en }}
            </dd>
          </div>
        </dl>

        <p
          v-if="errorPdf"
          class="mt-4 rounded-xl bg-peligro-50 px-4 py-2.5 text-center text-xs text-peligro-700 dark:bg-peligro-500/10 dark:text-peligro-500"
          role="alert"
        >
          {{ errorPdf }}
        </p>

        <div class="mt-8 flex flex-col flex-wrap gap-3 sm:flex-row sm:justify-center">
          <button
            v-if="visual === 'PAYMENT_SUCCESS' && comprobante?.id"
            type="button"
            :disabled="descargandoPdf"
            class="foco-dentro inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-borde-control bg-superficie-base px-5 py-3 text-center text-sm font-semibold text-texto-primario transition hover:bg-superficie-interactiva disabled:opacity-60"
            @click="descargarPdf"
          >
            <LoaderCircle v-if="descargandoPdf" class="size-4 animate-spin" aria-hidden="true" />
            <Download v-else class="size-4" aria-hidden="true" />
            {{ descargandoPdf ? 'Descargando…' : `Descargar ${comprobante.tipo === 'factura' ? 'Factura' : 'Boleta'} PDF` }}
          </button>

          <RouterLink
            v-if="visual === 'PAYMENT_SUCCESS'"
            to="/client/home"
            class="foco-dentro min-h-11 rounded-xl bg-botica-600 px-6 py-3 text-center text-sm font-semibold text-neutro-0 shadow-lg shadow-botica-600/20 transition hover:bg-botica-700"
          >
            Ver mis pedidos
          </RouterLink>

          <button
            v-if="visual === 'PAYMENT_ERROR' || visual === 'PAYMENT_CANCELLED'"
            type="button"
            class="foco-dentro min-h-11 rounded-xl bg-clinico-600 px-6 py-3 text-sm font-semibold text-neutro-0 shadow-lg shadow-clinico-600/20 transition hover:bg-clinico-700"
            @click="reintentar"
          >
            Intentar nuevamente
          </button>

          <RouterLink
            to="/products"
            class="foco-dentro min-h-11 rounded-xl border border-borde-sutil bg-superficie-elevada px-6 py-3 text-center text-sm font-medium text-texto-secundario transition hover:bg-superficie-hundida"
          >
            Continuar comprando
          </RouterLink>
        </div>
      </template>
    </section>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { Download, LoaderCircle } from 'lucide-vue-next'
import { descargarPdfComprobante } from '@/services/comprobantes'
import {
  consultarEstado,
  describirMedio,
  formatearMonto,
  aEstadoVisual,
  rutaDelEstado,
  type ComprobanteResumenPago,
  type EstadoVisual,
  type Pago,
} from '@/services/pagos'

const route = useRoute()
const router = useRouter()

const cargando = ref(true)
const pago = ref<Pago | null>(null)
const pedidoId = ref<number | null>(null)
const comprobante = ref<ComprobanteResumenPago | null>(null)
const descargandoPdf = ref(false)
const errorPdf = ref('')

/**
 * El estado visual proviene siempre de lo que responde el backend, nunca de la
 * ruta por la que se llego. Navegar a mano a /checkout/exitoso no convierte un
 * pedido en pagado.
 */
const visual = computed<EstadoVisual>(() => aEstadoVisual(pago.value?.estado))

const ASPECTOS: Record<EstadoVisual, { titulo: string; detalle: string; fondoIcono: string; colorIcono: string }> = {
  PAYMENT_SUCCESS: {
    titulo: '¡Compra realizada con éxito!',
    detalle: 'Tu pedido quedó confirmado y tu comprobante electrónico ya está listo.',
    fondoIcono: 'bg-botica-50 dark:bg-botica-500/15',
    colorIcono: 'text-botica-600 dark:text-botica-300',
  },
  PAYMENT_ERROR: {
    titulo: 'El pago no pudo completarse',
    detalle: 'Tu banco o proveedor de pagos rechazó la transacción. No se realizó ningún cargo.',
    fondoIcono: 'bg-peligro-50 dark:bg-peligro-500/15',
    colorIcono: 'text-peligro-600 dark:text-peligro-500',
  },
  PAYMENT_CANCELLED: {
    titulo: 'Cancelaste el pago',
    detalle: 'Tu pedido sigue guardado. Puedes retomarlo cuando quieras.',
    fondoIcono: 'bg-ambar-50 dark:bg-ambar-500/15',
    colorIcono: 'text-ambar-600 dark:text-ambar-400',
  },
  PAYMENT_PENDING: {
    titulo: 'Tu pago está pendiente',
    detalle: 'Algunos medios de pago requieren que completes una operación adicional. Te avisaremos al confirmarse.',
    fondoIcono: 'bg-ambar-50 dark:bg-ambar-500/15',
    colorIcono: 'text-ambar-600 dark:text-ambar-400',
  },
  PAYMENT_PROCESSING: {
    titulo: 'Seguimos verificando tu pago',
    detalle: 'Aún no tenemos una respuesta definitiva del proveedor.',
    fondoIcono: 'bg-clinico-50 dark:bg-clinico-500/15',
    colorIcono: 'text-clinico-600 dark:text-clinico-300',
  },
  PAYMENT_REFUNDED: {
    titulo: 'Pago reembolsado',
    detalle: 'El importe fue devuelto a tu medio de pago original.',
    fondoIcono: 'bg-superficie-interactiva',
    colorIcono: 'text-texto-secundario',
  },
}

const aspecto = computed(() => ASPECTOS[visual.value])

function reintentar(): void {
  if (pedidoId.value) {
    void router.push({ path: '/checkout/pago', query: { pedido: String(pedidoId.value) } })
  }
}

async function descargarPdf(): Promise<void> {
  if (!comprobante.value?.id || descargandoPdf.value) return
  descargandoPdf.value = true
  errorPdf.value = ''

  try {
    await descargarPdfComprobante(comprobante.value.id, comprobante.value.identificador)
  } catch {
    errorPdf.value = 'No pudimos descargar el PDF ahora. También lo encontrarás en Mis comprobantes.'
  } finally {
    descargandoPdf.value = false
  }
}

onMounted(async () => {
  const referencia = String(route.query.ref ?? '')

  if (!referencia) {
    await router.replace('/')
    return
  }

  try {
    const datos = await consultarEstado(referencia)
    pago.value = datos.pago
    pedidoId.value = datos.pedido_id
    comprobante.value = datos.comprobante ?? null

    // Si la ruta no corresponde al estado real, se corrige la URL.
    const rutaCorrecta = rutaDelEstado(datos.pago.estado)
    if (route.path !== rutaCorrecta) {
      await router.replace({ path: rutaCorrecta, query: { ref: referencia } })
    }
  } catch {
    await router.replace('/')
  } finally {
    cargando.value = false
  }
})
</script>

<style scoped>
.trazo {
  stroke-dasharray: 32;
  stroke-dashoffset: 32;
  animation: trazar 480ms ease-out 120ms forwards;
}

@media (prefers-reduced-motion: reduce) {
  .trazo {
    animation: none;
    stroke-dashoffset: 0;
  }
}

@keyframes trazar {
  to {
    stroke-dashoffset: 0;
  }
}
</style>
