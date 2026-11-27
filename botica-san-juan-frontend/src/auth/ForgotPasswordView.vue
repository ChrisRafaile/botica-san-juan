<template>
  <div class="min-h-dvh bg-superficie-base lg:grid lg:grid-cols-2">
    <!-- Panel de marca: cabecera en móvil, columna en escritorio. -->
    <aside class="relative isolate overflow-hidden bg-clinico-700 px-6 py-8 text-white sm:px-10 lg:flex lg:flex-col lg:justify-between lg:py-12">
      <div class="absolute -right-24 -top-24 -z-10 size-72 rounded-full bg-clinico-500/40" aria-hidden="true" />
      <div class="absolute -bottom-28 -left-16 -z-10 size-80 rounded-full bg-clinico-900/50" aria-hidden="true" />

      <RouterLink to="/" class="foco-dentro inline-flex items-center gap-3 rounded-xl">
        <span class="flex size-11 items-center justify-center rounded-xl bg-white/15">
          <PillIcon class="size-6" aria-hidden="true" />
        </span>
        <span>
          <span class="block text-lg font-bold leading-tight">Boticas San Juan</span>
          <span class="block text-sm text-white/80">San Juan de Lurigancho</span>
        </span>
      </RouterLink>

      <div class="hidden lg:block">
        <p class="text-3xl font-bold leading-tight text-balance">
          Tu cuenta y tus pedidos, protegidos.
        </p>
        <p class="mt-4 max-w-md text-white/85">
          El enlace de recuperación caduca en 60 minutos y solo sirve una vez. Nadie del
          equipo te pedirá tu contraseña.
        </p>
      </div>

      <ul class="mt-6 hidden gap-3 lg:grid" aria-label="Garantías de la botica">
        <li v-for="sello in sellos" :key="sello.texto" class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3 backdrop-blur-sm">
          <component :is="sello.icono" class="size-5 shrink-0 text-clinico-200" aria-hidden="true" />
          <span class="text-sm font-medium">{{ sello.texto }}</span>
        </li>
      </ul>
    </aside>

    <!-- Formulario -->
    <main class="flex items-center justify-center px-6 py-12 sm:px-10">
      <div class="w-full max-w-md">
        <RouterLink
          to="/login"
          class="foco-dentro mb-8 inline-flex items-center gap-2 rounded-md text-sm font-medium text-texto-secundario transition-colors hover:text-texto-primario"
        >
          <ArrowLeftIcon class="size-4" aria-hidden="true" />
          Volver al inicio de sesión
        </RouterLink>

        <!-- Misma caja para los dos estados: el cambio no desplaza nada. -->
        <Transition name="paso" mode="out-in">
          <section v-if="!enviado" key="formulario" aria-labelledby="titulo-olvide">
            <div class="mb-6 flex size-12 items-center justify-center rounded-2xl bg-clinico-100 dark:bg-clinico-500/15">
              <KeyRoundIcon class="size-6 text-clinico-700 dark:text-clinico-300" aria-hidden="true" />
            </div>
            <h1 id="titulo-olvide" class="text-3xl font-bold text-texto-primario">
              Recupera tu contraseña
            </h1>
            <p class="mt-3 text-texto-secundario">
              Escribe el correo de tu cuenta. Si existe, te enviaremos un enlace para elegir una
              contraseña nueva.
            </p>

            <form class="mt-8 space-y-5" novalidate @submit.prevent="enviar">
              <div>
                <label for="correo-olvide" class="mb-2 block text-sm font-medium text-texto-primario">
                  Correo electrónico
                </label>
                <div class="relative">
                  <MailIcon class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-texto-terciario" aria-hidden="true" />
                  <input
                    id="correo-olvide"
                    ref="campo"
                    v-model.trim="email"
                    type="email"
                    inputmode="email"
                    autocomplete="email"
                    autofocus
                    placeholder="tu@correo.com"
                    :aria-invalid="!!errorCampo"
                    :aria-describedby="errorCampo ? 'error-correo-olvide' : undefined"
                    :class="[
                      'w-full rounded-xl border bg-superficie-elevada py-3 pl-11 pr-4 text-texto-primario transition-colors focus:outline-none focus:ring-2 focus:ring-clinico-500',
                      errorCampo ? 'border-peligro-500' : 'border-borde-control'
                    ]"
                    @input="errorCampo = ''"
                  />
                </div>
                <p v-if="errorCampo" id="error-correo-olvide" class="mt-2 flex items-center gap-1.5 text-sm text-peligro-700 dark:text-peligro-300">
                  <CircleAlertIcon class="size-4 shrink-0" aria-hidden="true" />
                  {{ errorCampo }}
                </p>
              </div>

              <button
                type="submit"
                :disabled="cargando"
                class="foco-dentro flex w-full items-center justify-center gap-2 rounded-xl bg-clinico-600 px-4 py-3 font-semibold text-white transition-colors hover:bg-clinico-700 disabled:cursor-not-allowed disabled:opacity-70"
              >
                <LoaderCircleIcon v-if="cargando" class="size-5 animate-spin" aria-hidden="true" />
                {{ cargando ? 'Enviando…' : 'Enviar enlace' }}
              </button>

              <p v-if="errorGeneral" role="alert" class="flex items-start gap-2 rounded-xl border border-peligro-500/30 bg-peligro-50 p-4 text-sm text-peligro-700 dark:bg-peligro-500/10 dark:text-peligro-300">
                <CircleAlertIcon class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                {{ errorGeneral }}
              </p>
            </form>
          </section>

          <section v-else key="enviado" aria-labelledby="titulo-enviado" aria-live="polite">
            <div class="mb-6 flex size-12 items-center justify-center rounded-2xl bg-botica-100 dark:bg-botica-500/15">
              <MailCheckIcon class="size-6 text-botica-700 dark:text-botica-300" aria-hidden="true" />
            </div>
            <h1 id="titulo-enviado" ref="tituloEnviado" tabindex="-1" class="text-3xl font-bold text-texto-primario focus:outline-none">
              Revisa tu correo
            </h1>
            <!-- El texto lo redacta el backend y es el mismo exista o no la
                 cuenta. No se reescribe aquí: arriesgaría prometer un envío. -->
            <p class="mt-3 text-texto-secundario">{{ mensaje }}</p>
            <p class="mt-2 text-sm text-texto-terciario">
              Enviado a <span class="font-medium text-texto-secundario">{{ correoEnviado }}</span>. Mira también en spam.
            </p>

            <div class="mt-8 space-y-3">
              <button
                type="button"
                :disabled="restante > 0 || cargando"
                class="foco-dentro flex w-full items-center justify-center gap-2 rounded-xl border border-borde-control bg-superficie-elevada px-4 py-3 font-semibold text-texto-primario transition-colors hover:bg-superficie-hundida disabled:cursor-not-allowed disabled:opacity-60"
                @click="reenviar"
              >
                <LoaderCircleIcon v-if="cargando" class="size-5 animate-spin" aria-hidden="true" />
                <RotateCwIcon v-else class="size-5" aria-hidden="true" />
                <span class="cifras-tabulares">
                  {{ restante > 0 ? `Reenviar en ${Math.floor(restante / 60)}:${String(restante % 60).padStart(2, '0')}` : 'Reenviar enlace' }}
                </span>
              </button>
              <button
                type="button"
                class="foco-dentro w-full rounded-xl px-4 py-2 text-sm font-medium text-texto-acento hover:underline"
                @click="cambiarCorreo"
              >
                ¿Usaste otro correo?
              </button>
              <p v-if="errorGeneral" role="alert" class="text-sm text-peligro-700 dark:text-peligro-300">{{ errorGeneral }}</p>
            </div>
          </section>
        </Transition>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
/**
 * Recuperación de contraseña · diseño de pantalla dividida.
 *
 * El texto de éxito viene del backend y es idéntico exista o no la cuenta:
 * el formulario no puede servir para comprobar quién es cliente de la botica.
 * Por la misma razón el contador de reenvío arranca siempre, también cuando el
 * correo no tiene cuenta; detenerlo solo en un caso delataría la diferencia.
 */
import { nextTick, onBeforeUnmount, ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ArrowLeftIcon,
  BadgeCheckIcon,
  CircleAlertIcon,
  KeyRoundIcon,
  LoaderCircleIcon,
  MailCheckIcon,
  MailIcon,
  PillIcon,
  ReceiptTextIcon,
  RotateCwIcon,
  ShieldCheckIcon,
} from 'lucide-vue-next'
import { solicitarEnlace } from '@/services/correos'

const ESPERA_REENVIO = 60

const sellos = [
  { texto: 'Autorizado por DIGEMID', icono: BadgeCheckIcon },
  { texto: 'Comprobantes electrónicos SUNAT', icono: ReceiptTextIcon },
  { texto: 'Conexión cifrada por HTTPS', icono: ShieldCheckIcon },
]

const email = ref('')
const correoEnviado = ref('')
const mensaje = ref('')
const enviado = ref(false)
const cargando = ref(false)
const errorCampo = ref('')
const errorGeneral = ref('')
const restante = ref(0)
const campo = ref<HTMLInputElement | null>(null)
const tituloEnviado = ref<HTMLElement | null>(null)

let temporizador: ReturnType<typeof setInterval> | null = null

function detenerContador() {
  if (temporizador) clearInterval(temporizador)
  temporizador = null
}

function iniciarContador() {
  detenerContador()
  restante.value = ESPERA_REENVIO
  temporizador = setInterval(() => {
    restante.value -= 1
    if (restante.value <= 0) detenerContador()
  }, 1000)
}

const FORMATO_CORREO = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/

function validar(valor: string) {
  if (!valor) return 'Escribe tu correo electrónico.'
  if (!FORMATO_CORREO.test(valor)) return 'Ese correo no parece válido. Revisa que tenga @ y dominio.'
  return ''
}

async function pedirEnlace(destino: string) {
  cargando.value = true
  errorGeneral.value = ''
  try {
    mensaje.value = await solicitarEnlace(destino)
    correoEnviado.value = destino
    iniciarContador()
    return true
  } catch (error: unknown) {
    const respuesta = (error as { response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } } }).response
    if (respuesta?.status === 429) {
      errorGeneral.value = 'Has pedido varios enlaces seguidos. Espera unos minutos antes de volver a intentarlo.'
    } else if (respuesta?.status === 422 && respuesta.data?.errors?.email?.[0]) {
      errorCampo.value = respuesta.data.errors.email[0]
    } else {
      errorGeneral.value = respuesta?.data?.message ?? 'No pudimos procesar la solicitud. Revisa tu conexión e inténtalo de nuevo.'
    }
    return false
  } finally {
    cargando.value = false
  }
}

async function enviar() {
  errorCampo.value = validar(email.value)
  if (errorCampo.value) {
    campo.value?.focus()
    return
  }
  if (await pedirEnlace(email.value)) {
    enviado.value = true
    await nextTick()
    tituloEnviado.value?.focus()
  }
}

async function reenviar() {
  if (restante.value > 0 || cargando.value) return
  await pedirEnlace(correoEnviado.value)
}

async function cambiarCorreo() {
  detenerContador()
  restante.value = 0
  enviado.value = false
  errorGeneral.value = ''
  email.value = correoEnviado.value
  await nextTick()
  campo.value?.focus()
}

onBeforeUnmount(detenerContador)
</script>

<style scoped>
.paso-enter-active,
.paso-leave-active {
  transition:
    opacity var(--duracion-normal) var(--ease-salida),
    transform var(--duracion-normal) var(--ease-salida);
}

.paso-enter-from {
  opacity: 0;
  transform: translateY(8px);
}

.paso-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

@media (prefers-reduced-motion: reduce) {
  .paso-enter-active,
  .paso-leave-active {
    transition: none;
  }
}
</style>
