<template>
  <section class="py-20 bg-linear-to-br from-primary-600 via-primary-700 to-secondary-600 relative overflow-hidden">
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-10">
      <div class="absolute top-0 left-0 w-full h-full bg-linear-to-br from-white/20 to-transparent" />
    </div>

    <!-- Floating Elements -->
    <div class="absolute top-20 left-10 w-20 h-20 bg-white/10 rounded-full blur-xl cta-float-1" />
    <div class="absolute bottom-20 right-10 w-32 h-32 bg-accent-400/20 rounded-full blur-2xl cta-float-2" />
    <div class="absolute top-1/2 left-1/4 w-16 h-16 bg-secondary-400/15 rounded-full blur-lg cta-float-3" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
      <div class="text-center cta-content">
        <!-- Main CTA Content -->
        <div class="mb-12">
          <h2 class="text-4xl md:text-6xl font-bold text-black bg-white/90 px-6 py-4 rounded-2xl inline-block cta-title">
            Tu Salud es Nuestra Prioridad
          </h2>
          <p class="text-xl md:text-2xl text-gray-800 bg-white/80 px-4 py-2 rounded-xl inline-block mt-4 cta-subtitle">
            Únete a miles de familias que ya confían en Boticas San Juan.
            Regístrate ahora y obtén beneficios exclusivos en tu primera compra.
          </p>
        </div>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row gap-6 justify-center mb-16 cta-buttons">
          <router-link
            to="/register"
            class="btn-cta-primary bg-yellow-400 text-black px-8 py-4 text-lg font-semibold rounded-2xl shadow-2xl hover:shadow-yellow-500/25 transition-all duration-300 transform hover:scale-105"
          >
            <UserPlusIcon class="w-6 h-6 inline mr-2" />
            Registrarme Ahora
          </router-link>
          <router-link
            to="/products"
            class="btn-cta-secondary bg-white text-gray-900 px-8 py-4 text-lg font-semibold rounded-2xl border-2 border-gray-400 hover:border-gray-600 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl"
          >
            <ShoppingCartIcon class="w-6 h-6 inline mr-2" />
            Ver Productos
          </router-link>
        </div>

        <!-- Benefits Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto cta-benefits">
          <!-- Benefit 1 -->
          <div class="benefit-card bg-white rounded-2xl p-6 border border-gray-200 shadow-lg">
            <div class="w-12 h-12 bg-yellow-400 rounded-xl flex items-center justify-center mb-4 mx-auto">
              <GiftIcon class="w-6 h-6 text-black" />
            </div>
            <h3 class="text-xl font-bold text-black mb-2">
              20% de Descuento
            </h3>
            <p class="text-gray-600">
              En tu primera compra al registrarte en nuestro programa Agora
            </p>
          </div>

          <!-- Benefit 2 -->
          <div class="benefit-card bg-white rounded-2xl p-6 border border-gray-200 shadow-lg">
            <div class="w-12 h-12 bg-green-400 rounded-xl flex items-center justify-center mb-4 mx-auto">
              <TruckIcon class="w-6 h-6 text-black" />
            </div>
            <h3 class="text-xl font-bold text-black mb-2">
              Delivery Gratis
            </h3>
            <p class="text-gray-600">
              Envío gratuito en compras mayores a S/ 50 en toda Lima Metropolitana
            </p>
          </div>

          <!-- Benefit 3 -->
          <div class="benefit-card bg-white rounded-2xl p-6 border border-gray-200 shadow-lg">
            <div class="w-12 h-12 bg-blue-400 rounded-xl flex items-center justify-center mb-4 mx-auto">
              <PhoneIcon class="w-6 h-6 text-black" />
            </div>
            <h3 class="text-xl font-bold text-black mb-2">
              Asesoría Gratuita
            </h3>
            <p class="text-gray-600">
              Consultas farmacéuticas gratuitas con nuestros especialistas certificados
            </p>
          </div>
        </div>

        <!-- Trust Message -->
        <div class="mt-16 text-center cta-trust">
          <div class="flex flex-col sm:flex-row items-center justify-center gap-4 bg-white/90 rounded-lg p-4 shadow-md">
            <div class="flex items-center gap-2">
              <ShieldCheckIcon class="w-5 h-5 text-green-600" />
              <span class="text-sm text-black font-medium">Compra 100% Segura</span>
            </div>
            <div class="hidden sm:block w-px h-4 bg-gray-300"></div>
            <div class="flex items-center gap-2">
              <ClockIcon class="w-5 h-5 text-blue-600" />
              <span class="text-sm text-black font-medium">Atención 24/7</span>
            </div>
            <div class="hidden sm:block w-px h-4 bg-gray-300"></div>
            <div class="flex items-center gap-2">
              <AwardIcon class="w-5 h-5 text-purple-600" />
              <span class="text-sm text-black font-medium">Certificado DIGEMID</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted, nextTick } from 'vue'
import { gsap } from 'gsap'
import {
  UserPlusIcon,
  ShoppingCartIcon,
  GiftIcon,
  TruckIcon,
  PhoneIcon,
  ShieldCheckIcon,
  ClockIcon,
  AwardIcon
} from 'lucide-vue-next'

onMounted(async () => {
  await nextTick()

  // Simplified delay and animations to prevent loading issues
  setTimeout(() => {
    try {
      const tl = gsap.timeline()

      // Set initial states explicitly
      gsap.set('.cta-title', { y: 60, opacity: 0 })
      gsap.set('.cta-subtitle', { y: 40, opacity: 0 })
      gsap.set('.cta-buttons', { y: 50, opacity: 0, scale: 0.9 })
      gsap.set('.benefit-card', { y: 80, opacity: 0 })
      gsap.set('.cta-trust', { y: 30, opacity: 0 })

      // Animate main title
      tl.to('.cta-title', {
        y: 0,
        opacity: 1,
        duration: 1,
        ease: 'power3.out'
      })

      // Animate subtitle
      .to('.cta-subtitle', {
        y: 0,
        opacity: 1,
        duration: 0.8,
        ease: 'power2.out'
      }, '-=0.7')

      // Animate CTA buttons
      .to('.cta-buttons', {
        y: 0,
        opacity: 1,
        scale: 1,
        duration: 0.8,
        ease: 'back.out(1.7)'
      }, '-=0.5')

      // Animate benefit cards
      .to('.benefit-card', {
        y: 0,
        opacity: 1,
        duration: 0.8,
        stagger: 0.2,
        ease: 'power2.out'
      }, '-=0.3')

      // Animate trust message
      .to('.cta-trust', {
        y: 0,
        opacity: 1,
        duration: 0.6,
        ease: 'power2.out'
      }, '-=0.4')

      // Add simple hover animations for benefit cards
      document.querySelectorAll('.benefit-card').forEach((card) => {
        card.addEventListener('mouseenter', () => {
          gsap.to(card, {
            scale: 1.05,
            y: -5,
            duration: 0.3,
            ease: 'power2.out'
          })
        })

        card.addEventListener('mouseleave', () => {
          gsap.to(card, {
            scale: 1,
            y: 0,
            duration: 0.3,
            ease: 'power2.out'
          })
        })
      })
    } catch (error) {
      console.warn('GSAP animation error:', error)
      // Fallback: show elements without animation
      gsap.set(['.cta-title', '.cta-subtitle', '.cta-buttons', '.benefit-card', '.cta-trust'], {
        y: 0,
        opacity: 1,
        scale: 1
      })
    }
  }, 200) // Reduced delay
})
</script>

<style scoped>
.btn-cta-primary {
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  color: #1f2937;
  box-shadow: 0 20px 40px rgba(251, 191, 36, 0.3);
}

.btn-cta-secondary {
  background: white;
  color: #111827;
  border: 2px solid #9ca3af;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.btn-cta-secondary:hover {
  border-color: #6b7280;
  box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
}
</style>