<template>
  <div class="min-h-screen bg-linear-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="max-w-md w-full">
      <!-- Forgot Password Card -->
      <div
        ref="forgotCard"
        class="bg-white rounded-3xl shadow-xl border border-gray-200 p-8"
      >
        <!-- Back to Login -->
        <div class="mb-6">
          <router-link
            to="/login"
            class="inline-flex items-center text-gray-600 hover:text-gray-800 transition-colors"
          >
            <svg
              class="w-5 h-5 mr-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 19l-7-7 7-7"
              />
            </svg>
            Volver al inicio de sesión
          </router-link>
        </div>

        <!-- Icon -->
        <div class="text-center mb-6">
          <div class="w-16 h-16 mx-auto bg-blue-100 rounded-full flex items-center justify-center">
            <svg
              class="w-8 h-8 text-blue-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
              />
            </svg>
          </div>
        </div>

        <!-- Title -->
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">
          ¿Olvidaste tu contraseña?
        </h1>

        <!-- Description -->
        <p class="text-gray-600 text-center mb-8">
          Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
        </p>

        <!-- Form -->
        <form
          class="space-y-6"
          @submit.prevent="handleForgotPassword"
        >
          <!-- Email Input -->
          <div>
            <label
              for="email"
              class="block text-sm font-medium text-gray-700 mb-2"
            >
              Correo electrónico
            </label>
            <div class="relative">
              <input
                id="email"
                v-model="email"
                type="email"
                required
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                placeholder="tu@email.com"
              />
              <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                <svg
                  class="w-5 h-5 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"
                  />
                </svg>
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="isLoading"
            class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium py-3 px-4 rounded-xl transition-colors duration-200 flex items-center justify-center"
          >
            <span
              v-if="isLoading"
              class="mr-2"
            >
              <svg
                class="animate-spin h-4 w-4 text-white"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle
                  class="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  stroke-width="4"
                />
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
              </svg>
            </span>
            {{ isLoading ? 'Enviando...' : 'Enviar enlace de recuperación' }}
          </button>
        </form>

        <!-- Success Message -->
        <div
          v-if="successMessage"
          class="mt-6 p-4 bg-green-50 border border-green-200 rounded-xl"
        >
          <div class="flex items-center">
            <svg
              class="w-5 h-5 text-green-600 mr-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M5 13l4 4L19 7"
              />
            </svg>
            <p class="text-green-800 text-sm">
              {{ successMessage }}
            </p>
          </div>
        </div>

        <!-- Error Message -->
        <div
          v-if="errorMessage"
          class="mt-6 p-4 bg-red-50 border border-red-200 rounded-xl"
        >
          <div class="flex items-center">
            <svg
              class="w-5 h-5 text-red-600 mr-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <p class="text-red-800 text-sm">
              {{ errorMessage }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { gsap } from 'gsap'

// Reactive data
const email = ref('')
const isLoading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

// Refs
const forgotCard = ref()

// Handle forgot password
const handleForgotPassword = async () => {
  if (!email.value) return

  isLoading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    // TODO: Implement actual forgot password API call
    // For now, just show success message
    await new Promise(resolve => setTimeout(resolve, 2000)) // Simulate API call

    successMessage.value = 'Se ha enviado un enlace de recuperación a tu correo electrónico.'
    email.value = ''
  } catch {
    errorMessage.value = 'Ha ocurrido un error. Por favor, inténtalo de nuevo.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  // Animate card entrance
  if (forgotCard.value) {
    gsap.fromTo(forgotCard.value,
      { opacity: 0, y: 30 },
      { opacity: 1, y: 0, duration: 0.6, ease: "power2.out" }
    )
  }
})
</script>

<style scoped>
/* Additional styles if needed */
</style>