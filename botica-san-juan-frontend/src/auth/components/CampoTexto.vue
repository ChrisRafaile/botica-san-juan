<!--
  Campo de formulario de la pantalla de acceso.

  Existe porque los ocho campos de esta pantalla comparten estructura, estados
  de error y accesibilidad. Repetirla ocho veces garantiza que tarde o
  temprano una copia se quede sin `aria-describedby` y el lector de pantalla
  no anuncie el error.
-->
<template>
  <div>
    <label
      :for="id"
      class="mb-1.5 block text-sm font-medium text-texto-primario"
    >
      {{ etiqueta }}
    </label>

    <div class="relative">
      <component
        :is="icono"
        v-if="icono"
        class="pointer-events-none absolute left-3 top-1/2 size-4.5 -translate-y-1/2 text-texto-terciario"
        aria-hidden="true"
      />

      <input
        :id="id"
        :value="modelValue"
        :type="type"
        :inputmode="inputmode"
        :autocomplete="autocomplete"
        :maxlength="maxlength"
        :placeholder="placeholder"
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="error ? `${id}-error` : undefined"
        class="w-full rounded-xl border bg-superficie-elevada py-2.5 text-texto-primario outline-none transition placeholder:text-texto-deshabilitado focus:ring-4"
        :class="[
          icono ? 'pl-10' : 'pl-3.5',
          accionIcono ? 'pr-11' : 'pr-3.5',
          error
            ? 'border-peligro-600 focus:border-peligro-600 focus:ring-peligro-500/20'
            : 'border-borde-base focus:border-borde-marca focus:ring-botica-500/20',
        ]"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        @blur="$emit('blur')"
      />

      <button
        v-if="accionIcono"
        type="button"
        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-texto-terciario transition hover:bg-superficie-interactiva hover:text-texto-primario focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
        :aria-label="accionEtiqueta"
        @click="$emit('accion')"
      >
        <component
          :is="accionIcono"
          class="size-4.5"
          aria-hidden="true"
        />
      </button>
    </div>

    <!--
      El error se anuncia con role="alert" y se enlaza con aria-describedby:
      en un lector de pantalla, un mensaje rojo sin enlazar simplemente no
      existe.
    -->
    <p
      v-if="error"
      :id="`${id}-error`"
      class="mt-1.5 text-xs text-peligro-700 dark:text-peligro-500"
      role="alert"
    >
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
import type { Component } from 'vue'

withDefaults(
  defineProps<{
    id: string
    etiqueta: string
    modelValue: string
    icono?: Component
    type?: string
    inputmode?: 'text' | 'numeric' | 'tel' | 'email'
    autocomplete?: string
    maxlength?: string | number
    placeholder?: string
    error?: string
    accionIcono?: Component
    accionEtiqueta?: string
  }>(),
  { type: 'text' },
)

defineEmits<{
  'update:modelValue': [valor: string]
  blur: []
  accion: []
}>()
</script>
