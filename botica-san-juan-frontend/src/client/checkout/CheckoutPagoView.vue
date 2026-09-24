<template>
  <main class="min-h-screen bg-linear-to-br from-blue-50 to-indigo-100 px-4 py-10">
    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-5">
      <!-- Resumen del pedido -->
      <section
        class="lg:col-span-3 rounded-2xl bg-white/90 p-8 shadow-xl backdrop-blur-sm"
        aria-labelledby="titulo-resumen"
      >
        <h1
          id="titulo-resumen"
          class="mb-6 text-2xl font-bold text-gray-800"
        >
          Resumen de tu pedido
        </h1>

        <p
          v-if="cargando"
          class="text-gray-500"
          aria-live="polite"
        >
          Cargando el pedido…
        </p>

        <template v-else-if="pedido">
          <ul class="divide-y divide-gray-100">
            <li
              v-for="linea in pedido.pedido_detalles"
              :key="linea.id"
              class="flex items-start justify-between gap-4 py-3"
            >
              <div>
                <p class="font-medium text-gray-800">
                  {{ linea.producto?.nombre ?? 'Producto' }}
                </p>
                <p class="text-sm text-gray-500">
                  {{ linea.cantidad }} × {{ formatearMonto(Number(linea.precio_unitario ?? linea.precio), moneda) }}
                </p>
              </div>
              <p class="whitespace-nowrap font-semibold text-gray-800">
                {{ formatearMonto(Number(linea.subtotal ?? 0), moneda) }}
              </p>
            </li>
          </ul>

          <dl class="mt-6 space-y-2 border-t border-gray-100 pt-5">
            <div class="flex justify-between text-sm text-gray-600">
              <dt>Subtotal</dt>
              <dd>{{ formatearMonto(baseImponible, moneda) }}</dd>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
              <dt>IGV (18 %)</dt>
              <dd>{{ formatearMonto(igv, moneda) }}</dd>
            </div>
            <div class="flex justify-between border-t border-gray-100 pt-3 text-lg font-bold text-gray-900">
              <dt>Total</dt>
              <dd>{{ formatearMonto(total, moneda) }}</dd>
            </div>
          </dl>

          <p
            v-if="pedido.address"
            class="mt-6 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600"
          >
            <span class="font-medium text-gray-700">Entrega:</span> {{ pedido.address }}
          </p>
        </template>
      </section>

      <!-- Pago -->
      <section
        class="lg:col-span-2 rounded-2xl bg-white/90 p-8 shadow-xl backdrop-blur-sm"
        aria-labelledby="titulo-pago"
      >
        <h2
          id="titulo-pago"
          class="mb-2 text-xl font-bold text-gray-800"
        >
          Pago seguro
        </h2>
        <p class="mb-6 text-sm text-gray-600">
          Los datos de tu tarjeta se ingresan directamente en el entorno de la
          pasarela. Botica San Juan no los recibe ni los almacena.
        </p>

        <!--
          Contenedor del formulario del proveedor.

          Ningún campo de tarjeta, código de verificación ni vencimiento
          pertenece a esta aplicación: el proveedor los dibuja dentro de marcos
          aislados servidos desde su propio dominio, de modo que este código no
          puede leerlos aunque lo intentara.
        -->
        <div
          v-show="formularioMontado"
          id="izipay-container"
        >
          <div class="kr-smart-form" />
        </div>

        <div
          v-if="!formularioMontado"
          class="min-h-[8rem] rounded-xl border border-dashed border-gray-200 bg-gray-50/70 p-4 text-center text-sm text-gray-500"
          aria-live="polite"
        >
          <template v-if="modoSimulado">
            Modo simulado: aquí se monta el formulario de la pasarela cuando hay
            credenciales configuradas.
          </template>
          <template v-else-if="procesando">
            Cargando el formulario de pago…
          </template>
          <template v-else>
            Pulsa el botón para continuar con el pago.
          </template>
        </div>

        <p
          v-if="error"
          class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700"
          role="alert"
          aria-live="assertive"
        >
          {{ error }}
        </p>

        <button
          v-if="!formularioMontado"
          type="button"
          class="mt-6 w-full rounded-xl bg-linear-to-r from-blue-600 to-indigo-600 px-6 py-3 font-medium text-white shadow-lg shadow-blue-600/20 transition hover:from-blue-700 hover:to-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="procesando || cargando"
          @click="pagar"
        >
          {{ procesando ? 'Preparando el pago…' : `Pagar ${formatearMonto(total, moneda)}` }}
        </button>

        <p class="mt-4 flex items-center justify-center gap-2 text-xs text-gray-500">
          <svg
            class="h-4 w-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
          >
            <rect x="4" y="10" width="16" height="10" rx="2" />
            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
          </svg>
          Conexión cifrada · No almacenamos datos de tu tarjeta
        </p>
      </section>
    </div>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { montarFormulario, type RetornoVerificable } from '@/services/izipay'
import { formatearMonto, iniciarPago, validarRetorno, rutaDelEstado } from '@/services/pagos'

interface LineaPedido {
  id: number
  cantidad: number
  precio: string | number
  precio_unitario?: string | number
  subtotal?: string | number
  producto?: { nombre?: string }
}

interface PedidoResumen {
  id: number
  total: string | number
  moneda?: string
  address?: string | null
  estado_pago?: string
  pedido_detalles?: LineaPedido[]
}

const route = useRoute()
const router = useRouter()

const pedido = ref<PedidoResumen | null>(null)
const cargando = ref(true)
const procesando = ref(false)
const error = ref('')
const modoSimulado = ref(false)
const formularioMontado = ref(false)

let desmontarFormulario: (() => void) | null = null

const moneda = computed(() => pedido.value?.moneda ?? 'PEN')
const total = computed(() => Number(pedido.value?.total ?? 0))
const baseImponible = computed(() => Math.round((total.value / 1.18) * 100) / 100)
const igv = computed(() => Math.round((total.value - baseImponible.value) * 100) / 100)

/**
 * Encamina la interfaz una vez que el proveedor cerró la operación.
 *
 * El retorno se manda al backend para que verifique su firma, pero el estado
 * que se muestra al cliente es siempre el PERSISTIDO. Aunque alguien alterara
 * este mensaje para afirmar que pagó, la pantalla de espera seguiría
 * consultando al servidor, que solo cambia de estado con la notificación
 * servidor a servidor.
 */
async function encaminar(referencia: string, retorno?: RetornoVerificable): Promise<void> {
  if (retorno) {
    // Best effort: acorta la espera cuando la firma es válida. Si falla, la
    // pantalla de procesamiento resuelve igual por sondeo.
    await validarRetorno(retorno).catch(() => undefined)
  }

  await router.push({ path: '/checkout/procesando', query: { ref: referencia } })
}

/**
 * Inicia el cobro.
 *
 * El botón se deshabilita mientras la operación está en curso, pero esa es
 * solo una cortesía visual: la protección real contra el doble cobro está en
 * el backend, que reutiliza el pago existente del pedido en lugar de crear
 * otro.
 */
async function pagar(): Promise<void> {
  if (!pedido.value || procesando.value) return

  procesando.value = true
  error.value = ''

  try {
    const respuesta = await iniciarPago(pedido.value.id)

    if (!respuesta.ok) {
      error.value = respuesta.message ?? 'No se pudo iniciar el pago.'
      return
    }

    const referencia = respuesta.pago.referencia
    modoSimulado.value = respuesta.checkout.modo === 'simulado'

    // Sin credenciales el proveedor no entrega token de formulario. En ese caso
    // se sigue el flujo hasta la pantalla de espera, que es lo que permite
    // ejercitar la integración completa sin una cuenta comercial.
    if (modoSimulado.value || !respuesta.checkout.form_token) {
      await encaminar(referencia)
      return
    }

    desmontarFormulario = await montarFormulario({
      selector: '#izipay-container',
      configuracion: respuesta.checkout,
      alTerminar: (retorno) => { void encaminar(referencia, retorno) },
      alFallar: (mensaje) => { error.value = mensaje },
    })

    formularioMontado.value = true
  } catch {
    error.value = 'No pudimos contactar a la pasarela. Intenta nuevamente en unos segundos.'
  } finally {
    procesando.value = false
  }
}

onMounted(async () => {
  const id = Number(route.query.pedido)

  if (!id) {
    await router.replace('/')
    return
  }

  try {
    const { data } = await api.get<PedidoResumen>(`/pedidos/${id}`)
    pedido.value = data

    // Un pedido ya resuelto no vuelve a la pantalla de pago.
    if (data.estado_pago && data.estado_pago !== 'pendiente' && data.estado_pago !== 'procesando') {
      await router.replace(rutaDelEstado(data.estado_pago))
    }
  } catch {
    await router.replace('/')
  } finally {
    cargando.value = false
  }
})

onBeforeUnmount(() => {
  desmontarFormulario?.()
  desmontarFormulario = null
})
</script>
