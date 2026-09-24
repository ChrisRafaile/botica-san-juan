<script setup lang="ts">
/**
 * BoticaStat · Tarjeta de indicador
 * ---------------------------------------------------------------------------
 * Sustituye a las tarjetas de degradado saturado que había antes (rojo, naranja
 * y verde a pantalla completa).
 *
 * El motivo no es sólo estético. Un degradado saturado ocupando toda la tarjeta
 * grita lo mismo tenga el valor 0 o 340, así que el usuario deja de mirarlo: si
 * todo urge, nada urge. Aquí el color se reserva para el dato y un acento
 * discreto, y la superficie se mantiene neutra. El número es lo que se lee
 * primero, que es justamente lo que se quiere saber de un vistazo.
 *
 * El valor usa cifras tabulares: al actualizarse no "baila" horizontalmente.
 */

import { computed } from 'vue'
import type { Component } from 'vue'

type Tono = 'critico' | 'alerta' | 'exito' | 'marca' | 'info' | 'neutro'

const props = withDefaults(
  defineProps<{
    etiqueta: string
    valor: number | string
    icono?: Component
    tono?: Tono
    /** Texto de apoyo bajo el valor. */
    detalle?: string
    /** Deja el valor en gris y anima un pulso mientras se carga. */
    cargando?: boolean
  }>(),
  { tono: 'neutro' },
)

const TONOS: Record<Tono, { valor: string; chip: string; borde: string }> = {
  critico: {
    valor: 'text-peligro-600 dark:text-peligro-500',
    chip: 'bg-peligro-50 text-peligro-600 dark:bg-peligro-500/15 dark:text-peligro-500',
    borde: 'border-peligro-500/25',
  },
  alerta: {
    valor: 'text-alerta-700 dark:text-alerta-500',
    chip: 'bg-alerta-50 text-alerta-700 dark:bg-alerta-500/15 dark:text-alerta-500',
    borde: 'border-alerta-500/25',
  },
  exito: {
    valor: 'text-exito-700 dark:text-exito-500',
    chip: 'bg-exito-50 text-exito-700 dark:bg-exito-500/15 dark:text-exito-500',
    borde: 'border-exito-500/25',
  },
  marca: {
    valor: 'text-botica-700 dark:text-botica-300',
    chip: 'bg-botica-50 text-botica-700 dark:bg-botica-500/15 dark:text-botica-300',
    borde: 'border-botica-500/25',
  },
  info: {
    valor: 'text-clinico-700 dark:text-clinico-400',
    chip: 'bg-clinico-50 text-clinico-700 dark:bg-clinico-500/15 dark:text-clinico-400',
    borde: 'border-clinico-500/25',
  },
  neutro: {
    valor: 'text-texto-primario',
    chip: 'bg-superficie-interactiva text-texto-secundario',
    borde: 'border-borde-sutil',
  },
}

const t = computed(() => TONOS[props.tono])
</script>

<template>
  <div
    class="group relative overflow-hidden rounded-xl border bg-superficie-elevada p-4 shadow-xs transition-shadow duration-200 hover:shadow-md sm:p-5"
    :class="t.borde"
  >
    <div class="flex items-start justify-between gap-3">
      <p class="text-sm font-medium text-texto-secundario">
        {{ etiqueta }}
      </p>
      <span
        v-if="icono"
        class="grid size-8 shrink-0 place-items-center rounded-lg"
        :class="t.chip"
        aria-hidden="true"
      >
        <component
          :is="icono"
          class="size-4"
        />
      </span>
    </div>

    <p
      class="cifras-tabulares mt-2 font-display text-3xl font-semibold tracking-tight tabular-nums"
      :class="cargando ? 'animate-pulse text-texto-deshabilitado' : t.valor"
      :aria-busy="cargando || undefined"
    >
      {{ cargando ? '—' : valor }}
    </p>

    <p
      v-if="detalle"
      class="mt-1 text-xs text-texto-terciario"
    >
      {{ detalle }}
    </p>
  </div>
</template>
