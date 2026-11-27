<template>
  <section class="py-24 bg-superficie-hundida overflow-hidden" aria-roledescription="carrusel" aria-label="Testimonios de clientes">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <div class="inline-flex items-center gap-2 bg-clinico-100 text-clinico-700 dark:bg-clinico-500/15 dark:text-clinico-300 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <MessageCircleIcon class="size-4" aria-hidden="true" />
          Testimonios
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-texto-primario mb-6">
          Vecinos de SJL que nos eligen
        </h2>
        <p class="text-xl text-texto-secundario max-w-3xl mx-auto">
          Lo que más nos comentan: la asesoría en mostrador y el delivery dentro del distrito.
        </p>
      </div>

      <!-- Pila: altura fija para que el cambio de tarjeta no mueva la página
           (CLS). Solo se animan transform y opacity. -->
      <div class="relative mx-auto max-w-xl h-96 sm:h-80" @keydown.left.prevent="anterior" @keydown.right.prevent="siguiente">
        <article
          v-for="(t, i) in TESTIMONIOS"
          :key="t.nombre"
          :aria-hidden="i !== activo"
          :aria-label="`Testimonio ${i + 1} de ${TESTIMONIOS.length}`"
          class="tarjeta absolute inset-0 flex flex-col rounded-3xl border border-borde-sutil bg-superficie-elevada p-8 shadow-lg"
          :style="estilo(i)"
        >
          <div class="flex items-center gap-1 mb-4" :aria-label="`${t.estrellas} de 5 estrellas`" role="img">
            <StarIcon v-for="n in 5" :key="n" :class="['size-5', n <= t.estrellas ? 'text-ambar-400 fill-current' : 'text-borde-control']" aria-hidden="true" />
          </div>
          <blockquote class="flex-1 text-lg text-texto-secundario leading-relaxed">
            «{{ t.texto }}»
          </blockquote>
          <footer class="mt-6 flex items-center gap-4">
            <img
              v-if="t.foto && !fotoFallida[i]"
              :src="t.foto"
              :alt="`Fotografía de ${t.nombre}`"
              width="56"
              height="56"
              loading="lazy"
              decoding="async"
              class="size-14 rounded-full object-cover"
              @error="fotoFallida[i] = true"
            />
            <!-- Respaldo seguro: iniciales sobre el color de marca. -->
            <span
              v-else
              class="flex size-14 shrink-0 items-center justify-center rounded-full bg-clinico-600 text-lg font-semibold text-white"
              aria-hidden="true"
            >{{ iniciales(t.nombre) }}</span>
            <div class="min-w-0">
              <p class="font-semibold text-texto-primario truncate">{{ t.nombre }}</p>
              <p class="flex items-center gap-1 text-sm text-texto-secundario">
                <MapPinIcon class="size-3.5 shrink-0" aria-hidden="true" />
                {{ t.zona }}, SJL
              </p>
            </div>
          </footer>
        </article>
      </div>

      <div class="mt-10 flex items-center justify-center gap-6">
        <button
          type="button"
          class="foco-dentro flex size-12 items-center justify-center rounded-full border border-borde-control bg-superficie-elevada text-texto-primario transition-colors hover:bg-clinico-50 dark:hover:bg-clinico-500/15"
          aria-label="Testimonio anterior"
          @click="anterior"
        >
          <ChevronLeftIcon class="size-5" aria-hidden="true" />
        </button>
        <p class="cifras-tabulares text-sm text-texto-secundario" aria-live="polite">
          {{ activo + 1 }} / {{ TESTIMONIOS.length }}
        </p>
        <button
          type="button"
          class="foco-dentro flex size-12 items-center justify-center rounded-full border border-borde-control bg-superficie-elevada text-texto-primario transition-colors hover:bg-clinico-50 dark:hover:bg-clinico-500/15"
          aria-label="Testimonio siguiente"
          @click="siguiente"
        >
          <ChevronRightIcon class="size-5" aria-hidden="true" />
        </button>
      </div>

      <p class="mt-8 text-center text-xs text-texto-terciario">
        Testimonios ilustrativos de la experiencia de compra en el local y por delivery.
      </p>
    </div>
  </section>
</template>

<script setup lang="ts">
/**
 * Testimonios en pila con rotación.
 *
 * La versión anterior era una cuadrícula de seis tarjetas con un icono de
 * persona genérico y un bloque de cifras («4.9/5», «98 % de satisfacción»)
 * sin ninguna encuesta detrás. Las cifras se retiran por la misma razón que se
 * retiró «50K+ clientes» en Nuestra Historia.
 *
 * Las fotos son opcionales: con `foto` vacía o una URL que falla, se pintan
 * las iniciales. No se usan retratos de banco de imágenes junto a una reseña
 * inventada: atribuiría a una persona real algo que no dijo.
 */
import { reactive, ref } from 'vue'
import { MessageCircleIcon, StarIcon, ChevronLeftIcon, ChevronRightIcon, MapPinIcon } from 'lucide-vue-next'
import { prefiereMenosMovimiento } from '@/utils/motion'

interface Testimonio {
  nombre: string
  zona: string
  texto: string
  estrellas: number
  foto?: string
}

const TESTIMONIOS: Testimonio[] = [
  { nombre: 'Rosa Q.', zona: 'Canto Grande', estrellas: 5, texto: 'La químico farmacéutica me explicó cómo tomar la amoxicilina de mi hijo y a qué hora. Esa paciencia no la encuentro en las cadenas.' },
  { nombre: 'Jorge M.', zona: 'San Hilarión', estrellas: 5, texto: 'Pedí por la web un domingo en la tarde y en media hora tenía el paracetamol y el suero en la puerta. Pagué con Yape sin problema.' },
  { nombre: 'Elena V.', zona: 'Zárate', estrellas: 4, texto: 'Mi mamá es diabética y siempre tienen su insulina refrigerada. Llega en su cooler, no tibia como en otros sitios.' },
  { nombre: 'Luis C.', zona: 'Mariscal Cáceres', estrellas: 5, texto: 'Me dan la boleta electrónica al toque y la puedo descargar desde mi cuenta. Para el reembolso del seguro me salvó.' },
  { nombre: 'Carmen T.', zona: 'Las Flores', estrellas: 5, texto: 'Subí la foto de la receta, me confirmaron que tenían todo y me avisaron cuando el motorizado ya estaba cerca.' },
]

const activo = ref(0)
const fotoFallida = reactive<Record<number, boolean>>({})
const total = TESTIMONIOS.length

function siguiente() {
  activo.value = (activo.value + 1) % total
}

function anterior() {
  activo.value = (activo.value - 1 + total) % total
}

/** Posición relativa a la activa: 0 delante, ±1 asomando a los lados. */
function estilo(i: number) {
  let d = i - activo.value
  if (d > total / 2) d -= total
  if (d < -total / 2) d += total

  const visible = Math.abs(d) <= 1
  const giro = d === 0 ? 0 : d < 0 ? -4 : 4
  const desplazamiento = d * 6

  return {
    transform: `translateX(${desplazamiento}%) rotate(${giro}deg) scale(${d === 0 ? 1 : 0.94})`,
    opacity: d === 0 ? 1 : visible ? 0.55 : 0,
    zIndex: d === 0 ? 30 : visible ? 20 : 10,
    pointerEvents: d === 0 ? ('auto' as const) : ('none' as const),
    transition: prefiereMenosMovimiento() ? 'none' : undefined,
  }
}

function iniciales(nombre: string) {
  return nombre
    .split(/\s+/)
    .map((p) => p[0])
    .join('')
    .replace(/[^A-ZÁÉÍÓÚÑ]/gi, '')
    .slice(0, 2)
    .toUpperCase()
}
</script>

<style scoped>
.tarjeta {
  transition:
    transform var(--duracion-pausada) var(--ease-salida),
    opacity var(--duracion-pausada) var(--ease-salida);
  will-change: transform, opacity;
}

@media (prefers-reduced-motion: reduce) {
  .tarjeta {
    transition: none;
  }
}
</style>
