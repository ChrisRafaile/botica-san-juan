<template>
  <header class="fixed top-0 w-full bg-white/95 backdrop-blur-md border-b border-gray-200/50 shadow-lg z-50">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <!-- Logo -->
        <div class="flex items-center">
          <router-link
            to="/"
            class="flex items-center space-x-2 group"
          >
            <img
              src="@/assets/images/logo botica.jpg"
              alt="Botica San Juan Logo"
              class="h-10 w-auto transition-transform group-hover:scale-105"
            />
            <span class="text-xl font-bold text-gray-900 hidden sm:block">Botica San Juan</span>
          </router-link>
        </div>

        <!-- Desktop Navigation -->
        <div class="hidden md:flex items-center space-x-4 flex-1 justify-center">
          <router-link
            v-for="item in navigation"
            :key="item.name"
            :to="item.href"
            class="text-gray-700 hover:text-primary px-2 py-2 text-sm font-medium transition-colors duration-200 relative group whitespace-nowrap"
            :class="{ 'text-primary': $route.path === item.href }"
          >
            {{ item.name }}
            <span
              class="absolute -bottom-1 left-0 w-0 h-0.5 bg-primary transition-all duration-200 group-hover:w-full"
              :class="{ 'w-full': $route.path === item.href }"
            />
          </router-link>
        </div>

        <!-- User Actions -->
        <div class="flex items-center space-x-3">
          <!-- Cart -->
          <router-link
            to="/cart"
            class="relative p-2 text-gray-700 hover:text-primary transition-colors duration-200 group"
          >
            <ShoppingCartIcon class="w-6 h-6" />
            <span
              v-if="cartItemCount > 0"
              class="absolute -top-1 -right-1 bg-accent text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"
            >
              {{ cartItemCount }}
            </span>
          </router-link>

          <!-- User Menu -->
          <div
            v-if="isAuthenticated"
            class="relative"
          >
            <button
              class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 transition-colors duration-200"
              @click="toggleUserMenu"
            >
              <UserIcon class="w-6 h-6 text-gray-700" />
              <ChevronDownIcon
                class="w-4 h-4 text-gray-500 transition-transform"
                :class="{ 'rotate-180': showUserMenu }"
              />
            </button>

            <!-- Dropdown Menu -->
            <div
              v-if="showUserMenu"
              class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50"
            >
              <router-link
                to="/profile"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
              >
                Mi Perfil
              </router-link>
              <router-link
                to="/orders"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
              >
                Mis Pedidos
              </router-link>
              <hr class="my-1" />
              <button
                class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                @click="logout"
              >
                Cerrar Sesión
              </button>
            </div>
          </div>

          <!-- Login/Register -->
          <div
            v-if="!isAuthenticated"
            class="hidden md:flex items-center space-x-3 ml-4"
          >
            <router-link
              to="/login"
              class="text-gray-700 hover:text-primary px-3 py-2 text-sm font-medium transition-colors duration-200"
            >
              Iniciar Sesión
            </router-link>
            <router-link
              to="/register"
              class="btn-primary text-sm"
            >
              Registrarse
            </router-link>
          </div>

          <!-- Mobile menu button -->
          <button
            class="md:hidden p-2 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors duration-200"
            @click="toggleMobileMenu"
          >
            <MenuIcon
              v-if="!showMobileMenu"
              class="w-6 h-6"
            />
            <XIcon
              v-else
              class="w-6 h-6"
            />
          </button>
        </div>
      </div>

      <!-- Mobile Navigation -->
      <div
        v-if="showMobileMenu"
        class="md:hidden fixed inset-0 z-40 bg-black/50 backdrop-blur-sm"
        @click="closeMobileMenu"
      >
        <div class="bg-white shadow-lg border-t border-gray-200">
          <div class="flex flex-col space-y-2 py-4 px-4">
            <router-link
              v-for="item in navigation"
              :key="item.name"
              :to="item.href"
              class="text-gray-700 hover:text-primary px-3 py-3 text-base font-medium transition-colors duration-200 rounded-md hover:bg-gray-50"
              :class="{ 'text-primary bg-primary-50': $route.path === item.href }"
              @click="closeMobileMenu"
            >
              {{ item.name }}
            </router-link>

            <hr
              v-if="!isAuthenticated"
              class="my-2"
            />

            <div
              v-if="!isAuthenticated"
              class="flex flex-col space-y-2 pt-2"
            >
              <router-link
                to="/login"
                class="text-gray-700 hover:text-primary px-3 py-3 text-base font-medium transition-colors duration-200 rounded-md hover:bg-gray-50"
                @click="closeMobileMenu"
              >
                Iniciar Sesión
              </router-link>
              <router-link
                to="/register"
                class="btn-primary text-base text-center rounded-md py-3"
                @click="closeMobileMenu"
              >
                Registrarse
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </nav>
  </header>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useCartStore } from '@/stores/carrito'
import { useAuthStore } from '@/stores/auth'
import {
  ShoppingCartIcon,
  UserIcon,
  ChevronDownIcon,
  MenuIcon,
  XIcon
} from 'lucide-vue-next'

const router = useRouter()
const cartStore = useCartStore()
const authStore = useAuthStore()

const showUserMenu = ref(false)
const showMobileMenu = ref(false)

const navigation = [
  { name: 'Inicio', href: '/' },
  { name: 'Productos', href: '/products' },
  { name: 'Servicios', href: '/services' },
  { name: 'Sobre Nosotros', href: '/about' },
  { name: 'Contacto', href: '/contact' },
  { name: 'Zonas de Cobertura', href: '/coverage' }
]

const cartItemCount = computed(() => cartStore.items.length)
const isAuthenticated = computed(() => authStore.isAuthenticated)

const toggleUserMenu = () => {
  showUserMenu.value = !showUserMenu.value
}

const toggleMobileMenu = () => {
  showMobileMenu.value = !showMobileMenu.value
}

const closeMobileMenu = () => {
  showMobileMenu.value = false
}

const logout = async () => {
  await authStore.logout()
  showUserMenu.value = false
  router.push('/')
}

// Close menus when clicking outside
const handleClickOutside = (event: Event) => {
  const target = event.target as HTMLElement
  if (!target.closest('.relative')) {
    showUserMenu.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>