<script setup lang="ts">
/**
 * DistintivoReceta · Estado de la receta de un pedido, de un vistazo
 * ---------------------------------------------------------------------------
 * CUATRO ESTADOS, NO TRES
 *
 * «Pendiente de adjuntar» y «pendiente de revisar» parecen lo mismo y no lo
 * son: en el primero la pelota está en el tejado del cliente y en el segundo
 * en el del mostrador. Juntarlos haría que el boticario buscara recetas que
 * nadie ha subido todavía.
 *
 * EL COLOR NO ES EL ÚNICO INDICADOR
 *
 * Cada estado lleva su icono y su texto. Con solo color, quien no distingue
 * rojo de verde no recibe el mensaje; y en una pantalla que decide si se
 * dispensa un medicamento, eso no es un detalle estético.
 */
import { computed } from 'vue'
import { CircleAlert, CircleCheck, CircleX, Clock } from 'lucide-vue-next'

import type { EstadoReceta } from '@/services/recetas'

const props = withDefaults(
  defineProps<{
    /** `null` cuando el pedido necesita receta pero todavía no hay ninguna. */
    estado: EstadoReceta | null
    /** Compacto para las filas de una tabla. */
    compacto?: boolean
  }>(),
  { compacto: false },
)

const ESTILOS = {
  falta: {
    icono: CircleAlert,
    rotulo: 'Receta pendiente de adjuntar',
    corto: 'Falta receta',
    clases: 'bg-alerta-50 text-alerta-700 ring-alerta-500/30 dark:bg-alerta-500/10 dark:text-alerta-500',
  },
  pendiente: {
    icono: Clock,
    rotulo: 'Receta por revisar',
    corto: 'Por revisar',
    clases: 'bg-clinico-50 text-clinico-700 ring-clinico-500/30 dark:bg-clinico-600/10 dark:text-clinico-500',
  },
  validada: {
    icono: CircleCheck,
    rotulo: 'Receta validada',
    corto: 'Validada',
    clases: 'bg-exito-50 text-exito-700 ring-exito-500/30 dark:bg-exito-600/10 dark:text-exito-500',
  },
  rechazada: {
    icono: CircleX,
    rotulo: 'Receta rechazada',
    corto: 'Rechazada',
    clases: 'bg-peligro-50 text-peligro-700 ring-peligro-500/30 dark:bg-peligro-600/10 dark:text-peligro-500',
  },
} as const

const estilo = computed(() => ESTILOS[props.estado ?? 'falta'])
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1"
    :class="estilo.clases"
  >
    <component
      :is="estilo.icono"
      class="size-3.5 shrink-0"
      aria-hidden="true"
    />
    {{ compacto ? estilo.corto : estilo.rotulo }}
  </span>
</template>
