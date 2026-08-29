<template>
  <div class="min-h-screen bg-linear-to-br from-indigo-900 via-purple-900 to-pink-900 flex items-center justify-center p-4">
    <div class="max-w-md w-full">
      <!-- Main logout card -->
      <div
        ref="logoutCard"
        class="bg-white/10 backdrop-blur-xl rounded-3xl shadow-2xl border border-white/20 p-8 text-center"
      >
        <!-- Logout icon with animation -->
        <div class="mb-6">
          <div
            ref="iconContainer"
            class="w-20 h-20 mx-auto bg-linear-to-r from-red-500 to-pink-500 rounded-full flex items-center justify-center shadow-lg"
          >
            <svg
              class="w-10 h-10 text-white"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
              />
            </svg>
          </div>
        </div>

        <!-- Title -->
        <h1
          ref="title"
          class="text-3xl font-bold text-white mb-4"
        >
          Cerrando Sesión
        </h1>

        <!-- Personalized message -->
        <p
          ref="message"
          class="text-lg text-white/80 mb-6"
        >
          Gracias por usar nuestros servicios, {{ userName }}.
          <br />
          Tu sesión se cerrará en
          <span class="font-bold text-white">{{ countdown }}</span> segundos.
        </p>

        <!-- Progress bar -->
        <div class="mb-6">
          <div class="w-full bg-white/20 rounded-full h-2">
            <div
              ref="progressBar"
              class="bg-linear-to-r from-blue-400 to-purple-500 h-2 rounded-full transition-all duration-1000 ease-linear"
              :style="{ width: progressWidth }"
            ></div>
          </div>
        </div>

        <!-- Loading animation -->
        <div class="flex justify-center space-x-1 mb-4">
          <div
            v-for="dot in 3"
            :key="dot"
            ref="dots"
            class="w-3 h-3 bg-white rounded-full animate-pulse"
            :style="{ animationDelay: `${dot * 0.2}s` }"
          ></div>
        </div>

        <!-- Footer message -->
        <p class="text-sm text-white/60">
          Redirigiendo al inicio de sesión...
        </p>
      </div>

      <!-- Background particles -->
      <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div
          v-for="particle in particles"
          :key="particle.id"
          class="absolute w-2 h-2 bg-white/20 rounded-full"
          :style="{
            left: particle.x + '%',
            top: particle.y + '%',
            animation: `float-${particle.id} ${particle.duration}s ease-in-out infinite`
          }"
        ></div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { gsap } from 'gsap'
import { useAuthStore } from '../stores/auth'

interface Particle {
  id: number
  x: number
  y: number
  duration: number
}

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

// Refs
const logoutCard = ref()
const iconContainer = ref()
const title = ref()
const message = ref()
const progressBar = ref()
const dots = ref([])

// Reactive data
const countdown = ref(5)
const progressWidth = ref('100%')
const userName = ref('Usuario')
const particles = ref<Particle[]>([])

// Generate background particles
const generateParticles = () => {
  particles.value = []
  for (let i = 0; i < 20; i++) {
    particles.value.push({
      id: i,
      x: Math.random() * 100,
      y: Math.random() * 100,
      duration: 3 + Math.random() * 4
    })
  }
}

// Logout animation sequence
const startLogoutSequence = async () => {
  // Get user name from store or route params
  userName.value = authStore.user?.nombre || route.query.name as string || 'Usuario'

  // Initial animations
  const tl = gsap.timeline()

  // Card entrance
  tl.from(logoutCard.value, {
    scale: 0.8,
    opacity: 0,
    duration: 0.6,
    ease: "back.out(1.7)"
  })

  // Icon rotation
  tl.from(iconContainer.value, {
    rotation: -180,
    scale: 0,
    duration: 0.8,
    ease: "back.out(1.7)"
  }, "-=0.3")

  // Title and message
  tl.from([title.value, message.value], {
    y: 30,
    opacity: 0,
    duration: 0.6,
    stagger: 0.2,
    ease: "power2.out"
  }, "-=0.4")

  // Countdown timer
  const countdownInterval = setInterval(() => {
    countdown.value--
    progressWidth.value = `${(countdown.value / 5) * 100}%`

    // Animate progress bar
    gsap.to(progressBar.value, {
      scaleX: countdown.value / 5,
      duration: 1,
      ease: "power2.out",
      transformOrigin: "left"
    })

    if (countdown.value <= 0) {
      clearInterval(countdownInterval)
      completeLogout()
    }
  }, 1000)

  // Pulsing dots animation
  gsap.to(dots.value, {
    scale: 1.2,
    duration: 0.6,
    stagger: 0.1,
    repeat: -1,
    yoyo: true,
    ease: "power2.inOut"
  })
}

// Complete logout and redirect
const completeLogout = async () => {
  try {
    // Perform actual logout
    await authStore.logout()

    // Exit animation
    const tl = gsap.timeline()

    tl.to(logoutCard.value, {
      scale: 0.9,
      opacity: 0,
      y: -50,
      duration: 0.5,
      ease: "power2.in"
    })

    // Redirect after animation
    setTimeout(() => {
      router.push('/login')
    }, 500)

  } catch (error) {
    console.error('Logout error:', error)
    // Fallback redirect
    router.push('/login')
  }
}

onMounted(() => {
  generateParticles()
  startLogoutSequence()
})

onUnmounted(() => {
  // Cleanup any intervals or animations if needed
})
</script>

<style scoped>
@keyframes float-0 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-20px); } }
@keyframes float-1 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-15px); } }
@keyframes float-2 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-25px); } }
@keyframes float-3 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }
@keyframes float-4 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-30px); } }
@keyframes float-5 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-18px); } }
@keyframes float-6 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-22px); } }
@keyframes float-7 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-12px); } }
@keyframes float-8 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-28px); } }
@keyframes float-9 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-16px); } }
@keyframes float-10 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-24px); } }
@keyframes float-11 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-14px); } }
@keyframes float-12 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-26px); } }
@keyframes float-13 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-8px); } }
@keyframes float-14 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-32px); } }
@keyframes float-15 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-19px); } }
@keyframes float-16 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-21px); } }
@keyframes float-17 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-11px); } }
@keyframes float-18 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-27px); } }
@keyframes float-19 { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-13px); } }
</style>