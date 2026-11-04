<script setup lang="ts">
/**
 * ZonaDeReceta · Arrastrar y soltar la receta médica
 * ---------------------------------------------------------------------------
 * EN QUÉ SE DIFERENCIA DE ZonaDeFoto
 *
 * Acepta PDF además de imagen, y eso no es un detalle: en la botica real la
 * receta llega de dos maneras, la foto que el cliente hace con el móvil y el
 * PDF que le manda el consultorio. Rechazar el PDF obligaría a imprimirlo
 * para volver a fotografiarlo.
 *
 * Las decisiones de interacción vienen de ZonaDeFoto porque allí ya costaron
 * una iteración cada una:
 *
 * - `arrastrando` es un CONTADOR, no un booleano. `dragleave` se dispara
 *   también al pasar por encima de los hijos del contenedor, así que con un
 *   booleano el resaltado parpadea mientras se arrastra por dentro.
 * - El `<input type="file">` no se esconde con `display:none`, que lo saca
 *   del orden de tabulación: queda transparente encima de la zona, de modo
 *   que se puede llegar con el teclado.
 * - Tras elegir un archivo se limpia el valor del campo. Sin eso, volver a
 *   elegir EL MISMO archivo no dispara `change` y parece que no pasa nada.
 *
 * LO QUE ESTE COMPONENTE NO DECIDE
 *
 * No dice si la receta vale. Solo la recoge. La validación la hace una
 * persona en el mostrador, y por eso aquí no hay ningún mensaje que se
 * parezca a «receta correcta».
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { FileText, Loader2, Upload } from 'lucide-vue-next'

import ImagenConRespaldo from './ImagenConRespaldo.vue'

const props = withDefaults(
  defineProps<{
    /** Deshabilita la zona: ya hay una receta validada, o se está enviando. */
    bloqueada?: boolean
    subiendo?: boolean
  }>(),
  { bloqueada: false, subiendo: false },
)

const emit = defineEmits<{ (e: 'elegir', archivo: File): void }>()

/** Lo que el navegador ofrece en el diálogo y lo que acepta el servidor. */
const TIPOS = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']
const MAX_BYTES = 8 * 1024 * 1024

const arrastrando = ref(0)
const error = ref('')

/** Vista previa del archivo recién elegido, antes de enviarlo. */
const elegido = ref<File | null>(null)
const vistaPrevia = ref<string | null>(null)

const esImagen = computed(() => Boolean(elegido.value?.type.startsWith('image/')))

function soltarVistaPrevia() {
  if (vistaPrevia.value) {
    URL.revokeObjectURL(vistaPrevia.value)
    vistaPrevia.value = null
  }
}

/* El object URL ocupa memoria hasta que se revoca. Se suelta al cambiar de
   archivo y al desmontar el componente. */
watch(elegido, soltarVistaPrevia, { flush: 'pre' })
onBeforeUnmount(soltarVistaPrevia)

function aceptar(archivo: File | undefined) {
  if (!archivo || props.bloqueada) return

  error.value = ''

  /* Se comprueba aquí ADEMÁS de en el servidor. Esta comprobación es una
     cortesía —evitar que alguien espere a que suban 20 MB para que le digan
     que no—, no una medida de seguridad: el servidor vuelve a mirarlo todo,
     y mira el contenido real del archivo, no su extensión. */
  if (!TIPOS.includes(archivo.type)) {
    error.value = 'Solo aceptamos una foto (JPG, PNG o WEBP) o un PDF.'
    return
  }

  if (archivo.size > MAX_BYTES) {
    error.value = `El archivo pesa ${(archivo.size / 1024 / 1024).toFixed(1)} MB y el máximo son 8 MB.`
    return
  }

  elegido.value = archivo
  vistaPrevia.value = archivo.type.startsWith('image/') ? URL.createObjectURL(archivo) : null

  emit('elegir', archivo)
}

function alSoltar(evento: DragEvent) {
  arrastrando.value = 0
  aceptar(evento.dataTransfer?.files?.[0])
}

function alElegir(evento: Event) {
  const campo = evento.target as HTMLInputElement
  aceptar(campo.files?.[0])
  campo.value = ''
}
</script>

<template>
  <div>
    <div
      class="relative rounded-2xl border-2 border-dashed p-6 text-center transition-colors"
      :class="[
        bloqueada
          ? 'cursor-not-allowed border-borde-sutil bg-superficie-hundida opacity-60'
          : 'cursor-pointer',
        arrastrando > 0 && !bloqueada
          ? 'border-clinico-500 bg-clinico-50 dark:bg-clinico-600/10'
          : 'border-borde-control',
      ]"
      @dragenter.prevent="arrastrando++"
      @dragleave.prevent="arrastrando--"
      @dragover.prevent
      @drop.prevent="alSoltar"
    >
      <!-- Transparente y encima: se puede pulsar con el ratón y alcanzar con
           el tabulador. `display:none` lo sacaría del orden de tabulación. -->
      <input
        type="file"
        :accept="TIPOS.join(',')"
        :disabled="bloqueada || subiendo"
        class="absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
        aria-label="Adjuntar la receta médica: foto o PDF"
        @change="alElegir"
      />

      <div class="pointer-events-none flex flex-col items-center gap-2">
        <Loader2
          v-if="subiendo"
          class="size-8 animate-spin text-texto-acento"
        />
        <Upload
          v-else
          class="size-8 text-texto-terciario"
        />

        <p class="text-sm font-medium text-texto-primario">
          {{ subiendo ? 'Enviando tu receta…' : 'Arrastra la receta aquí o pulsa para elegirla' }}
        </p>
        <p class="text-xs text-texto-secundario">
          Una foto nítida o el PDF del consultorio. Hasta 8 MB.
        </p>
      </div>
    </div>

    <!-- Hueco reservado: el aviso aparece sin empujar lo que viene debajo. -->
    <p
      class="mt-1.5 min-h-5 text-xs text-peligro-700"
      role="alert"
    >
      {{ error }}
    </p>

    <!-- Vista previa de lo que se acaba de elegir -->
    <div
      v-if="elegido"
      class="mt-3 flex items-center gap-3 rounded-xl border border-borde-sutil bg-superficie-elevada p-3"
    >
      <ImagenConRespaldo
        v-if="esImagen"
        :fuente-directa="vistaPrevia"
        alt="Vista previa de la receta que vas a enviar"
        clase-contenedor="size-14 shrink-0 rounded-lg"
        clase-imagen="h-full w-full object-cover"
        carga="eager"
      />
      <div
        v-else
        class="flex size-14 shrink-0 items-center justify-center rounded-lg bg-superficie-hundida"
      >
        <FileText class="size-6 text-texto-terciario" />
      </div>

      <div class="min-w-0">
        <p class="truncate text-sm font-medium text-texto-primario">
          {{ elegido.name }}
        </p>
        <p class="text-xs text-texto-secundario">
          {{ (elegido.size / 1024).toFixed(0) }} kB
        </p>
      </div>
    </div>
  </div>
</template>
