<template>
  <main
    class="min-h-screen bg-linear-to-br from-blue-50 to-indigo-100 flex items-center justify-center px-4 py-12"
  >
    <section
      class="w-full max-w-lg bg-white/90 backdrop-blur-sm rounded-2xl shadow-xl p-10 text-center"
      aria-labelledby="titulo-procesando"
    >
      <!-- El indicador respeta prefers-reduced-motion: sin movimiento se
           sustituye por un punto estatico, no por una animacion mas lenta. -->
      <div
        class="mx-auto mb-8 h-16 w-16 rounded-full border-4 border-blue-100 border-t-blue-600 indicador"
        role="progressbar"
        aria-label="Verificando el pago"
      />

      <h1
        id="titulo-procesando"
        class="text-2xl font-bold text-gray-800 mb-3"
      >
        Procesando tu pago…
      </h1>

      <p
        class="text-gray-600 mb-2"
        aria-live="polite"
      >
        {{ mensaje }}
      </p>
      <p class="text-sm text-gray-500">
        No cierres esta ventana. Esto puede tardar unos segundos.
      </p>

      <p
        v-if="referencia"
        class="mt-8 text-xs text-gray-400"
      >
        Referencia {{ referencia }}
      </p>

      <button
        v-if="demorado"
        type="button"
        class="mt-6 text-sm font-medium text-blue-700 underline underline-offset-4 hover:text-blue-900"
        @click="verificar"
      >
        Verificar de nuevo
      </button>
    </section>
  </main>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { consultarEstado, rutaDelEstado } from '@/services/pagos'

const route = useRoute()
const router = useRouter()

const referencia = ref<string>(String(route.query.ref ?? ''))
const mensaje = ref('Estamos confirmando la transacción con tu banco.')
const demorado = ref(false)

let temporizador: number | undefined
let intentos = 0

/**
 * El backend es la fuente de verdad.
 *
 * Volver del checkout del proveedor no significa que el pago se aprobo, y
 * recargar esta pagina tampoco significa que fallo. En ambos casos se pregunta
 * al servidor.
 */
async function verificar(): Promise<void> {
  if (!referencia.value) {
    await router.replace('/checkout/error')
    return
  }

  try {
    const { pago, es_final } = await consultarEstado(referencia.value)

    if (es_final || pago.estado === 'fallido') {
      await router.replace({ path: rutaDelEstado(pago.estado), query: { ref: referencia.value } })
      return
    }

    intentos += 1

    // Espera creciente: la mayoria de los cobros resuelve en los primeros
    // segundos, y prolongar el sondeo cada dos segundos durante minutos solo
    // castiga al servidor.
    const espera = Math.min(2000 + intentos * 500, 8000)

    if (intentos >= 6) {
      demorado.value = true
      mensaje.value = 'Tu pago sigue en verificación. Puedes esperar aquí o volver más tarde a tus pedidos.'
    }

    temporizador = window.setTimeout(verificar, espera)
  } catch {
    demorado.value = true
    mensaje.value = 'No pudimos consultar el estado en este momento.'
  }
}

onMounted(verificar)
onUnmounted(() => window.clearTimeout(temporizador))
</script>

<style scoped>
.indicador {
  animation: girar 900ms linear infinite;
}

@media (prefers-reduced-motion: reduce) {
  .indicador {
    animation: none;
    border-top-color: rgb(37 99 235);
  }
}

@keyframes girar {
  to {
    transform: rotate(360deg);
  }
}
</style>
