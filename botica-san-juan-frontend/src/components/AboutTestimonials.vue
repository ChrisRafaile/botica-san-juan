<template>
  <section class="py-24 bg-gray-50">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <MessageCircle class="w-4 h-4" />
          Testimonios
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
          Lo que dicen nuestros clientes
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          La satisfacción de nuestros clientes es nuestro mayor orgullo
        </p>
      </div>

      <!-- Testimonials Grid -->
      <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
        <!-- Testimonial 1 -->
        <div class="testimonial-card bg-white rounded-3xl p-8 shadow-lg border border-gray-100">
          <div class="flex items-center gap-1 mb-4">
            <Star
              v-for="i in 5"
              :key="i"
              class="w-5 h-5 text-yellow-400 fill-current"
            />
          </div>
          <p class="text-gray-700 mb-6 leading-relaxed">
            "Excelente atención y productos de calidad. El personal es muy amable
            y siempre me asesoran correctamente sobre mis medicamentos."
          </p>
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
              <User class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <div class="font-semibold text-gray-900">
                María González
              </div>
              <div class="text-sm text-gray-600">
                Cliente fiel desde 2015
              </div>
            </div>
          </div>
        </div>

        <!-- Testimonial 2 -->
        <div class="testimonial-card bg-white rounded-3xl p-8 shadow-lg border border-gray-100">
          <div class="flex items-center gap-1 mb-4">
            <Star
              v-for="i in 5"
              :key="i"
              class="w-5 h-5 text-yellow-400 fill-current"
            />
          </div>
          <p class="text-gray-700 mb-6 leading-relaxed">
            "Me encanta que entreguen a domicilio. Siempre llegan a tiempo y
            con todos los productos que necesito. Servicio impecable."
          </p>
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
              <User class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <div class="font-semibold text-gray-900">
                Carlos Rodríguez
              </div>
              <div class="text-sm text-gray-600">
                Cliente desde 2020
              </div>
            </div>
          </div>
        </div>

        <!-- Testimonial 3 -->
        <div class="testimonial-card bg-white rounded-3xl p-8 shadow-lg border border-gray-100">
          <div class="flex items-center gap-1 mb-4">
            <Star
              v-for="i in 5"
              :key="i"
              class="w-5 h-5 text-yellow-400 fill-current"
            />
          </div>
          <p class="text-gray-700 mb-6 leading-relaxed">
            "La mejor botica de la zona. Tienen todo lo que busco y precios
            muy competitivos. Recomiendo ampliamente."
          </p>
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
              <User class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <div class="font-semibold text-gray-900">
                Ana López
              </div>
              <div class="text-sm text-gray-600">
                Cliente desde 2018
              </div>
            </div>
          </div>
        </div>

        <!-- Testimonial 4 -->
        <div class="testimonial-card bg-white rounded-3xl p-8 shadow-lg border border-gray-100">
          <div class="flex items-center gap-1 mb-4">
            <Star
              v-for="i in 5"
              :key="i"
              class="w-5 h-5 text-yellow-400 fill-current"
            />
          </div>
          <p class="text-gray-700 mb-6 leading-relaxed">
            "Profesionales capacitados que realmente se preocupan por tu salud.
            Siempre me explican todo sobre mis medicamentos."
          </p>
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
              <User class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <div class="font-semibold text-gray-900">
                Roberto Sánchez
              </div>
              <div class="text-sm text-gray-600">
                Cliente desde 2021
              </div>
            </div>
          </div>
        </div>

        <!-- Testimonial 5 -->
        <div class="testimonial-card bg-white rounded-3xl p-8 shadow-lg border border-gray-100">
          <div class="flex items-center gap-1 mb-4">
            <Star
              v-for="i in 5"
              :key="i"
              class="w-5 h-5 text-yellow-400 fill-current"
            />
          </div>
          <p class="text-gray-700 mb-6 leading-relaxed">
            "Servicio rápido y eficiente. La página web es muy fácil de usar
            y las entregas son puntuales. ¡Cinco estrellas!"
          </p>
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
              <User class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <div class="font-semibold text-gray-900">
                Laura Martínez
              </div>
              <div class="text-sm text-gray-600">
                Cliente desde 2022
              </div>
            </div>
          </div>
        </div>

        <!-- Testimonial 6 -->
        <div class="testimonial-card bg-white rounded-3xl p-8 shadow-lg border border-gray-100">
          <div class="flex items-center gap-1 mb-4">
            <Star
              v-for="i in 5"
              :key="i"
              class="w-5 h-5 text-yellow-400 fill-current"
            />
          </div>
          <p class="text-gray-700 mb-6 leading-relaxed">
            "Desde que los conocí, no voy a otra botica. Confío plenamente
            en su profesionalismo y calidad de productos."
          </p>
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
              <User class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <div class="font-semibold text-gray-900">
                Miguel Torres
              </div>
              <div class="text-sm text-gray-600">
                Cliente desde 2012
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats Section -->
      <div class="bg-white rounded-3xl p-8 shadow-lg">
        <div class="grid md:grid-cols-4 gap-8 text-center">
          <div class="stats-item">
            <div class="text-4xl font-bold text-blue-600 mb-2">
              4.9/5
            </div>
            <div class="text-gray-600 mb-1">
              Calificación Promedio
            </div>
            <div class="flex justify-center gap-1">
              <Star
                v-for="i in 5"
                :key="i"
                class="w-4 h-4 text-yellow-400 fill-current"
              />
            </div>
          </div>
          <div class="stats-item">
            <div class="text-4xl font-bold text-blue-600 mb-2">
              98%
            </div>
            <div class="text-gray-600">
              Satisfacción
            </div>
            <div class="text-sm text-gray-500">
              de Clientes
            </div>
          </div>
          <div class="stats-item">
            <div class="text-4xl font-bold text-blue-600 mb-2">
              24h
            </div>
            <div class="text-gray-600">
              Tiempo de
            </div>
            <div class="text-sm text-gray-500">
              Respuesta
            </div>
          </div>
          <div class="stats-item">
            <div class="text-4xl font-bold text-blue-600 mb-2">
              100%
            </div>
            <div class="text-gray-600">
              Productos
            </div>
            <div class="text-sm text-gray-500">
              Certificados
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
import { MessageCircle, Star, User } from 'lucide-vue-next'

onMounted(() => {
  // Animate testimonials with stagger
  gsap.from('.testimonial-card', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    stagger: 0.1,
    ease: 'power3.out'
  })

  // Animate stats items
  gsap.from('.stats-item', {
    duration: 0.8,
    y: 20,
    opacity: 0,
    stagger: 0.1,
    ease: 'power3.out',
    delay: 0.5
  })
})
</script>

<style scoped>
/* Component styles */
</style>