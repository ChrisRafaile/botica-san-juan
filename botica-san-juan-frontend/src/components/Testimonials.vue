<template>
  <section class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Section Header -->
      <div class="text-center mb-16 testimonials-header">
        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
          Lo que dicen nuestros clientes
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          Miles de personas confían en nosotros para cuidar su salud.
          Estas son algunas de sus experiencias.
        </p>
      </div>

      <!-- Testimonials Grid -->
      <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
        <!-- Testimonial 1: Maria Lopez -->
        <div class="card testimonial-card">
          <div class="flex items-center mb-4">
            <div class="flex text-yellow-400">
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
            </div>
          </div>
          <blockquote class="text-gray-700 mb-6 italic">
            "Botica San Juan ha sido mi farmacia de confianza por años.
            Siempre encuentro todo lo que necesito y el servicio es excepcional.
            Los recomiendo ampliamente."
          </blockquote>
          <div class="flex items-center">
            <div class="w-12 h-12 bg-primary-100 rounded-full flex items-center justify-center mr-4 overflow-hidden">
              <img
                src="@/assets/images/maria lopez.webp"
                alt="María López"
                class="w-full h-full object-cover"
              />
            </div>
            <div>
              <div class="font-semibold text-gray-900">
                María López
              </div>
              <div class="text-sm text-gray-600">
                Cliente fiel desde 2018
              </div>
            </div>
          </div>
        </div>

        <!-- Testimonial 2: Carlos Fernandez -->
        <div class="card testimonial-card">
          <div class="flex items-center mb-4">
            <div class="flex text-yellow-400">
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
            </div>
          </div>
          <blockquote class="text-gray-700 mb-6 italic">
            "El servicio de delivery es muy rápido y confiable. He recibido
            mis medicamentos en perfectas condiciones. Excelente atención
            al cliente."
          </blockquote>
          <div class="flex items-center">
            <div class="w-12 h-12 bg-secondary-100 rounded-full flex items-center justify-center mr-4 overflow-hidden">
              <img
                src="@/assets/images/carlos.webp"
                alt="Carlos Fernández"
                class="w-full h-full object-cover"
              />
            </div>
            <div>
              <div class="font-semibold text-gray-900">
                Carlos Fernández
              </div>
              <div class="text-sm text-gray-600">
                Cliente recurrente
              </div>
            </div>
          </div>
        </div>

        <!-- Testimonial 3: Luis Sanchez -->
        <div class="card testimonial-card">
          <div class="flex items-center mb-4">
            <div class="flex text-yellow-400">
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
            </div>
          </div>
          <blockquote class="text-gray-700 mb-6 italic">
            "Los precios son muy competitivos y siempre tienen los medicamentos
            que necesito. El programa Agora es una gran ventaja para ahorrar
            en mis compras."
          </blockquote>
          <div class="flex items-center">
            <div class="w-12 h-12 bg-accent-100 rounded-full flex items-center justify-center mr-4 overflow-hidden">
              <img
                src="@/assets/images/luis.webp"
                alt="Luis Sánchez"
                class="w-full h-full object-cover"
              />
            </div>
            <div>
              <div class="font-semibold text-gray-900">
                Luis Sánchez
              </div>
              <div class="text-sm text-gray-600">
                Miembro Agora
              </div>
            </div>
          </div>
        </div>

        <!-- Testimonial 4 -->
        <div class="card testimonial-card">
          <div class="flex items-center mb-4">
            <div class="flex text-yellow-400">
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
            </div>
          </div>
          <blockquote class="text-gray-700 mb-6 italic">
            "En una emergencia médica, llamé a las 2 AM y me atendieron
            inmediatamente. El delivery llegó en menos de una hora.
            Servicio excepcional."
          </blockquote>
          <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
              <UserIcon class="w-6 h-6 text-green-600" />
            </div>
            <div>
              <div class="font-semibold text-gray-900">
                Roberto Sánchez
              </div>
              <div class="text-sm text-gray-600">
                Cliente de emergencia
              </div>
            </div>
          </div>
        </div>

        <!-- Testimonial 5 -->
        <div class="card testimonial-card">
          <div class="flex items-center mb-4">
            <div class="flex text-yellow-400">
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
            </div>
          </div>
          <blockquote class="text-gray-700 mb-6 italic">
            "Como adulto mayor, aprecio mucho el servicio a domicilio.
            Los chicos son muy cuidadosos y respetuosos. Me siento seguro
            comprando aquí."
          </blockquote>
          <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
              <UserIcon class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <div class="font-semibold text-gray-900">
                Doña Carmen
              </div>
              <div class="text-sm text-gray-600">
                Cliente senior
              </div>
            </div>
          </div>
        </div>

        <!-- Testimonial 6 -->
        <div class="card testimonial-card">
          <div class="flex items-center mb-4">
            <div class="flex text-yellow-400">
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
              <StarIcon class="w-5 h-5 fill-current" />
            </div>
          </div>
          <blockquote class="text-gray-700 mb-6 italic">
            "Excelente atención en línea. Pude consultar sobre un medicamento
            por WhatsApp y me respondieron rápidamente con toda la información
            que necesitaba."
          </blockquote>
          <div class="flex items-center">
            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
              <UserIcon class="w-6 h-6 text-purple-600" />
            </div>
            <div>
              <div class="font-semibold text-gray-900">
                Luis Martínez
              </div>
              <div class="text-sm text-gray-600">
                Cliente digital
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Trust Indicators -->
      <div class="text-center">
        <div class="bg-white rounded-2xl p-8 shadow-lg trust-indicators">
          <h3 class="text-2xl font-bold text-gray-900 mb-6">
            Confianza que se traduce en resultados
          </h3>
          <div class="grid md:grid-cols-4 gap-8">
            <div class="text-center trust-stat">
              <div class="text-3xl font-bold text-primary mb-2">
                4.8/5
              </div>
              <div class="text-sm text-gray-600">
                Calificación promedio
              </div>
              <div class="flex justify-center mt-2">
                <div class="flex text-yellow-400">
                  <StarIcon class="w-4 h-4 fill-current" />
                  <StarIcon class="w-4 h-4 fill-current" />
                  <StarIcon class="w-4 h-4 fill-current" />
                  <StarIcon class="w-4 h-4 fill-current" />
                  <StarIcon class="w-4 h-4 fill-current" />
                </div>
              </div>
            </div>
            <div class="text-center trust-stat">
              <div class="text-3xl font-bold text-secondary-600 mb-2">
                95%
              </div>
              <div class="text-sm text-gray-600">
                Satisfacción del cliente
              </div>
            </div>
            <div class="text-center trust-stat">
              <div class="text-3xl font-bold text-accent-600 mb-2">
                2h
              </div>
              <div class="text-sm text-gray-600">
                Tiempo de respuesta
              </div>
            </div>
            <div class="text-center trust-stat">
              <div class="text-3xl font-bold text-green-600 mb-2">
                5K+
              </div>
              <div class="text-sm text-gray-600">
                Pedidos entregados
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted, nextTick } from 'vue'
import { gsap } from 'gsap'
import { StarIcon, UserIcon } from 'lucide-vue-next'

onMounted(async () => {
  await nextTick()

  // Increased delay to ensure DOM and assets are fully rendered
  setTimeout(() => {
    const tl = gsap.timeline()

    // Set initial states explicitly
    gsap.set('.testimonials-header h2', { y: 50, opacity: 0 })
    gsap.set('.testimonials-header p', { y: 30, opacity: 0 })
    gsap.set('.testimonial-card', { y: 60, opacity: 0 })
    gsap.set('.trust-indicators', { y: 40, opacity: 0 })
    gsap.set('.trust-indicators h3', { y: 30, opacity: 0 })
    gsap.set('.trust-stat', { y: 20, opacity: 0 })

    // Animate section header
    tl.to('.testimonials-header h2', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      ease: 'power3.out'
    })
    .to('.testimonials-header p', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      ease: 'power2.out'
    }, '-=0.4')

    // Animate testimonial cards with stagger
    .to('.testimonial-card', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      stagger: 0.15,
      ease: 'back.out(1.7)'
    }, '-=0.5')

    // Animate trust indicators section
    .to('.trust-indicators', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      ease: 'power2.out'
    }, '-=0.3')
    .to('.trust-indicators h3', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      ease: 'power2.out'
    }, '-=0.5')
    .to('.trust-stat', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      stagger: 0.1,
      ease: 'power2.out'
    }, '-=0.4')

    // Add hover animations for testimonial cards
    gsap.set('.testimonial-card', { transformOrigin: 'center bottom' })

    document.querySelectorAll('.testimonial-card').forEach((card) => {
      card.addEventListener('mouseenter', () => {
        gsap.to(card, {
          scale: 1.03,
          y: -8,
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