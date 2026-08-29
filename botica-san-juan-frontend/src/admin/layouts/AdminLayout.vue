<template>
  <div class="admin-layout">
    <!-- Sidebar -->
    <AdminSidebar
      v-model:collapsed="sidebarCollapsed"
      :menu-items="menuItems"
      :active-route="activeRoute"
      :user="user"
      @navigate="handleNavigate"
    />

    <!-- Main Content -->
    <div
      class="main-content"
      :class="{ 'sidebar-collapsed': sidebarCollapsed }"
    >
      <!-- Header -->
      <AdminHeader
        :user="user"
        :sidebar-collapsed="sidebarCollapsed"
        @toggle-sidebar="sidebarCollapsed = !sidebarCollapsed"
        @navigate-profile="goToProfile"
        @navigate-settings="goToSettings"
        @logout="handleLogout"
      />

      <!-- Page Content -->
      <div class="page-content">
        <router-view />
      </div>
    </div>

    <!-- Overlay for mobile -->
    <div
      v-if="!sidebarCollapsed && isMobile"
      class="sidebar-overlay"
      @click="sidebarCollapsed = true"
    />

    <AdminToastStack />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import AdminSidebar from '../components/AdminSidebar.vue'
import AdminHeader from '../components/AdminHeader.vue'
import AdminToastStack from '../components/AdminToastStack.vue'
import '../../styles/admin.css'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const sidebarCollapsed = ref(false)
const isMobile = ref(false)

const user = computed(() => authStore.user)
const activeRoute = computed(() => route.path)

const menuItems = [
  {
    id: 'dashboard',
    label: 'Dashboard',
    icon: 'LayoutDashboard',
    route: '/admin/home',
    color: 'from-blue-500 to-cyan-500'
  },
  {
    id: 'products',
    label: 'Productos',
    icon: 'Package',
    route: '/admin/products',
    color: 'from-emerald-500 to-teal-500',
    children: [
      { id: 'products-list', label: 'Ver Productos', route: '/admin/products' },
      { id: 'products-add', label: 'Agregar Producto', route: '/admin/products/add' },
      { id: 'products-categories', label: 'Categorías', route: '/admin/products/categories' },
      { id: 'products-subcategories', label: 'Subcategorías', route: '/admin/products/subcategories' }
    ]
  },
  {
    id: 'clients',
    label: 'Clientes',
    icon: 'Users',
    route: '/admin/clients',
    color: 'from-purple-500 to-pink-500',
    children: [
      { id: 'clients-list', label: 'Ver Clientes', route: '/admin/clients' },
      { id: 'clients-add', label: 'Agregar Cliente', route: '/admin/clients/add' }
    ]
  },
  {
    id: 'inventory',
    label: 'Inventario',
    icon: 'Package',
    route: '/admin/inventory',
    color: 'from-amber-500 to-yellow-500',
    children: [
      { id: 'inventory-list', label: 'Ver Inventario', route: '/admin/inventory' },
      { id: 'inventory-stock', label: 'Control de Stock', route: '/admin/inventory/stock' },
      { id: 'inventory-alerts', label: 'Alertas', route: '/admin/inventory/alerts' }
    ]
  },
  {
    id: 'billing',
    label: 'Facturación',
    icon: 'ReceiptText',
    route: '/admin/billing',
    color: 'from-cyan-500 to-blue-500',
    children: [
      { id: 'billing-main', label: 'Panel Facturación', route: '/admin/billing' },
      { id: 'billing-sunat', label: 'SUNAT Electrónica', route: '/admin/billing/sunat' }
    ]
  },
  {
    id: 'supply',
    label: 'Abastecimiento',
    icon: 'ShoppingCart',
    route: '/admin/supply/suppliers',
    color: 'from-teal-500 to-cyan-500',
    children: [
      { id: 'supply-suppliers', label: 'Proveedores', route: '/admin/supply/suppliers' },
      { id: 'supply-purchases', label: 'Compras', route: '/admin/supply/purchases' },
      { id: 'supply-digemid', label: 'DIGEMID', route: '/admin/supply/digemid' }
    ]
  },
  {
    id: 'reports',
    label: 'Reportes',
    icon: 'BarChart3',
    route: '/admin/reports',
    color: 'from-indigo-500 to-purple-500',
    children: [
      { id: 'reports-sales', label: 'Ventas', route: '/admin/reports/sales' },
      { id: 'reports-inventory', label: 'Inventario', route: '/admin/reports/inventory' }
    ]
  },
  {
    id: 'settings',
    label: 'Configuración',
    icon: 'Settings',
    route: '/admin/settings',
    color: 'from-gray-500 to-slate-500'
  }
]

const handleNavigate = (route: string) => {
  router.push(route)
  if (isMobile.value) {
    sidebarCollapsed.value = true
  }
}

const handleLogout = () => {
  router.push('/logout')
}

const goToProfile = () => {
  router.push('/admin/profile')
}

const goToSettings = () => {
  router.push('/admin/settings')
}

const checkMobile = () => {
  isMobile.value = window.innerWidth < 768
}

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})
</script>

<style scoped>
.admin-layout {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #cbd5e1 100%);
  display: flex;
}

/* CSS Variables para el layout */
.admin-layout {
  --admin-sidebar-width: 280px;
  --admin-sidebar-collapsed-width: 80px;
}

/* Main Content */
.main-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  transition: all 0.3s ease-in-out;
  margin-left: var(--admin-sidebar-width);
}

.main-content.sidebar-collapsed {
  margin-left: var(--admin-sidebar-collapsed-width);
}

/* Page Content */
.page-content {
  flex: 1;
  padding: 1.5rem;
  overflow: auto;
}

/* Sidebar Overlay */
.sidebar-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 10;
  display: none;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
  .main-content {
    margin-left: 0;
  }

  .main-content.sidebar-collapsed {
    margin-left: 0;
  }

  .sidebar-overlay {
    display: block;
  }
}
</style>