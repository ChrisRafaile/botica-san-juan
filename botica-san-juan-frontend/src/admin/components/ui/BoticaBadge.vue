<script setup lang="ts">
/**
 * BoticaBadge · Insignia de estado
 * ---------------------------------------------------------------------------
 * Traduce un estado del negocio a color, sin que cada vista tenga que decidir
 * qué tono usar. Los estados de stock y de vencimiento salen de los tokens de
 * dominio del design system, así que si cambia el criterio de "crítico" se
 * cambia en un sitio y cambia en toda la aplicación.
 *
 * Accesibilidad: el color nunca es el único portador del significado — el
 * texto de la insignia siempre dice el estado. Una persona con daltonismo lee
 * "Crítico" igual que cualquiera.
 */

import { computed } from 'vue'

type Tono =
  | 'critico'
  | 'bajo'
  | 'normal'
  | 'agotado'
  | 'vence-critico'
  | 'vence-alto'
  | 'vence-medio'
  | 'vigente'
  | 'neutro'
  | 'marca'

const props = withDefaults(
  defineProps<{
    tono?: Tono
    /** `suave` para tablas y listas; `solido` para destacar un dato puntual. */
    variante?: 'suave' | 'solido'
    /** Muestra un punto de color antes del texto. */
    punto?: boolean
  }>(),
  { tono: 'neutro', variante: 'suave', punto: false },
)

/* Cada tono define el color de fondo y el de texto. Se escriben como clases
   completas y no concatenadas, para que el escáner de Tailwind las detecte. */
const ESTILOS: Record<Tono, { suave: string; solido: string; punto: string }> = {
  critico: {
    suave: 'bg-stock-critico-suave text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-500',
    solido: 'bg-stock-critico text-white',
    punto: 'bg-stock-critico',
  },
  agotado: {
    suave: 'bg-stock-agotado-suave text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-500',
    solido: 'bg-stock-agotado text-white',
    punto: 'bg-stock-agotado',
  },
  bajo: {
    suave: 'bg-stock-bajo-suave text-alerta-700 dark:bg-alerta-500/15 dark:text-alerta-500',
    solido: 'bg-stock-bajo text-neutro-950',
    punto: 'bg-stock-bajo',
  },
  normal: {
    suave: 'bg-stock-optimo-suave text-exito-700 dark:bg-exito-500/15 dark:text-exito-500',
    solido: 'bg-stock-optimo text-white',
    punto: 'bg-stock-optimo',
  },
  'vence-critico': {
    suave: 'bg-venc-critico-suave text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-500',
    solido: 'bg-venc-critico text-white',
    punto: 'bg-venc-critico',
  },
  'vence-alto': {
    suave: 'bg-venc-alto-suave text-alerta-700 dark:bg-alerta-500/15 dark:text-alerta-500',
    solido: 'bg-venc-alto text-white',
    punto: 'bg-venc-alto',
  },
  'vence-medio': {
    suave: 'bg-venc-medio-suave text-alerta-700 dark:bg-alerta-500/15 dark:text-alerta-500',
    solido: 'bg-venc-medio text-neutro-950',
    punto: 'bg-venc-medio',
  },
  vigente: {
    suave: 'bg-venc-vigente-suave text-exito-700 dark:bg-exito-500/15 dark:text-exito-500',
    solido: 'bg-venc-vigente text-white',
    punto: 'bg-venc-vigente',
  },
  neutro: {
    suave: 'bg-superficie-interactiva text-texto-secundario',
    solido: 'bg-neutro-600 text-white',
    punto: 'bg-neutro-500',
  },
  marca: {
    suave: 'bg-botica-50 text-botica-700 dark:bg-botica-500/15 dark:text-botica-300',
    solido: 'bg-botica-700 text-white',
    punto: 'bg-botica-600',
  },
}

const clases = computed(() => ESTILOS[props.tono][props.variante])
const clasePunto = computed(() => ESTILOS[props.tono].punto)
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium"
    :class="clases"
  >
    <span
      v-if="punto"
      class="size-1.5 shrink-0 rounded-full"
      :class="clasePunto"
      aria-hidden="true"
    />
    <slot />
  </span>
</template>
