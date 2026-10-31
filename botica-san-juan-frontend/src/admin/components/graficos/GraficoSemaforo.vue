<script setup lang="ts">
/**
 * GraficoSemaforo · Vencimiento de lotes por tramos de 30, 60 y 90 días
 * ---------------------------------------------------------------------------
 * POR QUÉ SVG A MANO Y NO UNA LIBRERÍA
 *
 * Tres razones, en orden de peso:
 *
 * 1. El `viewBox` fija la relación de aspecto, así que el hueco del gráfico
 *    existe antes de que lleguen los datos y NO hay desplazamiento de
 *    maquetación. Una librería que mide el contenedor al montarse provoca
 *    justamente el salto que aquí hay que evitar.
 * 2. Los colores salen de los tokens del sistema de diseño, no de una paleta
 *    por omisión que no conoce la identidad de la botica.
 * 3. Cero kilobytes añadidos al paquete.
 *
 * LO QUE EL GRÁFICO TIENE QUE DECIR AUNQUE INCOMODE
 *
 * El dato más importante no es el reparto por tramos: es la COBERTURA. Si
 * solo el 0,1 % de los lotes tiene fecha capturada, un semáforo bonito sobre
 * ese 0,1 % da una tranquilidad falsa. Por eso la cobertura se muestra
 * primero y los lotes sin fecha aparecen como una barra propia, ni repartida
 * entre los tramos ni escondida.
 */
import { computed } from 'vue'
import type { Vencimientos } from '@/services/indicadores'

const props = defineProps<{ datos: Vencimientos }>()

const COLORES: Record<string, string> = {
  peligro: 'var(--color-peligro-600)',
  alerta: 'var(--color-alerta-600)',
  ambar: 'var(--color-ambar-600)',
  exito: 'var(--color-exito-600)',
}

/** Alto de cada fila y separación, en unidades del viewBox. */
const FILA = 34
const SEPARACION = 10
const ANCHO = 100
const ETIQUETA = 0 // las etiquetas van en HTML, no dentro del SVG

interface Barra {
  etiqueta: string
  lotes: number
  unidades: number
  color: string
  ancho: number
  destacada: boolean
}

const barras = computed<Barra[]>(() => {
  const filas = [
    ...props.datos.tramos.map((t) => ({
      etiqueta: t.etiqueta,
      lotes: t.lotes,
      unidades: t.unidades,
      color: COLORES[t.color] ?? COLORES.exito,
      destacada: false,
    })),
    {
      etiqueta: 'Sin fecha capturada',
      lotes: props.datos.sin_fecha.lotes,
      unidades: props.datos.sin_fecha.unidades,
      color: 'var(--color-neutro-400)',
      destacada: true,
    },
  ]

  const mayor = Math.max(1, ...filas.map((f) => f.lotes))

  return filas.map((f) => ({
    ...f,
    /* Mínimo visible de 0.6 para que un tramo con un solo lote no quede
       como una raya invisible junto a otro con 879. */
    ancho: f.lotes === 0 ? 0 : Math.max(0.6, (f.lotes / mayor) * ANCHO),
  }))
})

const alto = computed(() => barras.value.length * (FILA + SEPARACION))

const coberturaBaja = computed(() => props.datos.cobertura < 50)
</script>

<template>
  <div>
    <!-- La cobertura va arriba porque condiciona la lectura de todo lo demás. -->
    <div
      class="mb-4 flex items-baseline gap-2 rounded-lg px-3 py-2"
      :class="coberturaBaja
        ? 'bg-alerta-50 dark:bg-alerta-500/10'
        : 'bg-exito-50 dark:bg-exito-600/10'"
    >
      <span
        class="text-2xl font-bold tabular-nums"
        :class="coberturaBaja ? 'text-alerta-700 dark:text-alerta-500' : 'text-exito-700 dark:text-exito-500'"
      >{{ datos.cobertura }}%</span>
      <span class="text-sm text-texto-secundario">
        de los {{ datos.lotes_con_existencia.toLocaleString('es-PE') }} lotes con existencia
        tiene fecha de vencimiento registrada.
      </span>
    </div>

    <p
      v-if="coberturaBaja"
      class="mb-4 text-xs leading-relaxed text-texto-terciario"
    >
      Con esta cobertura el semáforo describe una parte pequeña del almacén.
      La forma de cerrarlo es el conteo por ciclos: cada lote contado deja su
      fecha capturada.
    </p>

    <!-- Filas: etiqueta en HTML (legible por lector de pantalla y
         seleccionable) y barra en SVG con alto reservado. -->
    <ul class="space-y-2.5">
      <li
        v-for="barra in barras"
        :key="barra.etiqueta"
        class="grid grid-cols-[9.5rem_1fr_auto] items-center gap-3"
      >
        <span
          class="truncate text-xs"
          :class="barra.destacada ? 'font-semibold text-texto-primario' : 'text-texto-secundario'"
        >{{ barra.etiqueta }}</span>

        <svg
          class="h-4 w-full"
          :viewBox="`0 0 ${ANCHO} 10`"
          preserveAspectRatio="none"
          role="presentation"
          aria-hidden="true"
        >
          <rect
            x="0"
            y="1"
            :width="ANCHO"
            height="8"
            rx="2"
            fill="var(--color-superficie-hundida)"
          />
          <rect
            x="0"
            y="1"
            :width="barra.ancho"
            height="8"
            rx="2"
            :fill="barra.color"
            class="barra"
          />
        </svg>

        <span class="text-xs tabular-nums text-texto-secundario">
          {{ barra.lotes.toLocaleString('es-PE') }}
          <span class="text-texto-terciario">
            · {{ barra.unidades.toLocaleString('es-PE') }} u.
          </span>
        </span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
/* La entrada anima `transform`, no `width`: así la compone la GPU y no
   provoca recálculo de maquetación en cada fotograma. El origen a la
   izquierda hace que la barra crezca desde su inicio. */
.barra {
  transform-origin: left center;
  animation: crecer var(--mov-medio) cubic-bezier(0.22, 1, 0.36, 1) both;
  will-change: transform;
}

@keyframes crecer {
  from { transform: scaleX(0); }
  to { transform: scaleX(1); }
}

/* Quien pidió menos movimiento no recibe cero: recibe lo mismo, amortiguado.
   Una animación cortada a 0.01ms salta más de lo que acompaña. */
@media (prefers-reduced-motion: reduce) {
  .barra {
    animation-duration: var(--mov-amortiguado);
  }
}
</style>
