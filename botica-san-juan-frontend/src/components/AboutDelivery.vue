<template>
  <section class="py-24 bg-superficie-elevada">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <div class="inline-flex items-center gap-2 bg-clinico-100 text-clinico-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <Truck class="w-4 h-4" />
          Servicios de Entrega
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-texto-primario mb-6">
          Entrega rápida y segura
        </h2>
        <p class="text-xl text-texto-secundario max-w-3xl mx-auto">
          Llevamos tus medicamentos directamente a tu puerta con el máximo cuidado y profesionalismo
        </p>
      </div>

      <!-- Delivery Services Grid -->
      <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
        <!-- Express Delivery -->
        <div class="delivery-card bg-linear-to-br from-clinico-500/10 to-clinico-500/15 rounded-3xl p-8 border border-clinico-200">
          <div class="w-16 h-16 bg-clinico-500 rounded-2xl flex items-center justify-center mb-6">
            <Zap class="w-8 h-8 text-white" />
          </div>
          <h3 class="text-xl font-bold text-texto-primario mb-4">
            Entrega Express
          </h3>
          <p class="text-texto-secundario mb-4">
            Recibe tus medicamentos en menos de 2 horas en zona metropolitana.
            Servicio disponible de lunes a sábado.
          </p>
          <div class="text-2xl font-bold text-clinico-600">
            2 horas
          </div>
          <div class="text-sm text-texto-secundario">
            tiempo promedio
          </div>
        </div>

        <!-- Same Day Delivery -->
        <div class="delivery-card bg-linear-to-br from-botica-500/10 to-botica-500/15 rounded-3xl p-8 border border-botica-200">
          <div class="w-16 h-16 bg-botica-500 rounded-2xl flex items-center justify-center mb-6">
            <Clock class="w-8 h-8 text-white" />
          </div>
          <h3 class="text-xl font-bold text-texto-primario mb-4">
            Entrega el Mismo Día
          </h3>
          <p class="text-texto-secundario mb-4">
            Pedidos realizados antes de las 2 PM se entregan el mismo día.
            Ideal para medicamentos urgentes.
          </p>
          <div class="text-2xl font-bold text-botica-600">
            Antes 2 PM
          </div>
          <div class="text-sm text-texto-secundario">
            pedido mismo día
          </div>
        </div>

        <!-- Free Delivery -->
        <div class="delivery-card bg-linear-to-br from-clinico-500/10 to-clinico-500/15 rounded-3xl p-8 border border-clinico-200">
          <div class="w-16 h-16 bg-clinico-500 rounded-2xl flex items-center justify-center mb-6">
            <Package class="w-8 h-8 text-white" />
          </div>
          <h3 class="text-xl font-bold text-texto-primario mb-4">
            Entrega Gratuita
          </h3>
          <p class="text-texto-secundario mb-4">
            Envío gratuito en compras mayores a S/ 50.00.
            Sin costo adicional por delivery.
          </p>
          <div class="text-2xl font-bold text-clinico-600">
            S/ 50+
          </div>
          <div class="text-sm text-texto-secundario">
            compra mínima
          </div>
        </div>

        <!-- Refrigerated Delivery -->
        <div class="delivery-card bg-linear-to-br from-peligro-500/10 to-peligro-500/15 rounded-3xl p-8 border border-peligro-500/30">
          <div class="w-16 h-16 bg-peligro-500 rounded-2xl flex items-center justify-center mb-6">
            <Thermometer class="w-8 h-8 text-white" />
          </div>
          <h3 class="text-xl font-bold text-texto-primario mb-4">
            Entrega Refrigerada
          </h3>
          <p class="text-texto-secundario mb-4">
            Productos que requieren cadena de frío se transportan
            en vehículos especializados con control de temperatura.
          </p>
          <div class="text-2xl font-bold text-peligro-600">
            2-8°C
          </div>
          <div class="text-sm text-texto-secundario">
            temperatura controlada
          </div>
        </div>

        <!-- Nationwide Delivery -->
        <div class="delivery-card bg-linear-to-br from-ambar-500/10 to-ambar-500/15 rounded-3xl p-8 border border-ambar-200">
          <div class="w-16 h-16 bg-ambar-500 rounded-2xl flex items-center justify-center mb-6">
            <MapPin class="w-8 h-8 text-white" />
          </div>
          <h3 class="text-xl font-bold text-texto-primario mb-4">
            Entrega Nacional
          </h3>
          <p class="text-texto-secundario mb-4">
            Servicio de entrega disponible en todo el país.
            Coordinamos con courier para entregas provinciales.
          </p>
          <div class="text-2xl font-bold text-ambar-600">
            Todo el Perú
          </div>
          <div class="text-sm text-texto-secundario">
            cobertura nacional
          </div>
        </div>

        <!-- 24/7 Support -->
        <div class="delivery-card bg-linear-to-br from-clinico-500/10 to-clinico-500/15 rounded-3xl p-8 border border-clinico-200">
          <div class="w-16 h-16 bg-clinico-500 rounded-2xl flex items-center justify-center mb-6">
            <Phone class="w-8 h-8 text-white" />
          </div>
          <h3 class="text-xl font-bold text-texto-primario mb-4">
            Soporte 24/7
          </h3>
          <p class="text-texto-secundario mb-4">
            Atención telefónica disponible las 24 horas del día,
            7 días a la semana para seguimiento de pedidos.
          </p>
          <div class="text-2xl font-bold text-clinico-600">
            24/7
          </div>
          <div class="text-sm text-texto-secundario">
            atención continua
          </div>
        </div>
      </div>

      <!-- Delivery Process -->
      <div class="bg-superficie-hundida rounded-3xl p-8 lg:p-12">
        <div class="text-center mb-12">
          <h3 class="text-3xl font-bold text-texto-primario mb-4">
            ¿Cómo funciona nuestro servicio de delivery?
          </h3>
          <p class="text-lg text-texto-secundario">
            Proceso simple y transparente en 4 pasos
          </p>
        </div>

        <div class="grid md:grid-cols-4 gap-8">
          <!-- Step 1 -->
          <div class="process-step text-center">
            <div class="w-16 h-16 bg-clinico-500 rounded-full flex items-center justify-center mx-auto mb-4 text-white font-bold text-xl">
              1
            </div>
            <h4 class="text-lg font-semibold text-texto-primario mb-2">
              Realiza tu Pedido
            </h4>
            <p class="text-texto-secundario text-sm">
              Selecciona tus productos desde nuestra web o app y confirma tu pedido
            </p>
          </div>

          <!-- Step 2 -->
          <div class="process-step text-center">
            <div class="w-16 h-16 bg-clinico-500 rounded-full flex items-center justify-center mx-auto mb-4 text-white font-bold text-xl">
              2
            </div>
            <h4 class="text-lg font-semibold text-texto-primario mb-2">
              Verificación
            </h4>
            <p class="text-texto-secundario text-sm">
              Nuestro farmacéutico verifica y prepara tu pedido con cuidado profesional
            </p>
          </div>

          <!-- Step 3 -->
          <div class="process-step text-center">
            <div class="w-16 h-16 bg-clinico-500 rounded-full flex items-center justify-center mx-auto mb-4 text-white font-bold text-xl">
              3
            </div>
            <h4 class="text-lg font-semibold text-texto-primario mb-2">
              Empaque Seguro
            </h4>
            <p class="text-texto-secundario text-sm">
              Empacamos tus medicamentos con materiales especiales para máxima protección
            </p>
          </div>

          <!-- Step 4 -->
          <div class="process-step text-center">
            <div class="w-16 h-16 bg-clinico-500 rounded-full flex items-center justify-center mx-auto mb-4 text-white font-bold text-xl">
              4
            </div>
            <h4 class="text-lg font-semibold text-texto-primario mb-2">
              Entrega a Domicilio
            </h4>
            <p class="text-texto-secundario text-sm">
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
import { prefiereMenosMovimiento } from '@/utils/motion'

onMounted(() => {
  /* Si la persona pidió reducir el movimiento, no se anima: el contenido ya
     está en su estado final y visible, que es justamente lo que se quiere. */
  if (prefiereMenosMovimiento()) return
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