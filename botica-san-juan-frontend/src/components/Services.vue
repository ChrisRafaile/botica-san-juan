<template>
  <section class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Section Header -->
      <div class="text-center mb-16 services-header">
        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
          Nuestros Servicios Médicos
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          Ofrecemos una amplia gama de servicios farmacéuticos y de salud
          para cuidar de tu bienestar y el de tu familia.
        </p>
      </div>

      <!-- Services Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
        <!-- Service 1: Telemedicine -->
        <div class="service-card bg-white rounded-2xl p-8 shadow-lg border border-gray-100 transition-all duration-300 hover:shadow-xl group">
          <div class="w-16 h-16 bg-primary-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-primary-200 transition-colors duration-300">
            <VideoIcon class="w-8 h-8 text-primary-600" />
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">
            Telemedicina Aliviamed
          </h3>
          <p class="text-gray-600 mb-6 leading-relaxed">
            Consultas médicas virtuales con especialistas certificados.
            Atención médica desde la comodidad de tu hogar, disponible 24/7.
          </p>
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Consultas ilimitadas
            </li>
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Recetas digitales
            </li>
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Seguimiento médico
            </li>
          </ul>
        </div>

        <!-- Service 2: Delivery -->
        <div class="service-card bg-white rounded-2xl p-8 shadow-lg border border-gray-100 transition-all duration-300 hover:shadow-xl group">
          <div class="w-16 h-16 bg-secondary-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-secondary-200 transition-colors duration-300">
            <TruckIcon class="w-8 h-8 text-secondary-600" />
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">
            Delivery Express
          </h3>
          <p class="text-gray-600 mb-6 leading-relaxed">
            Servicio de entrega a domicilio rápido y seguro en toda Lima Metropolitana.
            Recibe tus medicamentos sin salir de casa.
          </p>
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Entrega en 2 horas
            </li>
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Envío refrigerado
            </li>
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Seguimiento en tiempo real
            </li>
          </ul>
        </div>

        <!-- Service 3: Pharmacy Consultation -->
        <div class="service-card bg-white rounded-2xl p-8 shadow-lg border border-gray-100 transition-all duration-300 hover:shadow-xl group">
          <div class="w-16 h-16 bg-accent-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-accent-200 transition-colors duration-300">
            <UsersIcon class="w-8 h-8 text-accent-600" />
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">
            Asesoría Farmacéutica
          </h3>
          <p class="text-gray-600 mb-6 leading-relaxed">
            Nuestros farmacéuticos certificados te brindan orientación profesional
            sobre medicamentos, interacciones y cuidados de salud.
          </p>
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Revisión de recetas
            </li>
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Información sobre medicamentos
            </li>
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Consejos de salud preventiva
            </li>
          </ul>
        </div>

        <!-- Service 4: Health Tests -->
        <div class="service-card bg-white rounded-2xl p-8 shadow-lg border border-gray-100 transition-all duration-300 hover:shadow-xl group">
          <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-purple-200 transition-colors duration-300">
            <ActivityIcon class="w-8 h-8 text-purple-600" />
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">
            Pruebas de Salud
          </h3>
          <p class="text-gray-600 mb-6 leading-relaxed">
            Realiza exámenes de laboratorio y pruebas de salud en nuestra farmacia.
            Resultados rápidos y confiables para tu tranquilidad.
          </p>
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Pruebas de glucosa
            </li>
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Presión arterial
            </li>
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Análisis de colesterol
            </li>
          </ul>
        </div>

        <!-- Service 5: Vaccination -->
        <div class="service-card bg-white rounded-2xl p-8 shadow-lg border border-gray-100 transition-all duration-300 hover:shadow-xl group">
          <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-blue-200 transition-colors duration-300">
            <ShieldIcon class="w-8 h-8 text-blue-600" />
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">
            Vacunación
          </h3>
          <p class="text-gray-600 mb-6 leading-relaxed">
            Servicio de vacunación completo con las mejores vacunas disponibles.
            Protege tu salud y la de tu familia con nuestro equipo especializado.
          </p>
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Vacunas estacionales
            </li>
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Vacunas de rutina
            </li>
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Certificación oficial
            </li>
          </ul>
        </div>

        <!-- Service 6: Loyalty Program -->
        <div class="service-card bg-white rounded-2xl p-8 shadow-lg border border-gray-100 transition-all duration-300 hover:shadow-xl group">
          <div class="w-16 h-16 bg-yellow-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-yellow-200 transition-colors duration-300">
            <HeartIcon class="w-8 h-8 text-yellow-600" />
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">
            Programa Agora
          </h3>
          <p class="text-gray-600 mb-6 leading-relaxed">
            Únete a nuestro programa de lealtad y acumula puntos con cada compra.
            Disfruta de descuentos exclusivos y beneficios especiales.
          </p>
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Puntos por compra
            </li>
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Descuentos exclusivos
            </li>
            <li class="flex items-center">
              <CheckIcon class="w-4 h-4 text-secondary-500 mr-2 shrink-0" />
              Beneficios premium
            </li>
          </ul>
        </div>
      </div>

      <!-- CTA Section -->
      <div class="text-center">
        <div class="bg-white rounded-3xl p-8 md:p-12 shadow-xl border border-gray-100 services-cta">
          <h3 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
            ¿Necesitas algún servicio específico?
          </h3>
          <p class="text-lg text-gray-600 mb-8 max-w-2xl mx-auto">
            Nuestro equipo está listo para atenderte. Contáctanos y descubre
            cómo podemos ayudarte con tus necesidades de salud.
          </p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <router-link
              to="/services"
              class="btn-primary px-8 py-4 text-lg"
            >
              Ver Todos los Servicios
            </router-link>
            <router-link
              to="/contact"
              class="btn-secondary px-8 py-4 text-lg"
            >
              Contactar Ahora
            </router-link>
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
  VideoIcon,
  TruckIcon,
  UsersIcon,
  ActivityIcon,
  ShieldIcon,
  HeartIcon,
  CheckIcon
} from 'lucide-vue-next'

onMounted(async () => {
  await nextTick()

  // Increased delay to ensure DOM and assets are fully rendered
  setTimeout(() => {
    const tl = gsap.timeline()

    // Set initial states explicitly
    gsap.set('.services-header h2', { y: 50, opacity: 0 })
    gsap.set('.services-header p', { y: 30, opacity: 0 })
    gsap.set('.service-card', { y: 60, opacity: 0 })
    gsap.set('.services-cta', { y: 40, opacity: 0 })
    gsap.set('.services-cta h3', { y: 30, opacity: 0 })
    gsap.set('.services-cta p', { y: 20, opacity: 0 })

    // Animate section header
    tl.to('.services-header h2', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      ease: 'power3.out'
    })
    .to('.services-header p', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      ease: 'power2.out'
    }, '-=0.4')

    // Animate service cards with stagger
    .to('.service-card', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      stagger: 0.15,
      ease: 'back.out(1.7)'
    }, '-=0.5')

    // Animate CTA section
    .to('.services-cta', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      ease: 'power2.out'
    }, '-=0.3')
    .to('.services-cta h3', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      ease: 'power2.out'
    }, '-=0.5')
    .to('.services-cta p', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      ease: 'power2.out'
    }, '-=0.3')

    // Add hover animations for service cards
    gsap.set('.service-card', { transformOrigin: 'center bottom' })

    document.querySelectorAll('.service-card').forEach((card) => {
      card.addEventListener('mouseenter', () => {
        gsap.to(card, {
          scale: 1.02,
          y: -5,
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
  }, 500)
})
</script>

