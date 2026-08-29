<template>
  <div
    class="admin-sidebar"
    :class="{ collapsed: collapsed, 'mobile-open': !collapsed && isMobile }"
  >
    <!-- Sidebar Header -->
    <div class="sidebar-header">
      <div class="logo-section">
        <div class="logo">
          <div class="logo-icon">
            <svg
              class="w-8 h-8 text-white"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fill-rule="evenodd"
                d="M10 2L3 7v11a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V7l-7-5z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
          <div
            v-if="!collapsed"
            class="logo-text"
          >
            <h2 class="text-white font-bold text-lg">
              Botica San Juan
            </h2>
            <p class="text-blue-200 text-xs">
              Panel Administrativo
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
      <div class="nav-section">
        <div
          v-for="item in menuItems"
          :key="item.id"
          class="nav-item"
        >
          <!-- Main Menu Item -->
          <div
            class="nav-link"
            :class="{ active: isActive(item.route), expanded: expandedItems.includes(item.id) }"
            @click="toggleItem(item)"
          >
            <div class="nav-icon">
              <component
                :is="getIcon(item.icon)"
                class="w-5 h-5"
              />
            </div>
            <span
              v-if="!collapsed"
              class="nav-label"
            >
              {{ item.label }}
            </span>
            <div
              v-if="!collapsed && item.children"
              class="nav-arrow"
            >
              <ChevronDown
                class="w-4 h-4 transition-transform duration-200"
                :class="{ 'rotate-180': expandedItems.includes(item.id) }"
              />
            </div>
          </div>

          <!-- Submenu Items -->
          <div
            v-if="!collapsed && item.children && expandedItems.includes(item.id)"
            class="submenu"
          >
            <div
              v-for="child in item.children"
              :key="child.id"
              class="submenu-link"
              :class="{ active: isActive(child.route) }"
              @click="handleNavigate(child.route)"
            >
              <span class="submenu-label">{{ child.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
      <div
        v-if="!collapsed"
        class="user-info"
      >
        <div class="user-avatar">
          <User class="w-6 h-6" />
        </div>
        <div class="user-details">
          <p class="user-name">
            {{ user?.nombre || 'Admin' }}
          </p>
          <p class="user-role">
            {{ user?.rol || 'Administrador' }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import {
  LayoutDashboard,
  Package,
  Users,
  ShoppingCart,
  ReceiptText,
  BarChart3,
  Settings,
  User,
  ChevronDown
} from 'lucide-vue-next'

interface MenuItem {
  id: string
  label: string
  icon: string
  route: string
  color?: string
  children?: SubMenuItem[]
}

interface SubMenuItem {
  id: string
  label: string
  route: string
}

interface Props {
  collapsed: boolean
  menuItems: MenuItem[]
  activeRoute: string
  user?: {
    nombre?: string
    rol?: string
  } | null
}

interface Emits {
  'update:collapsed': [value: boolean]
  'navigate': [route: string]
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const expandedItems = ref<string[]>([])
const isMobile = ref(false)

const collapsed = computed({
  get: () => props.collapsed,
  set: (value) => emit('update:collapsed', value)
})

const getIcon = (iconName: string) => {
  const icons = {
    LayoutDashboard,
    Package,
    Users,
    ShoppingCart,
    ReceiptText,
    BarChart3,
    Settings,
    User
  }
  return icons[iconName as keyof typeof icons] || LayoutDashboard
}

const isActive = (itemRoute: string) => {
  return props.activeRoute === itemRoute || props.activeRoute.startsWith(itemRoute + '/')
}

const toggleItem = (item: MenuItem) => {
  if (item.children) {
    const index = expandedItems.value.indexOf(item.id)
    if (index > -1) {
      expandedItems.value.splice(index, 1)
    } else {
      expandedItems.value.push(item.id)
    }
  } else {
    handleNavigate(item.route)
  }
}

const handleNavigate = (route: string) => {
  emit('navigate', route)
}

const checkMobile = () => {
  isMobile.value = window.innerWidth < 768
}

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

// Auto-expand active menu items
watch(() => props.activeRoute, () => {
  props.menuItems.forEach(item => {
    if (item.children) {
      const hasActiveChild = item.children.some(child => isActive(child.route))
      if (hasActiveChild && !expandedItems.value.includes(item.id)) {
        expandedItems.value.push(item.id)
      }
    }
  })
}, { immediate: true })
</script>

<style scoped>
.admin-sidebar {
  position: fixed;
  left: 0;
  top: 0;
  height: 100vh;
  width: 280px;
  background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
  box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  z-index: 1000;
  transition: all 0.3s ease-in-out;
}

.admin-sidebar.collapsed {
  width: 80px;
}

.admin-sidebar.mobile-open {
  transform: translateX(0);
}

/* Sidebar Header */
.sidebar-header {
  padding: 2rem 1.5rem;
  border-bottom: 1px solid #475569;
}

.logo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.logo-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: rgba(59, 130, 246, 0.2);
  border-radius: 10px;
}

.logo-text h2 {
  font-size: 1.125rem;
  font-weight: 700;
  margin: 0;
}

.logo-text p {
  margin: 0;
  font-size: 0.75rem;
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  padding: 1rem 0;
  overflow-y: auto;
}

.nav-section {
  padding: 0 0.5rem;
}

.nav-item {
  margin-bottom: 0.25rem;
}

.nav-link {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  color: #cbd5e1;
  text-decoration: none;
  border-radius: 0 25px 25px 0;
  margin-right: 1rem;
  transition: all 0.3s ease;
  cursor: pointer;
  position: relative;
}

.nav-link:hover {
  background: rgba(59, 130, 246, 0.1);
  color: #60a5fa;
}

.nav-link.active {
  background: rgba(59, 130, 246, 0.2);
  color: #60a5fa;
  border-left: 4px solid #60a5fa;
}

.nav-link.active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: #60a5fa;
  border-radius: 0 2px 2px 0;
}

.nav-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  margin-right: 0.75rem;
  flex-shrink: 0;
}

.nav-label {
  flex: 1;
  font-size: 0.875rem;
  font-weight: 500;
}

.nav-arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  transition: transform 0.2s ease;
}

/* Submenu */
.submenu {
  margin-left: 2.5rem;
  margin-top: 0.25rem;
  animation: slideDown 0.2s ease-out;
}

.submenu-link {
  padding: 0.5rem 1rem;
  color: #94a3b8;
  font-size: 0.8125rem;
  cursor: pointer;
  border-radius: 6px;
  margin-bottom: 0.125rem;
  transition: all 0.2s ease;
}

.submenu-link:hover {
  background: rgba(59, 130, 246, 0.1);
  color: #60a5fa;
}

.submenu-link.active {
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
  font-weight: 500;
}

/* Sidebar Footer */
.sidebar-footer {
  padding: 1.5rem;
  border-top: 1px solid #475569;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar {
  width: 32px;
  height: 32px;
  background: rgba(59, 130, 246, 0.2);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.user-details {
  flex: 1;
}

.user-name {
  font-size: 0.875rem;
  font-weight: 600;
  color: white;
  margin: 0;
}

.user-role {
  font-size: 0.75rem;
  color: #94a3b8;
  margin: 0;
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

/* Collapsed State */
.admin-sidebar.collapsed .logo-text,
.admin-sidebar.collapsed .nav-label,
.admin-sidebar.collapsed .nav-arrow,
.admin-sidebar.collapsed .submenu,
.admin-sidebar.collapsed .user-details {
  display: none;
}

.admin-sidebar.collapsed .nav-link {
  justify-content: center;
  padding: 0.75rem;
}

.admin-sidebar.collapsed .nav-icon {
  margin-right: 0;
}

/* Mobile Styles */
@media (max-width: 768px) {
  .admin-sidebar {
    transform: translateX(-100%);
  }

  .admin-sidebar.mobile-open {
    transform: translateX(0);
  }
}
</style>