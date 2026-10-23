<!--
  Cierre de sesión.
  ===========================================================================
  QUÉ HACÍA ANTES Y POR QUÉ SE REHIZO

  La pantalla anterior mostraba una cuenta atrás de cinco segundos con una
  barra de progreso y veinte partículas flotando, y SOLO AL TERMINAR llamaba a
  `authStore.logout()`. Eso tenía tres problemas, y únicamente el primero era
  estético:

  1. La sesión seguía abierta durante cinco segundos después de pulsar «cerrar
     sesión». En un punto de venta compartido entre turnos, eso es tiempo real
     en el que la cuenta anterior continúa activa en un equipo que su dueña ya
     dio por cerrado.

  2. `onUnmounted` estaba vacío —con un comentario que decía «limpiar
     intervalos si hace falta»— mientras un `setInterval` seguía corriendo. Si
     alguien navegaba a otro sitio durante la cuenta atrás, el temporizador
     continuaba, terminaba y disparaba el cierre de sesión y un salto a
     /login desde una pantalla que no tenía nada que ver.

  3. La barra no medía nada. Avanzaba con el reloj, no con el progreso de la
     petición: marcaba el 100 % tanto si el servidor había respondido como si
     estaba fallando.

  AHORA la sesión se cierra de inmediato y la pantalla informa del estado real
  de esa operación. Lo que se ve es consecuencia de lo que ha pasado, no un
  adorno que corre en paralelo.
-->
<template>
  <div
    class="flex min-h-dvh items-center justify-center bg-superficie-base px-4 py-12"
  >
    <div
      ref="tarjeta"
      class="w-full max-w-sm rounded-3xl border border-borde-sutil bg-superficie-elevada p-8 text-center shadow-lg"
    >
      <!--
        El estado se anuncia a los lectores de pantalla. `polite` y no
        `assertive`: interrumpir la lectura en curso para decir «cerrando
        sesión» no aporta nada; esperar a que termine la frase, sí.
      -->
      <div
        class="mx-auto flex size-16 items-center justify-center rounded-full"
        :class="
          estado === 'error'
            ? 'bg-peligro-50 text-peligro-700 dark:bg-peligro-500/10'
            : 'bg-botica-50 text-texto-marca'
        "
        aria-hidden="true"
      >
        <LoaderCircle
          v-if="estado === 'cerrando'"
          class="size-7 animate-spin"
        />
        <CheckCircle2
          v-else-if="estado === 'cerrada'"
          class="size-7"
        />
        <AlertCircle
          v-else
          class="size-7"
        />
      </div>

      <h1 class="mt-5 text-xl font-bold tracking-tight text-texto-primario">
        {{ titulo }}
      </h1>

      <!--
        Reconocimiento del rol. No es adorno: el sistema lo usan tres perfiles
        con permisos distintos y, en un equipo compartido entre turnos, ver con
        qué cuenta se estaba trabajando al cerrarla evita el error de creer que
        sigues en la tuya.
      -->
      <p
        v-if="estado !== 'cerrando' && rol"
        class="mt-1 text-sm text-texto-secundario"
      >
        Gracias por tu trabajo como <span class="font-semibold text-texto-marca">{{ rol }}</span>.
      </p>

      <!--
        Altura reservada: los tres mensajes tienen longitudes distintas y, sin
        reserva, la tarjeta cambia de alto al pasar de uno a otro y empuja lo
        que hay debajo justo mientras se lee.
      -->
      <p
        class="mx-auto mt-2 min-h-10 max-w-xs text-sm text-texto-secundario"
        role="status"
        aria-live="polite"
      >
        {{ mensaje }}
      </p>

      <RouterLink
        v-if="estado !== 'cerrando'"
        to="/login"
        class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-full bg-botica-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-botica-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
      >
        <LogIn class="size-4" />
        Ir al acceso ahora
      </RouterLink>

      <!--
        La cuenta atrás informa, no retiene: la sesión YA está cerrada cuando
        empieza. Es la diferencia con la versión anterior, donde el contador
        corría ANTES de cerrar nada y había que esperarlo.

        `aria-live="off"` a propósito: un número que cambia cada segundo
        anunciado por un lector de pantalla es ruido que tapa el mensaje
        importante, que ya se anunció arriba.
      -->
      <p
        v-if="estado === 'cerrada'"
        class="mt-3 min-h-4 text-xs text-texto-terciario"
        aria-live="off"
      >
        Te llevamos al acceso en {{ segundos }} s.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { gsap } from 'gsap'
import { AlertCircle, CheckCircle2, LoaderCircle, LogIn } from 'lucide-vue-next'

import { useAuthStore } from '../stores/auth'

type Estado = 'cerrando' | 'cerrada' | 'error'

const router = useRouter()
const authStore = useAuthStore()

const SEGUNDOS_DE_CORTESIA = 4

const tarjeta = ref<HTMLElement | null>(null)
const estado = ref<Estado>('cerrando')
const nombre = ref('')
const rol = ref('')
const segundos = ref(SEGUNDOS_DE_CORTESIA)

/* Un temporizador para la cuenta atrás y uno para la salida, ambos guardados
   para poder cancelarlos. La versión anterior dejaba el suyo vivo. */
let cuenta: ReturnType<typeof setInterval> | null = null
let salida: ReturnType<typeof setTimeout> | null = null
let animacion: gsap.core.Tween | null = null

/** El rol que guarda la cuenta, dicho como lo diría una persona. */
const ROLES: Record<string, string> = {
  administrador: 'Administrador',
  vendedor: 'Vendedor',
  cliente: 'Cliente',
}

const titulo = computed(() => {
  if (estado.value === 'cerrando') return 'Cerrando sesión'
  if (estado.value === 'cerrada') return 'Sesión cerrada'

  return 'Sesión cerrada en este equipo'
})

const mensaje = computed(() => {
  if (estado.value === 'cerrando') return 'Estamos cerrando tu sesión de forma segura.'

  if (estado.value === 'cerrada') {
    return nombre.value
      ? `¡Hasta pronto, ${nombre.value}! Tu sesión se cerró de forma segura.`
      : 'Tu sesión se cerró de forma segura.'
  }

  /* El caso de error merece una explicación honesta: los datos locales se
     borraron igualmente, así que en este equipo la sesión está cerrada, pero
     no se pudo confirmar al servidor. Decir solo «error» dejaría a la persona
     sin saber si tiene que volver a intentarlo. */
  return 'No pudimos confirmarlo con el servidor, pero tus datos de acceso ya se borraron de este equipo.'
})

/** Respeta la preferencia del sistema en lugar de imponer movimiento. */
const movimientoReducido = () =>
  typeof window !== 'undefined' &&
  window.matchMedia('(prefers-reduced-motion: reduce)').matches

onMounted(async () => {
  /* Se leen ANTES de cerrar la sesión: después, el store ya no tiene usuario
     y la despedida saldría sin nombre ni rol. */
  nombre.value = authStore.user?.nombre?.split(' ')[0] ?? ''
  rol.value = ROLES[authStore.user?.rol ?? ''] ?? ''

  /* Solo `opacity` y `transform`: son las dos propiedades que el navegador
     resuelve en el compositor sin rehacer el diseño de la página. */
  if (tarjeta.value && !movimientoReducido()) {
    animacion = gsap.from(tarjeta.value, {
      opacity: 0,
      scale: 0.96,
      duration: 0.35,
      ease: 'power2.out',
    })
  }

  /* LO PRIMERO, sin esperar a ninguna animación. */
  try {
    await authStore.logout()
    estado.value = 'cerrada'
  } catch {
    estado.value = 'error'
  }

  cuenta = setInterval(() => {
    segundos.value = Math.max(0, segundos.value - 1)
  }, 1000)

  salida = setTimeout(() => router.push('/login'), SEGUNDOS_DE_CORTESIA * 1000)
})

/* Limpieza real, no un comentario que dice que haría falta. Sin esto, salir
   de esta pantalla antes de tiempo dejaba vivo el temporizador y acababa
   redirigiendo a /login desde donde fuera que estuviese la persona. */
onBeforeUnmount(() => {
  if (salida) clearTimeout(salida)
  if (cuenta) clearInterval(cuenta)
  animacion?.kill()
})
</script>
