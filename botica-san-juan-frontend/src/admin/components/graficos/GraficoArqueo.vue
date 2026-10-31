<script setup lang="ts">
/**
 * GraficoArqueo · Reparto de la caja por medio de pago
 * ---------------------------------------------------------------------------
 * Un anillo, no un pastel: el hueco central lleva el total, que es el número
 * que el dueño busca primero. Un pastel obliga a sumar mentalmente los
 * sectores para saber cuánto entró.
 *
 * Los sectores se dibujan con `stroke-dasharray` sobre un círculo en lugar de
 * con arcos `path`. Sale menos código, no hay trigonometría que revisar, y la
 * animación de entrada puede ser un giro —`transform`, compuesto por la
 * GPU— en vez de un redibujado de geometría.
 */
import { computed } from 'vue'
import type { MedioDePago } from '@/services/indicadores'

const props = defineProps<{
  medios: MedioDePago[]
  total: number
}>()

/* Paleta de la botica, en orden de uso. Si hubiera más medios que colores se
   reutiliza la lista: es preferible repetir un tono a inventar uno que no
   pertenece al sistema de diseño. */
const PALETA = [
  'var(--color-botica-600)',
  'var(--color-clinico-600)',
  'var(--color-ambar-600)',
  'var(--color-botica-800)',
  'var(--color-clinico-800)',
]

const RADIO = 42
const CIRCUNFERENCIA = 2 * Math.PI * RADIO

interface Sector {
  medio: string
  etiqueta: string
  color: string
  operaciones: number
  total: number
  porcentaje: number
  dash: string
  desfase: number
}

const sectores = computed<Sector[]>(() => {
  let acumulado = 0

  return props.medios.map((m, i) => {
    const largo = (m.porcentaje / 100) * CIRCUNFERENCIA
    const sector: Sector = {
      ...m,
      color: PALETA[i % PALETA.length],
      dash: `${largo} ${CIRCUNFERENCIA - largo}`,
      desfase: -acumulado,
    }
    acumulado += largo
    return sector
  })
})

const soles = (valor: number) =>
  valor.toLocaleString('es-PE', { style: 'currency', currency: 'PEN' })
</script>

<template>
  <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-center">
    <!-- El contenedor tiene tamaño fijo: el hueco existe antes que los datos. -->
    <div class="relative size-40 shrink-0">
      <svg
        viewBox="0 0 100 100"
        class="size-full -rotate-90"
        role="img"
        :aria-label="`Reparto de ${soles(total)} entre ${medios.length} medios de pago`"
      >
        <circle
          cx="50"
          cy="50"
          :r="RADIO"
          fill="none"
          stroke="var(--color-superficie-hundida)"
          stroke-width="12"
        />
        <circle
          v-for="sector in sectores"
          :key="sector.medio"
          cx="50"
          cy="50"
          :r="RADIO"
          fill="none"
          :stroke="sector.color"
          stroke-width="12"
          :stroke-dasharray="sector.dash"
          :stroke-dashoffset="sector.desfase"
          stroke-linecap="butt"
          class="sector"
        />
      </svg>

      <!-- El total va en HTML encima del SVG: así se puede seleccionar y los
           lectores de pantalla lo leen como texto, no como etiqueta gráfica. -->
      <div class="pointer-events-none absolute inset-0 grid place-items-center">
        <div class="text-center">
          <p class="text-lg font-bold tabular-nums text-texto-primario">
            {{ soles(total) }}
          </p>
          <p class="text-2xs uppercase tracking-wide text-texto-terciario">
            Total
          </p>
        </div>
      </div>
    </div>

    <ul class="w-full min-w-0 space-y-2">
      <li
        v-for="sector in sectores"
        :key="sector.medio"
        class="flex items-center gap-2.5"
      >
        <span
          class="size-2.5 shrink-0 rounded-full"
          :style="{ backgroundColor: sector.color }"
          aria-hidden="true"
        />
        <span class="min-w-0 flex-1 truncate text-sm text-texto-primario">
          {{ sector.etiqueta }}
          <span class="text-xs text-texto-terciario">
            · {{ sector.operaciones }}
            {{ sector.operaciones === 1 ? 'operación' : 'operaciones' }}
          </span>
        </span>
        <span class="shrink-0 text-sm tabular-nums text-texto-secundario">
          {{ soles(sector.total) }}
        </span>
        <span class="w-12 shrink-0 text-right text-xs tabular-nums text-texto-terciario">
          {{ sector.porcentaje }}%
        </span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.sector {
  transform-origin: center;
  animation: girar var(--mov-medio) cubic-bezier(0.22, 1, 0.36, 1) both;
  will-change: transform;
}

@keyframes girar {
  from { transform: rotate(-12deg) scale(0.94); opacity: 0; }
  to { transform: rotate(0) scale(1); opacity: 1; }
}

@media (prefers-reduced-motion: reduce) {
  .sector {
    animation-duration: var(--mov-amortiguado);
  }
}
</style>
