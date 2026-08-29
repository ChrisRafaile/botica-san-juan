<template>
  <section class="py-20 bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Section Header -->
      <div class="text-center mb-16">
        <div class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-800 rounded-full text-sm font-medium mb-6">
          <SendIcon class="w-4 h-4 mr-2" />
          Envíanos un mensaje
        </div>

        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6">
          ¿Cómo podemos
          <span class="text-blue-600">ayudarte</span>?
        </h2>

        <p class="text-xl text-gray-600 max-w-2xl mx-auto">
          Completa el formulario y nuestro equipo de profesionales te contactará lo antes posible.
        </p>
      </div>

      <!-- Contact Form -->
      <div class="bg-white rounded-3xl shadow-xl p-8 md:p-12">
        <form
          class="space-y-8"
          @submit.prevent="handleSubmit"
        >
          <!-- Name and Email Row -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="form-group">
              <label
                for="name"
                class="block text-sm font-semibold text-gray-700 mb-3"
              >
                Nombre completo *
              </label>
              <div class="relative">
                <UserIcon class="absolute left-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  id="name"
                  v-model="form.name"
                  type="text"
                  required
                  class="w-full pl-12 pr-4 py-4 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-300 text-gray-900 placeholder-gray-500"
                  placeholder="Ingresa tu nombre completo"
                />
              </div>
            </div>

            <div class="form-group">
              <label
                for="email"
                class="block text-sm font-semibold text-gray-700 mb-3"
              >
                Correo electrónico *
              </label>
              <div class="relative">
                <MailIcon class="absolute left-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  id="email"
                  v-model="form.email"
                  type="email"
                  required
                  class="w-full pl-12 pr-4 py-4 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-300 text-gray-900 placeholder-gray-500"
                  placeholder="Ingresa tu correo electrónico"
                />
              </div>
            </div>
          </div>

          <!-- Phone and Reason Row -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="form-group">
              <label
                for="phone"
                class="block text-sm font-semibold text-gray-700 mb-3"
              >
                Teléfono *
              </label>
              <div class="relative">
                <PhoneIcon class="absolute left-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  id="phone"
                  v-model="form.phone"
                  type="tel"
                  required
                  class="w-full pl-12 pr-4 py-4 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-300 text-gray-900 placeholder-gray-500"
                  placeholder="999-999-999"
                />
              </div>
            </div>

            <div class="form-group">
              <label
                for="reason"
                class="block text-sm font-semibold text-gray-700 mb-3"
              >
                Motivo de consulta *
              </label>
              <div class="relative">
                <MessageSquareIcon class="absolute left-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 z-10" />
                <select
                  id="reason"
                  v-model="form.reason"
                  required
                  class="w-full pl-12 pr-4 py-4 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-300 text-gray-900 appearance-none bg-white"
                >
                  <option value="">
                    Selecciona un motivo
                  </option>
                  <option value="consulta">
                    Consulta médica
                  </option>
                  <option value="pedido">
                    Realizar pedido
                  </option>
                  <option value="informacion">
                    Información general
                  </option>
                  <option value="queja">
                    Queja o sugerencia
                  </option>
                  <option value="otro">
                    Otro
                  </option>
                </select>
                <ChevronDownIcon class="absolute right-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" />
              </div>
            </div>
          </div>

          <!-- Message Field -->
          <div class="form-group">
            <label
              for="message"
              class="block text-sm font-semibold text-gray-700 mb-3"
            >
              Mensaje *
            </label>
            <div class="relative">
              <MessageSquareIcon class="absolute left-4 top-4 w-5 h-5 text-gray-400" />
              <textarea
                id="message"
                v-model="form.message"
                required
                rows="6"
                class="w-full pl-12 pr-4 py-4 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-300 text-gray-900 placeholder-gray-500 resize-vertical"
                placeholder="Describe tu consulta o mensaje..."
              />
            </div>
          </div>

          <!-- Submit Button -->
          <div class="text-center pt-4">
            <button
              type="submit"
              :disabled="isSubmitting"
              class="inline-flex items-center px-12 py-4 bg-linear-to-r from-blue-600 to-blue-700 text-white font-bold text-lg rounded-xl hover:from-blue-700 hover:to-blue-800 focus:ring-4 focus:ring-blue-500/20 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
            >
              <SendIcon
                v-if="!isSubmitting"
                class="w-6 h-6 mr-3"
              />
              <LoaderIcon
                v-else
                class="w-6 h-6 mr-3 animate-spin"
              />
              {{ isSubmitting ? 'Enviando...' : 'Enviar mensaje' }}
            </button>
          </div>
        </form>

        <!-- Success Message -->
        <div
          v-if="submitSuccess"
          class="mt-8 p-6 bg-green-50 border-2 border-green-200 rounded-xl animate-fade-in"
        >
          <div class="flex items-center">
            <CheckCircleIcon class="w-6 h-6 text-green-600 mr-3 shrink-0" />

            <div>
              <h3 class="font-semibold text-green-800 mb-1">
                ¡Mensaje enviado exitosamente!
              </h3>

              <p class="text-green-700">
                Te responderemos lo antes posible. Gracias por contactarnos.
              </p>
            </div>
          </div>
        </div>

        <!-- Error Message -->
        <div
          v-if="submitError"
          class="mt-8 p-6 bg-red-50 border-2 border-red-200 rounded-xl animate-fade-in"
        >
          <div class="flex items-center">
            <AlertCircleIcon class="w-6 h-6 text-red-600 mr-3 shrink-0" />

            <div>
              <h3 class="font-semibold text-red-800 mb-1">
                Error al enviar el mensaje
              </h3>

              <p class="text-red-700">
                {{ submitError }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import { useContactStore } from '@/stores/contacto'
import {
  SendIcon,
  LoaderIcon,
  CheckCircleIcon,
  AlertCircleIcon,
  UserIcon,
  MailIcon,
  PhoneIcon,
  MessageSquareIcon,
  ChevronDownIcon
} from 'lucide-vue-next'

gsap.registerPlugin(ScrollTrigger)

interface ContactForm {
  name: string
  email: string
  phone: string
  reason: string
  message: string
}

const contactStore = useContactStore()

const form = ref<ContactForm>({
  name: '',
  email: '',
  phone: '',
  reason: '',
  message: ''
})

const isSubmitting = ref(false)
const submitSuccess = ref(false)
const submitError = ref('')

const handleSubmit = async () => {
  isSubmitting.value = true
  submitError.value = ''
  submitSuccess.value = false

  try {
    await contactStore.sendContactForm({
      nombre: form.value.name,
      email: form.value.email,
      telefono: form.value.phone,
      motivo: form.value.reason,
      mensaje: form.value.message
    })

    // Reset form on success
    form.value = {
      name: '',
      email: '',
      phone: '',
      reason: '',
      message: ''
    }

    submitSuccess.value = true

    // Hide success message after 5 seconds
    setTimeout(() => {
      submitSuccess.value = false
    }, 5000)

  } catch (error) {
    submitError.value = error instanceof Error ? error.message : 'Error al enviar el mensaje. Por favor, inténtalo de nuevo.'
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  // Animate form elements with stagger
  gsap.from('.form-group', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    stagger: 0.1,
    ease: 'power3.out',
    scrollTrigger: {
      trigger: '.form-group',
      start: 'top 80%'
    }
  })

  // Animate submit button
  gsap.from('.submit-btn', {
    duration: 0.8,
    y: 20,
    opacity: 0,
    delay: 0.5,
    ease: 'power3.out',
    scrollTrigger: {
      trigger: '.submit-btn',
      start: 'top 80%'
    }
  })
})
</script>

<style scoped>
@keyframes fade-in {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in {
  animation: fade-in 0.5s ease-out;
}

/* Custom select arrow styling */
select {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
  background-position: right 0.75rem center;
  background-repeat: no-repeat;
  background-size: 1rem;
  padding-right: 2.5rem;
}
</style>