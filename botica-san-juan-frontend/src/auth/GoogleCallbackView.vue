<script setup lang="ts">
/**
 * GoogleCallbackView · Donde aterriza el navegador al volver de Google
 * ---------------------------------------------------------------------------
 * QUÉ HACE Y QUÉ NO
 *
 * No decide nada: la API ya resolvió quién es la persona. Esta pantalla
 * recoge el código efímero de la dirección, lo canjea por el token y lleva a
 * donde corresponda.
 *
 * SE LIMPIA LA DIRECCIÓN ANTES DE NAVEGAR
 *
 * El código ya se gastó al canjearlo, pero seguir mostrándolo en la barra
 * invita a copiarlo y pegarlo. Se sustituye por la ruta sin parámetros con
 * `replace`, de modo que además el botón «atrás» no vuelva a esta pantalla a
 * intentar canjear un código muerto.
 *
 * LA ALTURA NO CAMBIA ENTRE ESTADOS
 *
 * Comprobando, logrado y fallido ocupan lo mismo. Al resolverse la llamada,
 * la tarjeta no crece ni empuja nada.
 */
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CircleCheck, CircleX, Loader2 } from 'lucide-vue-next'

import { canjearCodigo, explicar } from '@/services/google'
import { useAuthStore } from '@/stores/auth'

const ruta = useRoute()
const router = useRouter()
const auth = useAuthStore()

const estado = ref<'canjeando' | 'listo' | 'fallo'>('canjeando')
const mensaje = ref('')
const faltaDni = ref(false)

onMounted(async () => {
  const codigo = ruta.query.codigo
  const error = ruta.query.error

  if (typeof error === 'string') {
    estado.value = 'fallo'
    mensaje.value = explicar(error)
    return
  }

  if (typeof codigo !== 'string' || codigo === '') {
    /* Se llega aquí escribiendo la dirección a mano. No es un error del
       sistema, pero tampoco una sesión: se dice y se ofrece la salida. */
    estado.value = 'fallo'
    mensaje.value = 'Este enlace está incompleto. Vuelve a entrar desde el botón de Google.'
    return
  }

  try {
    const sesion = await canjearCodigo(codigo)

    localStorage.setItem('auth_token', sesion.token)
    auth.setUser(sesion.user)

    faltaDni.value = sesion.necesita_dni
    estado.value = 'listo'

    /* Se deja ver un momento el resultado antes de navegar. Sin esta pausa
       la pantalla parpadea y la persona no llega a saber qué pasó. */
    window.setTimeout(() => {
      router.replace(sesion.necesita_dni ? '/perfil' : '/client/home')
    }, 1200)
  } catch (e: unknown) {
    const datos = (e as { response?: { data?: { message?: string } } }).response?.data

    estado.value = 'fallo'
    mensaje.value = datos?.message ?? 'No pudimos completar el acceso. Inténtalo de nuevo.'
  }
})
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-superficie-base p-4">
    <div class="w-full max-w-md rounded-3xl border border-borde-sutil bg-superficie-elevada p-8 shadow-xl">
      <div class="flex min-h-56 flex-col items-center justify-center text-center">
        <template v-if="estado === 'canjeando'">
          <Loader2 class="size-10 animate-spin text-texto-acento" />
          <p
            class="mt-4 text-texto-secundario"
            role="status"
          >
            Entrando con Google…
          </p>
        </template>

        <template v-else-if="estado === 'listo'">
          <div class="flex size-16 items-center justify-center rounded-full bg-exito-50">
            <CircleCheck class="size-8 text-exito-600" />
          </div>
          <h1 class="mt-5 text-2xl font-bold text-texto-primario">
            Listo
          </h1>
          <p class="mt-2 text-texto-secundario">
            {{
              faltaDni
                ? 'Te llevamos a tu perfil: nos falta tu DNI para poder emitir tus comprobantes.'
                : 'Ya estás dentro.'
            }}
          </p>
        </template>

        <template v-else>
          <div class="flex size-16 items-center justify-center rounded-full bg-peligro-50">
            <CircleX class="size-8 text-peligro-600" />
          </div>
          <h1 class="mt-5 text-2xl font-bold text-texto-primario">
            No pudimos entrar
          </h1>
          <p class="mt-2 text-texto-secundario">
            {{ mensaje }}
          </p>
          <router-link
            to="/login"
            class="mt-6 inline-flex items-center justify-center rounded-xl border border-borde-control px-5 py-3 font-medium text-texto-primario transition-colors hover:bg-superficie-hundida"
          >
            Volver al acceso
          </router-link>
        </template>
      </div>
    </div>
  </main>
</template>
