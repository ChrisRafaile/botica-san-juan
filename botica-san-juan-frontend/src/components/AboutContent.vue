<template>
  <section class="py-24 bg-white">
    <div class="container mx-auto px-6">
      <!-- History Section -->
      <div class="mb-24">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
          <div class="history-content">
            <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
              <Heart class="w-4 h-4" />
              Nuestra Historia
            </div>
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
              Más de 15 años cuidando tu salud
            </h2>
            <p class="text-lg text-gray-600 mb-6 leading-relaxed">
              Fundada en 2008, Botica San Juan comenzó como un pequeño establecimiento familiar
              con el sueño de proporcionar medicamentos y productos de salud de calidad a nuestra comunidad.
            </p>
            <p class="text-lg text-gray-600 mb-8 leading-relaxed">
              A lo largo de los años, hemos crecido manteniendo nuestros valores fundamentales:
              atención personalizada, productos de calidad y compromiso con el bienestar de nuestros clientes.
            </p>
            <div class="grid grid-cols-2 gap-6">
              <div class="text-center">
                <div class="text-3xl font-bold text-primary-600 mb-2">
                  2008
                </div>
                <div class="text-gray-600">
                  Fundación
                </div>
              </div>
              <div class="text-center">
                <div class="text-3xl font-bold text-primary-600 mb-2">
                  50K+
                </div>
                <div class="text-gray-600">
                  Clientes
                </div>
              </div>
            </div>
          </div>
          <div class="history-image">
            <div class="relative">
              <div class="bg-linear-to-br from-blue-100 to-blue-200 rounded-3xl p-8 transform rotate-3 hover:rotate-0 transition-transform duration-500">
                <div class="w-full h-80 bg-linear-to-br from-blue-200 to-blue-300 rounded-2xl shadow-lg flex items-center justify-center">
                  <div class="text-center text-blue-700">
                    <Heart class="w-16 h-16 mx-auto mb-4 opacity-50" />
                    <p class="font-semibold">
                      Botica San Juan
                    </p>
                    <p class="text-sm opacity-75">
                      Desde 2008
                    </p>
                  </div>
                </div>
              </div>
              <div class="absolute -bottom-6 -left-6 bg-white rounded-2xl p-4 shadow-lg">
                <div class="flex items-center gap-3">
                  <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <Award class="w-6 h-6 text-blue-600" />
                  </div>
                  <div>
                    <div class="font-semibold text-gray-900">
                      Premio Calidad
                    </div>
                    <div class="text-sm text-gray-600">
                      2023
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Vision & Mission Section -->
      <div class="mb-24">
        <div class="text-center mb-16">
          <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
            Nuestra Visión y Misión
          </h2>
          <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            Guiados por principios que nos han acompañado desde nuestros inicios
          </p>
        </div>

        <div class="grid md:grid-cols-2 gap-12">
          <!-- Vision Card -->
          <div class="vision-card bg-linear-to-br from-blue-50 to-blue-100 rounded-3xl p-8 border border-blue-200">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-16 h-16 bg-blue-500 rounded-2xl flex items-center justify-center">
                <Eye class="w-8 h-8 text-white" />
              </div>
              <h3 class="text-2xl font-bold text-gray-900">
                Visión
              </h3>
            </div>
            <p class="text-gray-700 leading-relaxed">
              Ser la botica de referencia en la comunidad, reconocida por nuestra excelencia
              en el servicio, innovación en productos de salud y compromiso inquebrantable
              con el bienestar de nuestros clientes.
            </p>
          </div>

          <!-- Mission Card -->
          <div class="mission-card bg-linear-to-br from-green-50 to-green-100 rounded-3xl p-8 border border-green-200">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-16 h-16 bg-green-500 rounded-2xl flex items-center justify-center">
                <Target class="w-8 h-8 text-white" />
              </div>
              <h3 class="text-2xl font-bold text-gray-900">
                Misión
              </h3>
            </div>
            <p class="text-gray-700 leading-relaxed">
              Proporcionar productos farmacéuticos y de salud de la más alta calidad,
              ofreciendo asesoramiento profesional y atención personalizada para mejorar
              la calidad de vida de nuestros clientes.
            </p>
          </div>
        </div>
      </div>

      <!-- Values Section -->
      <div class="text-center">
        <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <Shield class="w-4 h-4" />
          Nuestros Valores
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-16">
          Lo que nos define
        </h2>

        <div class="grid md:grid-cols-3 gap-8">
          <div class="values-card text-center">
            <div class="w-20 h-20 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
              <Shield class="w-10 h-10 text-blue-600" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-4">
              Confianza
            </h3>
            <p class="text-gray-600">
              Construimos relaciones duraderas basadas en la honestidad,
              transparencia y compromiso con nuestros clientes.
            </p>
          </div>

          <div class="values-card text-center">
            <div class="w-20 h-20 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
              <Users class="w-10 h-10 text-blue-600" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-4">
              Atención Personalizada
            </h3>
            <p class="text-gray-600">
              Cada cliente es único. Ofrecemos asesoramiento personalizado
              adaptado a las necesidades individuales de cada persona.
            </p>
          </div>

          <div class="values-card text-center">
            <div class="w-20 h-20 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
              <Award class="w-10 h-10 text-blue-600" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-4">
              Excelencia
            </h3>
            <p class="text-gray-600">
              Mantenemos los más altos estándares de calidad en nuestros
              productos y servicios, superando las expectativas.
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
import { Heart, Eye, Target, Shield, Users, Award } from 'lucide-vue-next'

onMounted(() => {
  // Animate history section
  gsap.from('.history-content', {
    duration: 1,
    x: -50,
    opacity: 0,
    ease: 'power3.out'
  })

  gsap.from('.history-image', {
    duration: 1,
    x: 50,
    opacity: 0,
    ease: 'power3.out',
    delay: 0.3
  })

  // Animate vision and mission cards
  gsap.from('.vision-card', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    ease: 'power3.out',
    delay: 0.6
  })

  gsap.from('.mission-card', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    ease: 'power3.out',
    delay: 0.8
  })

  // Animate values cards with stagger
  gsap.from('.values-card', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    stagger: 0.2,
    ease: 'power3.out',
    delay: 1.0
  })
})
</script>

<style scoped>
/* Component styles */
</style>