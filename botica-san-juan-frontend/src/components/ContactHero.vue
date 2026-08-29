<template>
  <section class="relative bg-linear-to-br from-blue-600 via-blue-700 to-blue-800 text-white overflow-hidden">
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-10">
      <div
        class="absolute inset-0"
        style="background-image: radial-gradient(circle at 25% 25%, rgba(255,255,255,0.2) 0%, transparent 50%), radial-gradient(circle at 75% 75%, rgba(255,255,255,0.1) 0%, transparent 50%);"
      ></div>
    </div>

    <!-- Floating Elements -->
    <div class="absolute top-20 left-10 w-20 h-20 bg-white/10 rounded-full blur-xl animate-pulse"></div>
    <div class="absolute bottom-20 right-10 w-32 h-32 bg-white/5 rounded-full blur-2xl animate-pulse delay-1000"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        <!-- Content -->
        <div class="text-center lg:text-left">
          <div class="inline-flex items-center px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full text-sm font-medium mb-6">
            <MessageCircleIcon class="w-4 h-4 mr-2" />
            Estamos aquí para ayudarte
          </div>

          <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 leading-tight">
            Contáctanos
            <span class="block text-accent-400">24/7</span>
          </h1>

          <p class="text-xl md:text-2xl text-blue-100 mb-8 leading-relaxed">
            Tu salud es nuestra prioridad. Estamos disponibles para resolver tus dudas y atender tus necesidades médicas.
          </p>

          <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
            <a
              href="tel:+016772892"
              class="inline-flex items-center px-8 py-4 bg-white text-blue-700 font-semibold rounded-xl hover:bg-blue-50 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1"
            >
              <PhoneIcon class="w-5 h-5 mr-3" />
              Llamar ahora
            </a>
            <a
              href="https://wa.me/016772892"
              target="_blank"
              class="inline-flex items-center px-8 py-4 bg-accent-500 text-white font-semibold rounded-xl hover:bg-accent-600 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1"
            >
              <MessageCircleIcon class="w-5 h-5 mr-3" />
              WhatsApp
            </a>
          </div>
        </div>

        <!-- Illustration -->
        <div class="relative">
          <div class="relative bg-white/10 backdrop-blur-sm rounded-3xl p-8 shadow-2xl">
            <div class="grid grid-cols-2 gap-6">
              <div class="bg-white/20 rounded-2xl p-6 text-center backdrop-blur-sm">
                <PhoneIcon class="w-12 h-12 mx-auto mb-4 text-white" />

                <h3 class="font-semibold text-white mb-2">
                  Llamadas
                </h3>

                <p class="text-blue-100 text-sm">
                  Atención inmediata
                </p>
              </div>
              <div class="bg-white/20 rounded-2xl p-6 text-center backdrop-blur-sm">
                <MailIcon class="w-12 h-12 mx-auto mb-4 text-white" />

                <h3 class="font-semibold text-white mb-2">
                  Email
                </h3>

                <p class="text-blue-100 text-sm">
                  Respuesta rápida
                </p>
              </div>
              <div class="bg-white/20 rounded-2xl p-6 text-center backdrop-blur-sm">
                <MessageCircleIcon class="w-12 h-12 mx-auto mb-4 text-white" />

                <h3 class="font-semibold text-white mb-2">
                  WhatsApp
                </h3>

                <p class="text-blue-100 text-sm">
                  Disponible 24/7
                </p>
              </div>
              <div class="bg-white/20 rounded-2xl p-6 text-center backdrop-blur-sm">
                <MapPinIcon class="w-12 h-12 mx-auto mb-4 text-white" />

                <h3 class="font-semibold text-white mb-2">
                  Ubicación
                </h3>

                <p class="text-blue-100 text-sm">
                  Fácil acceso
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import {
  MessageCircleIcon,
  PhoneIcon,
  MailIcon,
  MapPinIcon
} from 'lucide-vue-next'

gsap.registerPlugin(ScrollTrigger)

onMounted(() => {
  // Animate hero content
  gsap.from('.hero-content', {
    duration: 1,
    y: 50,
    opacity: 0,
    ease: 'power3.out'
  })

  // Animate floating elements
  gsap.to('.floating-element', {
    duration: 3,
    y: -20,
    ease: 'power2.inOut',
    yoyo: true,
    repeat: -1
  })

  // Animate contact cards with stagger
  gsap.from('.contact-card', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    stagger: 0.1,
    delay: 0.5,
    ease: 'power3.out'
  })

  // Parallax effect for background
  gsap.to('.hero-bg', {
    scrollTrigger: {
      trigger: '.hero-section',
      start: 'top bottom',
      end: 'bottom top',
      scrub: 1
    },
    y: -100
  })
})
</script>

<style scoped>
.hero-content {
  animation: fadeInUp 1s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>