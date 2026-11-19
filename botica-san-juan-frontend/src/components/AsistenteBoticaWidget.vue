<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { Bot, Send, Sparkles, X } from 'lucide-vue-next'
import { useAsistente } from '@/composables/useAsistente'
import { renderizarMarkdownSeguro } from '@/utils/markdownSeguro'

const props = withDefaults(
  defineProps<{
    rol?: 'cliente' | 'farmaceutico' | 'administrador' | string
  }>(),
  { rol: 'cliente' },
)

const abierto = ref(false)
const entrada = ref('')
const botonDisparadorRef = ref<HTMLButtonElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)
const botonCerrarRef = ref<HTMLButtonElement | null>(null)

const { habilitado, cargando, error, mensajes, verificarEstado, enviar } = useAsistente()

const esPersonal = computed(() =>
  props.rol === 'administrador' || props.rol === 'farmaceutico',
)

const sugerencias = computed(() =>
  esPersonal.value
    ? [
        '¿Qué lotes vencen en los próximos 30 días?',
        '¿Cómo van las ventas y mi caja hoy?',
        'Stock y lotes FEFO de amoxicilina',
      ]
    : [
        '¿Tienen amoxicilina 500mg disponible?',
        '¿Paracetamol requiere receta médica?',
        '¿Hay interacción entre ibuprofeno y omeprazol?',
      ],
)

async function abrirPanel() {
  abierto.value = true
  if (habilitado.value === null) {
    await verificarEstado()
  }
  await nextTick()
  if (habilitado.value === false) {
    botonCerrarRef.value?.focus()
  } else {
    inputRef.value?.focus()
  }
}

function cerrarPanel() {
  if (!abierto.value) return
  abierto.value = false
  nextTick(() => {
    botonDisparadorRef.value?.focus()
  })
}

async function enviarMensaje(texto?: string) {
  const contenido = (texto ?? entrada.value).trim()
  if (!contenido) return
  if (!texto) entrada.value = ''
  await enviar(contenido)
}

function alPulsarTecla(evento: KeyboardEvent) {
  if (evento.key === 'Escape' && abierto.value) {
    cerrarPanel()
  }
}

onMounted(() => {
  document.addEventListener('keydown', alPulsarTecla)
})

onUnmounted(() => {
  document.removeEventListener('keydown', alPulsarTecla)
})
</script>

<template>
  <div class="fixed bottom-4 right-4 z-40">
    <Transition
      enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
      enter-from-class="opacity-0 translate-y-2 scale-95"
      leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
      leave-to-class="opacity-0 translate-y-2 scale-95"
    >
      <section
        v-if="abierto"
        role="dialog"
        aria-modal="false"
        aria-label="Asistente de Botica San Juan"
        class="mb-3 flex max-h-[75vh] w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-borde-sutil bg-superficie-flotante shadow-xl sm:w-96"
      >
        <header class="flex items-center justify-between gap-2 border-b border-borde-sutil bg-botica-700 px-4 py-3 text-white">
          <div class="flex items-center gap-2">
            <Bot class="size-5 shrink-0" aria-hidden="true" />
            <div>
              <h2 class="text-sm font-semibold leading-tight">
                Asistente de Botica
              </h2>
              <p class="text-2xs text-botica-100">
                {{ esPersonal ? 'Modo gestión e inventario' : 'Consultas de catálogo' }}
              </p>
            </div>
          </div>
          <button
            ref="botonCerrarRef"
            type="button"
            class="rounded-lg p-1 text-botica-100 transition-colors hover:bg-botica-800 hover:text-white focus-visible:outline-2 focus-visible:outline-white"
            aria-label="Cerrar asistente"
            @click="cerrarPanel"
          >
            <X class="size-4" aria-hidden="true" />
          </button>
        </header>

        <div
          class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4 text-sm"
          aria-live="polite"
        >
          <div
            v-if="habilitado === false"
            class="rounded-xl border border-borde-sutil bg-superficie-hundida p-3 text-xs text-texto-secundario"
          >
            El asistente está deshabilitado porque no hay un proveedor de IA configurado en el servidor.
          </div>

          <template v-else>
            <div v-if="mensajes.length === 0" class="space-y-2">
              <p class="text-xs text-texto-secundario">
                Consulta disponibilidad, vencimientos o ventas según tu perfil. No reemplaza la orientación del químico farmacéutico.
              </p>
              <div class="flex flex-wrap gap-1.5">
                <button
                  v-for="sug in sugerencias"
                  :key="sug"
                  type="button"
                  class="rounded-lg border border-borde-base bg-superficie-hundida px-2.5 py-1.5 text-left text-xs text-texto-primario transition-colors hover:border-borde-marca"
                  @click="enviarMensaje(sug)"
                >
                  {{ sug }}
                </button>
              </div>
            </div>

            <div
              v-for="msg in mensajes"
              :key="msg.id"
              class="flex"
              :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
            >
              <div
                class="max-w-[85%] rounded-2xl px-3.5 py-2 text-xs leading-relaxed"
                :class="msg.role === 'user'
                  ? 'bg-botica-700 text-white'
                  : 'border border-borde-sutil bg-superficie-hundida text-texto-primario'"
              >
                <span v-if="msg.role === 'user'">{{ msg.content }}</span>
                <!-- eslint-disable-next-line vue/no-v-html -->
                <div v-else class="space-y-1" v-html="renderizarMarkdownSeguro(msg.content)" />
              </div>
            </div>

            <p v-if="cargando" class="text-xs text-texto-terciario">
              Consultando información…
            </p>

            <p
              v-if="error"
              role="alert"
              class="rounded-lg bg-peligro-50 px-3 py-2 text-xs text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-400"
            >
              {{ error }}
            </p>
          </template>
        </div>

        <form
          class="flex items-center gap-2 border-t border-borde-sutil bg-superficie-elevada p-3"
          @submit.prevent="enviarMensaje()"
        >
          <input
            ref="inputRef"
            v-model="entrada"
            type="text"
            maxlength="1000"
            :disabled="habilitado === false || cargando"
            placeholder="Escribe tu consulta..."
            aria-label="Mensaje para el asistente"
            class="h-9 min-w-0 flex-1 rounded-lg border border-borde-base bg-superficie-hundida px-3 text-xs text-texto-primario outline-none focus:border-borde-marca disabled:opacity-50"
          >
          <button
            type="submit"
            :disabled="habilitado === false || cargando || entrada.trim().length < 2"
            aria-label="Enviar mensaje"
            class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-botica-700 text-white transition-colors hover:bg-botica-800 disabled:opacity-50"
          >
            <Send class="size-4" aria-hidden="true" />
          </button>
        </form>
      </section>
    </Transition>

    <button
      ref="botonDisparadorRef"
      type="button"
      class="inline-flex items-center gap-2 rounded-full bg-botica-700 px-4 py-3 text-sm font-semibold text-white shadow-lg transition-transform hover:bg-botica-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco motion-reduce:transition-none"
      :aria-expanded="abierto"
      aria-label="Abrir asistente de botica"
      @click="abierto ? cerrarPanel() : abrirPanel()"
    >
      <Sparkles class="size-4" aria-hidden="true" />
      <span>Asistente</span>
    </button>
  </div>
</template>
