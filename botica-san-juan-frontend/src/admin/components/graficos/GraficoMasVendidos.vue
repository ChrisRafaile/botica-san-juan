<script setup lang="ts">
/**
 * GraficoMasVendidos · Top de medicamentos por unidades entregadas
 * ---------------------------------------------------------------------------
 * Barras horizontales y no verticales: los nombres de medicamento son largos
 * («AB-MOKS FORTE 250 mg») y en vertical habría que girarlos o truncarlos,
 * que es justo lo que vuelve ilegible este tipo de gráfico.
 *
 * Se ordena por UNIDADES, igual que el endpoint. Lo que interesa para reponer
 * es cuántas cajas salieron del anaquel, no cuánto facturaron: un producto
 * caro vendido dos veces no se repone antes que uno barato vendido cien.
 * El importe se muestra al lado, como contexto, no como criterio.
 */
import { computed } from 'vue'
import type { MasVendido } from '@/services/indicadores'

const props = defineProps<{ productos: MasVendido[] }>()

const mayor = computed(
  () => Math.max(1, ...props.productos.map((p) => p.unidades)),
)

const filas = computed(() =>
  props.productos.map((p, i) => ({
    ...p,
    porcentaje: (p.unidades / mayor.value) * 100,
    /* Escalonado corto: deja leer el orden sin que la última barra tarde
       un segundo en aparecer. Diez filas por 25 ms son 225 ms de cola. */
    retardo: `${i * 25}ms`,
  })),
)

const soles = (valor: number) =>
  valor.toLocaleString('es-PE', { style: 'currency', currency: 'PEN' })
</script>

<template>
  <ol class="space-y-2.5">
    <li
      v-for="(fila, indice) in filas"
      :key="fila.producto_id"
      class="grid grid-cols-[1.25rem_11rem_1fr_auto] items-center gap-2.5"
    >
      <span class="text-xs tabular-nums text-texto-terciario">
        {{ indice + 1 }}
      </span>

      <span
        class="truncate text-xs text-texto-primario"
        :title="fila.nombre"
      >{{ fila.nombre }}</span>

      <div
        class="h-4 overflow-hidden rounded bg-superficie-hundida"
        role="presentation"
      >
        <div
          class="h-full rounded bg-botica-600 barra"
          :style="{ width: `${fila.porcentaje}%`, animationDelay: fila.retardo }"
        />
      </div>

      <span class="shrink-0 text-xs tabular-nums text-texto-secundario">
        {{ fila.unidades }} u.
        <span class="text-texto-terciario">· {{ soles(fila.importe) }}</span>
      </span>
    </li>
  </ol>
</template>

<style scoped>
/* El ancho se fija con `width` en línea —es el dato— y la ENTRADA se anima
   con `transform`, que la GPU compone sin recalcular maquetación. Animar el
   `width` haría reflow en cada fotograma de las diez filas a la vez. */
.barra {
  transform-origin: left center;
  animation: crecer var(--mov-medio) cubic-bezier(0.22, 1, 0.36, 1) both;
  will-change: transform;
}

@keyframes crecer {
  from { transform: scaleX(0); }
  to { transform: scaleX(1); }
}

@media (prefers-reduced-motion: reduce) {
  .barra {
    animation-duration: var(--mov-amortiguado);
    animation-delay: 0ms !important;
  }
}
</style>
