<script setup lang="ts">
/**
 * MedidorDeContrasena · Fuerza de la contraseña en tiempo real
 * ---------------------------------------------------------------------------
 * QUÉ MIDE Y QUÉ NO
 *
 * Mide composición: longitud, mayúsculas, minúsculas, dígitos y símbolos.
 * Eso NO es lo mismo que resistencia real —«Contrasena1!» cumple las cinco
 * reglas y está en cualquier diccionario de ataque—, así que el medidor dice
 * explícitamente qué falta en lugar de limitarse a pintar una barra verde que
 * da una seguridad que no corresponde.
 *
 * La longitud pesa más que el resto porque es lo que de verdad encarece un
 * ataque por fuerza bruta; añadir un símbolo a una contraseña de seis
 * caracteres apenas cambia nada.
 *
 * ACCESIBILIDAD
 *
 * El color no es el único indicador: la etiqueta dice «Débil», «Aceptable» o
 * «Fuerte» en texto, y la lista de requisitos marca cada uno por separado.
 * Con solo color, quien no distingue rojo de verde no recibe el mensaje.
 */
import { computed } from 'vue'

const props = defineProps<{ contrasena: string }>()

interface Requisito {
  etiqueta: string
  cumple: boolean
}

const requisitos = computed<Requisito[]>(() => {
  const c = props.contrasena ?? ''

  return [
    { etiqueta: 'Al menos 8 caracteres', cumple: c.length >= 8 },
    { etiqueta: 'Una mayúscula', cumple: /[A-ZÁÉÍÓÚÑ]/.test(c) },
    { etiqueta: 'Una minúscula', cumple: /[a-záéíóúñ]/.test(c) },
    { etiqueta: 'Un número', cumple: /\d/.test(c) },
    { etiqueta: 'Un símbolo', cumple: /[^\w\sÁÉÍÓÚÑáéíóúñ]/.test(c) },
  ]
})

const puntuacion = computed(() => {
  const c = props.contrasena ?? ''
  if (!c) return 0

  const cumplidos = requisitos.value.filter((r) => r.cumple).length

  /* La longitud suma aparte: doce caracteres variados resisten mucho más que
     ocho con un símbolo metido con calzador. */
  const porLongitud = c.length >= 16 ? 2 : c.length >= 12 ? 1 : 0

  return Math.min(cumplidos + porLongitud, 7)
})

const nivel = computed<'vacia' | 'debil' | 'aceptable' | 'fuerte'>(() => {
  if (!props.contrasena) return 'vacia'
  if (puntuacion.value <= 2) return 'debil'
  if (puntuacion.value <= 4) return 'aceptable'
  return 'fuerte'
})

const ETIQUETAS = {
  vacia: '',
  debil: 'Débil',
  aceptable: 'Aceptable',
  fuerte: 'Fuerte',
} as const

const COLORES = {
  vacia: 'bg-neutro-300',
  debil: 'bg-peligro-600',
  aceptable: 'bg-alerta-500',
  fuerte: 'bg-exito-600',
} as const

const TEXTO = {
  vacia: 'text-texto-terciario',
  debil: 'text-peligro-600',
  aceptable: 'text-alerta-600',
  fuerte: 'text-exito-600',
} as const

const porcentaje = computed(() => Math.round((puntuacion.value / 7) * 100))
</script>

<template>
  <div v-if="contrasena">
    <div class="flex items-center gap-2">
      <!-- La barra es decorativa: lo que se anuncia es la etiqueta de al lado,
           que va en el aria-live. -->
      <div
        class="h-1.5 flex-1 overflow-hidden rounded-full bg-superficie-hundida"
        aria-hidden="true"
      >
        <div
          class="h-full rounded-full transition-[width,background-color] duration-300 ease-out"
          :class="COLORES[nivel]"
          :style="{ width: `${porcentaje}%` }"
        />
      </div>
      <span
        class="text-xs font-medium"
        :class="TEXTO[nivel]"
        aria-live="polite"
      >
        {{ ETIQUETAS[nivel] }}
      </span>
    </div>

    <ul class="mt-2 grid gap-x-4 gap-y-1 text-xs sm:grid-cols-2">
      <li
        v-for="requisito in requisitos"
        :key="requisito.etiqueta"
        class="flex items-center gap-1.5"
        :class="requisito.cumple ? 'text-exito-600' : 'text-texto-terciario'"
      >
        <span aria-hidden="true">{{ requisito.cumple ? '✓' : '○' }}</span>
        <span>{{ requisito.etiqueta }}</span>
        <span class="sr-only">{{ requisito.cumple ? '(cumplido)' : '(pendiente)' }}</span>
      </li>
    </ul>
  </div>
</template>
