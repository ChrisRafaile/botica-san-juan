<template>
  <section class="relative bg-linear-to-br from-blue-600 via-blue-700 to-blue-800 text-white overflow-hidden">
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-10">
      <div class="absolute inset-0 bg-linear-to-br from-white/20 to-transparent"></div>
    </div>

    <!-- Floating Elements -->
    <div class="absolute top-20 left-10 w-20 h-20 bg-white/10 rounded-full blur-xl animate-pulse"></div>
    <div class="absolute bottom-20 right-10 w-32 h-32 bg-white/5 rounded-full blur-2xl animate-pulse delay-1000"></div>
    <div class="absolute top-1/2 left-1/4 w-16 h-16 bg-white/10 rounded-full blur-lg animate-pulse delay-500"></div>

    <div class="relative container mx-auto px-6 py-24 lg:py-32">
      <div class="max-w-4xl mx-auto text-center hero-content">
        <!-- Main Heading -->
        <div class="mb-8">
          <h1 class="text-5xl md:text-6xl lg:text-7xl font-bold mb-6 leading-tight">
            Sobre <span class="text-blue-200">Nosotros</span>
          </h1>
          <div class="w-24 h-1 bg-blue-300 mx-auto mb-8 rounded-full"></div>
        </div>

        <!-- Subtitle -->
        <p class="text-xl md:text-2xl text-blue-100 mb-12 leading-relaxed max-w-3xl mx-auto">
          Conoce nuestra historia, valores y compromiso con tu salud y bienestar
        </p>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/20 stats-card">
            <div class="text-3xl font-bold mb-2">
              15+
            </div>
            <div class="text-blue-200">
              Años de Experiencia
            </div>
          </div>
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/20 stats-card">
            <div class="text-3xl font-bold mb-2">
              50K+
            </div>
            <div class="text-blue-200">
              Clientes Satisfechos
            </div>
          </div>
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/20 stats-card">
            <div class="text-3xl font-bold mb-2">
              24/7
            </div>
            <div class="text-blue-200">
              Servicio Disponible
            </div>
          </div>
        </div>

        <!-- CTA Button -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center cta-buttons">
          <button class="bg-white text-blue-700 px-8 py-4 rounded-full font-semibold text-lg hover:bg-blue-50 transition-all duration-300 transform hover:scale-105 shadow-lg">
            Conoce Nuestra Historia
          </button>
          <button class="border-2 border-white/30 text-white px-8 py-4 rounded-full font-semibold text-lg hover:bg-white/10 transition-all duration-300 backdrop-blur-sm">
            Nuestros Valores
          </button>
        </div>
      </div>
    </div>

    <!-- Bottom Wave -->
    <div class="absolute bottom-0 left-0 right-0">
      <svg
        viewBox="0 0 1440 120"
        class="w-full h-auto"
      >
        <path
          fill="#ffffff"
          d="M0,32L48,37.3C96,43,192,53,288,58.7C384,64,480,64,576,58.7C672,53,768,43,864,48C960,53,1056,75,1152,80C1248,85,1344,75,1392,69.3L1440,64L1440,120L1392,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z"
        />
      </svg>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { gsap } from 'gsap'

onMounted(() => {
  // Animate hero content
  gsap.from('.hero-content', {
    duration: 1,
    y: 50,
    opacity: 0,
    ease: 'power3.out'
  })

  // Animate stats cards with stagger
  gsap.from('.stats-card', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    stagger: 0.2,
    ease: 'power3.out',
    delay: 0.5
  })

  // Animate CTA buttons
  gsap.from('.cta-buttons', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    ease: 'power3.out',
    delay: 0.8
  })
})
</script>

<style scoped>
/* Component styles */
</style>