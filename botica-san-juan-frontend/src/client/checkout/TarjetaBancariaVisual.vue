<script setup lang="ts">
/**
 * Tarjeta bancaria interactiva para el flujo de checkout.
 *
 * PRIVACIDAD Y SEGURIDAD ESTRICTA:
 * No almacena datos en localStorage, cookies, session ni analíticas.
 * Los datos viven únicamente en memoria volátil de Vue para previsualización
 * interactiva y detección de franquicia bancaria en tiempo real.
 */
import { ref, computed } from 'vue'
import {
  detectarMarcaTarjeta,
  formatearNumeroTarjeta,
  formatearVencimiento,
  type MarcaTarjeta,
} from '@/utils/tarjetaBancaria'
import { ShieldCheckIcon, CreditCardIcon } from 'lucide-vue-next'

const numero = ref('')
const titular = ref('')
const vencimiento = ref('')
const codigoSeguridad = ref('')

/** Detección pura de la red emisora */
const marca = computed<MarcaTarjeta>(() => detectarMarcaTarjeta(numero.value))

/** Formateo en tiempo real según el esquema de la marca detectada */
function alEscribirNumero(evento: Event) {
  const target = evento.target as HTMLInputElement
  const formateado = formatearNumeroTarjeta(target.value)
  numero.value = formateado
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

/**
 * Representación visual del número en la tarjeta:
 * Muestra los dígitos ingresados y completa el resto con puntos (•) para guiar al usuario.
 */
const numeroVisual = computed(() => {
  const formateado = numero.value.trim()
  if (!formateado) {
    return marca.value === 'amex'
      ? '•••• •••••• •••••'
      : '•••• ••••• •••• ••••'
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

/** Longitud máxima de caracteres permitida en el input con espacios incluidos */
const longitudMaxNumero = computed(() => (marca.value === 'amex' ? 17 : 19))
const longitudMaxCvv = computed(() => (marca.value === 'amex' ? 4 : 3))
</script>

<template>
  <div class="space-y-6">
    <!-- Tarjeta visual interactiva -->
    <div
      class="relative mx-auto w-full max-w-md overflow-hidden rounded-2xl border border-white/20 bg-linear-to-br from-botica-800 via-botica-900 to-clinico-950 p-6 text-white shadow-xl transition-all duration-300"
      aria-label="Vista previa interactiva de tarjeta bancaria"
      role="region"
    >
      <!-- Patrón decorativo de fondo (líneas geométricas en SVG ligero sin shaders) -->
      <svg
        class="pointer-events-none absolute -right-12 -top-12 h-64 w-64 text-white/5"
        viewBox="0 0 200 200"
        fill="currentColor"
        aria-hidden="true"
      >
        <circle cx="100" cy="100" r="80" stroke="currentColor" stroke-width="1.5" fill="none" />
        <circle cx="100" cy="100" r="60" stroke="currentColor" stroke-width="1.5" fill="none" />
        <circle cx="100" cy="100" r="40" stroke="currentColor" stroke-width="1.5" fill="none" />
        <circle cx="100" cy="100" r="20" stroke="currentColor" stroke-width="1.5" fill="none" />
      </svg>

      <!-- Fila superior: Chip EMV, Símbolo Contactless y Marca detectada -->
      <div class="relative z-10 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <!-- Chip EMV estilizado con tokens ámbar/dorado -->
          <div
            class="flex h-9 w-12 items-center justify-center rounded-md border border-ambar-300/40 bg-linear-to-br from-ambar-200 to-ambar-400 p-1 shadow-xs"
            aria-hidden="true"
          >
            <div class="grid h-full w-full grid-cols-2 gap-0.5 rounded-xs border border-ambar-600/40 bg-ambar-300/60 p-0.5">
              <div class="rounded-2xs border border-ambar-700/30" />
              <div class="rounded-2xs border border-ambar-700/30" />
              <div class="rounded-2xs border border-ambar-700/30" />
              <div class="rounded-2xs border border-ambar-700/30" />
            </div>
          </div>

          <!-- Símbolo Contactless -->
          <svg
            class="h-6 w-6 text-white/80"
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
            class="rounded-md bg-white/95 px-3 py-1 font-sans text-xl font-black italic tracking-wider text-[#1a1f71] shadow-xs"
          >
            VISA
          </div>

          <!-- MASTERCARD -->
          <div
            v-else-if="marca === 'mastercard'"
            data-testid="logo-mastercard"
            class="flex items-center"
          >
            <div class="size-7 rounded-full bg-[#eb001b] opacity-90 shadow-xs" />
            <div class="-ml-3 size-7 rounded-full bg-[#f79e1b] opacity-90 shadow-xs" />
          </div>

          <!-- AMEX -->
          <div
            v-else-if="marca === 'amex'"
            data-testid="logo-amex"
            class="rounded-md bg-[#006fcf] px-2.5 py-1 text-xs font-black tracking-widest text-white shadow-xs"
          >
            AMERICAN EXPRESS
          </div>

          <!-- Desconocida / Genérica -->
          <div
            v-else
            data-testid="logo-desconocida"
            class="flex items-center gap-1.5 rounded-md bg-white/10 px-2.5 py-1 text-xs font-semibold tracking-wide text-white/90 backdrop-blur-xs"
          >
            <CreditCardIcon class="size-4" aria-hidden="true" />
            <span>TARJETA</span>
          </div>
        </div>
      </div>

      <!-- Número de tarjeta con cifras tabulares -->
      <div class="relative z-10 my-6">
        <p class="text-2xs font-medium uppercase tracking-widest text-botica-200/80">
          Número de tarjeta
        </p>
        <p
          data-testid="numero-tarjeta-visual"
          class="cifras-tabulares mt-1 font-mono text-lg font-semibold tracking-widest text-white sm:text-xl"
        >
          {{ numeroVisual }}
        </p>
      </div>

      <!-- Fila inferior: Titular y Vencimiento -->
      <div class="relative z-10 flex items-end justify-between gap-4">
        <div class="min-w-0 flex-1">
          <p class="text-2xs font-medium uppercase tracking-widest text-botica-200/80">
            Titular
          </p>
          <p
            data-testid="titular-visual"
            class="truncate font-sans text-sm font-semibold tracking-wide text-white"
          >
            {{ titular || 'NOMBRE Y APELLIDO' }}
          </p>
        </div>

        <div class="shrink-0 text-right">
          <p class="text-2xs font-medium uppercase tracking-widest text-botica-200/80">
            Vence
          </p>
          <p
            data-testid="vencimiento-visual"
            class="cifras-tabulares font-mono text-sm font-semibold tracking-widest text-white"
          >
            {{ vencimiento || 'MM/AA' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Formulario de interacción con la tarjeta -->
    <div
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
            />
          </div>

          <div>
            <label
              for="tarjeta-cvv"
              class="mb-1 block text-xs font-medium text-texto-secundario"
            >
              CVV / CVC
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
