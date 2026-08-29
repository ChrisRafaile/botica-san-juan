<template>
  <div class="min-h-screen bg-linear-to-br from-blue-50 to-indigo-100">
    <div class="container mx-auto px-4 py-8">
      <div class="max-w-4xl mx-auto">
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-8">
          <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">
              ¡Bienvenido, {{ user?.nombre }}!
            </h1>
            <p class="text-lg text-gray-600">
              Panel de Cliente - Botica San Juan
            </p>
          </div>

          <div class="grid md:grid-cols-2 gap-6 mb-8">
            <div class="bg-linear-to-r from-blue-500 to-blue-600 text-white p-6 rounded-xl">
              <h3 class="text-xl font-semibold mb-2">
                Mis Compras
              </h3>
              <p class="text-blue-100">
                Revisa tu historial de pedidos
              </p>
            </div>

            <div class="bg-linear-to-r from-green-500 to-green-600 text-white p-6 rounded-xl">
              <h3 class="text-xl font-semibold mb-2">
                Carrito de Compras
              </h3>
              <p class="text-green-100">
                Productos en tu carrito
              </p>
            </div>
          </div>

          <div class="bg-gray-50 rounded-xl p-6">
            <h3 class="text-2xl font-bold text-gray-800 mb-4">
              Productos Disponibles
            </h3>
            <div class="grid md:grid-cols-3 gap-4">
              <div class="bg-white p-4 rounded-lg shadow-sm">
                <h4 class="font-semibold text-gray-800">
                  Medicamentos
                </h4>
                <p class="text-gray-600 text-sm">
                  Amplia variedad de medicamentos
                </p>
              </div>
              <div class="bg-white p-4 rounded-lg shadow-sm">
                <h4 class="font-semibold text-gray-800">
                  Cuidado Personal
                </h4>
                <p class="text-gray-600 text-sm">
                  Productos para el cuidado personal
                </p>
              </div>
              <div class="bg-white p-4 rounded-lg shadow-sm">
                <h4 class="font-semibold text-gray-800">
                  Suplementos
                </h4>
                <p class="text-gray-600 text-sm">
                  Vitaminas y suplementos nutricionales
                </p>
              </div>
            </div>
          </div>

          <div class="mt-8 text-center">
            <button
              class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg font-semibold transition-colors"
              @click="logout"
            >
              Cerrar Sesión
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user)

const logout = () => {
  router.push('/logout')
}
</script>