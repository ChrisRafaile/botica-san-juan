<template>
  <section class="py-24 bg-gray-50">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <div class="inline-flex items-center gap-2 bg-secondary-100 text-secondary-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <TruckIcon class="w-4 h-4" />
          Servicios Especiales
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
          Más que Entregas
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          Ofrecemos servicios adicionales para hacer tu experiencia de compra más conveniente y segura
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Express Delivery -->
        <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group service-card">
          <div class="bg-red-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-red-200 transition-colors">
            <ZapIcon class="w-10 h-10 text-red-600" />
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">
            Entrega Express
          </h3>
          <p class="text-gray-600 text-lg leading-relaxed mb-6">
            Entregas en menos de 2 horas para pedidos urgentes en zonas de cobertura completa. Ideal para medicamentos de uso inmediato.
          </p>
          <div class="space-y-3 mb-6">
            <div class="flex items-center justify-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              <span>Menos de 2 horas</span>
            </div>
            <div class="flex items-center justify-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              <span>Zonas prioritarias</span>
            </div>
            <div class="flex items-center justify-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              <span>Medicamentos urgentes</span>
            </div>
          </div>
          <button class="w-full bg-red-600 text-white py-3 px-4 rounded-lg hover:bg-red-700 transition-colors font-medium">
            Solicitar Express
          </button>
        </div>

        <!-- Extended Hours -->
        <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group service-card">
          <div class="bg-blue-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-blue-200 transition-colors">
            <ClockIcon class="w-10 h-10 text-blue-600" />
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">
            Horarios Extendidos
          </h3>
          <p class="text-gray-600 text-lg leading-relaxed mb-6">
            Servicio de delivery disponible de lunes a domingo, incluyendo feriados. Nunca te quedes sin tus medicamentos.
          </p>
          <div class="space-y-3 mb-6">
            <div class="flex items-center justify-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              <span>7 días a la semana</span>
            </div>
            <div class="flex items-center justify-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              <span>Incluye feriados</span>
            </div>
            <div class="flex items-center justify-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              <span>Atención 24/7</span>
            </div>
          </div>
          <button class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors font-medium">
            Ver Horarios
          </button>
        </div>

        <!-- Secure Delivery -->
        <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 text-center group service-card">
          <div class="bg-green-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-green-200 transition-colors">
            <ShieldIcon class="w-10 h-10 text-green-600" />
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">
            Envío Seguro
          </h3>
          <p class="text-gray-600 text-lg leading-relaxed mb-6">
            Todos nuestros envíos incluyen seguro completo y seguimiento en tiempo real. Tus medicamentos llegan intactos y seguros.
          </p>
          <div class="space-y-3 mb-6">
            <div class="flex items-center justify-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              <span>Seguro incluido</span>
            </div>
            <div class="flex items-center justify-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              <span>Seguimiento GPS</span>
            </div>
            <div class="flex items-center justify-center text-sm text-gray-700">
              <CheckIcon class="w-4 h-4 text-green-600 mr-2 shrink-0" />
              <span>Entrega garantizada</span>
            </div>
          </div>
          <button class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 transition-colors font-medium">
            Más Información
          </button>
        </div>
      </div>

      <!-- Additional Features -->
      <div class="mt-16 bg-white rounded-2xl shadow-lg p-8">
        <h3 class="text-3xl font-bold text-center text-gray-900 mb-12">
          Características Destacadas
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
          <div class="text-center">
            <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
              <PackageIcon class="w-8 h-8 text-blue-600" />
            </div>
            <h4 class="text-lg font-bold text-gray-900 mb-2">
              Embalaje Especial
            </h4>
            <p class="text-gray-600 text-sm">
              Productos frágiles y medicamentos sensibles son embalados con cuidado especial
            </p>
          </div>

          <div class="text-center">
            <div class="bg-secondary-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
              <PhoneIcon class="w-8 h-8 text-secondary-600" />
            </div>
            <h4 class="text-lg font-bold text-gray-900 mb-2">
              Comunicación Constante
            </h4>
            <p class="text-gray-600 text-sm">
              Recibe actualizaciones en tiempo real sobre el estado de tu pedido
            </p>
          </div>

          <div class="text-center">
            <div class="bg-accent-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
              <RefreshCwIcon class="w-8 h-8 text-accent-600" />
            </div>
            <h4 class="text-lg font-bold text-gray-900 mb-2">
              Devoluciones Fáciles
            </h4>
            <p class="text-gray-600 text-sm">
              Proceso simple y rápido para cambios o devoluciones cuando sea necesario
            </p>
          </div>

          <div class="text-center">
            <div class="bg-purple-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
              <StarIcon class="w-8 h-8 text-purple-600" />
            </div>
            <h4 class="text-lg font-bold text-gray-900 mb-2">
              Calidad Garantizada
            </h4>
            <p class="text-gray-600 text-sm">
              Todos nuestros productos cumplen con los más altos estándares de calidad
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import {
  TruckIcon,
  ZapIcon,
  ClockIcon,
  ShieldIcon,
  CheckIcon,
  PackageIcon,
  PhoneIcon,
  RefreshCwIcon,
  StarIcon
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
    stagger: 0.2,
    ease: 'power3.out'
  })
})
</script>

<style scoped>
/* Coverage services styles */
</style>