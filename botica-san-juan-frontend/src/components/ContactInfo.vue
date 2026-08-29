<template>
  <section class="py-20 bg-linear-to-br from-gray-50 to-white">
    <div class="container mx-auto px-6">
      <div class="max-w-6xl mx-auto">
        <!-- Section Header -->
        <div class="text-center mb-16">
          <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
            Información de <span class="text-blue-600">Contacto</span>
          </h2>
          <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            Estamos aquí para atenderte. Encuentra toda la información necesaria para comunicarte con nosotros.
          </p>
        </div>

        <!-- Contact Info Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
          <!-- Location Card -->
          <div class="contact-info-card bg-white p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100">
            <div class="flex items-center mb-6">
              <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                <MapPinIcon class="w-6 h-6 text-blue-600" />
              </div>
              <h3 class="text-xl font-bold text-gray-900">
                Ubicación
              </h3>
            </div>
            <div class="space-y-2 text-gray-600">
              <p class="font-medium">
                Botica San Juan
              </p>
              <p>Av. Sta. Rosa de Lima 103</p>
              <p>San Juan de Lurigancho 15423</p>
              <p>Lima, Perú</p>
            </div>
          </div>

          <!-- Phone Card -->
          <div class="contact-info-card bg-white p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100">
            <div class="flex items-center mb-6">
              <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mr-4">
                <PhoneIcon class="w-6 h-6 text-green-600" />
              </div>
              <h3 class="text-xl font-bold text-gray-900">
                Teléfonos
              </h3>
            </div>
            <div class="space-y-3">
              <div class="flex items-center text-gray-600">
                <PhoneIcon class="w-4 h-4 mr-3 text-green-600" />
                <span>(01) 677-2892</span>
              </div>
              <div class="flex items-center text-gray-600">
                <PhoneIcon class="w-4 h-4 mr-3 text-green-600" />
                <span>800-BOTICA-01</span>
              </div>
              <div class="flex items-center text-gray-600">
                <MessageSquareIcon class="w-4 h-4 mr-3 text-blue-600" />
                <span>WhatsApp: +51 967 654 321</span>
              </div>
            </div>
          </div>

          <!-- Email Card -->
          <div class="contact-info-card bg-white p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100">
            <div class="flex items-center mb-6">
              <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                <MailIcon class="w-6 h-6 text-blue-600" />
              </div>
              <h3 class="text-xl font-bold text-gray-900">
                Correo
              </h3>
            </div>
            <div class="space-y-3">
              <div class="flex items-center text-gray-600">
                <MailIcon class="w-4 h-4 mr-3 text-blue-600" />
                <span>info@boticasan-juan.com</span>
              </div>
              <div class="flex items-center text-gray-600">
                <MailIcon class="w-4 h-4 mr-3 text-blue-600" />
                <span>ventas@boticasan-juan.com</span>
              </div>
              <div class="flex items-center text-gray-600">
                <MailIcon class="w-4 h-4 mr-3 text-blue-600" />
                <span>soporte@boticasan-juan.com</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Business Hours -->
        <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100 mb-16 business-hours">
          <div class="flex items-center mb-8">
            <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center mr-4">
              <ClockIcon class="w-6 h-6 text-orange-600" />
            </div>
            <h3 class="text-2xl font-bold text-gray-900">
              Horarios de Atención
            </h3>
          </div>

          <div class="grid md:grid-cols-2 gap-8">
            <div>
              <h4 class="text-lg font-semibold text-gray-900 mb-4">
                Horarios Regulares
              </h4>
              <div class="space-y-3">
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                  <span class="text-gray-600">Lunes a Viernes</span>
                  <span class="font-medium text-gray-900">7:00 AM - 10:00 PM</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                  <span class="text-gray-600">Sábados</span>
                  <span class="font-medium text-gray-900">8:00 AM - 9:00 PM</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-gray-600">Domingos y Festivos</span>
                  <span class="font-medium text-gray-900">9:00 AM - 6:00 PM</span>
                </div>
              </div>
            </div>

            <div>
              <h4 class="text-lg font-semibold text-gray-900 mb-4">
                Servicios Especiales
              </h4>
              <div class="space-y-3">
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                  <span class="text-gray-600">Urgencias 24/7</span>
                  <span class="font-medium text-green-600">Disponible</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                  <span class="text-gray-600">Entregas a Domicilio</span>
                  <span class="font-medium text-blue-600">24 Horas</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-gray-600">Consulta en Línea</span>
                  <span class="font-medium text-purple-600">24/7</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Map Section -->
        <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100 map-section">
          <div class="flex items-center mb-8">
            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mr-4">
              <MapPinIcon class="w-6 h-6 text-red-600" />
            </div>
            <h3 class="text-2xl font-bold text-gray-900">
              Nuestra Ubicación
            </h3>
          </div>

          <!-- Google Maps Embed -->
          <div class="aspect-video bg-gray-100 rounded-xl overflow-hidden border-2 border-gray-200">
            <iframe
              src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3903.1093089078936!2d-76.99819362405982!3d-11.966933240421248!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9105c5919fa13ef3%3A0xba67ea4c5d045e15!2sAv.%20Sta.%20Rosa%20de%20Lima%20103%2C%20San%20Juan%20de%20Lurigancho%2015423!5e0!3m2!1ses-419!2spe!4v1760860710905!5m2!1ses-419!2spe"
              width="100%"
              height="100%"
              style="border:0;"
              allowfullscreen
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
            />
          </div>

          <!-- Directions -->
          <div class="mt-8 grid md:grid-cols-2 gap-6">
            <div>
              <h4 class="text-lg font-semibold text-gray-900 mb-3">
                Cómo Llegar
              </h4>
              <ul class="space-y-2 text-gray-600">
                <li class="flex items-start">
                  <span class="w-2 h-2 bg-blue-600 rounded-full mt-2 mr-3 shrink-0"></span>
                  <span>Metro: Estación Santa Rosa (Línea 1) - 10 min en transporte público - SJL</span>
                </li>
                <li class="flex items-start">
                  <span class="w-2 h-2 bg-blue-600 rounded-full mt-2 mr-3 shrink-0"></span>
                  <span>Corredores Complementarios: Ruta 405 y 406</span>
                </li>
                <li class="flex items-start">
                  <span class="w-2 h-2 bg-blue-600 rounded-full mt-2 mr-3 shrink-0"></span>
                  <span>Estacionamiento gratuito disponible en las inmediaciones</span>
                </li>
              </ul>
            </div>

            <div>
              <h4 class="text-lg font-semibold text-gray-900 mb-3">
                Puntos de Referencia
              </h4>
              <ul class="space-y-2 text-gray-600">
                <li class="flex items-start">
                  <span class="w-2 h-2 bg-blue-600 rounded-full mt-2 mr-3 shrink-0"></span>
                  <span>Cerca del Hospital de San Juan de Lurigancho</span>
                </li>
                <li class="flex items-start">
                  <span class="w-2 h-2 bg-blue-600 rounded-full mt-2 mr-3 shrink-0"></span>
                  <span>A 3 cuadras de la Estación Santa Rosa</span>
                </li>
                <li class="flex items-start">
                  <span class="w-2 h-2 bg-blue-600 rounded-full mt-2 mr-3 shrink-0"></span>
                  <span>Cerca del Mercado Modelo de SJL y centros comerciales</span>
                </li>
              </ul>
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
  MapPinIcon,
  PhoneIcon,
  MailIcon,
  ClockIcon,
  MessageSquareIcon
} from 'lucide-vue-next'

gsap.registerPlugin(ScrollTrigger)

onMounted(() => {
  // Set initial state for contact info cards
  gsap.set('.contact-info-card', {
    y: 30,
    opacity: 0
  })

  // Set initial state for business hours section
  gsap.set('.business-hours', {
    y: 30,
    opacity: 0
  })

  // Set initial state for map section
  gsap.set('.map-section', {
    y: 30,
    opacity: 0
  })

  // Animate contact info cards with stagger
  gsap.to('.contact-info-card', {
    duration: 0.8,
    y: 0,
    opacity: 1,
    stagger: 0.2,
    ease: 'power3.out',
    delay: 0.2
  })

  // Animate business hours section
  gsap.to('.business-hours', {
    duration: 0.8,
    y: 0,
    opacity: 1,
    ease: 'power3.out',
    delay: 0.6
  })

  // Animate map section
  gsap.to('.map-section', {
    duration: 0.8,
    y: 0,
    opacity: 1,
    ease: 'power3.out',
    delay: 0.8
  })
})
</script>

<style scoped>
/* Component styles */
</style>