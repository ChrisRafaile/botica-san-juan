<script setup lang="ts">
/**
 * RestablecerContrasenaView · Elegir contraseña nueva
 * ---------------------------------------------------------------------------
 * EL TOKEN NO SE GUARDA EN NINGUNA PARTE
 *
 * Llega por la URL, vive en memoria mientras la pestaña está abierta y se
 * envía una sola vez. No se escribe en localStorage ni se registra en consola:
 * mientras está vigente equivale a la contraseña de la cuenta.
 *
 * QUÉ PASA DESPUÉS DE CAMBIARLA
 *
 * El backend revoca todas las sesiones de esa cuenta, así que no se inicia
 * sesión automáticamente aquí. Eso es deliberado: si la contraseña se cambia
 * porque alguien más la tenía, entrar sin volver a escribirla dejaría la
 * puerta medio abierta. Se envía al acceso.
 */
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { CircleCheck, CircleX, KeyRound, Loader2 } from 'lucide-vue-next'
import MedidorDeContrasena from '@/components/MedidorDeContrasena.vue'
import { restablecerContrasena } from '@/services/correos'
import { erroresPorCampo } from '@/utils/validacionServidor'

const ruta = useRoute()

const token = computed(() => (typeof ruta.query.token === 'string' ? ruta.query.token : ''))
const correo = computed(() => (typeof ruta.query.correo === 'string' ? ruta.query.correo : ''))

const contrasena = ref('')
const confirmacion = ref('')
const enviando = ref(false)
const logrado = ref(false)
const mensaje = ref('')
const errores = ref<Record<string, string>>({})

/** El enlace tiene que traer las dos piezas; el servidor necesita ambas. */
const enlaceCompleto = computed(() => token.value !== '' && correo.value !== '')

const coincide = computed(
  () => confirmacion.value === '' || contrasena.value === confirmacion.value,
)

const puedeEnviar = computed(
  () =>
    enlaceCompleto.value &&
    !enviando.value &&
    contrasena.value.length >= 8 &&
    contrasena.value === confirmacion.value,
)

async function enviar() {
  if (!puedeEnviar.value) return

  enviando.value = true
  mensaje.value = ''
  errores.value = {}

  try {
    mensaje.value = await restablecerContrasena({
      token: token.value,
      email: correo.value,
      password: contrasena.value,
      password_confirmation: confirmacion.value,
    })
    logrado.value = true
  } catch (error: unknown) {
    const datos = (error as { response?: { data?: unknown } }).response?.data
    errores.value = erroresPorCampo(datos)

    mensaje.value =
      (datos as { message?: string } | undefined)?.message ??
      'No pudimos cambiar tu contraseña. Vuelve a intentarlo.'
  } finally {
    /* Siempre se limpian los campos: si el token ya se gastó, dejar escrita
       la contraseña en pantalla no sirve de nada y queda a la vista. */
    enviando.value = false
    contrasena.value = ''
    confirmacion.value = ''
  }
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-superficie-base p-4">
    <div class="w-full max-w-md rounded-3xl border border-borde-sutil bg-superficie-elevada p-8 shadow-xl">
      <!-- Enlace logrado -->
      <div
        v-if="logrado"
        class="flex min-h-64 flex-col items-center justify-center text-center"
      >
        <div class="flex size-16 items-center justify-center rounded-full bg-botica-50">
          <CircleCheck class="size-8 text-botica-600" />
        </div>
        <h1 class="mt-5 text-2xl font-bold text-texto-primario">
          Contraseña actualizada
        </h1>
        <p class="mt-2 text-texto-secundario">
          {{ mensaje }}
        </p>
        <p class="mt-3 text-sm text-texto-terciario">
          Por seguridad cerramos las sesiones abiertas de tu cuenta. Entra de
          nuevo con tu contraseña nueva.
        </p>
        <router-link
          to="/login"
          class="mt-6 inline-flex items-center justify-center rounded-xl bg-clinico-600 px-5 py-3 font-medium text-white transition-colors hover:bg-clinico-700"
        >
          Iniciar sesión
        </router-link>
      </div>

      <!-- Enlace incompleto: no hay formulario que ofrecer -->
      <div
        v-else-if="!enlaceCompleto"
        class="flex min-h-64 flex-col items-center justify-center text-center"
      >
        <div class="flex size-16 items-center justify-center rounded-full bg-peligro-50">
          <CircleX class="size-8 text-peligro-600" />
        </div>
        <h1 class="mt-5 text-2xl font-bold text-texto-primario">
          Este enlace está incompleto
        </h1>
        <p class="mt-2 text-texto-secundario">
          Ábrelo desde el correo que te enviamos, o pide uno nuevo.
        </p>
        <router-link
          to="/forgot-password"
          class="mt-6 inline-flex items-center justify-center rounded-xl border border-borde-control px-5 py-3 font-medium text-texto-primario transition-colors hover:bg-superficie-hundida"
        >
          Pedir un enlace nuevo
        </router-link>
      </div>

      <!-- Formulario -->
      <template v-else>
        <div class="text-center">
          <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-clinico-100">
            <KeyRound class="size-8 text-clinico-600" />
          </div>
          <h1 class="mt-5 text-2xl font-bold text-texto-primario">
            Elige una contraseña nueva
          </h1>
          <p class="mt-2 text-sm text-texto-secundario">
            Para la cuenta <span class="font-medium text-texto-primario">{{ correo }}</span>
          </p>
        </div>

        <form
          class="mt-7 space-y-5"
          @submit.prevent="enviar"
        >
          <div>
            <label
              for="contrasena-nueva"
              class="mb-2 block text-sm font-medium text-texto-secundario"
            >
              Contraseña nueva
            </label>
            <input
              id="contrasena-nueva"
              v-model="contrasena"
              type="password"
              autocomplete="new-password"
              required
              class="w-full rounded-xl border border-borde-control bg-superficie-base px-4 py-3 text-texto-primario transition-all focus:border-transparent focus:ring-2 focus:ring-clinico-500"
            />
            <MedidorDeContrasena
              :contrasena="contrasena"
              class="mt-3"
            />
          </div>

          <div>
            <label
              for="contrasena-confirmacion"
              class="mb-2 block text-sm font-medium text-texto-secundario"
            >
              Repítela
            </label>
            <input
              id="contrasena-confirmacion"
              v-model="confirmacion"
              type="password"
              autocomplete="new-password"
              required
              :aria-invalid="!coincide"
              class="w-full rounded-xl border bg-superficie-base px-4 py-3 text-texto-primario transition-all focus:border-transparent focus:ring-2 focus:ring-clinico-500"
              :class="coincide ? 'border-borde-control' : 'border-peligro-600'"
            />
            <!-- Se reserva el hueco del aviso para que el botón no salte
                 cuando aparece. -->
            <p class="mt-1.5 min-h-5 text-xs text-peligro-700">
              <span v-if="!coincide">Las dos contraseñas no coinciden.</span>
            </p>
          </div>

          <button
            type="submit"
            :disabled="!puedeEnviar"
            class="flex w-full items-center justify-center rounded-xl bg-clinico-600 px-4 py-3 font-medium text-white transition-colors hover:bg-clinico-700 disabled:bg-clinico-400"
          >
            <Loader2
              v-if="enviando"
              class="mr-2 size-4 animate-spin"
            />
            {{ enviando ? 'Guardando…' : 'Guardar contraseña' }}
          </button>
        </form>

        <!-- Fallo. El servidor no distingue token caducado de token ya usado:
             un solo mensaje para los dos, igual que en el backend. -->
        <div
          v-if="mensaje && !logrado"
          class="mt-6 rounded-xl border border-peligro-500/30 bg-peligro-50 p-4"
          role="alert"
        >
          <p class="text-sm text-peligro-700">
            {{ errores.token ?? mensaje }}
          </p>
          <router-link
            to="/forgot-password"
            class="mt-2 inline-block text-sm font-medium text-clinico-700 underline"
          >
            Pedir un enlace nuevo
          </router-link>
        </div>
      </template>
    </div>
  </main>
</template>
