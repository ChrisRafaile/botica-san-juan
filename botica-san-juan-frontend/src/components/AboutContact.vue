<template>
  <section class="py-24 bg-gray-50">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <MapPin class="w-4 h-4" />
          Contáctanos
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
          Estamos aquí para ti
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          Visítanos en nuestra tienda física o contáctanos para cualquier consulta sobre salud
        </p>
      </div>

      <div class="grid lg:grid-cols-2 gap-12">
        <!-- Contact Information -->
        <div class="contact-info space-y-8">
          <!-- Store Location -->
          <div class="contact-card bg-white rounded-3xl p-8 shadow-lg border border-gray-100">
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center shrink-0">
                <MapPin class="w-6 h-6 text-blue-600" />
              </div>
              <div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">
                  Tienda Física
                </h3>
                <p class="text-gray-700 mb-4">
                  Av. Sta. Rosa de Lima 103<br />
                  San Juan de Lurigancho 15423<br />
                  Lima, Perú
                </p>
                <div class="flex flex-col sm:flex-row gap-4">
                  <button class="bg-blue-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-blue-700 transition-colors">
                    Cómo Llegar
                  </button>
                  <button class="border-2 border-blue-600 text-blue-600 px-6 py-3 rounded-xl font-semibold hover:bg-blue-50 transition-colors">
                    Ver en Maps
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Contact Details -->
          <div class="grid md:grid-cols-2 gap-6">
            <!-- Phone -->
            <div class="contact-card bg-white rounded-3xl p-6 shadow-lg border border-gray-100">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-green-100 rounded-2xl flex items-center justify-center">
                  <Phone class="w-6 h-6 text-green-600" />
                </div>
                <div>
                  <div class="font-semibold text-gray-900">
                    Teléfono
                  </div>
                  <div class="text-blue-600 font-medium">
                    (01) 677-2892
                  </div>
                </div>
              </div>
            </div>

            <!-- Email -->
            <div class="contact-card bg-white rounded-3xl p-6 shadow-lg border border-gray-100">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center">
                  <Mail class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                  <div class="font-semibold text-gray-900">
                    Email
                  </div>
                  <div class="text-blue-600 font-medium">
                    info@boticasan-juan.com
                  </div>
                </div>
              </div>
            </div>

            <!-- Hours -->
            <div class="contact-card bg-white rounded-3xl p-6 shadow-lg border border-gray-100">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-purple-100 rounded-2xl flex items-center justify-center">
                  <Clock class="w-6 h-6 text-purple-600" />
                </div>
                <div>
                  <div>
                    Horarios
                  </div>
                  <div class="text-gray-600 text-sm">
                    Lun-Vie: 7:00 - 22:00<br />
                    Sáb: 8:00 - 21:00<br />
                    Dom: 9:00 - 18:00
                  </div>
                </div>
              </div>
            </div>

            <!-- Emergency -->
            <div class="contact-card bg-white rounded-3xl p-6 shadow-lg border border-gray-100">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-red-100 rounded-2xl flex items-center justify-center">
                  <AlertTriangle class="w-6 h-6 text-red-600" />
                </div>
                <div>
                  <div>
                    Emergencias
                  </div>
                  <div>
                    24/7 Disponible
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="bg-blue-600 rounded-3xl p-8 text-white">
            <h3>
              ¿Necesitas ayuda inmediata?
            </h3>
            <p class="mb-6 opacity-90">
              Nuestro equipo de farmacéuticos está disponible para consultas urgentes
              sobre medicamentos y salud.
            </p>
            <div class="flex flex-col sm:flex-row gap-4">
              <button class="bg-white text-blue-600 px-6 py-3 rounded-xl font-semibold hover:bg-gray-100 transition-colors">
                Llamar Ahora
              </button>
              <button class="border-2 border-white/30 text-white px-6 py-3 rounded-xl font-semibold hover:bg-white/10 transition-colors">
                Chat en Línea
              </button>
            </div>
          </div>
        </div>

        <!-- Map Section -->
        <div class="map-section">
          <div class="bg-white rounded-3xl p-8 shadow-lg border border-gray-100 h-full">
            <h3>
              Nuestra Ubicación
            </h3>

            <!-- Map Placeholder -->
            <div class="relative bg-gray-100 rounded-2xl h-96 mb-6 overflow-hidden">
              <!-- Google Maps Embed -->
              <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3903.1093089078936!2d-76.99819362405982!3d-11.966933240421248!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9105c5919fa13ef3%3A0xba67ea4c5d045e15!2sAv.%20Sta.%20Rosa%20de%20Lima%20103%2C%20San%20Juan%20de%20Lurigancho%2015423!5e0!3m2!1ses-419!2spe!4v1760860710905!5m2!1ses-419!2spe"
                width="100%"
                height="100%"
                style="border:0;"
                allowfullscreen
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
              ></iframe>
            </div>

            <!-- Location Details -->
            <div class="space-y-4">
              <div class="flex items-start gap-3">
                <Navigation class="w-5 h-5 text-blue-600 mt-0.5" />
                <div>
                  <div>
                    Dirección
                  </div>
                  <div>
                    Av. Sta. Rosa de Lima 103, San Juan de Lurigancho, Lima
                  </div>
                </div>
              </div>

              <div class="flex items-start gap-3">
                <Car class="w-5 h-5 text-blue-600 mt-0.5" />
                <div>
                  <div>
                    Estacionamiento
                  </div>
                  <div>
                    Zona de estacionamiento gratuito disponible
                  </div>
                </div>
              </div>

              <div class="flex items-start gap-3">
                <Bus class="w-5 h-5 text-blue-600 mt-0.5" />
                <div>
                  <div>
                    Transporte Público
                  </div>
                  <div>
                    Metro: Estación Santa Rosa (Línea 1) - 10 min
                  </div>
                </div>
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
import { MapPin, Phone, Mail, Clock, AlertTriangle, Navigation, Car, Bus } from 'lucide-vue-next'

onMounted(() => {
  // Animate contact cards with stagger
  gsap.from('.contact-card', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    stagger: 0.1,
    ease: 'power3.out'
  })

  // Animate map section
  gsap.from('.map-section', {
    duration: 1,
    x: 30,
    opacity: 0,
    ease: 'power3.out',
    delay: 0.3
  })
})
</script>

<style scoped>
/* Component styles */
</style>