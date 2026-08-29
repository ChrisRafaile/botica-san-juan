<template>
  <div class="login-container">
    <!-- Animated Background -->
    <div class="animated-bg">
      <div
        v-for="i in 50"
        :key="i"
        class="particle"
        :style="{
          left: Math.random() * 100 + '%',
          animationDelay: Math.random() * 20 + 's',
          animationDuration: (10 + Math.random() * 10) + 's'
        }"
      ></div>
    </div>

    <!-- Success Overlay -->
    <div
      v-if="showSuccess"
      class="success-overlay"
    >
      <div class="success-content">
        <div class="success-icon">
          <CheckCircle class="w-20 h-20" />
        </div>
        <h2 class="success-title">
          ¡Bienvenido de vuelta!
        </h2>
        <p class="success-message">
          Iniciando sesión...
        </p>
        <div class="success-progress">
          <div
            class="progress-bar"
            :style="{ width: progress + '%' }"
          ></div>
        </div>
        <p class="redirect-text">
          Redirigiendo al dashboard...
        </p>
      </div>
    </div>

    <!-- Home Link -->
    <router-link
      to="/"
      class="home-link"
    >
      <Home class="home-icon" />
      <span class="home-text">Inicio</span>
    </router-link>

    <!-- Main Card -->
    <div class="login-card">
      <div class="card-content">
        <!-- Header Section -->
        <div class="header-section">
          <div class="logo-section">
            <div
              class="logo-3d"
              @mouseenter="handleLogoHover"
              @mouseleave="handleLogoLeave"
            >
              <div class="logo-front">
                <Pill class="w-16 h-16 text-blue-600" />
              </div>
              <div class="logo-shadow"></div>
            </div>
          </div>
          <h1 class="brand-title">
            BOTICA SAN JUAN
          </h1>
          <p class="brand-subtitle">
            Tu salud, nuestra prioridad
          </p>
        </div>

        <!-- Welcome Section -->
        <div class="welcome-section">
          <h2 class="welcome-title">
            Bienvenido de vuelta
          </h2>
          <p class="welcome-text">
            Ingresa tus credenciales para acceder a tu cuenta
          </p>
        </div>

        <!-- Login Form -->
        <form
          class="login-form"
          @submit.prevent="handleLogin"
        >
          <!-- Personal Info Section -->
          <div class="form-section">
            <div class="section-header">
              <User class="w-6 h-6 text-blue-600" />
              <h3 class="section-title">
                Información Personal
              </h3>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label
                  for="dni"
                  class="form-label"
                >
                  Número de DNI
                </label>
                <div class="input-wrapper">
                  <CreditCard class="input-icon" />
                  <input
                    id="dni"
                    v-model="form.dni"
                    type="text"
                    maxlength="8"
                    autocomplete="username"
                    class="form-input"
                    placeholder="Ingresa tu DNI (8 dígitos)"
                    :class="{ 'border-red-500': errors.dni }"
                    @input="validateField('dni')"
                    @blur="validateField('dni')"
                  />
                </div>
                <p
                  v-if="errors.dni"
                  class="error-text"
                >
                  {{ errors.dni }}
                </p>
              </div>
            </div>
          </div>

          <!-- Security Section -->
          <div class="form-section">
            <div class="section-header">
              <Shield class="w-6 h-6 text-blue-600" />
              <h3 class="section-title">
                Seguridad
              </h3>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label
                  for="password"
                  class="form-label"
                >
                  Contraseña
                </label>
                <div class="input-wrapper">
                  <Lock class="input-icon" />
                  <input
                    id="password"
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="current-password"
                    class="form-input pr-12"
                    placeholder="Ingresa tu contraseña"
                    :class="{ 'border-red-500': errors.password }"
                    @input="validateField('password')"
                    @blur="validateField('password')"
                  />
                  <button
                    type="button"
                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    @click="showPassword = !showPassword"
                  >
                    <Eye
                      v-if="!showPassword"
                      class="w-5 h-5"
                    />
                    <EyeOff
                      v-else
                      class="w-5 h-5"
                    />
                  </button>
                </div>
                <p
                  v-if="errors.password"
                  class="error-text"
                >
                  {{ errors.password }}
                </p>
              </div>
            </div>
          </div>

          <!-- Remember Me & Forgot Password -->
          <div class="options-section">
            <div class="options-container">
              <div class="remember-me">
                <label class="checkbox-wrapper">
                  <input
                    id="remember"
                    v-model="form.remember"
                    type="checkbox"
                    class="hidden-checkbox"
                  />
                  <span class="custom-checkbox">
                    <Check class="check-icon" />
                  </span>
                  <span class="checkbox-label">Recordarme</span>
                </label>
              </div>
              <div class="forgot-password">
                <router-link
                  to="/forgot-password"
                  class="forgot-link"
                >
                  <HelpCircle class="forgot-icon" />
                  ¿Olvidaste tu contraseña?
                </router-link>
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="loading || !isFormValid"
            class="submit-btn"
          >
            <div class="btn-content">
              <Loader2
                v-if="loading"
                class="w-5 h-5 mr-2 animate-spin"
              />
              <LogIn
                v-else
                class="w-5 h-5 mr-2"
              />
              {{ loading ? 'Iniciando sesión...' : 'INICIAR SESIÓN' }}
            </div>
            <div class="btn-glow"></div>
          </button>
        </form>

        <!-- Social Login Buttons -->
        <div class="social-login-section">
          <div class="divider">
            <span class="divider-text">o continúa con</span>
          </div>

          <div class="social-buttons">
            <button
              type="button"
              class="google-btn"
              @click="handleGoogleLogin"
            >
              <svg
                class="google-icon"
                viewBox="0 0 24 24"
              >
                <path
                  fill="#4285F4"
                  d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                />
                <path
                  fill="#34A853"
                  d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                />
                <path
                  fill="#FBBC05"
                  d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                />
                <path
                  fill="#EA4335"
                  d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                />
              </svg>
              <span class="google-text">Continuar con Google</span>
            </button>
          </div>
        </div>

        <!-- Register Link -->
        <div class="register-link">
          <p>
            ¿No tienes cuenta?
            <router-link
              to="/register"
              class="link"
            >
              Regístrate aquí
            </router-link>
          </p>
        </div>

        <!-- Error Message -->
        <div
          v-if="error"
          class="error-message"
        >
          <AlertCircle class="w-5 h-5 mr-2 shrink-0" />
          <span>{{ error }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { gsap } from 'gsap'
import { useAuthStore } from '../stores/auth'
import {
  User,
  CreditCard,
  Lock,
  Shield,
  Eye,
  EyeOff,
  LogIn,
  Loader2,
  CheckCircle,
  AlertCircle,
  Pill,
  Check,
  HelpCircle,
  Home
} from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()

interface LoginForm {
  dni: string
  password: string
  remember: boolean
}

interface FormErrors {
  dni?: string
  password?: string
}

const form = ref<LoginForm>({
  dni: '',
  password: '',
  remember: false
})

const errors = ref<FormErrors>({})
const loading = ref(false)
const error = ref('')
const showSuccess = ref(false)
const progress = ref(0)
const showPassword = ref(false)
const logoTimeline = ref<gsap.core.Timeline | null>(null)
const successInterval = ref<ReturnType<typeof setInterval> | null>(null)

const isFormValid = computed(() => {
  return form.value.dni.trim() &&
         form.value.password.trim() &&
         Object.keys(errors.value).length === 0
})

const validateField = (field: keyof LoginForm) => {
  const value = form.value[field] as string

  switch (field) {
    case 'dni':
      if (!value.trim()) {
        errors.value.dni = 'El DNI es obligatorio'
      } else if (!/^\d{8}$/.test(value.trim())) {
        errors.value.dni = 'El DNI debe tener exactamente 8 dígitos'
      } else {
        delete errors.value.dni
      }
      break

    case 'password':
      if (!value.trim()) {
        errors.value.password = 'La contraseña es obligatoria'
      } else if (value.trim().length < 6) {
        errors.value.password = 'La contraseña debe tener al menos 6 caracteres'
      } else {
        delete errors.value.password
      }
      break
  }
}

const validateForm = () => {
  validateField('dni')
  validateField('password')
  return Object.keys(errors.value).length === 0
}

const handleLogoHover = () => {
  if (logoTimeline.value) {
    logoTimeline.value.kill()
  }

  logoTimeline.value = gsap.timeline()
    .to('.logo-front', {
      rotationY: 15,
      rotationX: 10,
      scale: 1.1,
      duration: 0.3,
      ease: 'power2.out'
    })
    .to('.logo-shadow', {
      scale: 1.3,
      opacity: 0.4,
      duration: 0.3,
      ease: 'power2.out'
    }, 0)
}

const handleLogoLeave = () => {
  if (logoTimeline.value) {
    logoTimeline.value.kill()
  }

  logoTimeline.value = gsap.timeline()
    .to('.logo-front', {
      rotationY: 0,
      rotationX: 0,
      scale: 1,
      duration: 0.3,
      ease: 'power2.out'
    })
    .to('.logo-shadow', {
      scale: 1.1,
      opacity: 0.2,
      duration: 0.3,
      ease: 'power2.out'
    }, 0)
}

const animateSuccess = () => {
  console.log('🎉 Starting success animation')
  console.log('👤 Current user in store:', authStore.user)
  console.log('🔑 User role:', authStore.user?.rol)

  showSuccess.value = true
  progress.value = 0

  // Animar el progreso
  successInterval.value = setInterval(() => {
    progress.value += 2
    if (progress.value >= 100) {
      if (successInterval.value) {
        clearInterval(successInterval.value as unknown as number)
        successInterval.value = null
      }
      // Redirigir después de completar la animación
      setTimeout(() => {
        console.log('🚀 Starting redirect process')
        // Verificar si hay un parámetro de redirección en la URL
        const urlParams = new URLSearchParams(window.location.search)
        const redirectPath = urlParams.get('redirect')

        if (redirectPath) {
          console.log('🔀 Redirecting to:', redirectPath)
          router.push(redirectPath)
        } else {
          // Redirigir según el rol del usuario
          const userRole = authStore.user?.rol
          console.log('👑 User role for redirect:', userRole)
          if (userRole === 'administrador') {
            console.log('🏠 Redirecting admin to /admin/home')
            router.push('/admin/home')
          } else {
            console.log('🏠 Redirecting client to /client/home')
            router.push('/client/home')
          }
        }
      }, 500)
    }
  }, 30)

  // Animaciones GSAP para el overlay de éxito
  gsap.from('.success-overlay', {
    opacity: 0,
    scale: 0.8,
    duration: 0.5,
    ease: 'back.out(1.7)'
  })

  gsap.from('.success-icon', {
    scale: 0,
    rotation: -180,
    duration: 0.8,
    ease: 'back.out(1.7)',
    delay: 0.2
  })

  gsap.from('.success-title', {
    y: 30,
    opacity: 0,
    duration: 0.5,
    ease: 'power2.out',
    delay: 0.4
  })

  gsap.from('.success-message', {
    y: 20,
    opacity: 0,
    duration: 0.5,
    ease: 'power2.out',
    delay: 0.6
  })

  gsap.from('.success-progress', {
    scaleX: 0,
    duration: 0.8,
    ease: 'power2.out',
    delay: 0.8,
    transformOrigin: 'left'
  })
}

const handleGoogleLogin = async () => {
  try {
    // Aquí iría la lógica de autenticación con Google
    console.log('Google login attempt')

    // Simular autenticación con Google
    loading.value = true
    await new Promise(resolve => setTimeout(resolve, 1500))

    // Simular respuesta exitosa
    animateSuccess()

  } catch (err) {
    error.value = 'Error al iniciar sesión con Google. Inténtalo de nuevo.'
    console.error('Google login error:', err)
  } finally {
    loading.value = false
  }
}

const handleLogin = async () => {
  if (!validateForm()) {
    error.value = 'Por favor, corrige los errores en el formulario'
    return
  }

  loading.value = true
  error.value = ''

  try {
    // Use the auth store's login method instead of calling service directly
    const result = await authStore.login(form.value.dni, form.value.password)

    console.log('🔐 Login result:', result)
    console.log('👤 User in store:', authStore.user)
    console.log('🔑 Is authenticated:', authStore.isAuthenticated)

    if (result.success) {
      // Animar éxito y redirigir
      animateSuccess()
    } else {
      error.value = result.message || 'Error al iniciar sesión. Verifica tus credenciales.'
    }

  } catch (err: unknown) {
    if (err instanceof Error) {
      error.value = err.message || 'Error al iniciar sesión. Verifica tus credenciales.'
    } else {
      error.value = 'Error al iniciar sesión. Verifica tus credenciales.'
    }
    console.error('Login error:', err)

    // Animación de error
    gsap.from('.error-message', {
      x: -20,
      opacity: 0,
      duration: 0.5,
      ease: 'power2.out'
    })
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  // Establecer estado inicial invisible
  gsap.set('.login-card', { y: 50, opacity: 0 })
  gsap.set('.header-section', { y: 30, opacity: 0 })
  gsap.set('.welcome-section', { y: 20, opacity: 0 })
  gsap.set('.form-section', { y: 20, opacity: 0 })
  gsap.set('.submit-btn', { y: 20, opacity: 0 })
  gsap.set('.options-section', { y: 15, opacity: 0 })
  gsap.set('.social-login-section', { y: 15, opacity: 0 })
  gsap.set('.register-link', { y: 15, opacity: 0 })
  gsap.set('.home-link', { y: -20, opacity: 0 })

  // Animaciones de entrada con timeline
  const tl = gsap.timeline()

  tl.to('.home-link', {
    y: 0,
    opacity: 1,
    duration: 0.6,
    ease: 'power2.out'
  })

  tl.to('.login-card', {
    y: 0,
    opacity: 1,
    duration: 0.8,
    ease: 'power2.out'
  }, '-=0.4')

  tl.to('.header-section', {
    y: 0,
    opacity: 1,
    duration: 0.6,
    ease: 'power2.out'
  }, '-=0.4')

  tl.to('.welcome-section', {
    y: 0,
    opacity: 1,
    duration: 0.6,
    ease: 'power2.out'
  }, '-=0.3')

  tl.to('.form-section', {
    y: 0,
    opacity: 1,
    duration: 0.6,
    ease: 'power2.out',
    stagger: 0.1
  }, '-=0.3')

  tl.to('.submit-btn', {
    y: 0,
    opacity: 1,
    duration: 0.6,
    ease: 'power2.out'
  }, '-=0.2')

  tl.to('.options-section', {
    y: 0,
    opacity: 1,
    duration: 0.5,
    ease: 'power2.out'
  }, '-=0.1')

  tl.to('.social-login-section', {
    y: 0,
    opacity: 1,
    duration: 0.5,
    ease: 'power2.out'
  }, '-=0.1')

  tl.to('.register-link', {
    y: 0,
    opacity: 1,
    duration: 0.5,
    ease: 'power2.out'
  }, '-=0.1')
})

onUnmounted(() => {
  if (logoTimeline.value) {
    logoTimeline.value.kill()
  }
  if (successInterval.value) {
    clearInterval(successInterval.value)
  }
})
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg,
    #667eea 0%,
    #764ba2 25%,
    #f093fb 50%,
    #f5576c 75%,
    #4facfe 100%);
  background-size: 400% 400%;
  animation: gradientShift 15s ease infinite;
}

@keyframes gradientShift {
  0% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}

/* Animated Background Particles */
.animated-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.particle {
  position: absolute;
  width: 8px;
  height: 8px;
  background-color: white;
  border-radius: 50%;
  opacity: 0.2;
  animation: float 10s linear infinite;
}

.particle:nth-child(odd) {
  animation-duration: 15s;
  animation-delay: -5s;
}

.particle:nth-child(3n) {
  animation-duration: 20s;
  animation-delay: -10s;
}

@keyframes float {
  0% {
    transform: translateY(100vh) rotate(0deg);
    opacity: 0;
  }
  10% {
    opacity: 0.3;
  }
  90% {
    opacity: 0.3;
  }
  100% {
    transform: translateY(-100vh) rotate(360deg);
    opacity: 0;
  }
}

/* Success Overlay */
.success-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, #10b981, #3b82f6, #8b5cf6);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 10;
  backdrop-filter: blur(20px);
}

.success-content {
  text-align: center;
  color: white;
}

.success-icon {
  margin-bottom: 1.5rem;
}

.success-title {
  font-size: 2.25rem;
  font-weight: bold;
  margin-bottom: 1rem;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.success-message {
  font-size: 1.25rem;
  margin-bottom: 2rem;
  opacity: 0.9;
}

.success-progress {
  width: 16rem;
  height: 0.5rem;
  background-color: rgba(255, 255, 255, 0.2);
  border-radius: 9999px;
  margin-bottom: 1.5rem;
  overflow: hidden;
}

.progress-bar {
  height: 100%;
  background-color: white;
  border-radius: 9999px;
  transition: all 0.3s ease;
}

.redirect-text {
  font-size: 0.875rem;
  opacity: 0.75;
}

/* Home Link */
.home-link {
  position: absolute;
  top: 1.5rem;
  left: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  border-radius: 0.75rem;
  color: #374151;
  text-decoration: none;
  font-weight: 500;
  font-size: 0.875rem;
  border: 1px solid rgba(255, 255, 255, 0.3);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
  z-index: 5;
  opacity: 0;
  transform: translateY(-20px);
}

.home-link:hover {
  background: rgba(255, 255, 255, 1);
  transform: translateY(-22px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
  border-color: rgba(59, 130, 246, 0.2);
}

.home-icon {
  width: 1.25rem;
  height: 1.25rem;
  color: #3b82f6;
  transition: transform 0.2s ease;
}

.home-link:hover .home-icon {
  transform: scale(1.1);
}

.home-text {
  color: #374151;
  transition: color 0.2s ease;
}

.home-link:hover .home-text {
  color: #1f2937;
}

/* Main Card */
.login-card {
  background-color: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(16px);
  border-radius: 1.5rem;
  box-shadow:
    0 25px 50px -12px rgba(0, 0, 0, 0.25),
    0 0 0 1px rgba(255, 255, 255, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.2);
  max-width: 600px;
  width: 100%;
  position: relative;
  overflow: hidden;
}

/* Card Content */
.card-content {
  padding: 2rem;
}

/* Header Section */
.header-section {
  text-align: center;
  margin-bottom: 2rem;
}

.logo-section {
  margin-bottom: 1.5rem;
}

.logo-3d {
  position: relative;
  display: inline-block;
  cursor: pointer;
  perspective: 1000px;
}

.logo-front {
  transition: transform 0.3s ease;
  transform-style: preserve-3d;
}

.logo-shadow {
  position: absolute;
  inset: 0;
  background-color: rgba(37, 99, 235, 0.2);
  border-radius: 50%;
  filter: blur(16px);
  transform: scale(1.1) translateZ(-10px);
}

.brand-title {
  font-size: 2.25rem;
  font-weight: bold;
  background: linear-gradient(135deg, #2563eb, #7c3aed);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  margin-bottom: 0.5rem;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.brand-subtitle {
  color: #1f2937;
  font-size: 1.125rem;
}

.welcome-section {
  margin-bottom: 2rem;
}

.welcome-title {
  font-size: 1.875rem;
  font-weight: bold;
  color: white;
  margin-bottom: 1rem;
}

.welcome-text {
  color: #374151;
  font-size: 1.125rem;
}

/* Form Styles */
.login-form {
  display: grid;
  gap: 2rem;
}

.form-section {
  background-color: rgba(255, 255, 255, 0.8);
  border-radius: 1rem;
  padding: 1.5rem;
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
  opacity: 1; /* Asegurar visibilidad inicial */
  transform: translateY(0); /* Posición inicial */
}

.form-section:hover {
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  border-color: #bfdbfe;
  transform: translateY(-2px);
}

.section-header {
  display: flex;
  align-items: center;
  margin-bottom: 1.5rem;
}

.section-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: white;
  margin-left: 0.75rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
  margin-bottom: 1rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: #111827;
  margin-bottom: 0.5rem;
}

.input-wrapper {
  position: relative;
}

.input-icon {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  width: 1.25rem;
  height: 1.25rem;
  pointer-events: none;
}

.form-input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.75rem;
  transition: all 0.2s ease;
  background-color: white;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.form-input:focus {
  outline: none;
  box-shadow:
    0 0 0 3px rgba(59, 130, 246, 0.1),
    0 10px 15px -3px rgba(0, 0, 0, 0.1);
  transform: translateY(-1px);
}

.form-input::placeholder {
  color: #9ca3af;
}

/* Error Text */
.error-text {
  color: #dc2626;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

/* Options Section */
.options-section {
  margin: 1.5rem 0;
  opacity: 1; /* Asegurar visibilidad inicial */
  transform: translateY(0); /* Posición inicial */
}

.options-container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.7));
  border-radius: 1rem;
  border: 1px solid rgba(255, 255, 255, 0.3);
  backdrop-filter: blur(10px);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
}

.options-container:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
  border-color: rgba(59, 130, 246, 0.2);
}

/* Remember Me Section */
.remember-me {
  display: flex;
  align-items: center;
}

.checkbox-wrapper {
  display: flex;
  align-items: center;
  cursor: pointer;
  user-select: none;
  transition: all 0.2s ease;
}

.checkbox-wrapper:hover {
  transform: scale(1.02);
}

.hidden-checkbox {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  height: 0;
  width: 0;
}

.custom-checkbox {
  position: relative;
  height: 1.25rem;
  width: 1.25rem;
  background-color: rgba(255, 255, 255, 0.9);
  border: 2px solid #d1d5db;
  border-radius: 0.375rem;
  margin-right: 0.75rem;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.hidden-checkbox:checked ~ .custom-checkbox {
  background-color: #3b82f6;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.check-icon {
  width: 0.875rem;
  height: 0.875rem;
  color: white;
  opacity: 0;
  transform: scale(0.8);
  transition: all 0.2s ease;
}

.hidden-checkbox:checked ~ .custom-checkbox .check-icon {
  opacity: 1;
  transform: scale(1);
}

.checkbox-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
  transition: color 0.2s ease;
}

.checkbox-wrapper:hover .checkbox-label {
  color: #1f2937;
}

/* Forgot Password Section */
.forgot-password {
  display: flex;
  align-items: center;
}

.forgot-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #3b82f6;
  text-decoration: none;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  transition: all 0.2s ease;
  position: relative;
  overflow: hidden;
}

.forgot-link::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05));
  border-radius: 0.5rem;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.forgot-link:hover::before {
  opacity: 1;
}

.forgot-link:hover {
  color: #2563eb;
  transform: translateY(-1px);
}

.forgot-icon {
  width: 1rem;
  height: 1rem;
  transition: transform 0.2s ease;
}

.forgot-link:hover .forgot-icon {
  transform: rotate(12deg);
}

/* Social Login Section */
.social-login-section {
  margin-top: 2rem;
  opacity: 1; /* Asegurar visibilidad inicial */
  transform: translateY(0); /* Posición inicial */
}

/* Submit Button */
.submit-btn {
  width: 100%;
  padding: 1rem 1.5rem;
  border-radius: 0.75rem;
  font-weight: 600;
  color: white;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
  border: none;
  cursor: pointer;
}

.submit-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
}

.submit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-content {
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  z-index: 10;
}

.btn-glow {
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transform: translateX(-100%);
  transition: transform 0.6s ease;
}

.submit-btn:hover .btn-glow {
  transform: translateX(100%);
}

/* Social Login Section */
.social-login-section {
  margin-top: 2rem;
}

.divider {
  display: flex;
  align-items: center;
  margin-bottom: 1.5rem;
}

.divider::before,
.divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background-color: #e5e7eb;
}

.divider::before {
  margin-right: 1rem;
}

.divider::after {
  margin-left: 1rem;
}

.divider-text {
  color: white;
  font-size: 0.875rem;
  font-weight: 500;
  background-color: rgba(255, 255, 255, 0.9);
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  backdrop-filter: blur(4px);
}

.social-buttons {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.google-btn {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 0.875rem 1.5rem;
  border: 2px solid #e5e7eb;
  border-radius: 0.75rem;
  background-color: white;
  color: #374151;
  font-size: 0.875rem;
  font-weight: 500;
  transition: all 0.2s ease;
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.google-btn:hover {
  border-color: #d1d5db;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  transform: translateY(-1px);
}

.google-icon {
  width: 1.25rem;
  height: 1.25rem;
  flex-shrink: 0;
}

.google-text {
  font-weight: 500;
}

/* Register Link */
.register-link {
  text-align: center;
  margin-top: 1.5rem;
  opacity: 1; /* Asegurar visibilidad inicial */
  transform: translateY(0); /* Posición inicial */
}

.register-link p {
  color: white;
}

.link {
  color: #2563eb;
  font-weight: 500;
  text-decoration: underline;
}

.link:hover {
  color: #1d4ed8;
}

/* Error Message */
.error-message {
  display: flex;
  align-items: center;
  padding: 1rem;
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 0.75rem;
  color: #b91c1c;
  margin-top: 1.5rem;
  animation: shake 0.5s ease-in-out;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-5px); }
  75% { transform: translateX(5px); }
}

/* Responsive Design */
@media (max-width: 768px) {
  .login-card {
    margin: 1rem;
    border-radius: 0;
  }

  .card-content {
    padding: 1.5rem;
  }

  .brand-title {
    font-size: 1.875rem;
  }

  .welcome-title {
    font-size: 1.5rem;
  }

  .form-section {
    padding: 1rem;
  }
}

@media (max-width: 640px) {
  .login-container {
    padding: 0.5rem;
  }

  .card-content {
    padding: 1rem;
  }

  .section-title {
    font-size: 1.125rem;
  }
}

/* Accessibility */
@media (prefers-reduced-motion: reduce) {
  .login-container,
  .particle,
  .login-card,
  .form-input,
  .submit-btn,
  .success-overlay,
  .progress-bar {
    animation: none !important;
    transition: none !important;
  }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
  .login-card {
    background-color: rgba(17, 24, 39, 0.95);
    color: white;
  }

  .form-section {
    background-color: rgba(31, 41, 55, 0.5);
    border-color: #374151;
  }

  .form-input {
    background-color: #374151;
    border-color: #4b5563;
    color: white;
  }

  .form-label {
    color: #d1d5db;
  }

  .welcome-text,
  .brand-subtitle {
    color: #9ca3af;
  }
}

/* Print styles */
@media print {
  .animated-bg,
  .success-overlay {
    display: none !important;
  }

  .login-card {
    box-shadow: none;
  }
}
</style>
