<template>
  <section class="py-24 bg-superficie-hundida">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <div class="inline-flex items-center gap-2 bg-clinico-100 text-clinico-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <PackageIcon class="w-4 h-4" />
          Nuestros Productos
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-texto-primario mb-6">
          Categorías de Productos
        </h2>
        <p class="text-xl text-texto-secundario max-w-3xl mx-auto">
          Encuentra todo lo que necesitas para tu salud y bienestar en nuestras categorías especializadas
        </p>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
        <div class="bg-superficie-elevada p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-clinico-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-clinico-200 transition-colors">
            <PillIcon class="w-10 h-10 text-clinico-600" />
          </div>
          <h3 class="text-xl font-bold text-texto-primario mb-3">
            Medicamentos
          </h3>
          <p class="text-texto-secundario text-sm leading-relaxed">
            Amplia variedad de medicamentos con y sin receta médica
          </p>
          <div class="mt-4 text-texto-acento font-semibold text-sm">
            Ver productos →
          </div>
        </div>

        <div class="bg-superficie-elevada p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-botica-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-botica-200 transition-colors">
            <HeartIcon class="w-10 h-10 text-botica-600" />
          </div>
          <h3 class="text-xl font-bold text-texto-primario mb-3">
            Cuidado Personal
          </h3>
          <p class="text-texto-secundario text-sm leading-relaxed">
            Productos para el cuidado de la piel, cabello y higiene personal
          </p>
          <div class="mt-4 text-texto-acento font-semibold text-sm">
            Ver productos →
          </div>
        </div>

        <div class="bg-superficie-elevada p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-clinico-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-clinico-200 transition-colors">
            <BabyIcon class="w-10 h-10 text-clinico-600" />
          </div>
          <h3 class="text-xl font-bold text-texto-primario mb-3">
            Bebés y Niños
          </h3>
          <p class="text-texto-secundario text-sm leading-relaxed">
            Todo lo necesario para el cuidado de los más pequeños de la casa
          </p>
          <div class="mt-4 text-texto-acento font-semibold text-sm">
            Ver productos →
          </div>
        </div>

        <div class="bg-superficie-elevada p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-ambar-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-ambar-200 transition-colors">
            <UtensilsIcon class="w-10 h-10 text-ambar-600" />
          </div>
          <h3 class="text-xl font-bold text-texto-primario mb-3">
            Suplementos
          </h3>
          <p class="text-texto-secundario text-sm leading-relaxed">
            Vitaminas, minerales y suplementos nutricionales de calidad
          </p>
          <div class="mt-4 text-texto-acento font-semibold text-sm">
            Ver productos →
          </div>
        </div>
      </div>

      <!-- Additional Categories Row -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mt-12">
        <div class="bg-superficie-elevada p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-peligro-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:bg-peligro-500/20 transition-colors">
            <ThermometerIcon class="w-8 h-8 text-peligro-600" />
          </div>
          <h3 class="text-lg font-bold text-texto-primario mb-2">
            Primeros Auxilios
          </h3>
          <p class="text-texto-secundario text-xs leading-relaxed">
            Kits de emergencia y productos para curaciones
          </p>
        </div>

        <div class="bg-superficie-elevada p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-clinico-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:bg-clinico-200 transition-colors">
            <ShieldIcon class="w-8 h-8 text-clinico-600" />
          </div>
          <h3 class="text-lg font-bold text-texto-primario mb-2">
            Protección Solar
          </h3>
          <p class="text-texto-secundario text-xs leading-relaxed">
            Cremas y productos para protección contra el sol
          </p>
        </div>

        <div class="bg-superficie-elevada p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-ambar-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:bg-ambar-200 transition-colors">
            <SparklesIcon class="w-8 h-8 text-ambar-600" />
          </div>
          <h3 class="text-lg font-bold text-texto-primario mb-2">
            Belleza
          </h3>
          <p class="text-texto-secundario text-xs leading-relaxed">
            Cosméticos y productos de belleza natural
          </p>
        </div>

        <div class="bg-superficie-elevada p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-botica-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:bg-botica-200 transition-colors">
            <LeafIcon class="w-8 h-8 text-botica-600" />
          </div>
          <h3 class="text-lg font-bold text-texto-primario mb-2">
            Naturales
          </h3>
          <p class="text-texto-secundario text-xs leading-relaxed">
            Productos naturales y hierbas medicinales
          </p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import {
  PackageIcon,
  PillIcon,
  HeartIcon,
  BabyIcon,
  UtensilsIcon,
  ThermometerIcon,
  ShieldIcon,
  SparklesIcon,
  LeafIcon
} from 'lucide-vue-next'
import { onMounted } from 'vue'
import { gsap } from 'gsap'
import { prefiereMenosMovimiento } from '@/utils/motion'

onMounted(() => {
  /* Si la persona pidió reducir el movimiento, no se anima: el contenido ya
     está en su estado final y visible, que es justamente lo que se quiere. */
  if (prefiereMenosMovimiento()) return
  // Set initial state
  gsap.set('.category-card', {
    y: 50,
    opacity: 0
  })

  // Animate category cards with stagger
  gsap.to('.category-card', {
    duration: 0.8,
    y: 0,
    opacity: 1,
    stagger: 0.1,
    ease: 'power3.out'
  })
})
</script>

<style scoped>
/* Component styles */
</style>