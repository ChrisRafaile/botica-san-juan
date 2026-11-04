<script setup lang="ts">
/**
 * VerificarCorreoView · Resultado de confirmar el correo
 * ---------------------------------------------------------------------------
 * ESTA PANTALLA NO COMPRUEBA NADA: SOLO CUENTA LO QUE YA PASÓ
 *
 * La comprobación ocurre en la API. El enlace del correo apunta allí, firmado;
 * la API valida la firma, marca el correo y redirige aquí con el resultado en
 * `?resultado=`.
 *
 * El primer diseño era el contrario —la pantalla recibía el enlace firmado y
 * lo invocaba con `fetch`— y no funcionó: la API y el portal están en orígenes
 * distintos, así que esa llamada era una petición entre orígenes; en la prueba
 * la bloqueó el navegador antes de llegar siquiera a CORS. Abrir CORS para un
 * enlace que viaja por correo era el precio, y no valía la pena. Navegar es
 * más simple que llamar.
 *
 * Como consecuencia aquí no hay estado de carga ni fallo de red: cuando esta
 * pantalla se pinta, la decisión ya está tomada.
 */
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { CircleCheck, CircleX, MailCheck } from 'lucide-vue-next'

const ruta = useRoute()

const resultado = computed(() =>
  typeof ruta.query.resultado === 'string' ? ruta.query.resultado : 'invalido',
)

const confirmado = computed(() => resultado.value === 'confirmado')
const yaEstaba = computed(() => resultado.value === 'ya-confirmado')
const logrado = computed(() => confirmado.value || yaEstaba.value)
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-superficie-base p-4">
    <div class="w-full max-w-md rounded-3xl border border-borde-sutil bg-superficie-elevada p-8 shadow-xl">
      <div class="flex min-h-56 flex-col items-center justify-center text-center">
        <template v-if="logrado">
          <div class="flex size-16 items-center justify-center rounded-full bg-botica-50">
            <component
              :is="yaEstaba ? MailCheck : CircleCheck"
              class="size-8 text-botica-600"
            />
          </div>
          <h1 class="mt-5 text-2xl font-bold text-texto-primario">
            {{ yaEstaba ? 'Ya estaba confirmado' : 'Correo confirmado' }}
          </h1>
          <p class="mt-2 text-texto-secundario">
            {{
              yaEstaba
                ? 'Tu correo ya estaba confirmado. No hace falta que hagas nada más.'
                : 'Listo. Ya podemos avisarte del estado de tus pedidos.'
            }}
          </p>
          <router-link
            to="/login"
            class="mt-6 inline-flex items-center justify-center rounded-xl bg-clinico-600 px-5 py-3 font-medium text-white transition-colors hover:bg-clinico-700"
          >
            Ir a iniciar sesión
          </router-link>
        </template>

        <template v-else>
          <div class="flex size-16 items-center justify-center rounded-full bg-peligro-50">
            <CircleX class="size-8 text-peligro-600" />
          </div>
          <h1 class="mt-5 text-2xl font-bold text-texto-primario">
            Este enlace no sirve
          </h1>
          <p class="mt-2 text-texto-secundario">
            Puede que haya vencido o que ya hayas cambiado de correo. Entra a tu
            cuenta y pide uno nuevo desde tu perfil.
          </p>
          <!-- Pedir otro enlace exige sesión, así que se envía al acceso en
               lugar de ofrecer un botón que fallaría con 401. -->
          <router-link
            to="/login"
            class="mt-6 inline-flex items-center justify-center rounded-xl border border-borde-control px-5 py-3 font-medium text-texto-primario transition-colors hover:bg-superficie-hundida"
          >
            Inicia sesión y pide otro
          </router-link>
        </template>
      </div>
    </div>
  </main>
</template>
