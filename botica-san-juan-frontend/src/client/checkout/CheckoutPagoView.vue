<template>
  <main class="min-h-screen bg-superficie-fondo bg-linear-to-br from-clinico-50 to-clinico-100 px-4 py-10 text-texto-primario dark:from-superficie-fondo dark:to-superficie-hundida">
    <nav
      aria-label="Navegación reversible del pago"
      class="mx-auto mb-6 flex max-w-5xl flex-wrap items-center justify-between gap-3 rounded-2xl border border-borde-sutil bg-superficie-elevada/90 px-4 py-3 text-sm backdrop-blur-sm"
    >
      <div class="flex flex-wrap items-center gap-2">
        <a
          href="/cart"
          class="foco-dentro inline-flex items-center gap-1.5 rounded-full border border-borde-control bg-superficie-elevada px-3.5 py-1.5 font-medium text-texto-primario transition-colors hover:bg-superficie-interactiva"
          @click.prevent="router.push('/cart')"
        >
          ← Ver mi carrito
        </a>
        <a
          href="/products"
          class="foco-dentro inline-flex items-center gap-1.5 rounded-full border border-borde-control bg-superficie-elevada px-3.5 py-1.5 font-medium text-texto-secundario transition-colors hover:bg-superficie-interactiva hover:text-texto-primario"
          @click.prevent="router.push('/products')"
        >
          Seguir comprando
        </a>
        <a
          href="/client/home"
          class="foco-dentro inline-flex items-center gap-1.5 rounded-full border border-borde-control bg-superficie-elevada px-3.5 py-1.5 font-medium text-texto-marca transition-colors hover:bg-superficie-interactiva"
          @click.prevent="router.push('/client/home')"
        >
          Ir a mi portal
        </a>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <div v-if="otrosPedidosPendientes.length > 0" class="flex items-center gap-2">
          <label for="selector-pedido-pendiente" class="text-xs font-medium text-texto-terciario">
            Cambiar de pedido:
          </label>
          <select
            id="selector-pedido-pendiente"
            :value="pedido?.id ? String(pedido.id) : ''"
            class="foco-dentro rounded-lg border border-borde-control bg-superficie-base px-2.5 py-1.5 text-xs font-semibold text-texto-primario"
            @change="cambiarPedido($event)"
          >
            <option v-if="pedido" :value="String(pedido.id)">
              Pedido #{{ pedido.id }} (actual)
            </option>
            <option
              v-for="otro in otrosPedidosPendientes"
              :key="otro.id"
              :value="String(otro.id)"
            >
              Pedido #{{ otro.id }} — {{ formatearMonto(Number(otro.total), 'PEN') }}
            </option>
          </select>
        </div>
        <SelectorTema />
      </div>

      <!-- Decirlo aquí evita el malentendido que producía pedidos duplicados:
           quien vuelve al catálogo desde el pago cree estar editando este
           pedido, y lo que hace es empezar otro. -->
      <p
        v-if="pedido"
        data-testid="aviso-pedido-registrado"
        class="basis-full text-xs leading-relaxed text-texto-terciario"
      >
        El pedido #{{ pedido.id }} ya está registrado y te espera aquí: puedes salir y
        retomar el pago desde tu carrito o desde «Mis pedidos». Lo que agregues
        ahora al carrito irá en un pedido aparte.
      </p>
    </nav>

    <div class="mx-auto grid max-w-5xl items-start gap-6 lg:grid-cols-5">
      <!-- Resumen del pedido -->
      <section
        data-testid="seccion-resumen-pedido"
        class="self-start lg:col-span-3 rounded-2xl border border-borde-sutil bg-superficie-elevada/95 p-8 shadow-xl backdrop-blur-sm"
        aria-labelledby="titulo-resumen"
      >
        <h1
          id="titulo-resumen"
          class="mb-6 text-2xl font-bold text-texto-primario"
        >
          Resumen de tu pedido
        </h1>

        <p
          v-if="cargando"
          class="text-texto-terciario"
          aria-live="polite"
        >
          Cargando el pedido…
        </p>

        <template v-else-if="pedido">
          <ul class="divide-y divide-borde-sutil">
            <li
              v-for="linea in pedido.pedido_detalles"
              :key="linea.id"
              class="flex items-start justify-between gap-4 py-3"
            >
              <div>
                <p class="font-medium text-texto-primario">
                  {{ linea.producto?.nombre ?? 'Producto' }}
                </p>
                <p class="text-sm text-texto-terciario">
                  {{ linea.cantidad }} × {{ formatearMonto(Number(linea.precio_unitario ?? linea.precio), moneda) }}
                </p>
              </div>
              <p class="whitespace-nowrap font-semibold text-texto-primario">
                {{ formatearMonto(Number(linea.subtotal ?? 0), moneda) }}
              </p>
            </li>
          </ul>

          <dl class="mt-6 space-y-2 border-t border-borde-sutil pt-5">
            <div class="flex justify-between text-sm text-texto-secundario">
              <dt>Subtotal</dt>
              <dd>{{ formatearMonto(baseImponible, moneda) }}</dd>
            </div>
            <div class="flex justify-between text-sm text-texto-secundario">
              <dt>IGV (18 %)</dt>
              <dd>{{ formatearMonto(igv, moneda) }}</dd>
            </div>
            <div class="flex justify-between border-t border-borde-sutil pt-3 text-lg font-bold text-texto-primario">
              <dt>Total</dt>
              <dd>{{ formatearMonto(total, moneda) }}</dd>
            </div>
          </dl>

          <div
            v-if="textoEntrega || etiquetaComprobante"
            class="mt-6 space-y-2 rounded-xl bg-superficie-hundida px-4 py-3 text-sm text-texto-secundario"
          >
            <p v-if="textoEntrega">
              <span class="font-medium text-texto-primario">Entrega:</span> {{ textoEntrega }}
            </p>
            <p v-if="etiquetaComprobante">
              <span class="font-medium text-texto-primario">Comprobante:</span> {{ etiquetaComprobante }}
            </p>
          </div>

          <div
            v-if="pedido.expira_en && !pedidoAnuladoOExpirado"
            data-testid="indicador-reserva-expira"
            class="mt-4 flex items-center gap-2 rounded-xl border border-ambar-200 bg-ambar-50 px-4 py-3 text-xs font-medium text-ambar-800 dark:border-ambar-900/50 dark:bg-ambar-950/20 dark:text-ambar-300"
          >
            <span aria-hidden="true">⏳</span>
            <span>Reserva válida hasta las <strong>{{ formatearHora(pedido.expira_en) }}</strong> (liberación automática tras 15 minutos).</span>
          </div>

          <div v-if="!pedidoAnuladoOExpirado" class="mt-6 border-t border-borde-sutil pt-4">
            <button
              type="button"
              data-testid="boton-cancelar-pedido-checkout"
              class="foco-dentro inline-flex items-center gap-1.5 rounded-full border border-peligro-300 bg-superficie-elevada px-4 py-2 text-xs font-semibold text-peligro-700 transition hover:bg-peligro-50 dark:border-peligro-800 dark:text-peligro-400 dark:hover:bg-peligro-950/20 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="cancelando"
              @click="dialogoCancelarAbierto = true"
            >
              {{ cancelando ? 'Cancelando pedido…' : 'Cancelar este pedido' }}
            </button>
          </div>
        </template>
      </section>

      <!-- Pago -->
      <section
        class="lg:col-span-2 rounded-2xl border border-borde-sutil bg-superficie-elevada/95 p-8 shadow-xl backdrop-blur-sm"
        aria-labelledby="titulo-pago"
      >
        <template v-if="pedidoAnuladoOExpirado">
          <div
            data-testid="aviso-pedido-anulado-expirado"
            class="rounded-xl border border-peligro-200 bg-peligro-50 p-5 text-sm text-peligro-800 dark:border-peligro-900/50 dark:bg-peligro-950/20 dark:text-peligro-300"
            role="alert"
          >
            <h3 class="font-bold text-base text-peligro-900 dark:text-peligro-200">
              {{ pedido?.estado === 'anulado' ? 'Pedido cancelado' : 'Reserva de stock vencida' }}
            </h3>
            <p class="mt-2 leading-relaxed">
              {{ pedido?.estado === 'anulado'
                ? 'Este pedido fue cancelado y las unidades fueron devueltas al inventario disponible.'
                : 'El tiempo límite de 15 minutos para completar el pago ha expirado y las unidades reservadas se liberaron para otros clientes.' }}
            </p>
            <div class="mt-5 flex flex-wrap gap-2.5">
              <RouterLink
                to="/client/pedidos"
                class="rounded-full bg-peligro-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-peligro-700"
              >
                Ver mis pedidos
              </RouterLink>
              <RouterLink
                to="/products"
                class="rounded-full border border-borde-control bg-superficie-base px-4 py-2 text-xs font-semibold text-texto-primario transition hover:bg-superficie-interactiva"
              >
                Volver al catálogo
              </RouterLink>
            </div>
          </div>
        </template>

        <template v-else>
          <h2
            id="titulo-pago"
            class="mb-2 text-xl font-bold text-texto-primario"
          >
            Pago seguro
          </h2>
          <p class="mb-6 text-sm text-texto-secundario">
            Los datos de tu tarjeta se ingresan directamente en el entorno de la
            pasarela. Botica San Juan no los recibe ni los almacena.
          </p>

          <!-- Tarjeta bancaria interactiva propia con tokens del proyecto -->
          <TarjetaBancariaVisual
            class="mb-6"
            @cambio-estado="alCambiarTarjeta"
          />

          <!--
            Contenedor del formulario del proveedor.
          -->
          <div
            v-show="formularioMontado"
            id="izipay-container"
          >
            <div class="kr-smart-form" />
          </div>

          <div
            v-if="!formularioMontado"
            class="min-h-[8rem] rounded-xl border border-dashed border-borde-sutil bg-superficie-hundida/70 p-4 text-center text-sm text-texto-terciario"
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
            class="mt-4 rounded-lg bg-peligro-50 px-4 py-3 text-sm text-peligro-700 dark:bg-peligro-500/10 dark:text-peligro-500"
            role="alert"
            aria-live="assertive"
          >
            {{ error }}
          </p>

          <button
            v-if="!formularioMontado"
            type="button"
            class="foco-dentro mt-6 min-h-11 w-full rounded-xl bg-clinico-600 px-6 py-3 font-medium text-neutro-0 shadow-lg shadow-clinico-600/20 transition hover:bg-clinico-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="procesando || cargando"
            @click="pagar"
          >
            {{ procesando ? 'Preparando el pago…' : `Pagar ${formatearMonto(total, moneda)}` }}
          </button>

          <p class="mt-4 flex items-center justify-center gap-2 text-xs text-texto-terciario">
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
        </template>
      </section>
    </div>

    <DialogoConfirmacion
      :abierto="dialogoCancelarAbierto"
      id="dialogo-cancelar-pedido-checkout"
      titulo="¿Deseas cancelar este pedido?"
      texto="Al cancelar, los medicamentos reservados volverán al inventario disponible de inmediato y el comprobante asociado se anulará."
      etiqueta-confirmar="Sí, cancelar pedido"
      etiqueta-cancelar="No, continuar con el pago"
      :peligroso="true"
      @confirmar="ejecutarCancelacion"
      @cancelar="dialogoCancelarAbierto = false"
    />
  </main>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import type { Pinia } from 'pinia'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { montarFormulario, type RetornoVerificable } from '@/services/izipay'
import { formatearMonto, iniciarPago, simularPago, validarRetorno, rutaDelEstado } from '@/services/pagos'
import {
  cancelarPedido,
  cargarPedidosCliente,
  pedidoRequierePago,
  type PedidoCliente,
} from '@/services/pedidosCliente'
import { useAuthStore } from '@/stores/auth'
import SelectorTema from '@/components/SelectorTema.vue'
import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'
import { usePedidosPendientes } from '@/composables/usePedidosPendientes'
import TarjetaBancariaVisual, { type EstadoTarjetaVisual } from './TarjetaBancariaVisual.vue'

const pendientesCompartidos = usePedidosPendientes()

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
  estado?: string
  total: string | number
  moneda?: string
  address?: string | null
  observacion?: string | null
  estado_pago?: string
  expira_en?: string | null
  anulado_en?: string | null
  motivo_anulacion?: string | null
  comprobante_electronico?: {
    id: number
    tipo_comprobante: string
    serie: string
    numero: number
    estado_sunat: string
  } | null
  pedido_detalles?: LineaPedido[]
}

const route = useRoute()
const router = useRouter()
const piniaInstalada = (
  getCurrentInstance()?.appContext.config.globalProperties as { $pinia?: Pinia } | undefined
)?.$pinia
const auth = piniaInstalada ? useAuthStore(piniaInstalada) : null

const pedido = ref<PedidoResumen | null>(null)
const pedidosPendientesCliente = ref<PedidoCliente[]>([])
const cargando = ref(true)
const procesando = ref(false)
const cancelando = ref(false)
const dialogoCancelarAbierto = ref(false)
const claveIdempotenciaPago = ref<string | null>(null)
const error = ref('')
const modoSimulado = ref(false)
const formularioMontado = ref(false)
const tarjetaEstado = ref<EstadoTarjetaVisual>({ completa: false, marca: 'desconocida' })

let desmontarFormulario: (() => void) | null = null

const moneda = computed(() => pedido.value?.moneda ?? 'PEN')
const total = computed(() => Number(pedido.value?.total ?? 0))
const baseImponible = computed(() => Math.round((total.value / 1.18) * 100) / 100)
const igv = computed(() => Math.round((total.value - baseImponible.value) * 100) / 100)
const textoEntrega = computed(() => pedido.value?.observacion || pedido.value?.address || '')
const etiquetaComprobante = computed(() => {
  const c = pedido.value?.comprobante_electronico
  if (!c) return ''
  const tipo = c.tipo_comprobante === 'factura' ? 'Factura' : 'Boleta'
  const correlativo = `${c.serie}-${String(c.numero).padStart(8, '0')}`
  return `${tipo} ${correlativo}`
})

const pedidoAnuladoOExpirado = computed(() => {
  if (!pedido.value) return false
  if (pedido.value.estado === 'anulado') return true
  if (pedido.value.expira_en) {
    return new Date(pedido.value.expira_en).getTime() < Date.now()
  }
  return false
})

function formatearHora(fechaIso?: string | null): string {
  if (!fechaIso) return ''
  const d = new Date(fechaIso)
  if (isNaN(d.getTime())) return String(fechaIso)
  return d.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })
}

function alCambiarTarjeta(estado: EstadoTarjetaVisual) {
  tarjetaEstado.value = estado
  claveIdempotenciaPago.value = null
  if (estado.completa && error.value.includes('tarjeta 3D')) {
    error.value = ''
  }
}

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
 * Cancela el pedido voluntariamente liberando la reserva y devolviendo los productos.
 */
async function ejecutarCancelacion(): Promise<void> {
  if (!pedido.value || cancelando.value) return
  cancelando.value = true
  dialogoCancelarAbierto.value = false
  error.value = ''

  try {
    await cancelarPedido(pedido.value.id)
    if (auth?.user?.id) {
      pendientesCompartidos.refrescar(auth.user.id)
    }
    await router.push({ path: '/client/pedidos', query: { cancelado: String(pedido.value.id) } })
  } catch (e: any) {
    const msg = e?.response?.data?.message || 'No se pudo cancelar el pedido. Puede que ya haya sido pagado o procesado.'
    error.value = msg
  } finally {
    cancelando.value = false
  }
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

    // Sin credenciales el proveedor no entrega token de formulario. En modo
    // simulado validamos localmente en memoria la tarjeta 3D (sin enviar numero
    // completo ni CVV por red) y completamos la simulacion autenticada.
    if (modoSimulado.value || !respuesta.checkout.form_token) {
      if (!tarjetaEstado.value.completa) {
        error.value = 'Completa los datos en la tarjeta 3D antes de confirmar el pago.'
        return
      }

      if (!claveIdempotenciaPago.value) {
        claveIdempotenciaPago.value =
          typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
            ? crypto.randomUUID()
            : `pago-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`
      }

      await simularPago(
        referencia,
        {
          resultado: 'aprobar',
          marca_tarjeta: tarjetaEstado.value.marca,
        },
        claveIdempotenciaPago.value,
      )
      claveIdempotenciaPago.value = null
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
  } catch (e: any) {
    if (e?.response?.status === 409) {
      error.value = e?.response?.data?.message || 'La reserva de este pedido ha expirado o ya no está disponible.'
      if (pedido.value) {
        await cargarPedido(pedido.value.id)
      }
    } else {
      error.value = 'No pudimos contactar a la pasarela. Intenta nuevamente en unos segundos.'
    }
  } finally {
    procesando.value = false
  }
}

const otrosPedidosPendientes = computed(() =>
  pedidosPendientesCliente.value.filter((p) => p.id !== pedido.value?.id),
)

function cambiarPedido(evento: Event): void {
  const select = evento.target as HTMLSelectElement
  const nuevoId = Number(select.value)
  if (nuevoId && nuevoId !== pedido.value?.id) {
    void router.push({ path: '/checkout/pago', query: { pedido: String(nuevoId) } })
  }
}

async function cargarPedido(id: number): Promise<void> {
  if (!id) {
    await router.replace('/')
    return
  }

  cargando.value = true
  error.value = ''
  desmontarFormulario?.()
  desmontarFormulario = null
  formularioMontado.value = false

  try {
    const { data } = await api.get<PedidoResumen>(`/pedidos/${id}/mi-pedido`)
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
}

async function cargarPendientesAlternativos(): Promise<void> {
  const usuarioId = auth?.user?.id
  if (!usuarioId) return
  try {
    const lista = await cargarPedidosCliente(usuarioId)
    pedidosPendientesCliente.value = lista.filter(pedidoRequierePago)
    /* El carrito enseña estos mismos pedidos; dejárselos listos evita que el
       aviso aparezca tarde al volver allí. */
    pendientesCompartidos.sembrar(usuarioId, lista)
  } catch {
    pedidosPendientesCliente.value = []
  }
}

watch(
  () => route.query.pedido,
  (nuevo, anterior) => {
    if (nuevo && nuevo !== anterior) {
      void cargarPedido(Number(nuevo))
    }
  },
)

onMounted(async () => {
  await Promise.all([
    cargarPedido(Number(route.query.pedido)),
    cargarPendientesAlternativos(),
  ])
})

onBeforeUnmount(() => {
  desmontarFormulario?.()
  desmontarFormulario = null
})
</script>
