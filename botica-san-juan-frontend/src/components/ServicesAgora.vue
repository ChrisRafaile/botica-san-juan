<template>
  <section class="py-24 bg-white">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <GiftIcon class="w-4 h-4" />
          Programa de Fidelización
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
          Programa Agora
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          Nuestro programa de fidelización que te recompensa por elegir Botica San Juan
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        <div class="space-y-6">
          <div class="bg-linear-to-r from-blue-500 to-green-500 p-8 rounded-2xl text-white shadow-xl">
            <h3 class="text-3xl font-bold mb-6">
              ¿Cómo funciona?
            </h3>
            <div class="space-y-4">
              <div class="flex items-start">
                <div class="bg-white bg-opacity-20 rounded-full w-10 h-10 flex items-center justify-center mr-4 mt-1 shrink-0">
                  <span class="font-bold text-lg">1</span>
                </div>
                <div>
                  <h4 class="font-semibold mb-2 text-lg">
                    Regístrate
                  </h4>
                  <p class="text-blue-100 leading-relaxed">
                    Crea tu cuenta en el programa Agora de forma gratuita
                  </p>
                </div>
              </div>
              <div class="flex items-start">
                <div class="bg-white bg-opacity-20 rounded-full w-10 h-10 flex items-center justify-center mr-4 mt-1 shrink-0">
                  <span class="font-bold text-lg">2</span>
                </div>
                <div>
                  <h4 class="font-semibold mb-2 text-lg">
                    Acumula puntos
                  </h4>
                  <p class="text-blue-100 leading-relaxed">
                    Por cada compra recibe puntos Agora automáticamente
                  </p>
                </div>
              </div>
              <div class="flex items-start">
                <div class="bg-white bg-opacity-20 rounded-full w-10 h-10 flex items-center justify-center mr-4 mt-1 shrink-0">
                  <span class="font-bold text-lg">3</span>
                </div>
                <div>
                  <h4 class="font-semibold mb-2 text-lg">
                    Canjea recompensas
                  </h4>
                  <p class="text-blue-100 leading-relaxed">
                    Usa tus puntos por descuentos y productos exclusivos
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-gray-50 p-8 rounded-2xl shadow-lg">
            <h3 class="text-2xl font-bold text-gray-900 mb-6">
              Beneficios del Programa
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="flex items-center p-3 bg-white rounded-lg">
                <CheckIcon class="w-5 h-5 text-green-600 mr-3 shrink-0" />
                <span class="text-gray-700 font-medium">Descuentos exclusivos</span>
              </div>
              <div class="flex items-center p-3 bg-white rounded-lg">
                <CheckIcon class="w-5 h-5 text-green-600 mr-3 shrink-0" />
                <span class="text-gray-700 font-medium">Puntos por compras</span>
              </div>
              <div class="flex items-center p-3 bg-white rounded-lg">
                <CheckIcon class="w-5 h-5 text-green-600 mr-3 shrink-0" />
                <span class="text-gray-700 font-medium">Cumpleaños doble</span>
              </div>
              <div class="flex items-center p-3 bg-white rounded-lg">
                <CheckIcon class="w-5 h-5 text-green-600 mr-3 shrink-0" />
                <span class="text-gray-700 font-medium">Acceso anticipado</span>
              </div>
              <div class="flex items-center p-3 bg-white rounded-lg">
                <CheckIcon class="w-5 h-5 text-green-600 mr-3 shrink-0" />
                <span class="text-gray-700 font-medium">Entregas gratis</span>
              </div>
              <div class="flex items-center p-3 bg-white rounded-lg">
                <CheckIcon class="w-5 h-5 text-green-600 mr-3 shrink-0" />
                <span class="text-gray-700 font-medium">Atención prioritaria</span>
              </div>
            </div>
          </div>
        </div>

        <div class="text-center">
          <div class="relative mb-8">
            <div class="absolute inset-0 bg-linear-to-r from-blue-400 to-green-400 rounded-3xl transform rotate-6 opacity-20"></div>
            <img
              src="@/assets/images/botica1.jpg"
              alt="Programa Agora - Botica San Juan"
              class="relative w-full h-auto rounded-3xl shadow-2xl object-cover"
            />
          </div>
          <button class="bg-blue-600 text-white px-10 py-4 rounded-full hover:bg-blue-700 transition-all duration-300 transform hover:scale-105 shadow-lg font-semibold text-lg">
            Regístrate Ahora
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { GiftIcon, CheckIcon } from 'lucide-vue-next'
import { onMounted } from 'vue'
import { gsap } from 'gsap'

onMounted(() => {
  // Animate the content sections
  gsap.from('.agora-content', {
    duration: 1,
    x: -50,
    opacity: 0,
    ease: 'power3.out'
  })

  gsap.from('.agora-image', {
    duration: 1,
    x: 50,
    opacity: 0,
    ease: 'power3.out',
    delay: 0.3
  })
})
</script>

<style scoped>
/* Component styles */
</style>