<script setup lang="ts">
/**
 * Tarjeta bancaria interactiva en 3D para el flujo de checkout.
 *
 * PRIVACIDAD Y SEGURIDAD ESTRICTA:
 * No almacena datos en localStorage, cookies, sessionStorage ni analíticas.
 * Los datos viven únicamente en memoria volátil de Vue para previsualización
 * interactiva y detección de franquicia bancaria en tiempo real.
 */
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import {
  detectarMarcaTarjeta,
  esVencimientoValido,
  formatearNumeroTarjeta,
  formatearVencimiento,
  type MarcaTarjeta,
} from '@/utils/tarjetaBancaria'
import { ShieldCheckIcon, CreditCardIcon } from 'lucide-vue-next'

export interface EstadoTarjetaVisual {
  completa: boolean
  marca: MarcaTarjeta
}

const emit = defineEmits<{
  /** Emite únicamente estado no sensible (validez y franquicia) para habilitar el pago sin exponer PAN ni CVV. */
  (evento: 'cambio-estado', payload: EstadoTarjetaVisual): void
}>()

type CampoTarjeta = 'numero' | 'titular' | 'vencimiento' | 'cvv'
type CampoFrontal = CampoTarjeta

interface CapaFondoFranquicia {
  id: MarcaTarjeta
  etiqueta: string
  claseGradiente: string
}

const CAPAS_FONDO: readonly CapaFondoFranquicia[] = [
  {
    id: 'desconocida',
    etiqueta: 'Genérica',
    claseGradiente: 'bg-linear-to-br from-botica-800 via-botica-900 to-botica-950',
  },
  {
    id: 'visa',
    etiqueta: 'Visa',
    claseGradiente: 'bg-linear-to-br from-clinico-700 via-clinico-900 to-clinico-950',
  },
  {
    id: 'mastercard',
    etiqueta: 'Mastercard',
    claseGradiente: 'bg-linear-to-br from-neutro-950 via-ambar-950 to-ambar-800',
  },
  {
    id: 'amex',
    etiqueta: 'American Express',
    claseGradiente: 'bg-linear-to-br from-neutro-800 via-clinico-900 to-neutro-950',
  },
] as const

const BASE_MARCO_ANCHO = 200
const BASE_MARCO_ALTO = 64

const numero = ref('')
const titular = ref('')
const vencimiento = ref('')
const codigoSeguridad = ref('')
const campoEnfocado = ref<CampoTarjeta | null>(null)

const contenedorFrontalRef = ref<HTMLElement | null>(null)
const marcoFocoRef = ref<HTMLElement | null>(null)
const bloqueNumeroRef = ref<HTMLElement | null>(null)
const bloqueTitularRef = ref<HTMLElement | null>(null)
const bloqueVencimientoRef = ref<HTMLElement | null>(null)
const bloqueCvvFrontalRef = ref<HTMLElement | null>(null)

const marcoFoco = ref({
  x: 0,
  y: 0,
  scaleX: 1,
  scaleY: 1,
  visible: false,
})

let observadorTamano: ResizeObserver | null = null

/** Detección pura de la red emisora */
const marca = computed<MarcaTarjeta>(() => detectarMarcaTarjeta(numero.value))
const esAmex = computed(() => marca.value === 'amex')

/** La tarjeta gira 180 grados al enfocar CVV, excepto en Amex cuyo CID de 4 dígitos va en el anverso */
const estaVolteada = computed(() => campoEnfocado.value === 'cvv' && !esAmex.value)

const campoFrontalActivo = computed<CampoFrontal | null>(() => {
  if (!campoEnfocado.value) return null
  if (campoEnfocado.value === 'cvv' && !esAmex.value) return null
  return campoEnfocado.value
})

/** Formateo en tiempo real según el esquema de la marca detectada */
function alEscribirNumero(evento: Event) {
  const target = evento.target as HTMLInputElement
  numero.value = formatearNumeroTarjeta(target.value)
}

function alEscribirVencimiento(evento: Event) {
  const target = evento.target as HTMLInputElement
  vencimiento.value = formatearVencimiento(target.value)
}

function alEscribirTitular(evento: Event) {
  const target = evento.target as HTMLInputElement
  titular.value = target.value.toUpperCase()
}

function alEscribirCvv(evento: Event) {
  const target = evento.target as HTMLInputElement
  // Amex utiliza 4 dígitos en el frente; Visa/Mastercard utilizan 3 dígitos atrás
  const maxDigitos = marca.value === 'amex' ? 4 : 3
  codigoSeguridad.value = target.value.replace(/\D/g, '').slice(0, maxDigitos)
}

function obtenerReferenciaCampo(campo: CampoFrontal): HTMLElement | null {
  if (campo === 'numero') return bloqueNumeroRef.value
  if (campo === 'titular') return bloqueTitularRef.value
  if (campo === 'cvv') return bloqueCvvFrontalRef.value
  return bloqueVencimientoRef.value
}

/**
 * Calcula traslación y escala para mover el marco luminoso con GPU
 * sin animar propiedades de diseño (width, height, top, left).
 */
function recalcularMarcoFoco(): void {
  const campo = campoFrontalActivo.value
  if (!campo) {
    marcoFoco.value = {
      ...marcoFoco.value,
      visible: false,
    }
    return
  }

  const objetivo = obtenerReferenciaCampo(campo)
  if (!objetivo) {
    marcoFoco.value = {
      ...marcoFoco.value,
      visible: false,
    }
    return
  }

  const baseAncho = marcoFocoRef.value?.offsetWidth || BASE_MARCO_ANCHO
  const baseAlto = marcoFocoRef.value?.offsetHeight || BASE_MARCO_ALTO
  const ancho = objetivo.offsetWidth
  const alto = objetivo.offsetHeight

  marcoFoco.value = {
    x: objetivo.offsetLeft,
    y: objetivo.offsetTop,
    scaleX: ancho > 0 ? ancho / baseAncho : 1,
    scaleY: alto > 0 ? alto / baseAlto : 1,
    visible: true,
  }
}

function alEnfocarCampo(campo: CampoTarjeta) {
  campoEnfocado.value = campo
  recalcularMarcoFoco()
  void nextTick(recalcularMarcoFoco)
}

function alDesenfocarCampo(campo: CampoTarjeta) {
  // Evita borrar el foco si otro campo ya lo recibió antes en el mismo ciclo
  if (campoEnfocado.value === campo) {
    campoEnfocado.value = null
    recalcularMarcoFoco()
  }
}

const estiloMarcoFoco = computed(() => ({
  transform: `translate3d(${marcoFoco.value.x}px, ${marcoFoco.value.y}px, 0) scale(${marcoFoco.value.scaleX}, ${marcoFoco.value.scaleY})`,
  opacity: marcoFoco.value.visible ? '1' : '0',
}))

/**
 * Representación visual del número en la tarjeta:
 * Muestra los dígitos ingresados y completa el resto con puntos (•) para guiar al usuario.
 */
const numeroVisual = computed(() => {
  const formateado = numero.value.trim()
  if (!formateado) {
    return marca.value === 'amex'
      ? '•••• •••••• •••••'
      : '•••• •••• •••• ••••'
  }

  // Si es Amex completamos hasta 15 dígitos con esquema 4-6-5
  if (marca.value === 'amex') {
    const digitos = formateado.replace(/\D/g, '')
    const relleno = (digitos + '•••••••••••••••').slice(0, 15)
    return `${relleno.slice(0, 4)} ${relleno.slice(4, 10)} ${relleno.slice(10, 15)}`
  }

  // Visa / Mastercard / Desconocida: 16 dígitos 4-4-4-4
  const digitos = formateado.replace(/\D/g, '')
  const relleno = (digitos + '••••••••••••••••').slice(0, 16)
  return `${relleno.slice(0, 4)} ${relleno.slice(4, 8)} ${relleno.slice(8, 12)} ${relleno.slice(12, 16)}`
})

const ranurasNumero = computed(() =>
  Array.from(numeroVisual.value).map((caracter, indice) => ({
    indice,
    caracter,
    esEspacio: caracter === ' ',
  })),
)

const caracteresTitular = computed(() => Array.from(titular.value))
const caracteresVencimiento = computed(() => Array.from(vencimiento.value))

/** Puntos visibles del CVV; jamás expone los dígitos ingresados */
const puntosCvvIngresados = computed(() =>
  Array.from({ length: codigoSeguridad.value.length }, (_, indice) => indice),
)
const cvvPlaceholder = computed(() => (marca.value === 'amex' ? '••••' : '•••'))

/** Longitud máxima de caracteres permitida en el input con espacios incluidos */
const longitudMaxNumero = computed(() => (marca.value === 'amex' ? 17 : 19))
const longitudMaxCvv = computed(() => (marca.value === 'amex' ? 4 : 3))

const esTarjetaCompleta = computed(() => {
  const digitosNumero = numero.value.replace(/\D/g, '')
  const largoEsperado = esAmex.value ? 15 : 16
  return (
    digitosNumero.length === largoEsperado &&
    titular.value.trim().length >= 3 &&
    esVencimientoValido(vencimiento.value) &&
    codigoSeguridad.value.length === longitudMaxCvv.value
  )
})

watch(
  [esTarjetaCompleta, marca],
  ([completa, franquicia]) => {
    emit('cambio-estado', { completa, marca: franquicia })
  },
  { immediate: true },
)

watch(longitudMaxCvv, (max) => {
  if (codigoSeguridad.value.length > max) {
    codigoSeguridad.value = codigoSeguridad.value.slice(0, max)
  }
})

watch(
  [campoEnfocado, marca],
  () => {
    void nextTick(recalcularMarcoFoco)
  },
  { flush: 'post' },
)

watch(bloqueCvvFrontalRef, (nuevoNodo) => {
  if (nuevoNodo && observadorTamano) {
    observadorTamano.observe(nuevoNodo)
  }
})

onMounted(() => {
  if (typeof ResizeObserver === 'undefined') return

  observadorTamano = new ResizeObserver(() => {
    recalcularMarcoFoco()
  })

  const nodos = [
    contenedorFrontalRef.value,
    bloqueNumeroRef.value,
    bloqueTitularRef.value,
    bloqueVencimientoRef.value,
    bloqueCvvFrontalRef.value,
  ]
  for (const nodo of nodos) {
    if (nodo) observadorTamano.observe(nodo)
  }
})

onBeforeUnmount(() => {
  observadorTamano?.disconnect()
  observadorTamano = null
})
</script>

<template>
  <div class="space-y-6">
    <!-- Escena con perspectiva 3D; sin overflow-hidden aquí para no aplanar preserve-3d -->
    <div
      class="tarjeta-escena mx-auto w-full max-w-md"
      aria-label="Vista previa interactiva de tarjeta bancaria"
      role="region"
    >
      <div
        data-testid="tarjeta-cuerpo"
        class="tarjeta-cuerpo transition-[transform,opacity]"
        :class="{ 'esta-volteada': estaVolteada }"
      >
        <!-- CARA FRONTAL -->
        <div
          data-testid="cara-frontal"
          :aria-hidden="estaVolteada ? 'true' : undefined"
          class="tarjeta-cara tarjeta-cara--frontal overflow-hidden rounded-2xl border border-neutro-0/20 p-4 text-neutro-0 shadow-xl"
        >
          <!-- Capas de fondo por franquicia (cambia solo opacidad en GPU) -->
          <div
            v-for="capa in CAPAS_FONDO"
            :key="`frontal-${capa.id}`"
            :data-testid="`fondo-${capa.id}`"
            :data-activo="marca === capa.id ? 'true' : 'false'"
            class="capa-fondo pointer-events-none absolute inset-0"
            :class="[capa.claseGradiente, marca === capa.id ? 'opacity-100' : 'opacity-0']"
            aria-hidden="true"
          />

          <!-- Patrón decorativo geométrico en SVG ligero sin shaders -->
          <svg
            class="pointer-events-none absolute -right-12 -top-12 z-0 h-64 w-64 text-neutro-0/5"
            viewBox="0 0 200 200"
            fill="currentColor"
            aria-hidden="true"
          >
            <circle cx="100" cy="100" r="80" stroke="currentColor" stroke-width="1.5" fill="none" />
            <circle cx="100" cy="100" r="60" stroke="currentColor" stroke-width="1.5" fill="none" />
            <circle cx="100" cy="100" r="40" stroke="currentColor" stroke-width="1.5" fill="none" />
            <circle cx="100" cy="100" r="20" stroke="currentColor" stroke-width="1.5" fill="none" />
          </svg>

          <!-- Contenedor relativo compartido para que offsetLeft/Top de los campos coincidan -->
          <div ref="contenedorFrontalRef" class="relative z-10 p-2">
            <!-- Marco luminoso de enfoque animado solo con translate + scale + opacity -->
            <div
              ref="marcoFocoRef"
              data-testid="marco-foco"
              :data-visible="marcoFoco.visible ? 'true' : 'false'"
              :data-campo-activo="campoFrontalActivo ?? 'ninguno'"
              :style="estiloMarcoFoco"
              class="marco-foco pointer-events-none"
              aria-hidden="true"
            />

            <!-- Fila superior: Chip EMV, Símbolo Contactless y Marca detectada -->
            <div class="mb-4 flex items-center justify-between px-2">
              <div class="flex items-center gap-3">
                <!-- Chip EMV estilizado con tokens ámbar/dorado -->
                <div
                  class="flex h-9 w-12 items-center justify-center rounded-md border border-ambar-300/40 bg-linear-to-br from-ambar-200 to-ambar-400 p-1 shadow-xs"
                  aria-hidden="true"
                >
                  <div class="grid h-full w-full grid-cols-2 gap-0.5 rounded-xs border border-ambar-600/40 bg-ambar-300/60 p-0.5">
                    <div class="rounded-xs border border-ambar-700/30" />
                    <div class="rounded-xs border border-ambar-700/30" />
                    <div class="rounded-xs border border-ambar-700/30" />
                    <div class="rounded-xs border border-ambar-700/30" />
                  </div>
                </div>

                <!-- Símbolo Contactless -->
                <svg
                  class="h-6 w-6 text-neutro-0/85"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  aria-hidden="true"
                >
                  <path d="M7 16.5a7 7 0 0 1 0-9" />
                  <path d="M10.5 19a11 11 0 0 1 0-14" />
                  <path d="M14 21.5a15 15 0 0 1 0-19" />
                </svg>
              </div>

              <!-- Logotipo de la marca identificada -->
              <div class="flex h-9 items-center justify-end" aria-live="polite">
                <!-- VISA -->
                <div
                  v-if="marca === 'visa'"
                  data-testid="logo-visa"
                  class="rounded-md bg-neutro-0/95 px-3 py-1 font-sans text-xl font-black italic tracking-wider text-clinico-900 shadow-xs"
                >
                  VISA
                </div>

                <!-- MASTERCARD -->
                <div
                  v-else-if="marca === 'mastercard'"
                  data-testid="logo-mastercard"
                  class="flex items-center"
                >
                  <div class="size-7 rounded-full bg-peligro-600 opacity-90 shadow-xs" />
                  <div class="-ml-3 size-7 rounded-full bg-ambar-400 opacity-90 shadow-xs" />
                </div>

                <!-- AMEX -->
                <div
                  v-else-if="marca === 'amex'"
                  data-testid="logo-amex"
                  class="rounded-md bg-info-700 px-2.5 py-1 text-xs font-black tracking-widest text-neutro-0 shadow-xs"
                >
                  AMERICAN EXPRESS
                </div>

                <!-- Desconocida / Genérica -->
                <div
                  v-else
                  data-testid="logo-desconocida"
                  class="flex items-center gap-1.5 rounded-md bg-neutro-950/35 px-2.5 py-1 text-xs font-semibold tracking-wide text-neutro-0"
                >
                  <CreditCardIcon class="size-4" aria-hidden="true" />
                  <span>TARJETA</span>
                </div>
              </div>
            </div>

            <!-- Número de tarjeta con cifras tabulares y CID frontal para Amex -->
            <div class="my-3 flex items-center justify-between gap-2">
              <div ref="bloqueNumeroRef" class="min-w-0 flex-1 rounded-md px-2 py-1.5">
                <p class="text-2xs font-medium uppercase tracking-widest text-neutro-200">
                  Número de tarjeta
                </p>
                <p
                  data-testid="numero-tarjeta-visual"
                  class="cifras-tabulares mt-1 whitespace-nowrap font-mono text-lg font-semibold tracking-widest text-neutro-0 sm:text-xl"
                >
                  <template v-for="ranura in ranurasNumero" :key="ranura.indice">
                    <span
                      v-if="ranura.esEspacio"
                      class="inline-block w-[0.45ch] whitespace-pre"
                    >{{ ranura.caracter }}</span>
                    <span v-else class="relative inline-block">
                      <Transition name="digito">
                        <span
                          :key="`${ranura.indice}-${ranura.caracter}`"
                          class="inline-block"
                        >{{ ranura.caracter }}</span>
                      </Transition>
                    </span>
                  </template>
                </p>
              </div>

              <!-- CID frontal enmascarado de 4 dígitos exclusivo de American Express -->
              <div
                v-if="esAmex"
                ref="bloqueCvvFrontalRef"
                class="shrink-0 rounded-md px-2 py-1.5 text-right"
              >
                <p class="text-2xs font-medium uppercase tracking-widest text-neutro-200">
                  CID
                </p>
                <div
                  data-testid="cvv-frontal-visual"
                  class="cifras-tabulares relative mt-1 flex min-w-10 items-center justify-end font-mono text-sm font-bold tracking-widest text-neutro-0"
                  aria-label="Código de seguridad CID enmascarado"
                >
                  <span class="relative inline-block">
                    <TransitionGroup
                      v-if="puntosCvvIngresados.length > 0"
                      name="digito"
                      tag="span"
                      class="relative inline-block text-neutro-0"
                    >
                      <span
                        v-for="indice in puntosCvvIngresados"
                        :key="`cid-frontal-${indice}`"
                        class="inline-block"
                      >•</span>
                    </TransitionGroup>
                    <span v-else class="text-neutro-200">{{ cvvPlaceholder }}</span>
                  </span>
                </div>
              </div>
            </div>

            <!-- Fila inferior: Titular y Vencimiento -->
            <div class="mt-3 flex items-end justify-between gap-2">
              <div ref="bloqueTitularRef" class="min-w-0 flex-1 rounded-md px-2 py-1.5">
                <p class="text-2xs font-medium uppercase tracking-widest text-neutro-200">
                  Titular
                </p>
                <p
                  data-testid="titular-visual"
                  class="truncate font-sans text-sm font-semibold tracking-wide text-neutro-0"
                >
                  <span class="relative inline-block">
                    <TransitionGroup
                      v-if="titular"
                      name="digito"
                      tag="span"
                      class="relative inline-block"
                    >
                      <span
                        v-for="(caracter, indice) in caracteresTitular"
                        :key="`${indice}-${caracter}`"
                        class="inline-block whitespace-pre"
                      >{{ caracter }}</span>
                    </TransitionGroup>
                    <span v-else>NOMBRE Y APELLIDO</span>
                  </span>
                </p>
              </div>

              <div ref="bloqueVencimientoRef" class="shrink-0 rounded-md px-2 py-1.5 text-right">
                <p class="text-2xs font-medium uppercase tracking-widest text-neutro-200">
                  Vence
                </p>
                <p
                  data-testid="vencimiento-visual"
                  class="cifras-tabulares font-mono text-sm font-semibold tracking-widest text-neutro-0"
                >
                  <span class="relative inline-block">
                    <TransitionGroup
                      v-if="vencimiento"
                      name="digito"
                      tag="span"
                      class="relative inline-block"
                    >
                      <span
                        v-for="(caracter, indice) in caracteresVencimiento"
                        :key="`${indice}-${caracter}`"
                        class="inline-block whitespace-pre"
                      >{{ caracter }}</span>
                    </TransitionGroup>
                    <span v-else>MM/AA</span>
                  </span>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- CARA TRASERA -->
        <div
          data-testid="cara-trasera"
          :aria-hidden="!estaVolteada ? 'true' : undefined"
          class="tarjeta-cara tarjeta-cara--trasera flex flex-col justify-between overflow-hidden rounded-2xl border border-neutro-0/20 pb-5 pt-5 text-neutro-0 shadow-xl"
        >
          <!-- Capas de fondo sincronizadas con la franquicia detectada -->
          <div
            v-for="capa in CAPAS_FONDO"
            :key="`trasera-${capa.id}`"
            :data-testid="`fondo-trasero-${capa.id}`"
            :data-activo="marca === capa.id ? 'true' : 'false'"
            class="capa-fondo pointer-events-none absolute inset-0"
            :class="[capa.claseGradiente, marca === capa.id ? 'opacity-100' : 'opacity-0']"
            aria-hidden="true"
          />

          <!-- Banda magnética superior -->
          <div class="relative z-10 h-10 w-full bg-neutro-950" aria-hidden="true" />

          <!-- Banda blanca de firma y CVV enmascarado -->
          <div class="relative z-10 my-4 px-6">
            <div class="mb-1 flex items-center justify-end">
              <span class="text-2xs font-semibold uppercase tracking-widest text-neutro-200">
                CVV / CVC
              </span>
            </div>
            <div class="flex h-10 items-center justify-between gap-3 rounded-md bg-neutro-0 px-3 text-neutro-900 shadow-xs">
              <!-- Panel decorativo de firma autorizada -->
              <div class="flex flex-1 flex-col gap-1" aria-hidden="true">
                <span class="block h-0.5 w-full rounded-xs bg-neutro-200" />
                <span class="block h-0.5 w-4/5 rounded-xs bg-neutro-200" />
                <span class="block h-0.5 w-3/5 rounded-xs bg-neutro-200" />
              </div>

              <!-- CVV enmascarado SIEMPRE con puntos -->
              <div
                data-testid="cvv-visual"
                class="cifras-tabulares relative flex min-w-12 items-center justify-end font-mono text-base font-bold tracking-widest"
                aria-label="Código de seguridad enmascarado"
              >
                <span class="relative inline-block">
                  <TransitionGroup
                    v-if="puntosCvvIngresados.length > 0"
                    name="digito"
                    tag="span"
                    class="relative inline-block text-neutro-950"
                  >
                    <span
                      v-for="indice in puntosCvvIngresados"
                      :key="`cvv-punto-${indice}`"
                      class="inline-block"
                    >•</span>
                  </TransitionGroup>
                  <span v-else class="text-neutro-500">{{ cvvPlaceholder }}</span>
                </span>
              </div>
            </div>
          </div>

          <!-- Pie informativo de la cara trasera -->
          <div class="relative z-10 flex items-center justify-between gap-3 px-6">
            <p class="text-2xs text-neutro-200">
              Código de verificación · Solo en memoria
            </p>
            <span class="text-2xs font-bold uppercase tracking-widest text-neutro-100">
              {{ marca === 'desconocida' ? 'TARJETA' : marca }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Formulario de interacción con la tarjeta -->
    <div
      role="group"
      class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-5 shadow-xs"
      aria-label="Datos de la tarjeta"
    >
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-sm font-bold text-texto-primario">
          Datos de la tarjeta
        </h3>
        <span
          class="inline-flex items-center gap-1 text-2xs font-medium text-texto-secundario"
        >
          <ShieldCheckIcon class="size-3.5 text-exito-600" aria-hidden="true" />
          Detección instantánea
        </span>
      </div>

      <div class="space-y-4">
        <!-- Número -->
        <div>
          <label
            for="tarjeta-numero"
            class="mb-1 block text-xs font-medium text-texto-secundario"
          >
            Número de tarjeta
          </label>
          <div class="relative">
            <input
              id="tarjeta-numero"
              :value="numero"
              type="text"
              inputmode="numeric"
              autocomplete="cc-number"
              :maxlength="longitudMaxNumero"
              placeholder="0000 0000 0000 0000"
              class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 font-mono text-sm text-texto-primario placeholder:text-texto-terciario"
              @input="alEscribirNumero"
              @focus="alEnfocarCampo('numero')"
              @blur="alDesenfocarCampo('numero')"
            />
            <span
              v-if="marca !== 'desconocida'"
              class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold capitalize text-botica-700 dark:text-botica-300"
            >
              {{ marca }}
            </span>
          </div>
        </div>

        <!-- Titular -->
        <div>
          <label
            for="tarjeta-titular"
            class="mb-1 block text-xs font-medium text-texto-secundario"
          >
            Nombre en la tarjeta
          </label>
          <input
            id="tarjeta-titular"
            :value="titular"
            type="text"
            autocomplete="cc-name"
            maxlength="45"
            placeholder="COMO APARECE EN LA TARJETA"
            class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 font-sans text-sm text-texto-primario uppercase placeholder:text-texto-terciario"
            @input="alEscribirTitular"
            @focus="alEnfocarCampo('titular')"
            @blur="alDesenfocarCampo('titular')"
          />
        </div>

        <!-- Vencimiento y CVV -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label
              for="tarjeta-vencimiento"
              class="mb-1 block text-xs font-medium text-texto-secundario"
            >
              Vencimiento
            </label>
            <input
              id="tarjeta-vencimiento"
              :value="vencimiento"
              type="text"
              inputmode="numeric"
              autocomplete="cc-exp"
              maxlength="5"
              placeholder="MM/AA"
              class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 font-mono text-sm text-texto-primario placeholder:text-texto-terciario"
              @input="alEscribirVencimiento"
              @focus="alEnfocarCampo('vencimiento')"
              @blur="alDesenfocarCampo('vencimiento')"
            />
          </div>

          <div>
            <label
              for="tarjeta-cvv"
              class="mb-1 block text-xs font-medium text-texto-secundario"
            >
              {{ esAmex ? 'CID (4 dígitos)' : 'CVV / CVC' }}
            </label>
            <input
              id="tarjeta-cvv"
              :value="codigoSeguridad"
              type="password"
              inputmode="numeric"
              autocomplete="cc-csc"
              :maxlength="longitudMaxCvv"
              :placeholder="marca === 'amex' ? '1234' : '123'"
              class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 font-mono text-sm text-texto-primario placeholder:text-texto-terciario"
              @input="alEscribirCvv"
              @focus="alEnfocarCampo('cvv')"
              @blur="alDesenfocarCampo('cvv')"
            />
          </div>
        </div>
      </div>

      <p class="mt-3 text-2xs text-texto-terciario">
        Diseño exclusivo con tokens de Botica San Juan. Esta información no se guarda ni se envía en red.
      </p>
    </div>
  </div>
</template>

<style scoped>
.tarjeta-escena {
  perspective: 1000px;
}

.tarjeta-cuerpo {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  transform-style: preserve-3d;
  transition-property: transform, opacity;
  transition-duration: var(--duracion-pausada);
  transition-timing-function: var(--ease-suave);
}

.tarjeta-cuerpo.esta-volteada {
  transform: rotateY(180deg);
}

.tarjeta-cara {
  grid-area: 1 / 1;
  position: relative;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
}

.tarjeta-cara--frontal {
  transform: rotateY(0deg);
}

.tarjeta-cara--trasera {
  transform: rotateY(180deg);
}

.tarjeta-cuerpo.esta-volteada .tarjeta-cara--frontal {
  pointer-events: none;
}

.tarjeta-cuerpo:not(.esta-volteada) .tarjeta-cara--trasera {
  pointer-events: none;
}

.capa-fondo {
  transition-property: opacity;
  transition-duration: var(--duracion-pausada);
  transition-timing-function: var(--ease-suave);
}

.marco-foco {
  position: absolute;
  top: 0;
  left: 0;
  width: 200px;
  height: 64px;
  transform-origin: top left;
  border-radius: var(--radius-md);
  border: 1.5px solid color-mix(in oklab, var(--color-neutro-0) 70%, transparent);
  background-color: color-mix(in oklab, var(--color-neutro-950) 24%, transparent);
  transition-property: transform, opacity;
  transition-duration: var(--duracion-normal);
  transition-timing-function: var(--ease-salida);
}

/* Animación por dígito / carácter: únicamente transform y opacity */
.digito-enter-active {
  transition-property: transform, opacity;
  transition-duration: var(--duracion-rapida);
  transition-timing-function: var(--ease-salida);
}

.digito-leave-active {
  position: absolute;
  top: 0;
  pointer-events: none;
  transition-property: transform, opacity;
  transition-duration: var(--duracion-instante);
  transition-timing-function: var(--ease-entrada);
}

.digito-enter-from {
  opacity: 0;
  transform: translateY(0.35em);
}

.digito-leave-to {
  opacity: 0;
  transform: translateY(-0.35em);
}

@media (prefers-reduced-motion: reduce) {
  .tarjeta-cuerpo {
    transform-style: flat;
    transition: none;
  }

  .tarjeta-cuerpo.esta-volteada {
    transform: none;
  }

  .tarjeta-cara {
    transition-property: opacity;
    transition-duration: var(--duracion-rapida);
    transition-timing-function: var(--ease-suave);
  }

  .tarjeta-cara--frontal {
    transform: none;
    opacity: 1;
  }

  .tarjeta-cara--trasera {
    transform: none;
    opacity: 0;
  }

  .tarjeta-cuerpo.esta-volteada .tarjeta-cara--frontal {
    opacity: 0;
  }

  .tarjeta-cuerpo.esta-volteada .tarjeta-cara--trasera {
    opacity: 1;
  }

  .marco-foco {
    transition-property: opacity;
  }

  .digito-enter-from,
  .digito-leave-to {
    transform: none;
  }
}
</style>
