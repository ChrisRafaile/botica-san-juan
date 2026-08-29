<template>
  <header class="admin-header">
    <div class="header-content">
      <!-- Left Section -->
      <div class="header-left">
        <!-- Mobile Menu Toggle -->
        <button
          class="menu-toggle"
          @click="$emit('toggle-sidebar')"
        >
          <Menu class="w-6 h-6" />
        </button>

        <!-- Breadcrumb -->
        <nav class="breadcrumb">
          <div class="breadcrumb-item">
            <Home class="w-4 h-4" />
            <span>Dashboard</span>
          </div>
          <ChevronRight class="w-4 h-4 breadcrumb-separator" />
          <div class="breadcrumb-item active">
            <span>{{ currentPage }}</span>
          </div>
        </nav>
      </div>

      <!-- Right Section -->
      <div class="header-right">
        <!-- Search -->
        <div class="search-box">
          <Search class="w-4 h-4 search-icon" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Buscar..."
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </div>

        <!-- Notifications -->
        <button class="notification-btn">
          <Bell class="w-5 h-5" />
          <span
            v-if="notificationCount > 0"
            class="notification-badge"
          >
            {{ notificationCount }}
          </span>
        </button>

        <!-- User Menu -->
        <div class="user-menu">
          <button
            class="user-menu-btn"
            @click="toggleUserMenu"
          >
            <div class="user-avatar">
              <User class="w-5 h-5" />
            </div>
            <div class="user-info">
              <span class="user-name">{{ user?.nombre || 'Admin' }}</span>
              <ChevronDown class="w-4 h-4 user-menu-arrow" />
            </div>
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="showUserMenu"
            class="user-dropdown"
          >
            <div
              class="dropdown-item"
              @click="navigateToProfile"
            >
              <User class="w-4 h-4" />
              <span>Perfil</span>
            </div>
            <div
              class="dropdown-item"
              @click="navigateToSettings"
            >
              <Settings class="w-4 h-4" />
              <span>Configuración</span>
            </div>
            <div class="dropdown-divider"></div>
            <div
              class="dropdown-item logout"
              @click="logout"
            >
              <LogOut class="w-4 h-4" />
              <span>Cerrar Sesión</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  Menu,
  Home,
  ChevronRight,
  Search,
  Bell,
  User,
  ChevronDown,
  Settings,
  LogOut
} from 'lucide-vue-next'

interface Props {
  user: {
    nombre?: string
    rol?: string
  } | null
  sidebarCollapsed: boolean
}

interface Emits {
  'toggle-sidebar': []
  'logout': []
  'navigate-profile': []
  'navigate-settings': []
}

defineProps<Props>()
const emit = defineEmits<Emits>()

const route = useRoute()
const searchQuery = ref('')
const showUserMenu = ref(false)
const notificationCount = ref(3)

const currentPage = computed(() => {
  const routeName = route.name as string
  const pageNames: Record<string, string> = {
    'admin-home': 'Dashboard',
    'admin-products': 'Productos',
    'admin-clients': 'Clientes',
    'admin-orders': 'Pedidos',
    'admin-reports': 'Reportes',
    'admin-reports-sales': 'Reportes de ventas',
    'admin-reports-inventory': 'Reportes de inventario',
    'admin-profile': 'Mi perfil',
    'admin-settings': 'Configuración'
  }
  return pageNames[routeName] || 'Dashboard'
})

const handleSearch = () => {
  if (searchQuery.value.trim()) {
    console.log('Searching for:', searchQuery.value)
    // Implement search logic here
  }
}

const toggleUserMenu = () => {
  showUserMenu.value = !showUserMenu.value
}

const navigateToProfile = () => {
  showUserMenu.value = false
  emit('navigate-profile')
}

const navigateToSettings = () => {
  showUserMenu.value = false
  emit('navigate-settings')
}

const logout = () => {
  showUserMenu.value = false
  emit('logout')
}

// Close dropdown when clicking outside
const handleClickOutside = (event: Event) => {
  const target = event.target as HTMLElement
  if (!target.closest('.user-menu')) {
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

<style scoped>
.admin-header {
  background: white;
  border-bottom: 1px solid #e5e7eb;
  padding: 1rem 2rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  max-width: 100%;
}

/* Left Section */
.header-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.menu-toggle {
  display: none;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: #f3f4f6;
  border: none;
  border-radius: 8px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s ease;
}

.menu-toggle:hover {
  background: #e5e7eb;
  color: #374151;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #6b7280;
}

.breadcrumb-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.breadcrumb-item.active {
  color: #374151;
  font-weight: 500;
}

.breadcrumb-separator {
  color: #d1d5db;
}

/* Right Section */
.header-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 0.75rem;
  color: #9ca3af;
  pointer-events: none;
}

.search-input {
  width: 250px;
  padding: 0.5rem 0.75rem 0.5rem 2.5rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.875rem;
  background: #f9fafb;
  transition: all 0.2s ease;
}

.search-input:focus {
  outline: none;
  border-color: #3b82f6;
  background: white;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.notification-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: #f3f4f6;
  border: none;
  border-radius: 8px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s ease;
}

.notification-btn:hover {
  background: #e5e7eb;
  color: #374151;
}

.notification-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #ef4444;
  color: white;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.125rem 0.375rem;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
}

/* User Menu */
.user-menu {
  position: relative;
}

.user-menu-btn {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem;
  background: none;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.user-menu-btn:hover {
  background: #f3f4f6;
}

.user-avatar {
  width: 32px;
  height: 32px;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.user-name {
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
}

.user-menu-arrow {
  color: #9ca3af;
  transition: transform 0.2s ease;
}

.user-menu-btn:hover .user-menu-arrow {
  transform: rotate(180deg);
}

.user-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 0.5rem;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  min-width: 200px;
  z-index: 1000;
  animation: slideDown 0.2s ease-out;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  color: #374151;
  cursor: pointer;
  transition: all 0.2s ease;
}

.dropdown-item:hover {
  background: #f9fafb;
}

.dropdown-item.logout {
  color: #ef4444;
}

.dropdown-item.logout:hover {
  background: #fef2f2;
}

.dropdown-divider {
  height: 1px;
  background: #e5e7eb;
  margin: 0.25rem 0;
}

/* Animations */
@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
  .admin-header {
    padding: 1rem;
  }

  .menu-toggle {
    display: flex;
  }

  .breadcrumb {
    display: none;
  }

  .search-input {
    width: 200px;
  }

  .user-info {
    display: none;
  }
}

@media (max-width: 640px) {
  .search-box {
    display: none;
  }

  .header-right {
    gap: 0.5rem;
  }
}
</style>