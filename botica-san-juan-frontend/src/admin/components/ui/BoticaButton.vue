<script setup lang="ts">
/**
 * BoticaButton · Botón
 * ---------------------------------------------------------------------------
 * Un único botón con variantes, en lugar de repetir cadenas de clases en cada
 * vista. Antes convivían degradados azules, morados y verdes sin criterio; aquí
 * el color comunica jerarquía: `primario` para la acción principal de la
 * pantalla (una sola por vista), `secundario` para el resto, `fantasma` para
 * acciones de bajo peso y `peligro` para lo destructivo.
 *
 * Detalles que suelen olvidarse y aquí están resueltos:
 * - Estado de carga con `aria-busy` y bloqueo de doble envío.
 * - Área táctil mínima de 40px de alto, cómoda en móvil.
 * - El icono se marca decorativo, para que no se lea dos veces.
 */

import { computed } from 'vue'
import type { Component } from 'vue'
import { LoaderCircle } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    variante?: 'primario' | 'secundario' | 'fantasma' | 'peligro'
    tamano?: 'sm' | 'md'
    icono?: Component
    cargando?: boolean
    disabled?: boolean
    type?: 'button' | 'submit' | 'reset'
    /** Sólo icono: exige etiqueta accesible porque no habrá texto visible. */
    etiquetaAccesible?: string
  }>(),
  { variante: 'secundario', tamano: 'md', type: 'button' },
)

const VARIANTES = {
  primario:
    'bg-botica-700 text-white shadow-xs hover:bg-botica-800 active:bg-botica-900 disabled:hover:bg-botica-700',
  secundario:
    'border border-borde-base bg-superficie-elevada text-texto-primario hover:bg-superficie-interactiva active:bg-superficie-hundida',
  fantasma:
    'text-texto-secundario hover:bg-superficie-interactiva hover:text-texto-primario',
  peligro:
    'bg-peligro-600 text-white shadow-xs hover:bg-peligro-700 active:bg-peligro-700',
} as const

const TAMANOS = {
  sm: 'h-8 gap-1.5 px-2.5 text-xs',
  md: 'h-10 gap-2 px-3.5 text-sm',
} as const

const inactivo = computed(() => props.disabled || props.cargando)
</script>

<template>
  <button
    :type="type"
    :disabled="inactivo"
    :aria-busy="cargando || undefined"
    :aria-label="etiquetaAccesible"
    class="inline-flex shrink-0 items-center justify-center rounded-lg font-medium transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco disabled:cursor-not-allowed disabled:opacity-55"
    :class="[VARIANTES[variante], TAMANOS[tamano]]"
  >
    <LoaderCircle
      v-if="cargando"
      class="size-4 shrink-0 animate-spin"
      aria-hidden="true"
    />
    <component
      :is="icono"
      v-else-if="icono"
      class="size-4 shrink-0"
      aria-hidden="true"
    />
    <slot />
  </button>
</template>
