<template>
  <section class="py-24 bg-blue-600">
    <div class="container mx-auto px-6 text-center">
      <div class="max-w-4xl mx-auto">
        <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm text-white px-4 py-2 rounded-full text-sm font-medium mb-6">
          <MessageCircleIcon class="w-4 h-4" />
          ¿Necesitas Ayuda?
        </div>

        <h2 class="text-4xl md:text-5xl font-bold text-white mb-6">
          Estamos Aquí para Ti
        </h2>

        <p class="text-xl text-blue-100 mb-12 max-w-3xl mx-auto">
          Nuestro equipo de atención al cliente está listo para resolver cualquier duda sobre cobertura de delivery, tiempos de entrega o servicios adicionales
        </p>

        <!-- Contact Options -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
          <!-- WhatsApp -->
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 hover:bg-white/20 transition-all duration-300 contact-card">
            <div class="bg-green-500 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6">
              <MessageCircleIcon class="w-8 h-8 text-white" />
            </div>
            <h3 class="text-xl font-bold text-white mb-4">
              WhatsApp
            </h3>
            <p class="text-blue-100 mb-6">
              Atención inmediata y personalizada a través de WhatsApp Business
            </p>
            <a
              href="https://wa.me/016772892"
              target="_blank"
              class="inline-flex items-center justify-center w-full bg-green-600 text-white py-3 px-6 rounded-lg hover:bg-green-700 transition-colors font-medium"
            >
              <MessageCircleIcon class="w-4 h-4 mr-2" />
              Contactar por WhatsApp
            </a>
          </div>

          <!-- Phone -->
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 hover:bg-white/20 transition-all duration-300 contact-card">
            <div class="bg-blue-500 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6">
              <PhoneIcon class="w-8 h-8 text-white" />
            </div>
            <h3 class="text-xl font-bold text-white mb-4">
              Teléfono
            </h3>
            <p class="text-blue-100 mb-6">
              Llama directamente a nuestra línea dedicada de atención al cliente
            </p>
            <a
              href="tel:+016772892"
              class="inline-flex items-center justify-center w-full bg-blue-600 text-white py-3 px-6 rounded-lg hover:bg-blue-700 transition-colors font-medium"
            >
              <PhoneIcon class="w-4 h-4 mr-2" />
              Llamar ahora
            </a>
          </div>

          <!-- Email -->
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 hover:bg-white/20 transition-all duration-300 contact-card">
            <div class="bg-purple-500 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6">
              <MailIcon class="w-8 h-8 text-white" />
            </div>
            <h3 class="text-xl font-bold text-white mb-4">
              Correo Electrónico
            </h3>
            <p class="text-blue-100 mb-6">
              Envíanos un mensaje detallado y te responderemos lo antes posible
            </p>
            <a
              href="mailto:Boticassanjuan16@gmail.com"
              class="inline-flex items-center justify-center w-full bg-purple-600 text-white py-3 px-6 rounded-lg hover:bg-purple-700 transition-colors font-medium"
            >
              <MailIcon class="w-4 h-4 mr-2" />
              Enviar Email
            </a>
          </div>
        </div>

        <!-- Additional Info -->
        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8">
          <h3 class="text-2xl font-bold text-white mb-6">
            Información Importante
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-left">
            <div>
              <h4 class="text-lg font-semibold text-white mb-4">
                Horarios de Atención
              </h4>
              <div class="space-y-2 text-blue-100">
                <p><strong>Lunes - Domingo:</strong> 7:00 AM - 11:00 PM</p>
                <p><strong>Servicio continuo de delivery</strong></p>
              </div>
            </div>

            <div>
              <h4 class="text-lg font-semibold text-white mb-4">
                Nuestra Ubicación
              </h4>
              <div class="space-y-2 text-blue-100">
                <p><strong>Dirección:</strong></p>
                <p>Av. Sta. Rosa de Lima 103</p>
                <p>San Juan de Lurigancho 15423, Perú</p>
              </div>
            </div>

            <div>
              <h4 class="text-lg font-semibold text-white mb-4">
                Preguntas Frecuentes
              </h4>
              <div class="space-y-3">
                <div class="flex items-start">
                  <HelpCircleIcon class="w-5 h-5 text-blue-200 mr-3 mt-0.5 shrink-0" />
                  <div class="text-blue-100 text-sm">
                    <strong>¿Cuánto cuesta el delivery?</strong>
                    <br />
                    Depende del distrito: SJL S/ 5.00, Surco S/ 8.00, San Borja S/ 7.00, Cercado S/ 6.00, Callao S/ 10.00
                  </div>
                </div>
                <div class="flex items-start">
                  <HelpCircleIcon class="w-5 h-5 text-blue-200 mr-3 mt-0.5 shrink-0" />
                  <div class="text-blue-100 text-sm">
                    <strong>¿Cuál es el tiempo de entrega?</strong>
                    <br />
                    SJL: 20-35 min, Surco: 35-50 min, San Borja: 30-45 min, Cercado: 25-40 min, Callao: 40-60 min
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Pharmacy Location Map -->
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 mt-8">
            <h3 class="text-2xl font-bold text-white mb-6 text-center">
              Mapa Interactivo de Cobertura
            </h3>
            <div class="aspect-video rounded-xl overflow-hidden relative">
              <div
                id="map"
                class="w-full h-full"
              ></div>
              <!-- Leyenda -->
              <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm rounded-lg p-4 shadow-lg">
                <h4
                  class="text-sm font-semibold text-gray-800 mb-2"
                >
                  Leyenda de Cobertura
                </h4>
                <div class="space-y-2">
                  <div class="flex items-center">
                    <div class="w-4 h-4 bg-green-500 rounded mr-2"></div>
                    <span class="text-sm text-gray-700">San Juan de Lurigancho (SJL)</span>
                  </div>
                  <div class="flex items-center">
                    <div class="w-4 h-4 bg-blue-500 rounded mr-2"></div>
                    <span class="text-sm text-gray-700">Surco</span>
                  </div>
                  <div class="flex items-center">
                    <div class="w-4 h-4 bg-yellow-500 rounded mr-2"></div>
                    <span class="text-sm text-gray-700">San Borja</span>
                  </div>
                  <div class="flex items-center">
                    <div class="w-4 h-4 bg-purple-500 rounded mr-2"></div>
                    <span class="text-sm text-gray-700">Cercado de Lima</span>
                  </div>
                  <div class="flex items-center">
                    <div class="w-4 h-4 bg-red-500 rounded mr-2"></div>
                    <span class="text-sm text-gray-700">Callao</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-4 text-center">
              <p class="text-blue-100 text-sm">
                <strong>Dirección:</strong> Av. Sta. Rosa de Lima 103, San Juan de Lurigancho 15423, Perú
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import {
  MessageCircleIcon,
  PhoneIcon,
  MailIcon,
  HelpCircleIcon
} from 'lucide-vue-next'
import { onMounted } from 'vue'
import { gsap } from 'gsap'
import L from 'leaflet'

// Import Leaflet CSS
import 'leaflet/dist/leaflet.css'

onMounted(() => {
  // Set initial state
  gsap.set('.contact-card', {
    y: 30,
    opacity: 0
  })

  // Animate contact cards with stagger
  gsap.to('.contact-card', {
    duration: 0.8,
    y: 0,
    opacity: 1,
    stagger: 0.2,
    ease: 'power3.out'
  })

  // Initialize interactive map
  const map = L.map('map').setView([-11.9686252, -76.9943794], 13) // Centered on exact pharmacy location

  // Add OpenStreetMap tiles
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map)

  // Define district polygons (approximate coordinates)
  const districts = [
    {
      name: 'San Juan de Lurigancho',
      color: '#10B981', // green-500
      coordinates: [
        [-11.965, -76.997] as [number, number],
        [-11.965, -76.992] as [number, number],
        [-11.972, -76.992] as [number, number],
        [-11.972, -76.997] as [number, number],
        [-11.965, -76.997] as [number, number]
      ]
    },
    {
      name: 'Surco',
      color: '#3B82F6', // blue-500
      coordinates: [
        [-12.1, -77.0] as [number, number],
        [-12.1, -76.9] as [number, number],
        [-12.15, -76.9] as [number, number],
        [-12.15, -77.0] as [number, number],
        [-12.1, -77.0] as [number, number]
      ]
    },
    {
      name: 'San Borja',
      color: '#EAB308', // yellow-500
      coordinates: [
        [-12.08, -77.0] as [number, number],
        [-12.08, -76.95] as [number, number],
        [-12.12, -76.95] as [number, number],
        [-12.12, -77.0] as [number, number],
        [-12.08, -77.0] as [number, number]
      ]
    },
    {
      name: 'Cercado de Lima',
      color: '#A855F7', // purple-500
      coordinates: [
        [-12.05, -77.05] as [number, number],
        [-12.05, -77.0] as [number, number],
        [-12.08, -77.0] as [number, number],
        [-12.08, -77.05] as [number, number],
        [-12.05, -77.05] as [number, number]
      ]
    },
    {
      name: 'Callao',
      color: '#EF4444', // red-500
      coordinates: [
        [-12.0, -77.15] as [number, number],
        [-12.0, -77.1] as [number, number],
        [-12.05, -77.1] as [number, number],
        [-12.05, -77.15] as [number, number],
        [-12.0, -77.15] as [number, number]
      ]
    }
  ]

  // Add polygons to map
  districts.forEach(district => {
    L.polygon(district.coordinates, {
      color: district.color,
      fillColor: district.color,
      fillOpacity: 0.3,
      weight: 2
    }).addTo(map).bindPopup(`<b>${district.name}</b><br>Cobertura de delivery disponible`)
  })

  // Add marker for pharmacy location
  L.marker([-11.9686252, -76.9943794]).addTo(map)
    .bindPopup('<b>Boticas San Juan</b><br>Av. Sta. Rosa de Lima 103<br>San Juan de Lurigancho 15423, Perú')
    .openPopup()
})
</script>

<style scoped>
/* Coverage contact styles */
</style>