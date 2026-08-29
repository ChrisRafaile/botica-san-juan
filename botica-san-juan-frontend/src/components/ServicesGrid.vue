<template>
  <section class="py-24 bg-gray-50">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <MessageCircleIcon class="w-4 h-4" />
          Servicios Digitales
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
          Tecnología al servicio de tu salud
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          Utilizamos las últimas tecnologías para ofrecerte servicios rápidos, seguros y convenientes
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        <!-- WhatsApp Service -->
        <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 service-card">
          <div class="text-center mb-6">
            <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
              <MessageCircleIcon class="w-8 h-8 text-green-600" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">
              WhatsApp
            </h3>
            <p class="text-gray-600 text-sm">
              Atención inmediata y personalizada a través de WhatsApp
            </p>
          </div>
          <div class="space-y-3 mb-6">
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Consultas médicas
            </div>
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Información de productos
            </div>
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Pedidos y entregas
            </div>
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Soporte 24/7
            </div>
          </div>
          <a
            href="https://wa.me/016772892"
            target="_blank"
            class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 transition-colors inline-flex items-center justify-center font-medium"
          >
            <MessageCircleIcon class="w-4 h-4 mr-2" />
            Contactar por WhatsApp
          </a>
        </div>

        <!-- Aliviamed Service -->
        <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 service-card">
          <div class="text-center mb-6">
            <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
              <StethoscopeIcon class="w-8 h-8 text-blue-600" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">
              Aliviamed
            </h3>
            <p class="text-gray-600 text-sm">
              Consultas médicas especializadas en línea
            </p>
          </div>
          <div class="space-y-3 mb-6">
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Médicos certificados
            </div>
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Consultas virtuales
            </div>
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Recetas digitales
            </div>
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Seguimiento médico
            </div>
          </div>
          <button class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors font-medium">
            Más información
          </button>
        </div>

        <!-- SanJuanfono Service -->
        <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 service-card">
          <div class="text-center mb-6">
            <div class="bg-purple-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
              <PhoneIcon class="w-8 h-8 text-purple-600" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">
              SanJuanfono
            </h3>
            <p class="text-gray-600 text-sm">
              Línea telefónica dedicada para consultas
            </p>
          </div>
          <div class="space-y-3 mb-6">
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Atención telefónica
            </div>
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Información de productos
            </div>
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Horarios extendidos
            </div>
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Consultas especializadas
            </div>
          </div>
          <a
            href="tel:+016772892"
            class="w-full bg-purple-600 text-white py-3 px-4 rounded-lg hover:bg-purple-700 transition-colors inline-flex items-center justify-center font-medium"
          >
            <PhoneIcon class="w-4 h-4 mr-2" />
            Llamar ahora
          </a>
        </div>

        <!-- Comparte y Gana Service -->
        <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 service-card">
          <div class="text-center mb-6">
            <div class="bg-yellow-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
              <GiftIcon class="w-8 h-8 text-yellow-600" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">
              Comparte y Gana
            </h3>
            <p class="text-gray-600 text-sm">
              Programa de referidos con recompensas
            </p>
          </div>
          <div class="space-y-3 mb-6">
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Invita a tus amigos
            </div>
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Gana puntos por cada referido
            </div>
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Canjea por productos
            </div>
            <div class="flex items-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              Beneficios exclusivos
            </div>
          </div>
          <button class="w-full bg-yellow-600 text-white py-3 px-4 rounded-lg hover:bg-yellow-700 transition-colors font-medium">
            Únete al programa
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import {
  MessageCircleIcon,
  StethoscopeIcon,
  PhoneIcon,
  GiftIcon,
  CheckIcon
} from 'lucide-vue-next'
import { onMounted } from 'vue'
import { gsap } from 'gsap'

onMounted(() => {
  // Set initial state
  gsap.set('.service-card', {
    y: 50,
    opacity: 0
  })

  // Animate service cards with stagger
  gsap.to('.service-card', {
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