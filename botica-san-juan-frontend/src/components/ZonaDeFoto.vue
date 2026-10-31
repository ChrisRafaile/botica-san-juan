<script setup lang="ts">
/**
 * ZonaDeFoto · Arrastrar y soltar la foto de perfil
 * ---------------------------------------------------------------------------
 * Un único componente para los dos roles. Si cliente y administrador tuvieran
 * cada uno su zona de subida, las reglas de tamaño y los mensajes de error
 * divergirían a la primera corrección que se hiciera en solo uno de ellos.
 *
 * DECISIONES QUE NO SON OBVIAS
 *
 * - Se valida en el navegador ANTES de enviar, pero el servidor vuelve a
 *   validar. Lo de aquí es cortesía —evita esperar diez segundos para que te
 *   digan que no—, no seguridad: lo de allí es lo que manda.
 *
 * - La vista previa sale de `URL.createObjectURL`, que se revoca al cambiarla
 *   y al desmontar. Sin eso cada arrastre deja un blob retenido en memoria.
 *
 * - El `<input type="file">` no se esconde con `display:none`: queda
 *   transparente y encima de la zona. Oculto del todo, el teclado no lo
 *   alcanza y la subida se vuelve exclusiva del ratón.
 *
 * - Arrastrar sobre una ventana del navegador tiene una trampa conocida:
 *   `dragleave` se dispara también al pasar por encima de los hijos. Se lleva
 *   un contador de entradas y salidas en lugar de un booleano, que es lo que
 *   hace que el resaltado no parpadee.
 */
import { computed, onBeforeUnmount, ref } from 'vue'
import { UploadCloud, Trash2, Loader2 } from 'lucide-vue-next'
import BoticaAvatar from '@/admin/components/ui/BoticaAvatar.vue'

const props = defineProps<{
  nombre?: string | null
  /** Foto actual, ya resuelta a URL absoluta. */
  fotoActual?: string | null
  subiendo?: boolean
}>()

const emit = defineEmits<{
  seleccionar: [archivo: File]
  eliminar: []
}>()

const TIPOS = ['image/jpeg', 'image/png', 'image/webp']
const MAXIMO_MB = 2

const arrastrando = ref(0)
const error = ref<string | null>(null)
const vistaPrevia = ref<string | null>(null)
const entrada = ref<HTMLInputElement | null>(null)

const fotoMostrada = computed(() => vistaPrevia.value ?? props.fotoActual ?? null)

function liberarVistaPrevia() {
  if (vistaPrevia.value) {
    URL.revokeObjectURL(vistaPrevia.value)
    vistaPrevia.value = null
  }
}

onBeforeUnmount(liberarVistaPrevia)

function validar(archivo: File): string | null {
  if (!TIPOS.includes(archivo.type)) {
    return 'Tiene que ser una imagen JPG, PNG o WEBP.'
  }
  if (archivo.size > MAXIMO_MB * 1024 * 1024) {
    const pesa = (archivo.size / 1024 / 1024).toFixed(1)
    return `La imagen pesa ${pesa} MB y el máximo son ${MAXIMO_MB} MB.`
  }
  return null
}

function aceptar(archivo: File | undefined | null) {
  if (!archivo) return

  const fallo = validar(archivo)
  error.value = fallo

  if (fallo) return

  liberarVistaPrevia()
  vistaPrevia.value = URL.createObjectURL(archivo)
  emit('seleccionar', archivo)
}

function alSoltar(evento: DragEvent) {
  arrastrando.value = 0
  aceptar(evento.dataTransfer?.files?.[0])
}

function alElegir(evento: Event) {
  const campo = evento.target as HTMLInputElement
  aceptar(campo.files?.[0])
  /* Se limpia para que elegir DOS VECES el mismo archivo vuelva a disparar
     el evento: sin esto, corregir un error y reintentar con la misma imagen
     no hace nada y parece que la aplicación se ha colgado. */
  campo.value = ''
}

function quitar() {
  liberarVistaPrevia()
  error.value = null
  emit('eliminar')
}

/** La vista previa deja de ser válida en cuanto el padre confirma la subida. */
defineExpose({ limpiarVistaPrevia: liberarVistaPrevia })
</script>

<template>
  <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
    <BoticaAvatar
      :nombre="nombre"
      :foto="fotoMostrada"
      tamano="lg"
      class="shrink-0"
    />

    <div class="min-w-0 flex-1">
      <div
        class="relative rounded-xl border-2 border-dashed p-4 transition-colors"
        :class="arrastrando > 0
          ? 'border-botica-500 bg-botica-50 dark:bg-botica-500/10'
          : 'border-borde-sutil bg-superficie-hundida'"
        @dragenter.prevent="arrastrando++"
        @dragleave.prevent="arrastrando--"
        @dragover.prevent
        @drop.prevent="alSoltar"
      >
        <input
          ref="entrada"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          class="absolute inset-0 size-full cursor-pointer opacity-0"
          :disabled="subiendo"
          aria-label="Elegir una foto de perfil"
          @change="alElegir"
        >

        <div class="pointer-events-none flex items-center gap-3">
          <Loader2
            v-if="subiendo"
            class="size-5 shrink-0 animate-spin text-botica-600"
          />
          <UploadCloud
            v-else
            class="size-5 shrink-0 text-texto-terciario"
          />
          <div class="min-w-0 text-sm">
            <p class="font-medium text-texto-primario">
              {{ subiendo ? 'Subiendo…' : 'Arrastra tu foto aquí o pulsa para elegirla' }}
            </p>
            <p class="text-xs text-texto-terciario">
              JPG, PNG o WEBP · hasta {{ MAXIMO_MB }} MB
            </p>
          </div>
        </div>
      </div>

      <p
        v-if="error"
        class="mt-2 text-xs text-peligro-600"
        role="alert"
      >
        {{ error }}
      </p>

      <button
        v-if="fotoActual && !subiendo"
        type="button"
        class="mt-2 inline-flex items-center gap-1.5 text-xs text-texto-secundario transition-colors hover:text-peligro-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
        @click="quitar"
      >
        <Trash2 class="size-3.5" />
        Quitar foto
      </button>
    </div>
  </div>
</template>
