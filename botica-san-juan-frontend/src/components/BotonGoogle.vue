<script setup lang="ts">
/**
 * BotonGoogle · Acceso con una cuenta de Google
 * ---------------------------------------------------------------------------
 * SI NO ESTÁ CONFIGURADO, NO APARECE
 *
 * El botón se esconde cuando la API responde que el acceso con Google no está
 * disponible en este entorno. Un botón visible que falla al pulsarlo es peor
 * que no tenerlo: la persona cree que el problema es suyo y lo intenta otra
 * vez.
 *
 * La comprobación se hace pidiendo la URL al cargar, que es la misma llamada
 * que haría el botón: si responde 503, no hay botón. Cuesta una petición y
 * evita el peor momento de este flujo.
 *
 * EL LOGOTIPO VA EN SVG, DIBUJADO AQUÍ
 *
 * No se carga desde un dominio de Google: una etiqueta de imagen contra un
 * tercero le cuenta a ese tercero quién visita la pantalla de acceso, y
 * además deja el botón roto el día que esa dirección cambie.
 */
import { onMounted, ref } from 'vue'
import { Loader2 } from 'lucide-vue-next'

import { entrarConGoogle } from '@/services/google'
import api from '@/services/api'

const props = withDefaults(defineProps<{ rotulo?: string }>(), {
  rotulo: 'Continuar con Google',
})

const disponible = ref(false)
const entrando = ref(false)
const aviso = ref('')

onMounted(async () => {
  try {
    /* La cabecera silencia el aviso global de error: un 503 aquí significa
       «no está configurado», que es la respuesta que este sondeo busca, no
       una avería que haya que anunciarle a quien entra. */
    await api.get('/auth/google/redirect', { headers: { 'X-Silenciar-Aviso': '1' } })
    disponible.value = true
  } catch {
    /* 503 (sin credenciales) o cualquier otro fallo: el botón no se enseña.
       No se avisa de nada: quien entra a la pantalla de acceso no tiene por
       qué enterarse de qué integraciones faltan por configurar. */
    disponible.value = false
  }
})

async function pulsar() {
  entrando.value = true
  aviso.value = ''

  try {
    await entrarConGoogle()
    /* No se baja `entrando`: la navegación a Google se lleva la página por
       delante, y apagar el indicador antes dejaría el botón pulsable otra
       vez durante ese instante. */
  } catch {
    entrando.value = false
    aviso.value = 'No pudimos abrir el acceso con Google. Inténtalo de nuevo.'
  }
}
</script>

<template>
  <div v-if="disponible">
    <div class="my-4 flex items-center gap-3">
      <span class="h-px flex-1 bg-borde-sutil" />
      <span class="text-xs text-texto-terciario">o</span>
      <span class="h-px flex-1 bg-borde-sutil" />
    </div>

    <button
      type="button"
      :disabled="entrando"
      class="flex w-full items-center justify-center gap-3 rounded-xl border border-borde-control bg-superficie-elevada px-4 py-3 font-medium text-texto-primario transition-colors hover:bg-superficie-hundida disabled:opacity-60"
      @click="pulsar"
    >
      <Loader2
        v-if="entrando"
        class="size-5 animate-spin"
      />
      <svg
        v-else
        class="size-5"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path
          fill="#4285F4"
          d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.4a5.5 5.5 0 0 1-2.4 3.6v3h3.9c2.3-2.1 3.6-5.2 3.6-8.8z"
        />
        <path
          fill="#34A853"
          d="M12 24c3.2 0 6-1.1 8-2.9l-3.9-3a7.2 7.2 0 0 1-10.7-3.8h-4v3.1A12 12 0 0 0 12 24z"
        />
        <path
          fill="#FBBC05"
          d="M5.3 14.3a7.1 7.1 0 0 1 0-4.6v-3.1h-4a12 12 0 0 0 0 10.8l4-3.1z"
        />
        <path
          fill="#EA4335"
          d="M12 4.8c1.8 0 3.4.6 4.6 1.8l3.4-3.4A12 12 0 0 0 1.3 6.6l4 3.1A7.2 7.2 0 0 1 12 4.8z"
        />
      </svg>

      {{ entrando ? 'Abriendo Google…' : props.rotulo }}
    </button>

    <!-- Hueco reservado: el aviso no empuja el formulario al aparecer. -->
    <p
      class="mt-1.5 min-h-5 text-xs text-peligro-700"
      role="alert"
    >
      {{ aviso }}
    </p>
  </div>
</template>
