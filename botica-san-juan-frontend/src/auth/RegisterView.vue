<template>
  <div class="register-container">
    <!-- Animated Background -->
    <div class="animated-bg">
      <div
        v-for="i in 50"
        :key="i"
        class="particle"
        :style="{
          left: Math.random() * 100 + '%',
          top: Math.random() * 100 + '%',
          animationDelay: Math.random() * 10 + 's'
        }"
      ></div>
    </div>

    <!-- Home Link -->
    <router-link
      to="/"
      class="home-link"
    >
      <HomeIcon class="home-icon" />
      <span class="home-text">Inicio</span>
    </router-link>

    <!-- Main Registration Card -->
    <!-- Main Card -->
    <div
      ref="registerCard"
      class="register-card"
    >
      <!-- Success Overlay -->
      <div
        v-if="showSuccess"
        ref="successOverlay"
        class="success-overlay"
      >
        <div class="success-content">
          <div class="success-icon">
            <CheckCircleIcon class="w-24 h-24 text-green-400" />
          </div>
          <h2 class="success-title">
            ¡Registro Exitoso!
          </h2>
          <p class="success-message">
            Tu cuenta ha sido creada correctamente
          </p>
          <div class="success-progress">
            <div
              ref="progressBar"
              class="progress-bar"
            ></div>
          </div>
          <p class="redirect-text">
            Redirigiendo al login...
          </p>
        </div>
      </div>

      <div
        v-show="!showSuccess"
        class="card-content"
      >
        <!-- Header Section -->
        <div class="header-section">
          <div class="logo-section">
            <div class="logo-3d">
              <div class="logo-front">
                <PillIcon class="w-12 h-12 text-blue-600" />
              </div>
              <div class="logo-shadow"></div>
            </div>
            <h1 class="brand-title">
              Botica San Juan
            </h1>
            <p class="brand-subtitle">
              Tu salud, nuestra prioridad
            </p>
          </div>

          <div class="welcome-section">
            <h2 class="welcome-title">
              Únete a Nuestra Comunidad
            </h2>
            <p class="welcome-text">
              Crea tu cuenta y accede a beneficios exclusivos
            </p>
          </div>
        </div>

        <!-- Form Section -->
        <form
          ref="registerForm"
          class="register-form"
          @submit.prevent="handleRegister"
        >
          <div class="form-grid">
            <!-- Personal Information -->
            <div class="form-section">
              <div class="section-header">
                <UserIcon class="w-5 h-5 text-blue-600" />
                <h3 class="section-title">
                  Información Personal
                </h3>
              </div>

              <div class="form-group">
                <label class="form-label">Nombre Completo *</label>
                <div class="input-wrapper">
                  <UserIcon class="input-icon" />
                  <input
                    ref="nombreInput"
                    v-model="form.name"
                    type="text"
                    required
                    class="form-input"
                    placeholder="Ingresa tu nombre completo"
                  />
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">DNI *</label>
                <div class="input-wrapper">
                  <CreditCardIcon class="input-icon" />
                  <input
                    v-model="form.dni"
                    type="text"
                    required
                    maxlength="8"
                    class="form-input"
                    placeholder="12345678"
                  />
                </div>
              </div>
            </div>

            <!-- Contact Information -->
            <div class="form-section">
              <div class="section-header">
                <MailIcon class="w-5 h-5 text-green-600" />
                <h3 class="section-title">
                  Información de Contacto
                </h3>
              </div>

              <div class="form-group">
                <label class="form-label">Correo Electrónico *</label>
                <div class="input-wrapper">
                  <MailIcon class="input-icon" />
                  <input
                    v-model="form.email"
                    type="email"
                    required
                    class="form-input"
                    placeholder="tu@email.com"
                  />
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Teléfono Móvil *</label>
                <div class="input-wrapper">
                  <PhoneIcon class="input-icon" />
                  <input
                    v-model="form.telefono"
                    type="tel"
                    required
                    maxlength="9"
                    class="form-input"
                    placeholder="9XXXXXXXX"
                  />
                </div>
              </div>
            </div>

            <!-- Security Information -->
            <div class="form-section">
              <div class="section-header">
                <ShieldIcon class="w-5 h-5 text-red-600" />
                <h3 class="section-title">
                  Seguridad
                </h3>
              </div>

              <div class="form-group">
                <label class="form-label">Contraseña *</label>
                <div class="input-wrapper">
                  <LockIcon class="input-icon" />
                  <input
                    v-model="form.password"
                    type="password"
                    required
                    minlength="8"
                    class="form-input"
                    placeholder="Mínimo 8 caracteres"
                  />
                </div>
                <div
                  v-if="form.password"
                  class="password-strength"
                >
                  <div class="strength-bar">
                    <div
                      class="strength-fill"
                      :class="passwordStrengthClass"
                      :style="{ width: passwordStrengthWidth }"
                    ></div>
                  </div>
                  <span class="strength-text">{{ passwordStrengthText }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Terms and Conditions -->
          <div class="terms-section">
            <label class="checkbox-container">
              <input
                v-model="form.aceptaTerminos"
                type="checkbox"
                required
              />
              <span class="checkmark"></span>
              <span class="checkbox-text">
                Acepto los
                <a
                  href="#"
                  class="link"
                >Términos y Condiciones</a>
                y la
                <a
                  href="#"
                  class="link"
                >Política de Privacidad</a>
              </span>
            </label>
          </div>

          <!-- Submit Button -->
          <button
            ref="submitBtn"
            type="submit"
            :disabled="loading || !isFormValid"
            class="submit-btn"
          >
            <span class="btn-content">
              <UserPlusIcon class="w-5 h-5" />
              {{ loading ? 'Creando Cuenta...' : 'Crear Mi Cuenta' }}
            </span>
            <div class="btn-glow"></div>
          </button>

          <!-- Login Link -->
          <div class="login-link">
            <p>
              ¿Ya tienes cuenta?
              <router-link
                to="/login"
                class="link"
              >
                Inicia sesión aquí
              </router-link>
            </p>
          </div>
        </form>

        <!-- Error Messages -->
        <div
          v-if="error"
          ref="errorMessage"
          class="error-message"
        >
          <AlertCircleIcon class="w-5 h-5" />
          <span>{{ error }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { gsap } from 'gsap'
import {
  UserIcon,
  MailIcon,
  LockIcon,
  PhoneIcon,
  ShieldIcon,
  CreditCardIcon,
  CheckCircleIcon,
  AlertCircleIcon,
  UserPlusIcon,
  HomeIcon
} from 'lucide-vue-next'
import authService from '../services/auth'

// Router
const router = useRouter()

// Form data
const form = ref({
  name: '',
  dni: '',
  email: '',
  password: '',
  telefono: '',
  aceptaTerminos: false
})

// UI state
const loading = ref(false)
const error = ref('')
const showSuccess = ref(false)

// Refs
const registerCard = ref<HTMLElement>()
const registerForm = ref<HTMLFormElement>()
const successOverlay = ref<HTMLElement>()
const progressBar = ref<HTMLElement>()
const submitBtn = ref<HTMLButtonElement>()
const errorMessage = ref<HTMLElement>()
const nombreInput = ref<HTMLInputElement>()

// Computed properties
const passwordStrength = computed(() => {
  const password = form.value.password
  if (!password) return 0

  let strength = 0
  if (password.length >= 8) strength += 25
  if (/[a-z]/.test(password)) strength += 25
  if (/[A-Z]/.test(password)) strength += 25
  if (/[0-9]/.test(password)) strength += 15
  if (/[^A-Za-z0-9]/.test(password)) strength += 10

  return Math.min(strength, 100)
})

const passwordStrengthClass = computed(() => {
  const strength = passwordStrength.value
  if (strength < 25) return 'weak'
  if (strength < 50) return 'fair'
  if (strength < 75) return 'good'
  return 'strong'
})

const passwordStrengthWidth = computed(() => `${passwordStrength.value}%`)

const passwordStrengthText = computed(() => {
  const strength = passwordStrength.value
  if (strength < 25) return 'Débil'
  if (strength < 50) return 'Regular'
  if (strength < 75) return 'Buena'
  return 'Muy fuerte'
})

const isFormValid = computed(() => {
  return (
    form.value.name &&
    form.value.dni &&
    form.value.email &&
    form.value.password &&
    form.value.telefono &&
    form.value.aceptaTerminos &&
    passwordStrength.value >= 50 &&
    /^\d{8}$/.test(form.value.dni) &&
    /^9\d{8}$/.test(form.value.telefono)
  )
})

// Methods
const handleRegister = async () => {
  if (!isFormValid.value) return

  loading.value = true
  error.value = ''

  try {
    const response = await authService.register({
      nombre: form.value.name,
      dni: form.value.dni,
      email: form.value.email,
      password: form.value.password,
      telefono: form.value.telefono,
      acepta_terminos: form.value.aceptaTerminos
    })

    // Store authentication data
    authService.setAuthData(response)

    // Show success animation
    await showSuccessAnimation()

  } catch (err: unknown) {
    const errorMessage = err instanceof Error ? err.message : 'Error desconocido'
    error.value = errorMessage || 'Error al registrar usuario. Inténtalo de nuevo.'
    console.error('Register error:', err)
    showErrorAnimation()
  } finally {
    loading.value = false
  }
}

const showSuccessAnimation = async () => {
  showSuccess.value = true

  // Animate success overlay
  if (successOverlay.value) {
    gsap.fromTo(successOverlay.value,
      { opacity: 0, scale: 0.8 },
      { opacity: 1, scale: 1, duration: 0.8, ease: "back.out(1.7)" }
    )
  }

  // Animate progress bar
  if (progressBar.value) {
    gsap.fromTo(progressBar.value,
      { width: '0%' },
      { width: '100%', duration: 3, ease: "power2.inOut" }
    )
  }

  // Animate success icon
  const successIcon = successOverlay.value?.querySelector('.success-icon')
  if (successIcon) {
    gsap.fromTo(successIcon,
      { scale: 0, rotation: -180 },
      { scale: 1, rotation: 0, duration: 1, delay: 0.3, ease: "back.out(1.7)" }
    )
  }

  // Redirect after animation
  setTimeout(() => {
    router.push('/login')
  }, 3500)
}

const showErrorAnimation = () => {
  if (errorMessage.value) {
    gsap.fromTo(errorMessage.value,
      { opacity: 0, y: -20 },
      { opacity: 1, y: 0, duration: 0.5, ease: "power2.out" }
    )
  }
}

// Animations
const initAnimations = () => {
  // Card entrance animation
  if (registerCard.value) {
    gsap.fromTo(registerCard.value,
      { opacity: 0, y: 50, scale: 0.9 },
      { opacity: 1, y: 0, scale: 1, duration: 1, ease: "power3.out" }
    )
  }

  // Home link animation
  const homeLink = document.querySelector('.home-link')
  if (homeLink) {
    gsap.fromTo(homeLink,
      { opacity: 0, y: -20 },
      { opacity: 1, y: 0, duration: 0.6, delay: 0.2, ease: "power2.out" }
    )
  }

  // Form elements stagger animation
  const formElements = registerForm.value?.querySelectorAll('.form-section')
  if (formElements) {
    gsap.fromTo(formElements,
      { opacity: 0, x: -30 },
      {
        opacity: 1,
        x: 0,
        duration: 0.8,
        stagger: 0.1,
        delay: 0.3,
        ease: "power2.out"
      }
    )
  }

  // Button animation
  if (submitBtn.value) {
    gsap.fromTo(submitBtn.value,
      { opacity: 0, y: 20 },
      { opacity: 1, y: 0, duration: 0.6, delay: 0.8, ease: "power2.out" }
    )
  }

  // Focus animations for inputs
  const inputs = registerForm.value?.querySelectorAll('.form-input')
  inputs?.forEach(input => {
    input.addEventListener('focus', () => {
      gsap.to(input, {
        scale: 1.02,
        duration: 0.2,
        ease: "power2.out"
      })
    })

    input.addEventListener('blur', () => {
      gsap.to(input, {
        scale: 1,
        duration: 0.2,
        ease: "power2.out"
      })
    })
  })
}

// Hover effects
const initHoverEffects = () => {
  // Submit button hover
  if (submitBtn.value) {
    const btn = submitBtn.value
    btn.addEventListener('mouseenter', () => {
      gsap.to(btn, {
        scale: 1.05,
        duration: 0.3,
        ease: "power2.out"
      })
    })

    btn.addEventListener('mouseleave', () => {
      gsap.to(btn, {
        scale: 1,
        duration: 0.3,
        ease: "power2.out"
      })
    })
  }

  // Logo 3D effect
  const logo3d = document.querySelector('.logo-3d')
  if (logo3d) {
    logo3d.addEventListener('mouseenter', () => {
      gsap.to('.logo-front', {
        rotationY: 15,
        rotationX: 10,
        duration: 0.3,
        ease: "power2.out"
      })
    })

    logo3d.addEventListener('mouseleave', () => {
      gsap.to('.logo-front', {
        rotationY: 0,
        rotationX: 0,
        duration: 0.3,
        ease: "power2.out"
      })
    })
  }
}

// Lifecycle
onMounted(() => {
  nextTick(() => {
    initAnimations()
    initHoverEffects()

    // Focus first input
    if (nombreInput.value) {
      nombreInput.value.focus()
    }
  })
})
</script>

<style scoped>
.register-container {
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
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: #374151;
  text-decoration: none;
  font-weight: 500;
  font-size: 0.875rem;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
  z-index: 20;
  opacity: 0;
  transform: translateY(-20px);
}

.home-link:hover {
  background: rgba(255, 255, 255, 1);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
  color: #1f2937;
}

.home-icon {
  width: 1.25rem;
  height: 1.25rem;
  transition: transform 0.2s ease;
}

.home-link:hover .home-icon {
  transform: scale(1.1);
}

.home-text {
  transition: all 0.2s ease;
}

/* Main Card */
.register-card {
  background-color: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(16px);
  border-radius: 1.5rem;
  box-shadow:
    0 25px 50px -12px rgba(0, 0, 0, 0.25),
    0 0 0 1px rgba(255, 255, 255, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.2);
  max-width: 1200px;
  width: 100%;
  min-height: 80vh;
  position: relative;
  overflow: hidden;
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
.register-form {
  display: grid;
  gap: 2rem;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 2rem;
}

.form-section {
  background-color: rgba(255, 255, 255, 0.8);
  border-radius: 1rem;
  padding: 1.5rem;
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
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

/* Radio Group */
.radio-group {
  display: flex;
  gap: 1.5rem;
}

.radio-option {
  display: flex;
  align-items: center;
  cursor: pointer;
}

.radio-option input {
  margin-right: 0.5rem;
}

.radio-label {
  color: white;
}

/* Password Strength */
.password-strength {
  margin-top: 0.5rem;
}

.strength-bar {
  width: 100%;
  height: 0.5rem;
  background-color: #e5e7eb;
  border-radius: 9999px;
  overflow: hidden;
  margin-bottom: 0.25rem;
}

.strength-fill {
  height: 100%;
  transition: all 0.3s ease;
}

.strength-fill.weak {
  background-color: #ef4444;
}

.strength-fill.fair {
  background-color: #f59e0b;
}

.strength-fill.good {
  background-color: #3b82f6;
}

.strength-fill.strong {
  background-color: #10b981;
}

.strength-text {
  font-size: 0.75rem;
  color: #111827;
}

/* Error Text */
.error-text {
  color: #dc2626;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

/* Terms Section */
.terms-section {
  background-color: rgba(255, 255, 255, 0.8);
  border-radius: 0.75rem;
  padding: 1.5rem;
  border: 1px solid #e5e7eb;
}

.checkbox-container {
  display: flex;
  align-items: flex-start;
  margin-bottom: 1rem;
  cursor: pointer;
}

.checkbox-container:last-child {
  margin-bottom: 0;
}

.checkbox-container input {
  margin-top: 0.25rem;
  margin-right: 0.75rem;
}

.checkmark {
  width: 1.25rem;
  height: 1.25rem;
  border: 2px solid #d1d5db;
  border-radius: 0.375rem;
  margin-right: 0.75rem;
  margin-top: 0.25rem;
  flex-shrink: 0;
  position: relative;
  transition: all 0.2s ease;
}

.checkbox-container input:checked + .checkmark {
  background-color: #2563eb;
  border-color: #2563eb;
}

.checkbox-container input:checked + .checkmark::after {
  content: '✓';
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 0.875rem;
}

.checkbox-text {
  color: white;
  line-height: 1.625;
}

.link {
  color: #2563eb;
  font-weight: 500;
  text-decoration: underline;
}

.link:hover {
  color: #1d4ed8;
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

/* Login Link */
.login-link {
  text-align: center;
  margin-top: 1.5rem;
}

.login-link p {
  color: white;
}

.login-link .link {
  color: #2563eb;
  font-weight: 500;
}

.login-link .link:hover {
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
@media (min-width: 1024px) {
  .form-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 768px) {
  .register-card {
    margin: 1rem;
    border-radius: 0;
    min-height: 100vh;
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

  .radio-group {
    flex-direction: column;
    gap: 0.75rem;
  }

  .form-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .register-container {
    padding: 0.5rem;
  }

  .card-content {
    padding: 1rem;
  }

  .section-title {
    font-size: 1.125rem;
  }

  .form-section {
    padding: 1rem;
  }
}

/* Accessibility */
@media (prefers-reduced-motion: reduce) {
  .register-container,
  .particle,
  .register-card,
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
  .register-card {
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

  .terms-section {
    background-color: rgba(31, 41, 55, 0.5);
    border-color: #374151;
  }
}

/* Print styles */
@media print {
  .animated-bg,
  .success-overlay {
    display: none !important;
  }

  .register-card {
    box-shadow: none;
  }
}
</style>
