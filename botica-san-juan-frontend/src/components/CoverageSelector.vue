<template>
  <section id="zonas" class="py-24 bg-superficie-elevada scroll-mt-20">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <div class="inline-flex items-center gap-2 bg-clinico-100 text-clinico-700 dark:bg-clinico-500/15 dark:text-clinico-300 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <MapPinIcon class="size-4" aria-hidden="true" />
          San Juan de Lurigancho
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-texto-primario mb-6">
          Encuentra tu zona
        </h2>
        <p class="text-xl text-texto-secundario max-w-3xl mx-auto">
          Repartimos en todo el distrito desde nuestro local. Elige tu urbanización para ver
          el tiempo de entrega y la tarifa.
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <!-- Zonas -->
        <div class="min-w-0 bg-superficie-elevada rounded-2xl shadow-lg p-6 sm:p-8 border border-borde-sutil">
          <h3 class="text-2xl font-bold text-texto-primario mb-6">
            Zonas de reparto
          </h3>

          <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3" aria-label="Zonas de reparto en San Juan de Lurigancho">
            <li v-for="zona in ZONAS" :key="zona.id" class="min-w-0">
              <button
                type="button"
                :aria-pressed="seleccionada?.id === zona.id"
                :class="[
                  'foco-dentro w-full min-w-0 p-4 rounded-xl border-2 text-left transition-colors duration-200',
                  seleccionada?.id === zona.id
                    ? 'border-clinico-500 bg-clinico-50 dark:bg-clinico-500/15'
                    : 'border-borde-control hover:border-clinico-300 hover:bg-superficie-hundida'
                ]"
                @click="seleccionar(zona)"
              >
                <span class="block font-semibold text-texto-primario truncate">{{ zona.nombre }}</span>
                <span class="mt-2 flex items-center gap-4 text-sm text-texto-secundario cifras-tabulares">
                  <span class="inline-flex items-center gap-1.5">
                    <TruckIcon class="size-4 shrink-0 text-clinico-600" aria-hidden="true" />
                    {{ rangoMinutos(zona) }}
                  </span>
                  <span class="inline-flex items-center gap-1.5">
                    <WalletIcon class="size-4 shrink-0 text-botica-600" aria-hidden="true" />
                    {{ soles(zona.tarifa) }}
                  </span>
                </span>
              </button>
            </li>
          </ul>
        </div>

        <!-- Mapa -->
        <div class="min-w-0 bg-superficie-elevada rounded-2xl shadow-lg p-6 sm:p-8 border border-borde-sutil">
          <h3 class="text-2xl font-bold text-texto-primario mb-6">
            Mapa de cobertura
          </h3>

          <!-- Altura fija: el mapa se monta después del primer pintado y no
               debe empujar nada al aparecer. -->
          <div class="rounded-xl h-96 overflow-hidden isolate">
            <div ref="contenedorMapa" class="size-full" role="region" aria-label="Mapa de zonas de reparto en San Juan de Lurigancho"></div>
          </div>

          <div class="mt-6 flex items-start gap-3 p-4 bg-clinico-50 dark:bg-clinico-500/15 rounded-xl">
            <StoreIcon class="size-5 shrink-0 mt-0.5 text-clinico-700 dark:text-clinico-300" aria-hidden="true" />
            <div>
              <p class="font-semibold text-clinico-800 dark:text-clinico-200">Nuestro local</p>
              <p class="text-clinico-700 dark:text-clinico-300">
                {{ DIRECCION.calle }}, {{ DIRECCION.distrito }} {{ DIRECCION.codigoPostal }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Detalle de la zona elegida. Siempre hay una elegida: así el bloque
           no aparece de golpe al primer clic y no desplaza la página. -->
      <div
        v-if="seleccionada"
        class="mt-12 bg-superficie-hundida rounded-2xl p-6 sm:p-8"
        aria-live="polite"
      >
        <h3 class="text-2xl font-bold text-texto-primario mb-2 text-center">
          {{ seleccionada.nombre }}
        </h3>
        <p class="text-center text-texto-secundario mb-8">{{ seleccionada.referencia }}</p>

        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div v-for="dato in detalle" :key="dato.titulo" class="bg-superficie-elevada p-5 rounded-xl shadow-sm">
            <dt class="flex items-center gap-3 mb-2 font-semibold text-texto-primario">
              <component :is="dato.icono" class="size-5 shrink-0 text-clinico-600" aria-hidden="true" />
              {{ dato.titulo }}
            </dt>
            <dd class="text-texto-secundario cifras-tabulares">{{ dato.valor }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue'
import {
  MapPinIcon,
  ClockIcon,
  WalletIcon,
  CalendarIcon,
  ShoppingCartIcon,
  TruckIcon,
  StoreIcon,
} from 'lucide-vue-next'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { DIRECCION, HORARIO } from '@/datos/botica'
import { LOCAL, PEDIDO_MINIMO, ZONAS, rangoMinutos, soles, type ZonaReparto } from '@/datos/cobertura'

const seleccionada = ref<ZonaReparto>(ZONAS[0]!)

const detalle = computed(() => [
  { titulo: 'Tiempo de entrega', valor: rangoMinutos(seleccionada.value), icono: ClockIcon },
  { titulo: 'Tarifa de envío', valor: soles(seleccionada.value.tarifa), icono: WalletIcon },
  { titulo: 'Horario de reparto', valor: HORARIO[0]!.texto + ' (lun.–vie.)', icono: CalendarIcon },
  { titulo: 'Pedido mínimo', valor: soles(PEDIDO_MINIMO), icono: ShoppingCartIcon },
])

const contenedorMapa = ref<HTMLElement | null>(null)
const mapa = shallowRef<L.Map | null>(null)
const marcadores = new Map<string, L.CircleMarker>()

/* Leaflet pinta en SVG y no entiende clases de Tailwind: se lee el valor del
   token en tiempo de ejecución para que el mapa siga la paleta (y el tema). */
function token(nombre: string, respaldo: string) {
  const valor = getComputedStyle(document.documentElement).getPropertyValue(nombre).trim()
  return valor || respaldo
}

function pintarSeleccion() {
  const activo = token('--color-clinico-700', '#1d4ed8')
  const reposo = token('--color-clinico-400', '#60a5fa')
  for (const [id, marcador] of marcadores) {
    const elegido = id === seleccionada.value.id
    marcador.setStyle({ color: elegido ? activo : reposo, fillColor: elegido ? activo : reposo, radius: elegido ? 11 : 7, fillOpacity: elegido ? 0.6 : 0.35 })
  }
}

function seleccionar(zona: ZonaReparto) {
  seleccionada.value = zona
  pintarSeleccion()
  marcadores.get(zona.id)?.openPopup()
}

onMounted(() => {
  if (!contenedorMapa.value) return

  const m = L.map(contenedorMapa.value, { scrollWheelZoom: false }).setView([-11.99, -76.995], 12)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
  }).addTo(m)

  for (const zona of ZONAS) {
    const marcador = L.circleMarker(zona.coordenadas, { weight: 2 })
      .addTo(m)
      .bindPopup(`<b>${zona.nombre}</b><br>${rangoMinutos(zona)} · ${soles(zona.tarifa)}`)
    marcador.on('click', () => {
      seleccionada.value = zona
      pintarSeleccion()
    })
    marcadores.set(zona.id, marcador)
  }

  L.circleMarker(LOCAL, {
    radius: 9,
    weight: 3,
    color: token('--color-peligro-600', '#dc2626'),
    fillColor: token('--color-peligro-500', '#ef4444'),
    fillOpacity: 0.9,
  })
    .addTo(m)
    .bindPopup(`<b>Boticas San Juan</b><br>${DIRECCION.calle}`)

  mapa.value = m
  pintarSeleccion()
})

/* Leaflet registra escuchadores en window (resize) y en el contenedor; sin
   remove() siguen vivos tras salir de la ruta. */
onBeforeUnmount(() => {
  mapa.value?.remove()
  mapa.value = null
  marcadores.clear()
})
</script>
