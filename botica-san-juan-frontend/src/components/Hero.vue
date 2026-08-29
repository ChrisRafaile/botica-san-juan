<template>
  <section class="relative min-h-screen flex items-center justify-center overflow-hidden">
    <!-- Background with gradient and pattern -->
    <div class="absolute inset-0 hero-gradient" />
    <div class="absolute inset-0 opacity-10">
      <div
        class="absolute inset-0"
        style="background-image: radial-gradient(circle at 25% 25%, rgba(255,255,255,0.2) 0%, transparent 50%), radial-gradient(circle at 75% 75%, rgba(255,255,255,0.1) 0%, transparent 50%);"
      />
    </div>

    <!-- Floating elements for visual interest -->
    <div class="absolute top-1/4 left-4 sm:left-10 w-16 sm:w-20 h-16 sm:h-20 bg-white/5 rounded-full blur-xl float-element-1 hidden lg:block" />
    <div class="absolute bottom-1/4 right-4 sm:right-10 w-24 sm:w-32 h-24 sm:h-32 bg-white/3 rounded-full blur-2xl float-element-2 hidden lg:block" />
    <div class="absolute top-3/4 left-1/3 w-12 sm:w-16 h-12 sm:h-16 bg-accent-400/10 rounded-full blur-lg float-element-3 hidden lg:block" />

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-center">
        <!-- Content -->
        <div class="text-white space-y-6 lg:space-y-8 order-2 lg:order-1">
          <div class="space-y-4">
            <div class="flex justify-center lg:justify-start mb-6">
              <img
                src="@/assets/images/logo botica.jpg"
                alt="Botica San Juan Logo"
                class="h-12 sm:h-16 w-auto hero-logo drop-shadow-lg"
              />
            </div>
            <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl xl:text-7xl font-bold leading-tight hero-title text-center lg:text-left">
              Tu Farmacia de
              <span class="text-accent-300 block">Confianza</span>
            </h1>
            <p class="text-lg sm:text-xl md:text-2xl text-white/90 max-w-2xl mx-auto lg:mx-0 text-center lg:text-left hero-description">
              En Botica San Juan, ubicada en Av. Santa Rosa 103, te ofrecemos
              medicamentos de calidad, asesoramiento profesional y servicios
              farmacéuticos con más de 25 años de experiencia en Lima.
            </p>
          </div>

          <!-- Stats -->
          <div class="grid grid-cols-3 gap-4 sm:gap-6 max-w-sm sm:max-w-md mx-auto lg:mx-0">
            <div class="text-center hero-stat bg-white/20 backdrop-blur-sm border-white/20 hover:bg-white/30">
              <div class="text-2xl sm:text-3xl font-bold text-accent-200">
                25+
              </div>
              <div class="text-sm text-white/90 font-medium">
                Años Sirviendo
              </div>
            </div>
            <div class="text-center hero-stat bg-white/20 backdrop-blur-sm border-white/20 hover:bg-white/30">
              <div class="text-2xl sm:text-3xl font-bold text-accent-200">
                5K+
              </div>
              <div class="text-sm text-white/90 font-medium">
                Clientes Confían
              </div>
            </div>
            <div class="text-center hero-stat bg-white/20 backdrop-blur-sm border-white/20 hover:bg-white/30">
              <div class="text-2xl sm:text-3xl font-bold text-accent-200">
                24/7
              </div>
              <div class="text-sm text-white/90 font-medium">
                Servicio Disponible
              </div>
            </div>
          </div>

          <!-- CTA Buttons -->
          <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start hero-buttons">
            <router-link
              to="/products"
              class="bg-white hover:bg-gray-50 text-gray-900 font-semibold text-base sm:text-lg px-6 sm:px-8 py-3 sm:py-4 rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 border border-white/30 hover:border-white/50"
            >
              Ver Productos
            </router-link>
            <router-link
              to="/contact"
              class="bg-accent-500 hover:bg-accent-600 text-white font-semibold text-base sm:text-lg px-6 sm:px-8 py-3 sm:py-4 rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 border border-accent-400/60 hover:border-accent-400"
            >
              Contactar Ahora
            </router-link>
          </div>
        </div>

        <!-- Hero Image/Illustration -->
        <div class="relative hero-illustration mt-8 lg:mt-0 order-1 lg:order-2">
          <div class="relative z-10">
            <!-- Main pharmacy illustration -->
            <div class="bg-white/20 backdrop-blur-glass rounded-2xl sm:rounded-3xl p-6 sm:p-8 shadow-2xl border border-white/30">
              <div class="text-center space-y-4 sm:space-y-6">
                <div class="w-40 sm:w-48 h-24 sm:h-32 mx-auto bg-white/30 rounded-xl flex items-center justify-center overflow-hidden shadow-inner border border-white/20">
                  <img
                    src="@/assets/images/medicamentos.jpg"
                    alt="Productos Farmacéuticos"
                    class="w-full h-full object-center object-cover rounded-lg"
                  />
                </div>
                <h3 class="text-lg sm:text-2xl font-bold text-white drop-shadow-lg">
                  Productos de Calidad
                </h3>
                <p class="text-sm sm:text-base text-white/90 font-medium">
                  Medicamentos originales y productos de salud confiables
                </p>

                <!-- Feature highlights -->
                <div class="grid grid-cols-2 gap-3 sm:gap-4 mt-6 sm:mt-8">
                  <div class="text-center p-3 sm:p-4 bg-white/20 rounded-xl border border-white/20 hover:bg-white/30 transition-all duration-200 shadow-lg">
                    <TruckIcon class="w-6 sm:w-8 h-6 sm:h-8 text-accent-200 mx-auto mb-2 drop-shadow-sm" />
                    <div class="text-xs sm:text-sm font-semibold text-white">
                      Entrega a Domicilio
                    </div>
                  </div>
                  <div class="text-center p-3 sm:p-4 bg-white/20 rounded-xl border border-white/20 hover:bg-white/30 transition-all duration-200 shadow-lg">
                    <ShieldIcon class="w-6 sm:w-8 h-6 sm:h-8 text-accent-200 mx-auto mb-2 drop-shadow-sm" />
                    <div class="text-xs sm:text-sm font-semibold text-white">
                      Asesoría Profesional
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Decorative elements -->
          <div class="absolute -top-4 -right-4 w-20 sm:w-24 h-20 sm:h-24 bg-accent-400/40 rounded-full blur-xl animate-pulse float-element-1 hidden lg:block" />
          <div class="absolute -bottom-4 -left-4 w-12 sm:w-16 h-12 sm:h-16 bg-secondary-400/40 rounded-full blur-lg animate-pulse float-element-2 hidden lg:block" />
        </div>
      </div>
    </div>

    <!-- Scroll indicator -->
    <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 scroll-indicator">
      <ChevronDownIcon class="w-6 h-6 text-white/70" />
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted, nextTick } from 'vue'
import { gsap } from 'gsap'
import { TruckIcon, ShieldIcon, ChevronDownIcon } from 'lucide-vue-next'

onMounted(async () => {
  await nextTick()

  // Small delay to ensure DOM is fully rendered
  setTimeout(() => {
    const tl = gsap.timeline()

    // Animate logo and title
    tl.from('.hero-logo', {
      x: -100,
      opacity: 0,
      duration: 1,
      ease: 'power3.out'
    })
    .from('.hero-title', {
      x: -50,
      opacity: 0,
      duration: 0.8,
      ease: 'power3.out'
    }, '-=0.5')
    .from('.hero-description', {
      y: 30,
      opacity: 0,
      duration: 0.8,
      ease: 'power2.out'
    }, '-=0.3')

    // Animate stats with stagger
    .from('.hero-stat', {
      y: 50,
      opacity: 0,
      duration: 0.6,
      stagger: 0.2,
      ease: 'back.out(1.7)'
    }, '-=0.5')

    // Animate CTA buttons
    .from('.hero-buttons', {
      y: 30,
      opacity: 0,
      duration: 0.8,
      ease: 'power2.out'
    }, '-=0.3')

    // Animate hero illustration
    .from('.hero-illustration', {
      scale: 0.8,
      opacity: 0,
      duration: 1,
      ease: 'power3.out'
    }, '-=0.8')

    // Animate floating elements
    gsap.to('.float-element-1', {
      y: 'random(-20, 20)',
      rotation: 'random(-10, 10)',
      duration: 6,
      ease: 'none',
      repeat: -1,
      yoyo: true
    })

    gsap.to('.float-element-2', {
      y: 'random(-30, 30)',
      rotation: 'random(-15, 15)',
      duration: 8,
      ease: 'none',
      repeat: -1,
      yoyo: true
    })

    // Animate scroll indicator
    gsap.to('.scroll-indicator', {
      y: 10,
      duration: 1.5,
      ease: 'power2.inOut',
      repeat: -1,
      yoyo: true
    })
  }, 100)
})
</script>

<style scoped>
.hero-gradient {
  background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #3b82f6 100%);
}

.hero-logo {
  filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
  transition: transform 0.3s ease;
}

.hero-logo:hover {
  transform: scale(1.05);
}

.hero-title {
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
  color: white;
  font-weight: 800;
}

.hero-title span {
  color: #fbbf24;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.hero-description {
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
  color: rgba(255, 255, 255, 0.95);
  font-weight: 500;
}

.hero-stat {
  backdrop-filter: blur(10px);
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 12px;
  padding: 1rem;
  transition: transform 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
}

.hero-stat:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  background: rgba(255, 255, 255, 0.12);
}

.hero-illustration {
  filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.2));
  transform: translateZ(0); /* Force hardware acceleration */
}

.scroll-indicator {
  animation: bounce 2s infinite;
}

@keyframes bounce {
  0%, 20%, 50%, 80%, 100% {
    transform: translateY(0);
  }
  40% {
    transform: translateY(-10px);
  }
  60% {
    transform: translateY(-5px);
  }
}
</style>