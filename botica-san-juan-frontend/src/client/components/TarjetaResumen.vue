<!--
  Tarjeta de cifra del portal del cliente.

  LO IMPORTANTE ES EL ESQUELETO, NO EL ADORNO

  Mientras carga muestra un bloque del MISMO alto que el contenido final. Sin
  esa reserva, las cuatro tarjetas aparecen de golpe cuando responde la API y
  empujan hacia abajo todo lo que tienen debajo —el historial y los
  comprobantes—, que es cómo el catálogo llegó a un CLS de 0.44.

  Por eso el contenedor fija su altura y el esqueleto vive dentro, en vez de
  usar un `v-if` que cambie el tamaño de la tarjeta.
-->
<template>
  <component
    :is="enlace ? RouterLink : 'div'"
    :to="enlace"
    class="block h-28 rounded-2xl border border-borde-sutil bg-superficie-elevada p-4 transition"
    :class="enlace ? 'hover:border-borde-marca focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco' : ''"
  >
    <div class="flex items-start justify-between gap-2">
      <p class="text-xs font-medium text-texto-secundario">
        {{ etiqueta }}
      </p>
      <component
        :is="icono"
        class="size-4 shrink-0 text-texto-terciario"
        aria-hidden="true"
      />
    </div>

    <template v-if="cargando">
      <div class="mt-3 h-7 w-20 animate-pulse rounded bg-superficie-hundida" />
      <div class="mt-2 h-3 w-28 animate-pulse rounded bg-superficie-hundida" />
    </template>

    <template v-else>
      <p class="mt-2 text-2xl font-bold tracking-tight text-texto-primario">
        {{ valor }}
      </p>
      <p class="mt-1 truncate text-xs text-texto-terciario">
        {{ detalle }}
      </p>
    </template>
  </component>
</template>

<script setup lang="ts">
import type { Component } from 'vue'
import { RouterLink } from 'vue-router'

defineProps<{
  etiqueta: string
  valor: string | number
  detalle: string
  icono: Component
  cargando: boolean
  /** Si se indica, la tarjeta entera es un enlace. */
  enlace?: string
}>()
</script>
