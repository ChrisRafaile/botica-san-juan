<template>
  <section class="py-24 bg-white">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <MapPinIcon class="w-4 h-4" />
          Selecciona tu Zona
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
          Encuentra tu Distrito
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          Selecciona el distrito donde resides para conocer los tiempos de entrega, costos de envío y áreas específicas de cobertura
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <!-- District Selector -->
        <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-all duration-300">
          <h3 class="text-2xl font-bold text-gray-900 mb-6">
            Distritos Disponibles
          </h3>

          <div class="grid grid-cols-1 gap-4">
            <button
              v-for="district in districts"
              :key="district.id"
              :class="[
                'p-4 rounded-lg border-2 transition-all text-left hover:shadow-md',
                selectedDistrict?.id === district.id
                  ? 'border-blue-500 bg-blue-50 text-blue-700 shadow-md'
                  : 'border-gray-200 hover:border-blue-300 hover:bg-gray-50'
              ]"
              @click="selectDistrict(district)"
            >
              <h4 class="font-semibold text-gray-900 mb-1">
                {{ district.name }}
              </h4>
              <p class="text-sm text-gray-600 mb-2">
                {{ district.description }}
              </p>
              <div class="flex items-center gap-4 text-xs text-gray-500">
                <span>🚚 {{ district.deliveryTime }}</span>
                <span>💰 {{ district.shippingCost }}</span>
              </div>
            </button>
          </div>
        </div>

        <!-- Map Section -->
        <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-all duration-300">
          <h3 class="text-2xl font-bold text-gray-900 mb-6">
            Mapa de San Juan de Lurigancho
          </h3>

          <div class="relative">
            <!-- Leaflet Map -->
            <div class="rounded-lg h-96 overflow-hidden">
              <div
                id="sjl-map"
                class="w-full h-full"
              ></div>
            </div>
          </div>

          <!-- Address Info -->
          <div class="mt-6 p-4 bg-blue-50 rounded-lg">
            <h4 class="font-semibold text-blue-800 mb-2">
              📍 Dirección de Nuestra Botica
            </h4>
            <p class="text-blue-700">
              Av. Sta. Rosa de Lima 103<br />
              San Juan de Lurigancho 15423, Perú
            </p>
          </div>

          <!-- Legend -->
          <div class="mt-6">
            <h4 class="font-semibold text-gray-900 mb-3">
              Leyenda del Mapa
            </h4>
            <div class="flex flex-wrap gap-4">
              <div class="flex items-center">
                <div class="w-4 h-4 bg-green-500 rounded-full mr-2" />
                <span class="text-sm text-gray-700">Zona de cobertura SJL</span>
              </div>
              <div class="flex items-center">
                <div class="w-4 h-4 bg-red-500 rounded-full mr-2" />
                <span class="text-sm text-gray-700">Ubicación de la botica</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Coverage Info -->
      <div
        v-if="selectedDistrict"
        class="mt-12 bg-gray-50 rounded-2xl p-8"
      >
        <h3 class="text-2xl font-bold text-gray-900 mb-6 text-center">
          Información de Cobertura - {{ selectedDistrict.name }}
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-white p-6 rounded-lg shadow-sm">
            <div class="flex items-center mb-3">
              <ClockIcon class="w-5 h-5 text-blue-600 mr-3" />
              <span class="font-semibold text-gray-900">Tiempo de entrega</span>
            </div>
            <p class="text-gray-700">
              {{ selectedDistrict.deliveryTime }}
            </p>
          </div>

          <div class="bg-white p-6 rounded-lg shadow-sm">
            <div class="flex items-center mb-3">
              <DollarSignIcon class="w-5 h-5 text-green-600 mr-3" />
              <span class="font-semibold text-gray-900">Costo de envío</span>
            </div>
            <p class="text-gray-700">
              {{ selectedDistrict.shippingCost }}
            </p>
          </div>

          <div class="bg-white p-6 rounded-lg shadow-sm">
            <div class="flex items-center mb-3">
              <CalendarIcon class="w-5 h-5 text-blue-600 mr-3" />
              <span class="font-semibold text-gray-900">Horario</span>
            </div>
            <p class="text-gray-700">
              {{ selectedDistrict.schedule }}
            </p>
          </div>

          <div class="bg-white p-6 rounded-lg shadow-sm">
            <div class="flex items-center mb-3">
              <ShoppingCartIcon class="w-5 h-5 text-purple-600 mr-3" />
              <span class="font-semibold text-gray-900">Mínimo de compra</span>
            </div>
            <p class="text-gray-700">
              {{ selectedDistrict.minOrder }}
            </p>
          </div>
        </div>

        <div class="mt-8">
          <h4 class="font-semibold text-gray-900 mb-4 text-center">
            Áreas específicas de cobertura:
          </h4>
          <div class="flex flex-wrap gap-3 justify-center">
            <span
              v-for="area in selectedDistrict.areas"
              :key="area"
              class="px-4 py-2 bg-blue-100 text-blue-700 rounded-full text-sm font-medium"
            >
              {{ area }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import {
  MapPinIcon,
  ClockIcon,
  DollarSignIcon,
  CalendarIcon,
  ShoppingCartIcon
} from 'lucide-vue-next'
import { onMounted } from 'vue'
import { gsap } from 'gsap'
import L from 'leaflet'

// Import Leaflet CSS
import 'leaflet/dist/leaflet.css'

interface District {
  id: number
  name: string
  description: string
  deliveryTime: string
  shippingCost: string
  schedule: string
  minOrder: string
  areas: string[]
  mapImage: string
}

const districts = ref<District[]>([
  {
    id: 1,
    name: 'San Juan de Lurigancho',
    description: 'Nuestra ubicación principal',
    deliveryTime: '20-35 minutos',
    shippingCost: 'S/ 5.00',
    schedule: '7:00 AM - 11:00 PM',
    minOrder: 'S/ 15.00',
    areas: ['Centro', 'Los Pinos', 'Santa Rosa', 'Manchay'],
    mapImage: '/images/SJL.jpg'
  },
  {
    id: 2,
    name: 'Surco',
    description: 'Zona residencial moderna',
    deliveryTime: '35-50 minutos',
    shippingCost: 'S/ 8.00',
    schedule: '7:00 AM - 11:00 PM',
    minOrder: 'S/ 20.00',
    areas: ['Chacarilla', 'La Molina', 'Santiago de Surco'],
    mapImage: '/images/SURCO.jpg'
  },
  {
    id: 3,
    name: 'San Borja',
    description: 'Zona comercial y residencial',
    deliveryTime: '30-45 minutos',
    shippingCost: 'S/ 7.00',
    schedule: '7:00 AM - 11:00 PM',
    minOrder: 'S/ 20.00',
    areas: ['Centro', 'Parque San Borja', 'Jockey Plaza'],
    mapImage: '/images/SANBORJA.jpg'
  },
  {
    id: 4,
    name: 'Cercado de Lima',
    description: 'Centro histórico de Lima',
    deliveryTime: '25-40 minutos',
    shippingCost: 'S/ 6.00',
    schedule: '7:00 AM - 11:00 PM',
    minOrder: 'S/ 15.00',
    areas: ['Centro Histórico', 'Plaza Mayor', 'Barrios Coloniales'],
    mapImage: '/images/CERCADO.jpg'
  },
  {
    id: 5,
    name: 'Callao',
    description: 'Puerto principal del Perú',
    deliveryTime: '40-60 minutos',
    shippingCost: 'S/ 10.00',
    schedule: '7:00 AM - 11:00 PM',
    minOrder: 'S/ 25.00',
    areas: ['Centro', 'La Punta', 'Bellavista'],
    mapImage: '/images/CALLAO.jpg'
  }
])

const selectedDistrict = ref<District | null>(null)

const selectDistrict = (district: District) => {
  selectedDistrict.value = district
}

onMounted(() => {
  // Initialize SJL map with exact coordinates from Google Maps
  const sjlMap = L.map('sjl-map').setView([-11.9686252, -76.9943794], 16)

  // Add OpenStreetMap tiles
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(sjlMap)

  // Define SJL polygon (approximate coordinates around the pharmacy)
  const sjlCoordinates: [number, number][] = [
    [-11.965, -76.997] as [number, number],
    [-11.965, -76.992] as [number, number],
    [-11.972, -76.992] as [number, number],
    [-11.972, -76.997] as [number, number],
    [-11.965, -76.997] as [number, number]
  ]

  // Add SJL polygon
  L.polygon(sjlCoordinates, {
    color: '#10B981', // green-500
    fillColor: '#10B981',
    fillOpacity: 0.3,
    weight: 2
  }).addTo(sjlMap).bindPopup('<b>San Juan de Lurigancho</b><br>Zona de cobertura de delivery')

  // Add marker for pharmacy location with exact coordinates
  L.marker([-11.9686252, -76.9943794]).addTo(sjlMap)
    .bindPopup('<b>Boticas San Juan</b><br>Av. Sta. Rosa de Lima 103<br>San Juan de Lurigancho 15423, Perú')
    .openPopup()

  // Animate district buttons with stagger
  gsap.from('.district-button', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    stagger: 0.1,
    ease: 'power3.out'
  })

  // Animate coverage points
  gsap.from('.coverage-point', {
    duration: 0.6,
    scale: 0,
    opacity: 0,
    stagger: 0.2,
    ease: 'back.out(1.7)',
    delay: 0.5
  })
})
</script>

<style scoped>
/* Coverage selector styles */
</style>