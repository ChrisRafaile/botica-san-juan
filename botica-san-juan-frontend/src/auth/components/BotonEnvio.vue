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
    class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-linear-to-r from-clinico-600 to-botica-600 px-4 py-3 font-semibold tracking-wide text-white shadow-md transition-[filter,box-shadow,transform] duration-200 hover:brightness-110 hover:shadow-lg active:scale-[0.99] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:brightness-100"
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
