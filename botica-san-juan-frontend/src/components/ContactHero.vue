<template>
  <section class="relative bg-linear-to-br from-clinico-700 via-clinico-800 to-clinico-900 text-white overflow-hidden">
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
          <div class="inline-flex items-center px-4 py-2 bg-black/15 backdrop-blur-sm rounded-full text-sm font-medium mb-6">
            <MessageCircleIcon class="w-4 h-4 mr-2" />
            Estamos aquí para ayudarte
          </div>

          <!-- Decía "Contáctanos 24/7" sobre un horario que cierra a las diez.
               La promesa que el cliente recuerda es la grande, y el que se
               presenta a la una de la mañana se va con razón enfadado. -->
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 leading-tight">
            Contáctanos
            <span class="block text-ambar-400">todos los días</span>
          </h1>

          <p class="text-xl md:text-2xl text-white/90 mb-8 leading-relaxed">
            Tu salud es nuestra prioridad. Estamos disponibles para resolver tus dudas y atender tus necesidades médicas.
          </p>

          <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
            <a
              :href="`tel:${TELEFONO_FIJO_E164}`"
              class="foco-dentro inline-flex items-center px-8 py-4 bg-white text-clinico-700 font-semibold rounded-xl hover:bg-clinico-50 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1"
            >
              <PhoneIcon class="w-5 h-5 mr-3" aria-hidden="true" />
              Llamar al {{ TELEFONO_FIJO }}
            </a>
            <!-- El enlace apuntaba a wa.me/016772892, que es el FIJO: WhatsApp
                 no existe en un número fijo, así que ese botón no abría ninguna
                 conversación. Ahora sale de datos/botica.ts y, si el número no
                 está configurado, no se pinta en vez de prometer un canal que
                 no responde. -->
            <a
              v-if="enlaceWa"
              :href="enlaceWa"
              target="_blank"
              rel="noopener noreferrer"
              class="foco-dentro inline-flex items-center px-8 py-4 bg-ambar-400 text-ambar-950 font-semibold rounded-xl hover:bg-ambar-600 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1"
            >
              <MessageCircleIcon class="w-5 h-5 mr-3" aria-hidden="true" />
              WhatsApp
            </a>
          </div>
        </div>

        <!-- Illustration -->
        <div class="relative">
          <div class="relative bg-black/15 backdrop-blur-sm rounded-3xl p-8 shadow-2xl">
            <div class="grid grid-cols-2 gap-6">
              <div class="bg-white/20 rounded-2xl p-6 text-center backdrop-blur-sm">
                <PhoneIcon class="w-12 h-12 mx-auto mb-4 text-white" />

                <h3 class="font-semibold text-white mb-2">
                  Llamadas
                </h3>

                <p class="text-white/90 text-sm">
                  Atención inmediata
                </p>
              </div>
              <div class="bg-white/20 rounded-2xl p-6 text-center backdrop-blur-sm">
                <MailIcon class="w-12 h-12 mx-auto mb-4 text-white" />

                <h3 class="font-semibold text-white mb-2">
                  Email
                </h3>

                <p class="text-white/90 text-sm">
                  Respuesta rápida
                </p>
              </div>
              <div class="bg-white/20 rounded-2xl p-6 text-center backdrop-blur-sm">
                <MessageCircleIcon class="w-12 h-12 mx-auto mb-4 text-white" />

                <h3 class="font-semibold text-white mb-2">
                  WhatsApp
                </h3>

                <p class="text-white/90 text-sm">
                  En horario de atención
                </p>
              </div>
              <div class="bg-white/20 rounded-2xl p-6 text-center backdrop-blur-sm">
                <MapPinIcon class="w-12 h-12 mx-auto mb-4 text-white" />

                <h3 class="font-semibold text-white mb-2">
                  Ubicación
                </h3>

                <p class="text-white/90 text-sm">
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
import { prefiereMenosMovimiento } from '@/utils/motion'
import { TELEFONO_FIJO, TELEFONO_FIJO_E164, enlaceWhatsApp } from '@/datos/botica'

gsap.registerPlugin(ScrollTrigger)

const enlaceWa = enlaceWhatsApp('Hola, tengo una consulta para Botica San Juan.')

onMounted(() => {
  /* Si la persona pidió reducir el movimiento, no se anima: el contenido ya
     está en su estado final y visible, que es justamente lo que se quiere. */
  if (prefiereMenosMovimiento()) return
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