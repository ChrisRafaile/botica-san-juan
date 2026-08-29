<template>
  <section class="py-24 bg-white">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <Truck class="w-4 h-4" />
          Servicios de Entrega
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
          Entrega rápida y segura
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          Llevamos tus medicamentos directamente a tu puerta con el máximo cuidado y profesionalismo
        </p>
      </div>

      <!-- Delivery Services Grid -->
      <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
        <!-- Express Delivery -->
        <div class="delivery-card bg-linear-to-br from-blue-50 to-blue-100 rounded-3xl p-8 border border-blue-200">
          <div class="w-16 h-16 bg-blue-500 rounded-2xl flex items-center justify-center mb-6">
            <Zap class="w-8 h-8 text-white" />
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-4">
            Entrega Express
          </h3>
          <p class="text-gray-700 mb-4">
            Recibe tus medicamentos en menos de 2 horas en zona metropolitana.
            Servicio disponible de lunes a sábado.
          </p>
          <div class="text-2xl font-bold text-blue-600">
            2 horas
          </div>
          <div class="text-sm text-gray-600">
            tiempo promedio
          </div>
        </div>

        <!-- Same Day Delivery -->
        <div class="delivery-card bg-linear-to-br from-green-50 to-green-100 rounded-3xl p-8 border border-green-200">
          <div class="w-16 h-16 bg-green-500 rounded-2xl flex items-center justify-center mb-6">
            <Clock class="w-8 h-8 text-white" />
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-4">
            Entrega el Mismo Día
          </h3>
          <p class="text-gray-700 mb-4">
            Pedidos realizados antes de las 2 PM se entregan el mismo día.
            Ideal para medicamentos urgentes.
          </p>
          <div class="text-2xl font-bold text-green-600">
            Antes 2 PM
          </div>
          <div class="text-sm text-gray-600">
            pedido mismo día
          </div>
        </div>

        <!-- Free Delivery -->
        <div class="delivery-card bg-linear-to-br from-purple-50 to-purple-100 rounded-3xl p-8 border border-purple-200">
          <div class="w-16 h-16 bg-purple-500 rounded-2xl flex items-center justify-center mb-6">
            <Package class="w-8 h-8 text-white" />
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-4">
            Entrega Gratuita
          </h3>
          <p class="text-gray-700 mb-4">
            Envío gratuito en compras mayores a S/ 50.00.
            Sin costo adicional por delivery.
          </p>
          <div class="text-2xl font-bold text-purple-600">
            S/ 50+
          </div>
          <div class="text-sm text-gray-600">
            compra mínima
          </div>
        </div>

        <!-- Refrigerated Delivery -->
        <div class="delivery-card bg-linear-to-br from-red-50 to-red-100 rounded-3xl p-8 border border-red-200">
          <div class="w-16 h-16 bg-red-500 rounded-2xl flex items-center justify-center mb-6">
            <Thermometer class="w-8 h-8 text-white" />
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-4">
            Entrega Refrigerada
          </h3>
          <p class="text-gray-700 mb-4">
            Productos que requieren cadena de frío se transportan
            en vehículos especializados con control de temperatura.
          </p>
          <div class="text-2xl font-bold text-red-600">
            2-8°C
          </div>
          <div class="text-sm text-gray-600">
            temperatura controlada
          </div>
        </div>

        <!-- Nationwide Delivery -->
        <div class="delivery-card bg-linear-to-br from-orange-50 to-orange-100 rounded-3xl p-8 border border-orange-200">
          <div class="w-16 h-16 bg-orange-500 rounded-2xl flex items-center justify-center mb-6">
            <MapPin class="w-8 h-8 text-white" />
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-4">
            Entrega Nacional
          </h3>
          <p class="text-gray-700 mb-4">
            Servicio de entrega disponible en todo el país.
            Coordinamos con courier para entregas provinciales.
          </p>
          <div class="text-2xl font-bold text-orange-600">
            Todo el Perú
          </div>
          <div class="text-sm text-gray-600">
            cobertura nacional
          </div>
        </div>

        <!-- 24/7 Support -->
        <div class="delivery-card bg-linear-to-br from-indigo-50 to-indigo-100 rounded-3xl p-8 border border-indigo-200">
          <div class="w-16 h-16 bg-indigo-500 rounded-2xl flex items-center justify-center mb-6">
            <Phone class="w-8 h-8 text-white" />
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-4">
            Soporte 24/7
          </h3>
          <p class="text-gray-700 mb-4">
            Atención telefónica disponible las 24 horas del día,
            7 días a la semana para seguimiento de pedidos.
          </p>
          <div class="text-2xl font-bold text-indigo-600">
            24/7
          </div>
          <div class="text-sm text-gray-600">
            atención continua
          </div>
        </div>
      </div>

      <!-- Delivery Process -->
      <div class="bg-gray-50 rounded-3xl p-8 lg:p-12">
        <div class="text-center mb-12">
          <h3 class="text-3xl font-bold text-gray-900 mb-4">
            ¿Cómo funciona nuestro servicio de delivery?
          </h3>
          <p class="text-lg text-gray-600">
            Proceso simple y transparente en 4 pasos
          </p>
        </div>

        <div class="grid md:grid-cols-4 gap-8">
          <!-- Step 1 -->
          <div class="process-step text-center">
            <div class="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-4 text-white font-bold text-xl">
              1
            </div>
            <h4 class="text-lg font-semibold text-gray-900 mb-2">
              Realiza tu Pedido
            </h4>
            <p class="text-gray-600 text-sm">
              Selecciona tus productos desde nuestra web o app y confirma tu pedido
            </p>
          </div>

          <!-- Step 2 -->
          <div class="process-step text-center">
            <div class="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-4 text-white font-bold text-xl">
              2
            </div>
            <h4 class="text-lg font-semibold text-gray-900 mb-2">
              Verificación
            </h4>
            <p class="text-gray-600 text-sm">
              Nuestro farmacéutico verifica y prepara tu pedido con cuidado profesional
            </p>
          </div>

          <!-- Step 3 -->
          <div class="process-step text-center">
            <div class="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-4 text-white font-bold text-xl">
              3
            </div>
            <h4 class="text-lg font-semibold text-gray-900 mb-2">
              Empaque Seguro
            </h4>
            <p class="text-gray-600 text-sm">
              Empacamos tus medicamentos con materiales especiales para máxima protección
            </p>
          </div>

          <!-- Step 4 -->
          <div class="process-step text-center">
            <div class="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-4 text-white font-bold text-xl">
              4
            </div>
            <h4 class="text-lg font-semibold text-gray-900 mb-2">
              Entrega a Domicilio
            </h4>
            <p class="text-gray-600 text-sm">
              Nuestro personal calificado entrega en tu puerta con protocolo de seguridad
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { gsap } from 'gsap'
import { Truck, Zap, Clock, Package, Thermometer, MapPin, Phone } from 'lucide-vue-next'

onMounted(() => {
  // Animate delivery cards with stagger
  gsap.from('.delivery-card', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    stagger: 0.1,
    ease: 'power3.out'
  })

  // Animate process steps
  gsap.from('.process-step', {
    duration: 0.8,
    y: 20,
    opacity: 0,
    stagger: 0.15,
    ease: 'power3.out',
    delay: 0.5
  })
})
</script>

<style scoped>
/* Component styles */
</style>