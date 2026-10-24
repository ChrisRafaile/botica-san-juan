<!--
  Estado de la conexión y de la cola, para la barra del punto de venta.

  QUÉ TIENE QUE SABER EL VENDEDOR, Y EN QUÉ ORDEN

  Tres cosas, y no más, porque está atendiendo:

    1. si hay red o no, de un vistazo y sin leer;
    2. cuántas ventas lleva sin mandar;
    3. si hay algo que exige actuar hoy — la cola pasada de 24 h, o el
       almacenamiento degradado, que significa que apagar el equipo
       perdería las ventas.

  Lo que NO se hace aquí es decidir por él. El sistema avisa; qué hacer con
  una cola de 48 horas lo decide la persona, que puede llamar al contador o
  mover el equipo a otra red.

  SOBRE EL MOVIMIENTO

  El punto parpadea solo cuando está sincronizando, y con `opacity`, que el
  compositor resuelve en la GPU sin recalcular maquetación. Un indicador que
  late todo el rato en el borde del campo visual cansa en un turno de ocho
  horas; `prefers-reduced-motion` lo apaga del todo.

  SOBRE EL ESPACIO QUE OCUPA

  La altura es fija. Si el bloque creciera al aparecer un aviso, empujaría la
  lista de productos hacia abajo justo cuando el vendedor va a pulsar algo.
-->
<template>
  <div class="flex min-h-9 items-center gap-2 text-xs">
    <!-- Conexión -->
    <span
      class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 font-medium"
      :class="enLinea
        ? 'border-exito-500/30 bg-exito-50 text-exito-700 dark:bg-exito-500/15 dark:text-exito-500'
        : 'border-alerta-500/30 bg-alerta-50 text-alerta-700 dark:bg-alerta-500/15 dark:text-alerta-500'"
    >
      <span
        class="size-2 rounded-full"
        :class="[
          enLinea ? 'bg-exito-600' : 'bg-alerta-600',
          sincronizando ? 'animate-pulse motion-reduce:animate-none' : '',
        ]"
        aria-hidden="true"
      />
      {{ enLinea ? 'En línea' : 'Sin conexión' }}
    </span>

    <!-- Cola pendiente -->
    <button
      v-if="pendientes > 0"
      type="button"
      class="inline-flex items-center gap-1.5 rounded-full border border-borde-sutil bg-superficie-elevada px-2.5 py-1 font-medium text-texto-secundario transition hover:border-borde-marca focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco disabled:opacity-60"
      :disabled="sincronizando || !enLinea"
      @click="emitir('sincronizar')"
    >
      <CloudUpload class="size-3.5" aria-hidden="true" />
      {{ pendientes }} {{ pendientes === 1 ? 'venta por enviar' : 'ventas por enviar' }}
      <span v-if="enLinea && !sincronizando" class="text-texto-terciario">· Enviar</span>
    </button>

    <!-- Atraso frente al envío -->
    <span
      v-if="nivelDeAtraso !== 'ninguno'"
      class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 font-semibold"
      :class="nivelDeAtraso === 'alerta'
        ? 'border-peligro-500/30 bg-peligro-50 text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-500'
        : 'border-alerta-500/30 bg-alerta-50 text-alerta-700 dark:bg-alerta-500/15 dark:text-alerta-500'"
      role="status"
    >
      <TriangleAlert class="size-3.5" aria-hidden="true" />
      {{ textoDeAtraso }}
    </span>

    <!-- Almacenamiento volátil -->
    <span
      v-if="almacenamientoDegradado"
      class="inline-flex items-center gap-1.5 rounded-full border border-peligro-500/30 bg-peligro-50 px-2.5 py-1 font-semibold text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-500"
      role="status"
    >
      <HardDriveDownload class="size-3.5" aria-hidden="true" />
      No cierres la aplicación: las ventas no están guardadas en disco
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { CloudUpload, HardDriveDownload, TriangleAlert } from 'lucide-vue-next'

const props = defineProps<{
  enLinea: boolean
  sincronizando: boolean
  pendientes: number
  horasDeAtraso: number
  nivelDeAtraso: 'ninguno' | 'aviso' | 'alerta'
  almacenamientoDegradado: boolean
}>()

const emitir = defineEmits<{ sincronizar: [] }>()

const textoDeAtraso = computed(() => {
  const horas = Math.floor(props.horasDeAtraso)

  /* El mensaje nombra la acción, no el umbral: «48 h» no le dice nada a quien
     está en el mostrador. */
  return props.nivelDeAtraso === 'alerta'
    ? `Sin enviar desde hace ${horas} h · avisa al responsable`
    : `Sin enviar desde hace ${horas} h`
})
</script>
