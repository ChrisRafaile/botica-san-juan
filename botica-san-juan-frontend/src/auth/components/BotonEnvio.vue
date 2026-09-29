<!--
  Botón de envío de los formularios de acceso.

  Se deshabilita mientras la petición está en vuelo. No es cosmética: sin eso,
  una segunda pulsación durante el envío crea un segundo registro de usuario o
  un segundo token de sesión.
-->
<template>
  <button
    type="submit"
    :disabled="cargando"
    :aria-busy="cargando || undefined"
    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-botica-700 px-4 py-3 font-semibold text-white shadow-xs transition hover:bg-botica-800 active:bg-botica-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco disabled:cursor-not-allowed disabled:opacity-60"
  >
    <LoaderCircle
      v-if="cargando"
      class="size-4.5 animate-spin"
      aria-hidden="true"
    />
    <component
      :is="icono"
      v-else-if="icono"
      class="size-4.5"
      aria-hidden="true"
    />
    <slot />
  </button>
</template>

<script setup lang="ts">
import type { Component } from 'vue'
import { LoaderCircle } from 'lucide-vue-next'

defineProps<{
  cargando?: boolean
  icono?: Component
}>()
</script>
