<template>
  <section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Section Header -->
      <div class="text-center mb-16 stats-header">
        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
          Confianza que se Construye con Resultados
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          Más de 15 años sirviendo a la comunidad con excelencia,
          compromiso y dedicación a tu salud.
        </p>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-16">
        <!-- Stat 1: Years of Experience -->
        <div class="stat-card text-center">
          <div class="w-20 h-20 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <CalendarIcon class="w-10 h-10 text-primary-600" />
          </div>
          <div class="stat-number text-4xl md:text-5xl font-bold text-primary-600 mb-2">
            15+
          </div>
          <div class="text-lg font-semibold text-gray-900 mb-2">
            Años de Experiencia
          </div>
          <p class="text-gray-600 text-sm">
            Sirviendo a la comunidad con dedicación y profesionalismo
          </p>
        </div>

        <!-- Stat 2: Happy Customers -->
        <div class="stat-card text-center">
          <div class="w-20 h-20 bg-secondary-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <UsersIcon class="w-10 h-10 text-secondary-600" />
          </div>
          <div class="stat-number text-4xl md:text-5xl font-bold text-secondary-600 mb-2">
            50K+
          </div>
          <div class="text-lg font-semibold text-gray-900 mb-2">
            Clientes Satisfechos
          </div>
          <p class="text-gray-600 text-sm">
            Familias que confían en nosotros para su cuidado de salud
          </p>
        </div>

        <!-- Stat 3: Products Available -->
        <div class="stat-card text-center">
          <div class="w-20 h-20 bg-accent-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <PackageIcon class="w-10 h-10 text-accent-600" />
          </div>
          <div class="stat-number text-4xl md:text-5xl font-bold text-accent-600 mb-2">
            10K+
          </div>
          <div class="text-lg font-semibold text-gray-900 mb-2">
            Productos Disponibles
          </div>
          <p class="text-gray-600 text-sm">
            Amplio catálogo de medicamentos y productos de salud
          </p>
        </div>

        <!-- Stat 4: Delivery Coverage -->
        <div class="stat-card text-center">
          <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <MapPinIcon class="w-10 h-10 text-purple-600" />
          </div>
          <div class="stat-number text-4xl md:text-5xl font-bold text-purple-600 mb-2">
            100%
          </div>
          <div class="text-lg font-semibold text-gray-900 mb-2">
            Cobertura de Delivery
          </div>
          <p class="text-gray-600 text-sm">
            Entregas en toda Lima Metropolitana sin costo adicional
          </p>
        </div>
      </div>

      <!-- Trust Indicators -->
      <div class="bg-linear-to-r from-primary-50 to-secondary-50 rounded-3xl p-8 md:p-12 stats-trust">
        <div class="text-center mb-12">
          <h3 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
            ¿Por qué elegir Boticas San Juan?
          </h3>
          <p class="text-lg text-gray-600 max-w-2xl mx-auto">
            Somos más que una farmacia, somos tu aliado en salud con certificaciones
            y reconocimientos que avalan nuestra calidad y compromiso.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <!-- Trust 1: Certifications -->
          <div class="trust-item text-center">
            <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg">
              <AwardIcon class="w-8 h-8 text-primary-600" />
            </div>
            <h4 class="text-xl font-bold text-gray-900 mb-3">
              Certificaciones DIGEMID
            </h4>
            <p class="text-gray-600">
              Autorización sanitaria completa y cumplimiento de todas las normativas
              vigentes para garantizar la calidad y seguridad de nuestros productos.
            </p>
          </div>

          <!-- Trust 2: 24/7 Service -->
          <div class="trust-item text-center">
            <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg">
              <ClockIcon class="w-8 h-8 text-secondary-600" />
            </div>
            <h4 class="text-xl font-bold text-gray-900 mb-3">
              Servicio 24/7
            </h4>
            <p class="text-gray-600">
              Atención continua con nuestro servicio de telemedicina Aliviamed
              y delivery express disponible las 24 horas del día.
            </p>
          </div>

          <!-- Trust 3: Quality Guarantee -->
          <div class="trust-item text-center">
            <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg">
              <ShieldCheckIcon class="w-8 h-8 text-accent-600" />
            </div>
            <h4 class="text-xl font-bold text-gray-900 mb-3">
              Garantía de Calidad
            </h4>
            <p class="text-gray-600">
              Todos nuestros productos cuentan con cadena de frío garantizada
              y verificación de autenticidad para tu tranquilidad.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted, nextTick } from 'vue'
import { gsap } from 'gsap'
import {
  CalendarIcon,
  UsersIcon,
  PackageIcon,
  MapPinIcon,
  AwardIcon,
  ClockIcon,
  ShieldCheckIcon
} from 'lucide-vue-next'

onMounted(async () => {
  await nextTick()

  // Increased delay to ensure DOM and assets are fully rendered
  setTimeout(() => {
    const tl = gsap.timeline()

    // Set initial states explicitly
    gsap.set('.stats-header h2', { y: 50, opacity: 0 })
    gsap.set('.stats-header p', { y: 30, opacity: 0 })
    gsap.set('.stat-card', { y: 60, opacity: 0, scale: 0.8 })
    gsap.set('.stat-number', { scale: 0 })
    gsap.set('.stats-trust', { y: 40, opacity: 0 })
    gsap.set('.stats-trust h3', { y: 30, opacity: 0 })
    gsap.set('.stats-trust p', { y: 20, opacity: 0 })
    gsap.set('.trust-item', { y: 50, opacity: 0 })

    // Animate section header
    tl.to('.stats-header h2', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      ease: 'power3.out'
    })
    .to('.stats-header p', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      ease: 'power2.out'
    }, '-=0.4')

    // Animate stat cards with stagger
    .to('.stat-card', {
      y: 0,
      opacity: 1,
      scale: 1,
      duration: 0.8,
      stagger: 0.15,
      ease: 'back.out(1.7)'
    }, '-=0.5')

    // Animate stat numbers with bounce effect
    .to('.stat-number', {
      scale: 1,
      duration: 0.6,
      stagger: 0.1,
      ease: 'back.out(1.7)'
    }, '-=0.3')

    // Animate trust section
    .to('.stats-trust', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      ease: 'power2.out'
    }, '-=0.3')
    .to('.stats-trust h3', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      ease: 'power2.out'
    }, '-=0.5')
    .to('.stats-trust p', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      ease: 'power2.out'
    }, '-=0.3')

    // Animate trust items with stagger
    .to('.trust-item', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      stagger: 0.2,
      ease: 'power2.out'
    }, '-=0.4')

    // Add hover animations for stat cards
    document.querySelectorAll('.stat-card').forEach((card) => {
      card.addEventListener('mouseenter', () => {
        gsap.to(card, {
          scale: 1.05,
          y: -10,
          duration: 0.3,
          ease: 'power2.out'
        })
      })

      card.addEventListener('mouseleave', () => {
        gsap.to(card, {
          scale: 1,
          y: 0,
          duration: 0.3,
          ease: 'power2.out'
        })
      })
    })

    // Add hover animations for trust items
    document.querySelectorAll('.trust-item').forEach((item) => {
      item.addEventListener('mouseenter', () => {
        gsap.to(item.querySelector('.w-16'), {
          scale: 1.1,
          rotation: 5,
          duration: 0.3,
          ease: 'power2.out'
        })
      })

      item.addEventListener('mouseleave', () => {
        gsap.to(item.querySelector('.w-16'), {
          scale: 1,
          rotation: 0,
          duration: 0.3,
          ease: 'power2.out'
        })
      })
    })
  }, 500)
})
</script>