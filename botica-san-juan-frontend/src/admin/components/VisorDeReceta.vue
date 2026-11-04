<script setup lang="ts">
/**
 * VisorDeReceta · Ver la receta de un pedido y decidir sobre ella
 * ---------------------------------------------------------------------------
 * EL ARCHIVO NO LLEGA POR URL
 *
 * Una receta lleva el nombre del paciente, el del médico y, por el
 * medicamento, su diagnóstico, así que no se sirve desde /storage. Se
 * descarga con la sesión y se pinta desde un object URL en memoria, que se
 * libera al cerrar.
 *
 * Eso tiene un efecto práctico que conviene conocer: la imagen no está en la
 * caché del navegador, de modo que cada apertura vuelve a pedirla y vuelve a
 * pasar por la comprobación de propiedad del servidor.
 *
 * RECHAZAR EXIGE ESCRIBIR POR QUÉ
 *
 * El botón de rechazar no hace nada hasta que hay motivo. No es una molestia
 * burocrática: un «rechazada» a secas deja al cliente adivinando qué repetir,
 * y lo que llega después es una segunda receta igual de inservible.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { CircleCheck, CircleX, FileText, Loader2, RotateCcw } from 'lucide-vue-next'

import ImagenConRespaldo from '@/components/ImagenConRespaldo.vue'
import DistintivoReceta from '@/components/DistintivoReceta.vue'
import { descargarArchivo, revisarReceta, type Receta } from '@/services/recetas'

const props = defineProps<{ pedidoId: number; receta: Receta }>()
const emit = defineEmits<{ (e: 'revisada', receta: Receta): void }>()

const cargandoArchivo = ref(true)
const falloArchivo = ref(false)
const enviando = ref(false)
const motivo = ref('')
const pidiendoMotivo = ref(false)
const aviso = ref('')

const url = ref<string | null>(null)
let soltar: (() => void) | null = null

const puedeRechazar = computed(() => motivo.value.trim().length >= 10)
const yaDecidida = computed(() => props.receta.estado !== 'pendiente')

const fechas = new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short' })

function liberar() {
  soltar?.()
  soltar = null
  url.value = null
}

async function traer() {
  liberar()
  cargandoArchivo.value = true
  falloArchivo.value = false

  try {
    const archivo = await descargarArchivo(props.pedidoId)
    url.value = archivo.url
    soltar = archivo.soltar
  } catch {
    falloArchivo.value = true
  } finally {
    cargandoArchivo.value = false
  }
}

/* Se vuelve a traer cuando cambia la receta: tras un rechazo el cliente
   adjunta otra, y seguir mostrando la anterior haría revisar dos veces la
   misma imagen. */
watch(() => [props.pedidoId, props.receta.id, props.receta.version], traer, { immediate: true })
onBeforeUnmount(liberar)

/** Abre el PDF en una pestaña. El object URL vive mientras viva esta vista. */
function abrirEnPestana() {
  if (url.value) window.open(url.value, '_blank', 'noopener')
}

async function decidir(estado: 'validada' | 'rechazada') {
  if (estado === 'rechazada' && !puedeRechazar.value) {
    pidiendoMotivo.value = true
    return
  }

  enviando.value = true
  aviso.value = ''

  try {
    const actualizada = await revisarReceta(
      props.pedidoId,
      estado,
      estado === 'rechazada' ? motivo.value.trim() : undefined,
    )
    motivo.value = ''
    pidiendoMotivo.value = false
    emit('revisada', actualizada)
  } catch (error: unknown) {
    const datos = (error as { response?: { data?: { message?: string } } }).response?.data
    aviso.value = datos?.message ?? 'No se pudo registrar la decisión. Inténtalo de nuevo.'
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <div class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-4">
    <header class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <div class="min-w-0">
        <p class="truncate text-sm font-semibold text-texto-primario">
          {{ receta.nombre_original }}
        </p>
        <p class="text-xs text-texto-secundario">
          {{ (receta.tamano_bytes / 1024).toFixed(0) }} kB ·
          enviada el {{ fechas.format(new Date(receta.created_at)) }}
          <span v-if="receta.version > 1"> · intento {{ receta.version }}</span>
        </p>
      </div>
      <DistintivoReceta :estado="receta.estado" />
    </header>

    <!-- Vista del documento. Altura fija: la tarjeta no salta al cargar. -->
    <div class="relative flex h-72 items-center justify-center overflow-hidden rounded-xl bg-superficie-hundida">
      <Loader2
        v-if="cargandoArchivo"
        class="size-8 animate-spin text-texto-acento"
      />

      <p
        v-else-if="falloArchivo"
        class="px-4 text-center text-sm text-texto-secundario"
      >
        No se pudo cargar el archivo.
        <button
          type="button"
          class="font-medium text-clinico-700 underline"
          @click="traer"
        >
          Reintentar
        </button>
      </p>

      <ImagenConRespaldo
        v-else-if="receta.es_imagen"
        :fuente-directa="url"
        :alt="`Receta médica adjunta al pedido ${pedidoId}`"
        clase-contenedor="h-72 w-full"
        clase-imagen="h-full w-full object-contain"
        carga="eager"
      />

      <!-- PDF: no se incrusta. Un visor embebido en una tarjeta de 288 px de
           alto se lee peor que el visor propio del navegador. -->
      <button
        v-else
        type="button"
        class="flex flex-col items-center gap-2 rounded-xl px-6 py-4 transition-colors hover:bg-superficie-elevada"
        @click="abrirEnPestana"
      >
        <FileText class="size-10 text-texto-terciario" />
        <span class="text-sm font-medium text-clinico-700 underline">
          Abrir el PDF de la receta
        </span>
      </button>
    </div>

    <!-- Decisión ya tomada -->
    <div
      v-if="yaDecidida"
      class="mt-3 rounded-xl bg-superficie-hundida p-3"
    >
      <p class="text-sm text-texto-primario">
        {{ receta.estado === 'validada' ? 'Validada' : 'Rechazada' }}
        <span v-if="receta.revisada_por">por {{ receta.revisada_por }}</span>
        <span v-if="receta.revisada_en">
          · {{ fechas.format(new Date(receta.revisada_en)) }}</span>
      </p>
      <p
        v-if="receta.motivo_rechazo"
        class="mt-1 text-sm text-texto-secundario"
      >
        Motivo: {{ receta.motivo_rechazo }}
      </p>
      <!-- Rectificar es posible, pero se nombra como lo que es. Una decisión
           sobre un medicamento no se cambia sin que quede dicho. -->
      <button
        v-if="receta.estado === 'rechazada'"
        type="button"
        class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-clinico-700 underline"
        :disabled="enviando"
        @click="decidir('validada')"
      >
        <RotateCcw class="size-3.5" />
        Rectificar y validar
      </button>
    </div>

    <!-- Decisión pendiente -->
    <template v-else>
      <div
        v-if="pidiendoMotivo"
        class="mt-3"
      >
        <label
          :for="`motivo-${pedidoId}`"
          class="mb-1.5 block text-sm font-medium text-texto-secundario"
        >
          Motivo del rechazo
        </label>
        <textarea
          :id="`motivo-${pedidoId}`"
          v-model="motivo"
          rows="2"
          maxlength="300"
          placeholder="Qué tiene que corregir el cliente. Por ejemplo: la foto corta la firma del médico."
          class="w-full rounded-xl border border-borde-control bg-superficie-base px-3 py-2 text-sm text-texto-primario focus:border-transparent focus:ring-2 focus:ring-clinico-500"
        />
        <p class="mt-1 min-h-4 text-xs text-texto-terciario">
          <span v-if="!puedeRechazar">Escribe al menos una frase: el cliente solo verá esto.</span>
        </p>
      </div>

      <div class="mt-3 flex flex-wrap gap-2">
        <button
          type="button"
          :disabled="enviando"
          class="inline-flex items-center gap-1.5 rounded-xl bg-exito-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-exito-700 disabled:opacity-60"
          @click="decidir('validada')"
        >
          <CircleCheck class="size-4" />
          Validar
        </button>
        <button
          type="button"
          :disabled="enviando || (pidiendoMotivo && !puedeRechazar)"
          class="inline-flex items-center gap-1.5 rounded-xl border border-peligro-600/40 px-4 py-2 text-sm font-medium text-peligro-700 transition-colors hover:bg-peligro-50 disabled:opacity-60"
          @click="decidir('rechazada')"
        >
          <CircleX class="size-4" />
          {{ pidiendoMotivo ? 'Confirmar rechazo' : 'Rechazar' }}
        </button>
      </div>
    </template>

    <p
      class="mt-2 min-h-5 text-sm text-peligro-700"
      role="alert"
    >
      {{ aviso }}
    </p>
  </div>
</template>
