<script setup lang="ts">
/**
 * BoticaEstadoVacio · Estado vacío
 * ---------------------------------------------------------------------------
 * Un listado sin resultados no es un error: es una situación normal que debe
 * explicarse. La diferencia entre "no hay nada todavía" y "tu búsqueda no
 * encontró nada" importa, porque la acción que resuelve cada caso es distinta.
 * Por eso el componente acepta un mensaje y una acción opcional en lugar de
 * mostrar siempre el mismo texto genérico.
 */

import type { Component } from 'vue'
import { PackageOpen } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    titulo: string
    descripcion?: string
    icono?: Component
    /** Reduce el espaciado, para usarlo dentro de una tarjeta pequeña. */
    compacto?: boolean
  }>(),
  { icono: () => PackageOpen, compacto: false },
)
</script>

<template>
  <div
    class="flex flex-col items-center justify-center px-6 text-center"
    :class="compacto ? 'py-10' : 'py-16'"
  >
    <div
      class="grid size-12 place-items-center rounded-xl bg-superficie-hundida text-texto-terciario"
      aria-hidden="true"
    >
      <component
        :is="icono"
        class="size-6"
      />
    </div>

    <p class="mt-4 font-display text-md font-semibold text-texto-primario">
      {{ titulo }}
    </p>
    <p
      v-if="descripcion"
      class="mt-1 max-w-sm text-sm text-texto-secundario"
    >
      {{ descripcion }}
    </p>

    <div
      v-if="$slots.accion"
      class="mt-5"
    >
      <slot name="accion" />
    </div>
  </div>
</template>
