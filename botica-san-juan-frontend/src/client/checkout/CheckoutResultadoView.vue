<template>
  <main
    class="min-h-screen bg-linear-to-br from-blue-50 to-indigo-100 flex items-center justify-center px-4 py-12"
  >
    <section
      class="w-full max-w-xl bg-white/90 backdrop-blur-sm rounded-2xl shadow-xl p-10"
      aria-labelledby="titulo-resultado"
    >
      <div
        v-if="cargando"
        class="text-center text-gray-500"
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
            class="text-2xl font-bold text-gray-800 mb-2"
            aria-live="polite"
          >
            {{ aspecto.titulo }}
          </h1>
          <p class="text-gray-600">
            {{ aspecto.detalle }}
          </p>
        </div>

        <dl
          v-if="pago"
          class="mt-8 divide-y divide-gray-100 rounded-xl border border-gray-100 bg-gray-50/60"
        >
          <div class="flex justify-between px-5 py-3">
            <dt class="text-sm text-gray-500">
              Pedido
            </dt>
            <dd class="text-sm font-semibold text-gray-800">
              #{{ pedidoId }}
            </dd>
          </div>
          <div class="flex justify-between px-5 py-3">
            <dt class="text-sm text-gray-500">
              Referencia
            </dt>
            <dd class="text-sm font-mono text-gray-800">
              {{ pago.referencia }}
            </dd>
          </div>
          <div class="flex justify-between px-5 py-3">
            <dt class="text-sm text-gray-500">
              Total
            </dt>
            <dd class="text-sm font-semibold text-gray-800">
              {{ formatearMonto(pago.monto, pago.moneda) }}
            </dd>
          </div>
          <div
            v-if="visual === 'PAYMENT_SUCCESS'"
            class="flex justify-between px-5 py-3"
          >
            <dt class="text-sm text-gray-500">
              Medio de pago
            </dt>
            <dd class="text-sm text-gray-800">
              {{ describirMedio(pago) }}
            </dd>
          </div>
          <div
            v-if="pago.pagado_en"
            class="flex justify-between px-5 py-3"
          >
            <dt class="text-sm text-gray-500">
              Fecha
            </dt>
            <dd class="text-sm text-gray-800">
              {{ pago.pagado_en }}
            </dd>
          </div>
        </dl>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
          <RouterLink
            v-if="visual === 'PAYMENT_SUCCESS'"
            to="/client/home"
            class="rounded-xl bg-linear-to-r from-blue-600 to-indigo-600 px-6 py-3 text-center font-medium text-white shadow-lg shadow-blue-600/20 transition hover:from-blue-700 hover:to-indigo-700"
          >
            Ver mi pedido
          </RouterLink>

          <button
            v-if="visual === 'PAYMENT_ERROR' || visual === 'PAYMENT_CANCELLED'"
            type="button"
            class="rounded-xl bg-linear-to-r from-blue-600 to-indigo-600 px-6 py-3 font-medium text-white shadow-lg shadow-blue-600/20 transition hover:from-blue-700 hover:to-indigo-700"
            @click="reintentar"
          >
            Intentar nuevamente
          </button>

          <RouterLink
            to="/"
            class="rounded-xl border border-gray-200 bg-white px-6 py-3 text-center font-medium text-gray-700 transition hover:bg-gray-50"
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
import {
  consultarEstado,
  describirMedio,
  formatearMonto,
  aEstadoVisual,
  rutaDelEstado,
  type EstadoVisual,
  type Pago,
} from '@/services/pagos'

const route = useRoute()
const router = useRouter()

const cargando = ref(true)
const pago = ref<Pago | null>(null)
const pedidoId = ref<number | null>(null)

/**
 * El estado visual proviene siempre de lo que responde el backend, nunca de la
 * ruta por la que se llego. Navegar a mano a /checkout/exitoso no convierte un
 * pedido en pagado.
 */
const visual = computed<EstadoVisual>(() => aEstadoVisual(pago.value?.estado))

const ASPECTOS: Record<EstadoVisual, { titulo: string; detalle: string; fondoIcono: string; colorIcono: string }> = {
  PAYMENT_SUCCESS: {
    titulo: '¡Compra realizada con éxito!',
    detalle: 'Recibirás tu comprobante electrónico en unos minutos.',
    fondoIcono: 'bg-emerald-50',
    colorIcono: 'text-emerald-600',
  },
  PAYMENT_ERROR: {
    titulo: 'El pago no pudo completarse',
    detalle: 'Tu banco o proveedor de pagos rechazó la transacción. No se realizó ningún cargo.',
    fondoIcono: 'bg-red-50',
    colorIcono: 'text-red-600',
  },
  PAYMENT_CANCELLED: {
    titulo: 'Cancelaste el pago',
    detalle: 'Tu pedido sigue guardado. Puedes retomarlo cuando quieras.',
    fondoIcono: 'bg-amber-50',
    colorIcono: 'text-amber-600',
  },
  PAYMENT_PENDING: {
    titulo: 'Tu pago está pendiente',
    detalle: 'Algunos medios de pago requieren que completes una operación adicional. Te avisaremos al confirmarse.',
    fondoIcono: 'bg-amber-50',
    colorIcono: 'text-amber-600',
  },
  PAYMENT_PROCESSING: {
    titulo: 'Seguimos verificando tu pago',
    detalle: 'Aún no tenemos una respuesta definitiva del proveedor.',
    fondoIcono: 'bg-blue-50',
    colorIcono: 'text-blue-600',
  },
  PAYMENT_REFUNDED: {
    titulo: 'Pago reembolsado',
    detalle: 'El importe fue devuelto a tu medio de pago original.',
    fondoIcono: 'bg-slate-100',
    colorIcono: 'text-slate-600',
  },
}

const aspecto = computed(() => ASPECTOS[visual.value])

function reintentar(): void {
  if (pedidoId.value) {
    void router.push({ path: '/checkout/pago', query: { pedido: String(pedidoId.value) } })
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
