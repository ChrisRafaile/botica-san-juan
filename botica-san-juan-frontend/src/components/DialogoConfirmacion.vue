<!--
  Diálogo de confirmación accesible.
  ===========================================================================
  Se usa antes de cualquier acción que cueste deshacer. El caso que lo
  motivó es cerrar sesión desde el mostrador: un clic accidental en plena
  atención obliga a volver a entrar con el cliente delante.

  LO QUE HACE QUE SEA ACCESIBLE, Y NO SOLO QUE LO PAREZCA

  · `role="dialog"` y `aria-modal="true"` para que el lector de pantalla
    anuncie que lo de detrás queda fuera de juego.
  · `aria-labelledby` y `aria-describedby` apuntando al título y al texto: sin
    ellos el diálogo se anuncia como «diálogo» y nada más.
  · El foco entra en el botón SEGURO, no en el destructivo. Quien pulsa Intro
    por inercia debe quedarse donde está, no cerrar la sesión.
  · El foco queda atrapado dentro mientras está abierto: tabular no debe
    llevarte a la página de detrás, que para un lector de pantalla ni siquiera
    está ahí.
  · Escape cancela, que es lo que todo el mundo espera.
  · Al cerrar, el foco VUELVE al elemento que lo abrió. Sin eso, quien navega
    con teclado aparece al principio del documento y tiene que rehacer el
    camino.
-->
<template>
  <Teleport to="body">
    <Transition name="dialogo">
      <div
        v-if="abierto"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
        @click.self="cancelar"
      >
        <div
          ref="panel"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="`${id}-titulo`"
          :aria-describedby="`${id}-texto`"
          class="w-full max-w-sm rounded-2xl border border-borde-sutil bg-superficie-elevada p-6 shadow-xl"
          @keydown.esc.prevent="cancelar"
          @keydown.tab="atraparFoco"
        >
          <div
            class="mx-auto flex size-12 items-center justify-center rounded-full"
            :class="
              peligroso
                ? 'bg-peligro-50 text-peligro-700 dark:bg-peligro-500/10'
                : 'bg-botica-50 text-texto-marca'
            "
            aria-hidden="true"
          >
            <slot name="icono">
              <AlertTriangle class="size-6" />
            </slot>
          </div>

          <h2
            :id="`${id}-titulo`"
            class="mt-4 text-center text-lg font-bold text-texto-primario"
          >
            {{ titulo }}
          </h2>

          <p
            :id="`${id}-texto`"
            class="mt-2 text-center text-sm text-texto-secundario"
          >
            {{ texto }}
          </p>

          <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row">
            <!-- El seguro va primero en el DOM: recibe el foco inicial. -->
            <button
              ref="botonSeguro"
              type="button"
              class="flex-1 rounded-full border border-borde-control px-4 py-2.5 text-sm font-semibold text-texto-primario transition hover:bg-superficie-interactiva focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
              @click="cancelar"
            >
              {{ etiquetaCancelar }}
            </button>
            <button
              ref="botonConfirmar"
              type="button"
              class="flex-1 rounded-full px-4 py-2.5 text-sm font-semibold text-white transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
              :class="
                peligroso
                  ? 'bg-peligro-600 hover:bg-peligro-700'
                  : 'bg-botica-700 hover:bg-botica-800'
              "
              @click="confirmar"
            >
              {{ etiquetaConfirmar }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { AlertTriangle } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    abierto: boolean
    titulo: string
    texto: string
    etiquetaConfirmar?: string
    etiquetaCancelar?: string
    /** Tiñe de rojo el botón de confirmar y el icono. */
    peligroso?: boolean
    id?: string
  }>(),
  {
    etiquetaConfirmar: 'Confirmar',
    etiquetaCancelar: 'Cancelar',
    peligroso: false,
    id: 'dialogo',
  },
)

const emit = defineEmits<{ confirmar: []; cancelar: [] }>()

const panel = ref<HTMLElement | null>(null)
const botonSeguro = ref<HTMLButtonElement | null>(null)
const botonConfirmar = ref<HTMLButtonElement | null>(null)

/** Quién tenía el foco antes de abrir, para devolvérselo al cerrar. */
let origenDelFoco: HTMLElement | null = null

watch(
  () => props.abierto,
  async (estaAbierto) => {
    if (estaAbierto) {
      origenDelFoco = document.activeElement as HTMLElement | null
      await nextTick()
      botonSeguro.value?.focus()
      return
    }

    /* `nextTick` para que el diálogo haya salido del DOM: devolver el foco
       antes deja el navegador decidiendo, y a veces se queda en el body. */
    await nextTick()
    origenDelFoco?.focus()
    origenDelFoco = null
  },
)

/**
 * Mantiene el foco dentro del diálogo.
 *
 * Sin esto, tabular desde el último botón se va a la barra del navegador o al
 * contenido de detrás, que para un lector de pantalla no existe mientras el
 * diálogo está abierto: la persona se pierde en una página que no se le está
 * anunciando.
 */
function atraparFoco(evento: KeyboardEvent): void {
  const primero = botonSeguro.value
  const ultimo = botonConfirmar.value
  if (!primero || !ultimo) return

  if (evento.shiftKey && document.activeElement === primero) {
    evento.preventDefault()
    ultimo.focus()
  } else if (!evento.shiftKey && document.activeElement === ultimo) {
    evento.preventDefault()
    primero.focus()
  }
}

const confirmar = () => emit('confirmar')
const cancelar = () => emit('cancelar')
</script>

<style scoped>
/*
  Solo `opacity`: la propiedad que el navegador resuelve en el compositor sin
  rehacer el diseño. Un diálogo que entra escalando se ve bien y obliga al
  navegador a recalcular en cada fotograma.
*/
.dialogo-enter-active,
.dialogo-leave-active {
  transition: opacity 160ms ease;
}

.dialogo-enter-from,
.dialogo-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .dialogo-enter-active,
  .dialogo-leave-active {
    transition: none;
  }
}
</style>
