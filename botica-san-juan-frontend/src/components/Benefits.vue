<template>
  <section class="py-16 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Section Header -->
      <div class="text-center mb-10 benefits-header">
        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
          ¿Por qué elegir Botica San Juan?
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          Nos comprometemos con tu salud ofreciendo servicios de calidad,
          productos originales y atención personalizada.
        </p>
      </div>

      <!-- Benefits Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- Benefit 1: WhatsApp Service -->
        <div class="card group hover:scale-105 transition-transform duration-300 benefit-card">
          <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-green-200 transition-colors duration-300 overflow-hidden">
            <img
              src="@/assets/images/whatsapp-icon-lineal.svg"
              alt="WhatsApp"
              class="w-8 h-8"
            />
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-3">
            Pedidos por WhatsApp
          </h3>
          <p class="text-gray-600">
            Realiza tus pedidos de medicamentos de forma rápida y segura
            a través de WhatsApp. Atención inmediata y personalizada.
          </p>
        </div>

        <!-- Benefit 2: Aliviamed -->
        <div class="card group hover:scale-105 transition-transform duration-300 benefit-card">
          <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-blue-200 transition-colors duration-300 overflow-hidden">
            <img
              src="@/assets/images/aliviamed_icon.svg"
              alt="Aliviamed"
              class="w-8 h-8"
            />
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-3">
            Consultas con Aliviamed
          </h3>
          <p class="text-gray-600">
            Accede a consultas médicas profesionales desde la comodidad
            de tu hogar con nuestro servicio de telemedicina Aliviamed.
          </p>
        </div>

        <!-- Benefit 3: Delivery -->
        <div class="card group hover:scale-105 transition-transform duration-300 benefit-card">
          <div class="w-16 h-16 bg-secondary-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-secondary-200 transition-colors duration-300">
            <TruckIcon class="w-8 h-8 text-secondary-600" />
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-3">
            Delivery en Lima
          </h3>
          <p class="text-gray-600">
            Servicio de entrega a domicilio rápido y confiable en toda
            Lima Metropolitana. Recibe tus medicamentos sin salir de casa.
          </p>
        </div>

        <!-- Benefit 4: Professional Advice -->
        <div class="card group hover:scale-105 transition-transform duration-300 benefit-card">
          <div class="w-16 h-16 bg-accent-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-accent-200 transition-colors duration-300">
            <UsersIcon class="w-8 h-8 text-accent-600" />
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-3">
            Asesoramiento Profesional
          </h3>
          <p class="text-gray-600">
            Nuestro equipo de farmacéuticos calificados te brinda
            orientación personalizada sobre medicamentos y salud.
          </p>
        </div>

        <!-- Benefit 5: Quality Products -->
        <div class="card group hover:scale-105 transition-transform duration-300 benefit-card">
          <div class="w-16 h-16 bg-primary-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-primary-200 transition-colors duration-300">
            <AwardIcon class="w-8 h-8 text-primary" />
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-3">
            Productos de Calidad
          </h3>
          <p class="text-gray-600">
            Medicamentos originales y productos farmacéuticos de las
            mejores marcas, con todas las certificaciones requeridas.
          </p>
        </div>

        <!-- Benefit 6: Agora Loyalty -->
        <div class="card group hover:scale-105 transition-transform duration-300 benefit-card">
          <div class="w-16 h-16 bg-purple-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-purple-200 transition-colors duration-300">
            <HeartIcon class="w-8 h-8 text-purple-600" />
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-3">
            Programa Agora
          </h3>
          <p class="text-gray-600">
            Únete a nuestro programa de lealtad Agora y acumula puntos
            con cada compra para obtener descuentos y beneficios exclusivos.
          </p>
        </div>
      </div>

      <!-- Call to Action -->
      <div class="text-center mt-10">
        <div class="bg-linear-to-r from-primary-50 to-secondary-50 rounded-2xl p-6 md:p-10 benefits-cta">
          <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">
            ¿Necesitas ayuda con tu salud?
          </h3>
          <p class="text-lg text-gray-600 mb-8 max-w-2xl mx-auto">
            Nuestros especialistas están listos para atenderte.
            Contáctanos hoy mismo para recibir asesoramiento personalizado.
          </p>
          <div class="flex flex-col sm:flex-row gap-3 justify-center benefits-cta-buttons">
            <router-link
              to="/contact"
              class="btn-primary"
            >
              Contactar Farmacéutico
            </router-link>
            <router-link
              to="/services"
              class="btn-secondary"
            >
              Ver Servicios
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
  AwardIcon,
  TruckIcon,
  UsersIcon,
  HeartIcon
} from 'lucide-vue-next'

onMounted(async () => {
  await nextTick()

  // Increased delay to ensure DOM and assets are fully rendered
  setTimeout(() => {
    const tl = gsap.timeline()

    // Set initial states explicitly
    gsap.set('.benefits-header h2', { y: 50, opacity: 0 })
    gsap.set('.benefits-header p', { y: 30, opacity: 0 })
    gsap.set('.benefit-card', { y: 60, opacity: 0 })
    gsap.set('.benefits-cta', { y: 40, opacity: 0 })
    gsap.set('.benefits-cta h3', { y: 30, opacity: 0 })
    gsap.set('.benefits-cta p', { y: 20, opacity: 0 })
    gsap.set('.benefits-cta-buttons', { y: 30, opacity: 0 })

    // Animate section header
    tl.to('.benefits-header h2', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      ease: 'power3.out'
    })
    .to('.benefits-header p', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      ease: 'power2.out'
    }, '-=0.4')

    // Animate benefit cards with stagger
    .to('.benefit-card', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      stagger: 0.15,
      ease: 'back.out(1.7)'
    }, '-=0.5')

    // Animate CTA section
    .to('.benefits-cta', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      ease: 'power2.out'
    }, '-=0.3')
    .to('.benefits-cta h3', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      ease: 'power2.out'
    }, '-=0.5')
    .to('.benefits-cta p', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      ease: 'power2.out'
    }, '-=0.3')
    .to('.benefits-cta-buttons', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      ease: 'power2.out'
    }, '-=0.4')

    // Add hover animations for cards
    gsap.set('.benefit-card', { transformOrigin: 'center bottom' })

    document.querySelectorAll('.benefit-card').forEach((card) => {
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
  }, 500)
})
</script>