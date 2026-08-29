import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('../client/HomeView.vue'),
    },
    {
      path: '/about',
      name: 'about',
      component: () => import('../client/AboutView.vue'),
    },
    {
      path: '/contact',
      name: 'contact',
      component: () => import('../client/ContactView.vue'),
    },
    {
      path: '/services',
      name: 'services',
      component: () => import('../client/ServicesView.vue'),
    },
    {
      path: '/coverage',
      name: 'coverage',
      component: () => import('../client/CoverageView.vue'),
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../auth/LoginView.vue'),
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../auth/RegisterView.vue'),
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('../auth/ForgotPasswordView.vue'),
    },
    {
      path: '/logout',
      name: 'logout',
      component: () => import('../auth/LogoutView.vue'),
    },
    {
      path: '/client/home',
      name: 'client-home',
      component: () => import('../client/ClientHomeView.vue'),
      meta: { requiresAuth: true, role: 'cliente' },
    },
    {
      path: '/admin',
      component: () => import('../admin/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true, role: 'administrador' },
      children: [
        {
          path: '',
          redirect: '/admin/home'
        },
        {
          path: 'home',
          name: 'admin-home',
          component: () => import('../admin/AdminHomeView.vue'),
        },
        {
          path: 'products',
          name: 'admin-products',
          component: () => import('../admin/views/AdminProductsView.vue'),
        },
        {
          path: 'products/add',
          name: 'admin-products-add',
          component: () => import('../admin/views/AdminProductsView.vue'),
        },
        {
          path: 'products/categories',
          name: 'admin-products-categories',
          component: () => import('../admin/views/AdminProductsView.vue'),
        },
        {
          path: 'products/subcategories',
          name: 'admin-products-subcategories',
          component: () => import('../admin/views/AdminProductsView.vue'),
        },
        {
          path: 'products/bulk-upload',
          name: 'admin-products-bulk-upload',
          component: () => import('../admin/views/AdminBulkUploadView.vue'),
        },
        {
          path: 'clients',
          name: 'admin-clients',
          component: () => import('../admin/views/AdminClientsView.vue'),
        },
        {
          path: 'clients/add',
          name: 'admin-clients-add',
          component: () => import('../admin/views/AdminClientsView.vue'),
        },
        {
          path: 'orders',
          name: 'admin-orders',
          component: () => import('../admin/views/AdminOrdersView.vue'),
        },
        {
          path: 'billing',
          name: 'admin-billing',
          component: () => import('../admin/views/AdminBillingView.vue'),
        },
        {
          path: 'billing/sunat',
          name: 'admin-billing-sunat',
          component: () => import('../admin/views/AdminBillingView.vue'),
        },
        {
          path: 'supply/suppliers',
          name: 'admin-suppliers',
          component: () => import('../admin/views/AdminSupplyView.vue'),
        },
        {
          path: 'supply/purchases',
          name: 'admin-purchases',
          component: () => import('../admin/views/AdminSupplyView.vue'),
        },
        {
          path: 'supply/digemid',
          name: 'admin-digemid',
          component: () => import('../admin/views/AdminSupplyView.vue'),
        },
        {
          path: 'reports',
          name: 'admin-reports',
          component: () => import('../admin/views/AdminReportsView.vue'),
        },
        {
          path: 'reports/sales',
          name: 'admin-reports-sales',
          component: () => import('../admin/views/AdminReportsView.vue'),
        },
        {
          path: 'reports/inventory',
          name: 'admin-reports-inventory',
          component: () => import('../admin/views/AdminReportsView.vue'),
        },
        {
          path: 'inventory',
          name: 'admin-inventory',
          component: () => import('../admin/views/AdminInventoryView.vue'),
        },
        {
          path: 'inventory/stock',
          name: 'admin-inventory-stock',
          component: () => import('../admin/views/AdminInventoryView.vue'),
        },
        {
          path: 'inventory/alerts',
          name: 'admin-inventory-alerts',
          component: () => import('../admin/views/AdminInventoryView.vue'),
        },
        {
          path: 'profile',
          name: 'admin-profile',
          component: () => import('../admin/views/AdminProfileView.vue'),
        },
        {
          path: 'settings',
          name: 'admin-settings',
          component: () => import('../admin/views/AdminSettingsView.vue'),
        },
      ],
    },
  ],
})

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  const isAuthenticated = authStore.isAuthenticated
  const userRole = authStore.user?.rol

  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login')
  } else if (to.meta.role && userRole !== to.meta.role) {
    next('/')
  } else {
    next()
  }
})

export default router
