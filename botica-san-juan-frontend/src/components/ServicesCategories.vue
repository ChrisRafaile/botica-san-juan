<template>
  <section class="py-24 bg-gray-50">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <PackageIcon class="w-4 h-4" />
          Nuestros Productos
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
          Categorías de Productos
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          Encuentra todo lo que necesitas para tu salud y bienestar en nuestras categorías especializadas
        </p>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
        <div class="bg-white p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-blue-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-blue-200 transition-colors">
            <PillIcon class="w-10 h-10 text-blue-600" />
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-3">
            Medicamentos
          </h3>
          <p class="text-gray-600 text-sm leading-relaxed">
            Amplia variedad de medicamentos con y sin receta médica
          </p>
          <div class="mt-4 text-blue-600 font-semibold text-sm">
            Ver productos →
          </div>
        </div>

        <div class="bg-white p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-green-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-green-200 transition-colors">
            <HeartIcon class="w-10 h-10 text-green-600" />
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-3">
            Cuidado Personal
          </h3>
          <p class="text-gray-600 text-sm leading-relaxed">
            Productos para el cuidado de la piel, cabello y higiene personal
          </p>
          <div class="mt-4 text-blue-600 font-semibold text-sm">
            Ver productos →
          </div>
        </div>

        <div class="bg-white p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-purple-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-purple-200 transition-colors">
            <BabyIcon class="w-10 h-10 text-purple-600" />
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-3">
            Bebés y Niños
          </h3>
          <p class="text-gray-600 text-sm leading-relaxed">
            Todo lo necesario para el cuidado de los más pequeños de la casa
          </p>
          <div class="mt-4 text-blue-600 font-semibold text-sm">
            Ver productos →
          </div>
        </div>

        <div class="bg-white p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-yellow-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-yellow-200 transition-colors">
            <UtensilsIcon class="w-10 h-10 text-yellow-600" />
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-3">
            Suplementos
          </h3>
          <p class="text-gray-600 text-sm leading-relaxed">
            Vitaminas, minerales y suplementos nutricionales de calidad
          </p>
          <div class="mt-4 text-blue-600 font-semibold text-sm">
            Ver productos →
          </div>
        </div>
      </div>

      <!-- Additional Categories Row -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mt-12">
        <div class="bg-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-red-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:bg-red-200 transition-colors">
            <ThermometerIcon class="w-8 h-8 text-red-600" />
          </div>
          <h3 class="text-lg font-bold text-gray-900 mb-2">
            Primeros Auxilios
          </h3>
          <p class="text-gray-600 text-xs leading-relaxed">
            Kits de emergencia y productos para curaciones
          </p>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-indigo-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:bg-indigo-200 transition-colors">
            <ShieldIcon class="w-8 h-8 text-indigo-600" />
          </div>
          <h3 class="text-lg font-bold text-gray-900 mb-2">
            Protección Solar
          </h3>
          <p class="text-gray-600 text-xs leading-relaxed">
            Cremas y productos para protección contra el sol
          </p>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-pink-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:bg-pink-200 transition-colors">
            <SparklesIcon class="w-8 h-8 text-pink-600" />
          </div>
          <h3 class="text-lg font-bold text-gray-900 mb-2">
            Belleza
          </h3>
          <p class="text-gray-600 text-xs leading-relaxed">
            Cosméticos y productos de belleza natural
          </p>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group category-card">
          <div class="bg-teal-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:bg-teal-200 transition-colors">
            <LeafIcon class="w-8 h-8 text-teal-600" />
          </div>
          <h3 class="text-lg font-bold text-gray-900 mb-2">
            Naturales
          </h3>
          <p class="text-gray-600 text-xs leading-relaxed">
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

onMounted(() => {
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