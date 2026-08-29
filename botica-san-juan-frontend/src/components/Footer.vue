<template>
  <footer class="bg-gray-900 text-white">
    <!-- Main Footer Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 footer-content">
        <!-- Company Info -->
        <div class="footer-section">
          <div class="flex items-center mb-6">
            <div class="w-12 h-12 bg-primary-500 rounded-xl flex items-center justify-center mr-3">
              <PillIcon class="w-7 h-7 text-white" />
            </div>
            <div>
              <h3
                class="text-xl font-bold"
              >
                Boticas San Juan
              </h3>
              <p
                class="text-sm text-gray-400"
              >
                Tu salud, nuestra prioridad
              </p>
            </div>
          </div>
          <p class="text-gray-300 mb-6 leading-relaxed">
            Más de 15 años cuidando de tu salud y la de tu familia.
            Farmacia certificada con servicio de calidad y compromiso.
          </p>
          <div class="flex space-x-4">
            <a
              href="#"
              class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-primary-500 transition-colors duration-300"
            >
              <FacebookIcon class="w-5 h-5" />
            </a>
            <a
              href="#"
              class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-primary-500 transition-colors duration-300"
            >
              <InstagramIcon class="w-5 h-5" />
            </a>
            <a
              href="https://wa.me/51999999999"
              class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-green-500 transition-colors duration-300"
            >
              <MessageCircleIcon class="w-5 h-5" />
            </a>
            <a
              href="#"
              class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-primary-500 transition-colors duration-300"
            >
              <YoutubeIcon class="w-5 h-5" />
            </a>
          </div>
        </div>

        <!-- Quick Links -->
        <div class="footer-section">
          <h4
            class="text-lg font-semibold mb-6"
          >
            Enlaces Rápidos
          </h4>
          <ul class="space-y-3">
            <li>
              <router-link
                to="/"
                class="text-gray-300 hover:text-primary-400 transition-colors duration-300 flex items-center"
              >
                <HomeIcon class="w-4 h-4 mr-2" />
                Inicio
              </router-link>
            </li>
            <li>
              <router-link
                to="/products"
                class="text-gray-300 hover:text-primary-400 transition-colors duration-300 flex items-center"
              >
                <PackageIcon class="w-4 h-4 mr-2" />
                Productos
              </router-link>
            </li>
            <li>
              <router-link
                to="/services"
                class="text-gray-300 hover:text-primary-400 transition-colors duration-300 flex items-center"
              >
                <StethoscopeIcon class="w-4 h-4 mr-2" />
                Servicios
              </router-link>
            </li>
            <li>
              <router-link
                to="/about"
                class="text-gray-300 hover:text-primary-400 transition-colors duration-300 flex items-center"
              >
                <InfoIcon class="w-4 h-4 mr-2" />
                Sobre Nosotros
              </router-link>
            </li>
            <li>
              <router-link
                to="/contact"
                class="text-gray-300 hover:text-primary-400 transition-colors duration-300 flex items-center"
              >
                <PhoneIcon class="w-4 h-4 mr-2" />
                Contacto
              </router-link>
            </li>
          </ul>
        </div>

        <!-- Services -->
        <div class="footer-section">
          <h4
            class="text-lg font-semibold mb-6"
          >
            Nuestros Servicios
          </h4>
          <ul class="space-y-3">
            <li>
              <a
                href="#"
                class="text-gray-300 hover:text-primary-400 transition-colors duration-300 flex items-center"
              >
                <VideoIcon class="w-4 h-4 mr-2" />
                Telemedicina Aliviamed
              </a>
            </li>
            <li>
              <a
                href="#"
                class="text-gray-300 hover:text-primary-400 transition-colors duration-300 flex items-center"
              >
                <TruckIcon class="w-4 h-4 mr-2" />
                Delivery Express
              </a>
            </li>
            <li>
              <a
                href="#"
                class="text-gray-300 hover:text-primary-400 transition-colors duration-300 flex items-center"
              >
                <UsersIcon class="w-4 h-4 mr-2" />
                Asesoría Farmacéutica
              </a>
            </li>
            <li>
              <a
                href="#"
                class="text-gray-300 hover:text-primary-400 transition-colors duration-300 flex items-center"
              >
                <ShieldIcon class="w-4 h-4 mr-2" />
                Vacunación
              </a>
            </li>
            <li>
              <a
                href="#"
                class="text-gray-300 hover:text-primary-400 transition-colors duration-300 flex items-center"
              >
                <HeartIcon class="w-4 h-4 mr-2" />
                Programa Agora
              </a>
            </li>
          </ul>
        </div>

        <!-- Contact Info -->
        <div class="footer-section">
          <h4
            class="text-lg font-semibold mb-6"
          >
            Contáctanos
          </h4>
          <div class="space-y-4">
            <div class="flex items-start">
              <MapPinIcon class="w-5 h-5 text-primary-400 mr-3 mt-0.5 shrink-0" />
              <div>
                <p class="text-gray-300 text-sm">
                  Av. Santa Rosa 103<br />
                  Lima, Perú
                </p>
              </div>
            </div>
            <div class="flex items-center">
              <PhoneIcon class="w-5 h-5 text-primary-400 mr-3 shrink-0" />
              <div>
                <p class="text-gray-300 text-sm">
                  <a
                    href="tel:+5116772892"
                    class="hover:text-primary-400 transition-colors"
                  >
                    (01) 677-2892
                  </a>
                </p>
                <p
                  class="text-gray-400 text-xs"
                >
                  Línea principal
                </p>
              </div>
            </div>
            <div class="flex items-center">
              <MailIcon class="w-5 h-5 text-primary-400 mr-3 shrink-0" />
              <div>
                <p class="text-gray-300 text-sm">
                  <a
                    href="mailto:Boticassanjuan16@gmail.com"
                    class="hover:text-primary-400 transition-colors"
                  >
                    Boticassanjuan16@gmail.com
                  </a>
                </p>
                <p
                  class="text-gray-400 text-xs"
                >
                  Atención al cliente
                </p>
              </div>
            </div>
            <div class="flex items-center">
              <ClockIcon class="w-5 h-5 text-primary-400 mr-3 shrink-0" />
              <div>
                <p
                  class="text-gray-300 text-sm"
                >
                  Lun - Dom: 24/7
                </p>
                <p
                  class="text-gray-400 text-xs"
                >
                  Servicio continuo
                </p>
              </div>
            </div>
            <div class="flex items-center">
              <AwardIcon class="w-5 h-5 text-primary-400 mr-3 shrink-0" />
              <div>
                <p
                  class="text-gray-300 text-sm"
                >
                  R.U.C.: 10075148297
                </p>
                <p
                  class="text-gray-400 text-xs"
                >
                  Certificado DIGEMID
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Newsletter Section -->
    <div class="border-t border-gray-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col md:flex-row items-center justify-between footer-newsletter">
          <div class="mb-4 md:mb-0">
            <h4
              class="text-lg font-semibold mb-2"
            >
              Suscríbete a nuestro boletín
            </h4>
            <p class="text-gray-400 text-sm">
              Recibe ofertas exclusivas, consejos de salud y novedades de nuestros productos.
            </p>
          </div>
          <div class="flex w-full md:w-auto max-w-md">
            <input
              type="email"
              placeholder="Tu correo electrónico"
              class="flex-1 px-4 py-3 bg-gray-800 border border-gray-700 rounded-l-lg text-white placeholder-gray-400 focus:outline-none focus:border-primary-500"
            />
            <button class="px-6 py-3 bg-primary-500 hover:bg-primary-600 text-white font-semibold rounded-r-lg transition-colors duration-300">
              <SendIcon class="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Footer -->
    <div class="border-t border-gray-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col md:flex-row items-center justify-between footer-bottom">
          <div class="text-gray-400 text-sm mb-4 md:mb-0">
            © {{ currentYear }} Boticas San Juan. Todos los derechos reservados.
          </div>
          <div class="flex flex-wrap items-center gap-6 text-sm text-gray-400">
            <router-link
              to="/privacy"
              class="hover:text-primary-400 transition-colors"
            >
              Política de Privacidad
            </router-link>
            <router-link
              to="/terms"
              class="hover:text-primary-400 transition-colors"
            >
              Términos de Servicio
            </router-link>
            <router-link
              to="/cookies"
              class="hover:text-primary-400 transition-colors"
            >
              Política de Cookies
            </router-link>
            <div class="flex items-center">
              <AwardIcon class="w-4 h-4 text-primary-400 mr-1" />
              <span>Certificado DIGEMID</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup lang="ts">
import { onMounted, nextTick, computed } from 'vue'
import { gsap } from 'gsap'
import {
  PillIcon,
  FacebookIcon,
  InstagramIcon,
  MessageCircleIcon,
  YoutubeIcon,
  HomeIcon,
  PackageIcon,
  StethoscopeIcon,
  InfoIcon,
  PhoneIcon,
  VideoIcon,
  TruckIcon,
  UsersIcon,
  ShieldIcon,
  HeartIcon,
  MapPinIcon,
  MailIcon,
  ClockIcon,
  SendIcon,
  AwardIcon
} from 'lucide-vue-next'

const currentYear = computed(() => new Date().getFullYear())

onMounted(async () => {
  await nextTick()

  // Increased delay to ensure DOM and assets are fully rendered
  setTimeout(() => {
    const tl = gsap.timeline()

    // Set initial states explicitly
    gsap.set('.footer-section', { y: 50, opacity: 0 })
    gsap.set('.footer-newsletter', { y: 30, opacity: 0 })
    gsap.set('.footer-bottom', { y: 20, opacity: 0 })

    // Animate footer sections with stagger
    tl.to('.footer-section', {
      y: 0,
      opacity: 1,
      duration: 0.8,
      stagger: 0.15,
      ease: 'power2.out'
    })

    // Animate newsletter section
    .to('.footer-newsletter', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      ease: 'power2.out'
    }, '-=0.4')

    // Animate bottom footer
    .to('.footer-bottom', {
      y: 0,
      opacity: 1,
      duration: 0.6,
      ease: 'power2.out'
    }, '-=0.3')

    // Add hover animations for social media icons
    document.querySelectorAll('.footer-section a').forEach((link) => {
      link.addEventListener('mouseenter', () => {
        gsap.to(link, {
          scale: 1.05,
          duration: 0.2,
          ease: 'power2.out'
        })
      })

      link.addEventListener('mouseleave', () => {
        gsap.to(link, {
          scale: 1,
          duration: 0.2,
          ease: 'power2.out'
        })
      })
    })

    // Add hover animation for newsletter button
    const newsletterBtn = document.querySelector('.footer-newsletter button')
    if (newsletterBtn) {
      newsletterBtn.addEventListener('mouseenter', () => {
        gsap.to(newsletterBtn, {
          scale: 1.05,
          duration: 0.2,
          ease: 'power2.out'
        })
      })

      newsletterBtn.addEventListener('mouseleave', () => {
        gsap.to(newsletterBtn, {
          scale: 1,
          duration: 0.2,
          ease: 'power2.out'
        })
      })
    }
  }, 500)
})
</script>