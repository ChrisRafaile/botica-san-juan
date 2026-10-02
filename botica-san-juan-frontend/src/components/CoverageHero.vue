<template>
  <section class="relative min-h-[60vh] flex items-center justify-center overflow-hidden">
    <!-- Background Image -->
    <div class="absolute inset-0">
      <img
        src="/images/frontal.jpg"
        alt="Botica San Juan - Cobertura de Delivery"
        class="w-full h-full object-cover"
      />
      <div class="absolute inset-0 bg-linear-to-r from-clinico-900/80 to-clinico-900/80" />
    </div>

    <!-- Floating Elements -->
    <div class="absolute top-20 left-10 w-20 h-20 bg-clinico-500/20 rounded-full blur-xl animate-pulse" />
    <div
      class="absolute bottom-20 right-10 w-32 h-32 bg-clinico-500/20 rounded-full blur-xl animate-pulse"
      style="animation-delay: 1s"
    />
    <div
      class="absolute top-1/2 left-1/4 w-16 h-16 bg-ambar-500/20 rounded-full blur-xl animate-pulse"
      style="animation-delay: 2s"
    />

    <!-- Content -->
    <div class="relative z-10 text-center text-white px-6 max-w-4xl mx-auto">
      <div class="inline-flex items-center gap-2 bg-superficie-elevada/10 backdrop-blur-sm text-white px-4 py-2 rounded-full text-sm font-medium mb-6">
        <MapPinIcon class="w-4 h-4" />
        Zonas de Cobertura
      </div>

      <h1 class="text-5xl md:text-7xl font-bold mb-6 leading-tight">
        Entregas a Domicilio
        <span class="block text-clinico-300">en Lima Metropolitana</span>
      </h1>

      <p class="text-xl md:text-2xl text-white/80 mb-8 max-w-3xl mx-auto leading-relaxed">
        Servicio de delivery farmacéutico en San Juan de Lurigancho y alrededores. 
        <span class="font-semibold text-white">Av. Santa Rosa 103 Urb. Los Pinos</span>
      </p>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
        <div class="bg-superficie-elevada/10 backdrop-blur-sm rounded-2xl p-6">
          <div class="text-3xl font-bold text-white mb-2">
            5
          </div>
          <div class="text-clinico-200">
            Distritos Cubiertos
          </div>
        </div>
        <div class="bg-superficie-elevada/10 backdrop-blur-sm rounded-2xl p-6">
          <div class="text-3xl font-bold text-white mb-2">
            16h
          </div>
          <div class="text-clinico-200">
            Horario de Atención
          </div>
        </div>
        <div class="bg-superficie-elevada/10 backdrop-blur-sm rounded-2xl p-6">
          <div class="text-3xl font-bold text-white mb-2">
            30min
          </div>
          <div class="text-clinico-200">
            Tiempo Promedio
          </div>
        </div>
      </div>

      <!-- CTA Buttons -->
      <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <button class="bg-clinico-600 hover:bg-clinico-700 text-white px-8 py-4 rounded-xl font-semibold text-lg transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl">
          Ver Zonas de Cobertura
        </button>
        <button class="bg-superficie-elevada/10 backdrop-blur-sm hover:bg-superficie-elevada/20 text-white px-8 py-4 rounded-xl font-semibold text-lg transition-all duration-300 border border-white/20">
          Hacer Pedido
        </button>
      </div>
    </div>

    <!-- Scroll Indicator -->
    <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
      <ChevronDownIcon class="w-6 h-6 text-white/70" />
    </div>
  </section>
</template>

<script setup lang="ts">
import { MapPinIcon, ChevronDownIcon } from 'lucide-vue-next'
import { onMounted } from 'vue'
import { gsap } from 'gsap'
import { prefiereMenosMovimiento } from '@/utils/motion'

onMounted(() => {
  /* Si la persona pidió reducir el movimiento, no se anima: el contenido ya
     está en su estado final y visible, que es justamente lo que se quiere. */
  if (prefiereMenosMovimiento()) return
  // Animate hero content
  gsap.from('.hero-content', {
    duration: 1,
    y: 50,
    opacity: 0,
    ease: 'power3.out'
  })

  // Animate stats cards with stagger
  gsap.from('.stat-card', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    stagger: 0.2,
    ease: 'power3.out',
    delay: 0.3
  })

  // Animate CTA buttons
  gsap.from('.cta-buttons', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    ease: 'power3.out',
    delay: 0.6
  })
})
</script>

<style scoped>
/* Hero section styles */
</style>