<template>
  <section class="relative min-h-[60vh] flex items-center justify-center overflow-hidden">
    <div class="absolute inset-0">
      <img
        src="/images/frontal.jpg"
        alt=""
        width="1600"
        height="900"
        fetchpriority="high"
        class="size-full object-cover"
      />
      <div class="absolute inset-0 bg-clinico-900/80" />
    </div>

    <div ref="contenido" class="relative z-10 text-center text-white px-6 py-16 max-w-4xl mx-auto">
      <div class="inline-flex items-center gap-2 bg-black/15 backdrop-blur-sm px-4 py-2 rounded-full text-sm font-medium mb-6">
        <MapPinIcon class="size-4" aria-hidden="true" />
        Cobertura de delivery
      </div>

      <h1 class="text-4xl sm:text-5xl md:text-7xl font-bold mb-6 leading-tight text-balance">
        Delivery a domicilio
        <span class="block text-clinico-300">en San Juan de Lurigancho</span>
      </h1>

      <p class="text-lg md:text-2xl text-white/85 mb-10 max-w-3xl mx-auto leading-relaxed">
        Llevamos tus medicamentos desde nuestro local en
        <span class="font-semibold text-white">{{ DIRECCION.calle }}</span>
        a cada urbanización del distrito.
      </p>

      <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-10">
        <div v-for="dato in datos" :key="dato.titulo" class="bg-black/15 backdrop-blur-sm rounded-2xl p-5">
          <dt class="sr-only">{{ dato.titulo }}</dt>
          <dd>
            <component :is="dato.icono" class="size-6 mx-auto mb-2 text-clinico-300" aria-hidden="true" />
            <span class="block text-2xl sm:text-3xl font-bold cifras-tabulares">{{ dato.valor }}</span>
            <span class="block text-white/85">{{ dato.titulo }}</span>
          </dd>
        </div>
      </dl>

      <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a
          href="#zonas"
          class="foco-dentro inline-flex items-center justify-center gap-2 bg-clinico-600 hover:bg-clinico-700 text-white px-8 py-4 rounded-xl font-semibold text-lg transition-colors duration-200 shadow-lg"
        >
          <MapIcon class="size-5" aria-hidden="true" />
          Ver zonas y tarifas
        </a>
        <RouterLink
          to="/products"
          class="foco-dentro inline-flex items-center justify-center gap-2 bg-black/15 backdrop-blur-sm hover:bg-white/20 text-white px-8 py-4 rounded-xl font-semibold text-lg transition-colors duration-200 border border-white"
        >
          <ShoppingBagIcon class="size-5" aria-hidden="true" />
          Hacer pedido
        </RouterLink>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { MapPinIcon, MapIcon, ShoppingBagIcon, LayersIcon, TimerIcon, WalletIcon } from 'lucide-vue-next'
import { gsap } from 'gsap'
import { DIRECCION } from '@/datos/botica'
import { MINUTOS_EXTREMOS, TARIFA_EXTREMOS, ZONAS } from '@/datos/cobertura'
import { DUR, EASE, prefiereMenosMovimiento } from '@/utils/motion'

const datos = [
  { titulo: 'Zonas de SJL cubiertas', valor: String(ZONAS.length), icono: LayersIcon },
  { titulo: 'Tiempo de entrega', valor: `${MINUTOS_EXTREMOS[0]}–${MINUTOS_EXTREMOS[1]} min`, icono: TimerIcon },
  { titulo: 'Tarifa de envío', valor: `S/ ${TARIFA_EXTREMOS[0]}–${TARIFA_EXTREMOS[1]}`, icono: WalletIcon },
]

const contenido = ref<HTMLElement | null>(null)
let animacion: gsap.core.Tween | null = null

onMounted(() => {
  if (prefiereMenosMovimiento() || !contenido.value) return
  animacion = gsap.from(contenido.value, { y: 24, opacity: 0, duration: DUR.pausada, ease: EASE.salida })
})

onBeforeUnmount(() => {
  animacion?.kill()
  animacion = null
})
</script>
