<script setup lang="ts">
/**
 * Bitácora de operación.
 * ---------------------------------------------------------------------------
 * LA PANTALLA QUE FALTABA
 *
 * La tabla `eventos_operacion` llevaba semanas recibiendo eventos de los tres
 * niveles y no había forma de verlos. Un registro que nadie puede consultar
 * no es gestión de eventos: es una tabla que crece.
 *
 * CÓMO SE USA DE VERDAD
 *
 * Esta pantalla se abre cuando algo va mal, no para pasear. De ahí el orden:
 * primero los recuentos por nivel —que dicen si hay algo que mirar—, después
 * los filtros, y al final la lista. Y por eso las pastillas de nivel son
 * botones: ver «3 errores» y tener que escribirlo en un formulario para
 * verlos sería absurdo.
 *
 * Los recuentos se calculan sobre la tabla entera, no sobre la página
 * visible. Un contador que cambia al pasar de página no sirve para nada.
 */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { CircleAlert, Info, RefreshCw, TriangleAlert, X } from 'lucide-vue-next'

import {
  listarEventos,
  resumenDeEventos,
  type EventoDeOperacion,
  type FiltrosDeEventos,
  type NivelEvento,
  type PaginaDeEventos,
  type ResumenDeEventos,
} from '@/services/eventos'

const cargando = ref(false)
const error = ref('')

const pagina = ref<PaginaDeEventos | null>(null)
const resumen = ref<ResumenDeEventos | null>(null)

const filtros = reactive<FiltrosDeEventos>({
  nivel: '',
  codigo: '',
  buscar: '',
  desde: '',
  hasta: '',
  page: 1,
  per_page: 25,
})

/** El detalle que se está mirando, o null. */
const detalle = ref<EventoDeOperacion | null>(null)

/* Apariencia de cada nivel. En un solo sitio: si el color del aviso viviera
   repartido entre la pastilla, la fila y el detalle, bastaría cambiar uno
   para que dejaran de coincidir. */
const APARIENCIA: Record<NivelEvento, { etiqueta: string; icono: typeof Info; clases: string; punto: string }> = {
  info: {
    etiqueta: 'Información',
    icono: Info,
    clases: 'bg-botica-50 text-botica-700 dark:bg-botica-600/10 dark:text-botica-400',
    punto: 'bg-botica-500',
  },
  warning: {
    etiqueta: 'Advertencia',
    icono: TriangleAlert,
    clases: 'bg-alerta-50 text-alerta-700 dark:bg-alerta-500/10 dark:text-alerta-500',
    punto: 'bg-alerta-500',
  },
  error: {
    etiqueta: 'Error',
    icono: CircleAlert,
    clases: 'bg-peligro-50 text-peligro-700 dark:bg-peligro-600/10 dark:text-peligro-500',
    punto: 'bg-peligro-500',
  },
}

const NIVELES: NivelEvento[] = ['info', 'warning', 'error']

const hayFiltros = computed(
  () => Boolean(filtros.nivel || filtros.codigo || filtros.buscar || filtros.desde || filtros.hasta),
)

const eventos = computed(() => pagina.value?.datos ?? [])

async function cargar() {
  cargando.value = true
  error.value = ''

  try {
    const [lista, cabecera] = await Promise.all([
      listarEventos(filtros),
      resumenDeEventos(),
    ])

    pagina.value = lista
    resumen.value = cabecera
  } catch {
    error.value = 'No se pudo leer la bitácora. Inténtalo de nuevo en un momento.'
  } finally {
    cargando.value = false
  }
}

/* Cambiar un filtro vuelve SIEMPRE a la primera página. Sin esto, filtrar
   estando en la página 4 deja una lista vacía y parece que no hay
   resultados. */
watch(
  () => [filtros.nivel, filtros.codigo, filtros.buscar, filtros.desde, filtros.hasta],
  () => {
    filtros.page = 1
    cargar()
  },
)

watch(() => filtros.page, cargar)

function alternarNivel(nivel: NivelEvento) {
  filtros.nivel = filtros.nivel === nivel ? '' : nivel
}

function limpiar() {
  filtros.nivel = ''
  filtros.codigo = ''
  filtros.buscar = ''
  filtros.desde = ''
  filtros.hasta = ''
}

/** Fecha y hora en el formato de aquí, no el del navegador de quien mire. */
function cuando(iso: string | null): string {
  if (!iso) return '—'

  return new Date(iso).toLocaleString('es-PE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(cargar)
</script>

<template>
  <div class="space-y-6 p-4 sm:p-6 lg:p-8">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-texto-primario">
          Bitácora de operación
        </h1>
        <p class="mt-1 text-texto-secundario">
          Lo que el sistema registra mientras funciona. Solo lectura: estos
          eventos los escribe el código, no una persona.
        </p>
      </div>

      <button
        type="button"
        :disabled="cargando"
        class="grid size-9 place-items-center rounded-lg border border-borde-sutil text-texto-secundario transition-colors hover:bg-superficie-interactiva disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
        title="Actualizar"
        @click="cargar"
      >
        <RefreshCw
          class="size-4"
          :class="{ 'animate-spin': cargando }"
        />
      </button>
    </header>

    <p
      v-if="error"
      class="flex items-center gap-2 rounded-lg bg-peligro-50 px-3 py-2.5 text-sm text-peligro-700 dark:bg-peligro-600/10 dark:text-peligro-500"
      role="alert"
    >
      <TriangleAlert class="size-4 shrink-0" />
      {{ error }}
    </p>

    <!-- Recuentos por nivel. Son botones: ver «3 errores» y tener que
         escribirlo en un formulario para verlos seria absurdo. -->
    <section
      class="grid gap-3 sm:grid-cols-3"
      aria-label="Eventos por nivel"
    >
      <button
        v-for="nivel in NIVELES"
        :key="nivel"
        type="button"
        class="flex items-center gap-3 rounded-2xl bg-superficie-elevada p-4 text-left shadow-lg ring-1 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
        :class="filtros.nivel === nivel
          ? 'ring-2 ring-botica-500'
          : 'ring-borde-sutil/80 hover:bg-superficie-interactiva'"
        :aria-pressed="filtros.nivel === nivel"
        @click="alternarNivel(nivel)"
      >
        <span
          class="grid size-10 shrink-0 place-items-center rounded-xl"
          :class="APARIENCIA[nivel].clases"
        >
          <component
            :is="APARIENCIA[nivel].icono"
            class="size-5"
          />
        </span>
        <span class="min-w-0">
          <span class="block text-sm text-texto-secundario">{{ APARIENCIA[nivel].etiqueta }}</span>
          <span class="block text-2xl font-bold text-texto-primario tabular-nums">
            {{ resumen?.niveles[nivel] ?? 0 }}
          </span>
        </span>
      </button>
    </section>

    <!-- Filtros -->
    <section class="rounded-2xl bg-superficie-elevada p-4 shadow-lg ring-1 ring-borde-sutil/80 sm:p-5">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-texto-secundario">Buscar en el mensaje</span>
          <input
            v-model.lazy="filtros.buscar"
            type="search"
            placeholder="correlativo, stock…"
            class="w-full rounded-lg border border-borde-base bg-superficie-base px-3 py-2 text-texto-primario focus:border-botica-500 focus:outline-none focus:ring-2 focus:ring-botica-500/20"
          >
        </label>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-texto-secundario">Código</span>
          <select
            v-model="filtros.codigo"
            class="w-full rounded-lg border border-borde-base bg-superficie-base px-3 py-2 text-texto-primario focus:border-botica-500 focus:outline-none focus:ring-2 focus:ring-botica-500/20"
          >
            <option value="">
              Todos
            </option>
            <option
              v-for="c in resumen?.codigos ?? []"
              :key="c.codigo"
              :value="c.codigo"
            >
              {{ c.codigo }} ({{ c.total }})
            </option>
          </select>
        </label>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-texto-secundario">Desde</span>
          <input
            v-model="filtros.desde"
            type="date"
            class="w-full rounded-lg border border-borde-base bg-superficie-base px-3 py-2 text-texto-primario focus:border-botica-500 focus:outline-none focus:ring-2 focus:ring-botica-500/20"
          >
        </label>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-texto-secundario">Hasta</span>
          <input
            v-model="filtros.hasta"
            type="date"
            class="w-full rounded-lg border border-borde-base bg-superficie-base px-3 py-2 text-texto-primario focus:border-botica-500 focus:outline-none focus:ring-2 focus:ring-botica-500/20"
          >
        </label>
      </div>

      <div
        v-if="hayFiltros"
        class="mt-3 flex items-center justify-between gap-3"
      >
        <p class="text-sm text-texto-secundario">
          {{ pagina?.paginacion.total ?? 0 }} evento(s) con estos filtros
        </p>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-texto-secundario transition-colors hover:bg-superficie-interactiva focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
          @click="limpiar"
        >
          <X class="size-4" />
          Quitar filtros
        </button>
      </div>
    </section>

    <!-- Lista -->
    <section class="overflow-hidden rounded-2xl bg-superficie-elevada shadow-lg ring-1 ring-borde-sutil/80">
      <!-- Esqueleto con la MISMA altura de fila, para que al llegar los datos
           no salte nada. -->
      <div
        v-if="cargando && !eventos.length"
        class="divide-y divide-borde-sutil"
      >
        <div
          v-for="n in 6"
          :key="n"
          class="h-[4.5rem] animate-pulse bg-superficie-hundida/40"
        />
      </div>

      <p
        v-else-if="!eventos.length"
        class="px-5 py-14 text-center text-texto-secundario"
      >
        <template v-if="hayFiltros">
          Ningún evento coincide con estos filtros.
        </template>
        <template v-else>
          Todavía no hay eventos registrados.
        </template>
      </p>

      <ul
        v-else
        class="divide-y divide-borde-sutil"
      >
        <li
          v-for="evento in eventos"
          :key="evento.id"
        >
          <button
            type="button"
            class="flex w-full items-start gap-3 px-4 py-3.5 text-left transition-colors hover:bg-superficie-interactiva focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-anillo-foco sm:px-5"
            @click="detalle = evento"
          >
            <span
              class="mt-2 size-2 shrink-0 rounded-full"
              :class="APARIENCIA[evento.nivel].punto"
              :title="APARIENCIA[evento.nivel].etiqueta"
            />

            <span class="min-w-0 flex-1">
              <span class="flex flex-wrap items-center gap-2">
                <span
                  class="rounded px-1.5 py-0.5 text-xs font-semibold"
                  :class="APARIENCIA[evento.nivel].clases"
                >{{ APARIENCIA[evento.nivel].etiqueta }}</span>
                <code class="text-xs text-texto-secundario">{{ evento.codigo }}</code>
              </span>
              <span class="mt-1 block truncate text-texto-primario">{{ evento.mensaje }}</span>
            </span>

            <span class="shrink-0 text-right text-xs text-texto-secundario tabular-nums">
              {{ cuando(evento.ocurrio_en) }}
              <span class="mt-0.5 block">{{ evento.origen }}</span>
            </span>
          </button>
        </li>
      </ul>

      <!-- Paginación -->
      <div
        v-if="pagina && pagina.paginacion.paginas > 1"
        class="flex items-center justify-between gap-3 border-t border-borde-sutil px-4 py-3 sm:px-5"
      >
        <button
          type="button"
          :disabled="pagina.paginacion.pagina <= 1 || cargando"
          class="rounded-lg border border-borde-sutil px-3 py-1.5 text-sm font-medium text-texto-secundario transition-colors hover:bg-superficie-interactiva disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
          @click="filtros.page = (filtros.page ?? 1) - 1"
        >
          Anterior
        </button>

        <p class="text-sm text-texto-secundario tabular-nums">
          Página {{ pagina.paginacion.pagina }} de {{ pagina.paginacion.paginas }}
        </p>

        <button
          type="button"
          :disabled="pagina.paginacion.pagina >= pagina.paginacion.paginas || cargando"
          class="rounded-lg border border-borde-sutil px-3 py-1.5 text-sm font-medium text-texto-secundario transition-colors hover:bg-superficie-interactiva disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
          @click="filtros.page = (filtros.page ?? 1) + 1"
        >
          Siguiente
        </button>
      </div>
    </section>

    <!-- Detalle. El contexto es un campo libre: llega ya depurado del
         servidor, pero se muestra como texto y nunca como HTML. -->
    <Teleport to="body">
      <div
        v-if="detalle"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="titulo-evento"
        @click.self="detalle = null"
      >
        <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-superficie-elevada shadow-2xl">
          <header class="flex shrink-0 items-start justify-between gap-4 border-b border-borde-sutil p-5">
            <div>
              <span
                class="rounded px-1.5 py-0.5 text-xs font-semibold"
                :class="APARIENCIA[detalle.nivel].clases"
              >{{ APARIENCIA[detalle.nivel].etiqueta }}</span>
              <h2
                id="titulo-evento"
                class="mt-2 font-mono text-lg font-semibold text-texto-primario"
              >
                {{ detalle.codigo }}
              </h2>
            </div>
            <button
              type="button"
              class="grid size-8 shrink-0 place-items-center rounded-lg text-texto-secundario transition-colors hover:bg-superficie-interactiva focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
              aria-label="Cerrar"
              @click="detalle = null"
            >
              <X class="size-4" />
            </button>
          </header>

          <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
            <p class="text-texto-primario">
              {{ detalle.mensaje }}
            </p>

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-texto-secundario">
                  Ocurrió
                </dt>
                <dd class="text-texto-primario tabular-nums">
                  {{ cuando(detalle.ocurrio_en) }}
                </dd>
              </div>
              <div>
                <dt class="text-texto-secundario">
                  Origen
                </dt>
                <dd class="text-texto-primario">
                  {{ detalle.origen }}
                </dd>
              </div>
              <div v-if="detalle.dispositivo">
                <dt class="text-texto-secundario">
                  Dispositivo
                </dt>
                <dd class="text-texto-primario">
                  {{ detalle.dispositivo }}
                </dd>
              </div>
              <div v-if="detalle.pedido_id">
                <dt class="text-texto-secundario">
                  Pedido
                </dt>
                <dd class="text-texto-primario tabular-nums">
                  #{{ detalle.pedido_id }}
                </dd>
              </div>
              <div v-if="detalle.usuario_id">
                <dt class="text-texto-secundario">
                  Usuario
                </dt>
                <dd class="text-texto-primario tabular-nums">
                  #{{ detalle.usuario_id }}
                </dd>
              </div>
            </dl>

            <div v-if="detalle.contexto">
              <h3 class="mb-1.5 text-sm font-medium text-texto-secundario">
                Contexto
              </h3>
              <pre class="overflow-x-auto rounded-lg bg-superficie-hundida p-3 text-xs text-texto-primario">{{ JSON.stringify(detalle.contexto, null, 2) }}</pre>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
