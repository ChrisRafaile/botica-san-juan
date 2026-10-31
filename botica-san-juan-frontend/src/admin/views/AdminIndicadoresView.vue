<script setup lang="ts">
/**
 * Panel gerencial · Tres indicadores
 * ---------------------------------------------------------------------------
 * CÓMO SE EVITA EL SALTO DE MAQUETACIÓN
 *
 * Cada tarjeta declara un alto mínimo que NO cambia entre el esqueleto, el
 * estado vacío y el gráfico con datos. El contenido se sustituye dentro de un
 * hueco que ya existe, así que nada de lo que hay debajo se mueve cuando
 * llega la respuesta. Ese es el origen habitual del CLS en un tablero: no las
 * imágenes, sino las tarjetas que crecen al poblarse.
 *
 * POR QUÉ TRES PETICIONES NO, UNA SÍ
 *
 * Los tres indicadores llegan juntos. Pedirlos por separado dejaría la
 * pantalla recomponiéndose tres veces y triplicaría el trabajo de la base,
 * que resuelve los tres agregados en una pasada.
 */
import { onMounted, ref } from 'vue'
import { RefreshCw, TriangleAlert } from 'lucide-vue-next'
import { obtenerIndicadores, type Indicadores } from '@/services/indicadores'
import GraficoSemaforo from '../components/graficos/GraficoSemaforo.vue'
import GraficoArqueo from '../components/graficos/GraficoArqueo.vue'
import GraficoMasVendidos from '../components/graficos/GraficoMasVendidos.vue'

const VENTANAS = [7, 30, 90] as const

const datos = ref<Indicadores | null>(null)
const cargando = ref(true)
const error = ref<string | null>(null)
const ventana = ref<number>(30)

async function cargar(dias = ventana.value) {
  cargando.value = true
  error.value = null
  ventana.value = dias
  try {
    datos.value = await obtenerIndicadores(dias)
  } catch {
    error.value = 'No se pudieron cargar los indicadores.'
  } finally {
    cargando.value = false
  }
}

onMounted(() => cargar())

const soles = (valor: number) =>
  valor.toLocaleString('es-PE', { style: 'currency', currency: 'PEN' })
</script>

<template>
  <div class="space-y-6 p-4 sm:p-6 lg:p-8">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-texto-primario">
          Indicadores
        </h1>
        <p class="mt-1 text-texto-secundario">
          Qué se vence, cómo te pagan y qué se mueve.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <div
          class="flex rounded-lg border border-borde-sutil p-0.5"
          role="group"
          aria-label="Ventana de tiempo"
        >
          <button
            v-for="dias in VENTANAS"
            :key="dias"
            type="button"
            class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
            :class="ventana === dias
              ? 'bg-botica-600 text-white'
              : 'text-texto-secundario hover:bg-superficie-interactiva'"
            :aria-pressed="ventana === dias"
            @click="cargar(dias)"
          >
            {{ dias }} d
          </button>
        </div>

        <button
          type="button"
          class="grid size-9 place-items-center rounded-lg border border-borde-sutil text-texto-secundario transition-colors hover:bg-superficie-interactiva focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
          :disabled="cargando"
          aria-label="Actualizar indicadores"
          @click="cargar()"
        >
          <RefreshCw
            class="size-4"
            :class="{ 'animate-spin': cargando }"
          />
        </button>
      </div>
    </header>

    <p
      v-if="error"
      class="flex items-center gap-2 rounded-lg bg-peligro-50 px-3 py-2.5 text-sm text-peligro-700 dark:bg-peligro-600/10 dark:text-peligro-500"
      role="alert"
    >
      <TriangleAlert class="size-4 shrink-0" />
      {{ error }}
    </p>

    <div class="grid gap-6 xl:grid-cols-2">
      <!-- Vencimientos. min-h idéntico en los tres estados: el hueco no cambia. -->
      <section
        class="min-h-[26rem] rounded-2xl bg-superficie-elevada p-6 shadow-lg ring-1 ring-borde-sutil/80 xl:col-span-2"
      >
        <h2 class="mb-1 text-lg font-semibold text-texto-primario">
          Vencimiento de lotes
        </h2>
        <p class="mb-5 text-sm text-texto-secundario">
          Reparto de las existencias por cercanía a la fecha de caducidad.
        </p>

        <div
          v-if="cargando"
          class="space-y-3"
          aria-busy="true"
        >
          <div
            v-for="n in 6"
            :key="n"
            class="h-8 animate-pulse rounded-lg bg-superficie-hundida"
          />
        </div>
        <GraficoSemaforo
          v-else-if="datos"
          :datos="datos.vencimientos"
        />
      </section>

      <!-- Arqueo -->
      <section class="min-h-[18rem] rounded-2xl bg-superficie-elevada p-6 shadow-lg ring-1 ring-borde-sutil/80">
        <h2 class="mb-1 text-lg font-semibold text-texto-primario">
          Arqueo por medio de pago
        </h2>
        <p class="mb-5 text-sm text-texto-secundario">
          Últimos {{ ventana }} días, sin pedidos anulados.
        </p>

        <div
          v-if="cargando"
          class="flex items-center gap-5"
          aria-busy="true"
        >
          <div class="size-40 shrink-0 animate-pulse rounded-full bg-superficie-hundida" />
          <div class="flex-1 space-y-2.5">
            <div
              v-for="n in 3"
              :key="n"
              class="h-5 animate-pulse rounded bg-superficie-hundida"
            />
          </div>
        </div>
        <GraficoArqueo
          v-else-if="datos && datos.arqueo.medios.length"
          :medios="datos.arqueo.medios"
          :total="datos.arqueo.total"
        />
        <p
          v-else-if="datos"
          class="grid min-h-40 place-items-center text-center text-sm text-texto-terciario"
        >
          No hay cobros registrados en los últimos {{ ventana }} días.
        </p>
      </section>

      <!-- Más vendidos -->
      <section class="min-h-[18rem] rounded-2xl bg-superficie-elevada p-6 shadow-lg ring-1 ring-borde-sutil/80">
        <h2 class="mb-1 text-lg font-semibold text-texto-primario">
          Más entregados
        </h2>
        <p class="mb-5 text-sm text-texto-secundario">
          Por unidades salidas del anaquel, no por importe.
        </p>

        <div
          v-if="cargando"
          class="space-y-2.5"
          aria-busy="true"
        >
          <div
            v-for="n in 8"
            :key="n"
            class="h-5 animate-pulse rounded bg-superficie-hundida"
          />
        </div>
        <GraficoMasVendidos
          v-else-if="datos && datos.mas_vendidos.length"
          :productos="datos.mas_vendidos"
        />
        <p
          v-else-if="datos"
          class="grid min-h-40 place-items-center text-center text-sm text-texto-terciario"
        >
          No hay ventas registradas en los últimos {{ ventana }} días.
        </p>
      </section>
    </div>

    <p
      v-if="datos"
      class="text-xs text-texto-terciario"
    >
      Calculado el
      {{ new Date(datos.generado_en).toLocaleString('es-PE') }}
      sobre {{ datos.vencimientos.lotes_con_existencia.toLocaleString('es-PE') }}
      lotes con existencia.
      <span v-if="datos.arqueo.total > 0">
        Caja de la ventana: {{ soles(datos.arqueo.total) }}.
      </span>
    </p>
  </div>
</template>
