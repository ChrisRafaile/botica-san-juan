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
      /* El menu del portal enlazaba /products desde el principio, pero la ruta
         no estaba declarada: el enlace principal del catalogo llevaba a una
         pantalla en blanco. La vista existia y estaba terminada. */
      path: '/products',
      name: 'products',
      component: () => import('../client/ProductsView.vue'),
    },
    {
      /* La cabecera enlaza el icono del carrito a /cart desde el principio,
         pero la ruta nunca se declaro: el boton llevaba a "pagina no
         encontrada". Mismo fallo, y misma causa, que tuvo /products. */
      path: '/cart',
      name: 'cart',
      component: () => import('../client/CartView.vue'),
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
    /* Acceso y registro comparten pantalla: AuthView desliza un panel entre
       los dos formularios segun la ruta activa. Se mantienen las dos rutas
       para que los enlaces externos y el boton atras sigan funcionando. */
    {
      path: '/login',
      name: 'login',
      component: () => import('../auth/AuthView.vue'),
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../auth/AuthView.vue'),
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
    // Checkout. Las pantallas de resultado no deciden nada por si mismas:
    // cada una consulta al backend el estado real del pago y, si no coincide
    // con la ruta, redirige a la que corresponde. Navegar a mano a
    // /checkout/exitoso no convierte un pedido en pagado.
    {
      path: '/checkout/pago',
      name: 'checkout-pago',
      component: () => import('../client/checkout/CheckoutPagoView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/checkout/procesando',
      name: 'checkout-procesando',
      component: () => import('../client/checkout/CheckoutProcesandoView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/checkout/exitoso',
      name: 'checkout-exitoso',
      component: () => import('../client/checkout/CheckoutResultadoView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/checkout/error',
      name: 'checkout-error',
      component: () => import('../client/checkout/CheckoutResultadoView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/checkout/cancelado',
      name: 'checkout-cancelado',
      component: () => import('../client/checkout/CheckoutResultadoView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/checkout/pendiente',
      name: 'checkout-pendiente',
      component: () => import('../client/checkout/CheckoutResultadoView.vue'),
      meta: { requiresAuth: true },
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
          // Punto de venta de mostrador. Es la pantalla mas usada del sistema:
          // se abre al empezar el turno y se cierra al terminarlo.
          path: 'pos',
          name: 'admin-pos',
          component: () => import('../admin/views/AdminPosView.vue')
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
          // Conteo fisico por ciclos. Es lo que va poblando los lotes y las
          // fechas de vencimiento reales que hoy le faltan al FEFO.
          path: 'inventory/conteo',
          name: 'admin-inventory-conteo',
          component: () => import('../admin/views/AdminConteoView.vue'),
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
    {
      /* Comodin, SIEMPRE la ultima: vue-router evalua en orden y esta
         capturaria cualquier ruta declarada despues.

         Sin ella, una direccion equivocada no mostraba un error sino una
         pantalla vacia, que para quien llega desde un enlace viejo es
         indistinguible de un sistema caido. */
      path: '/:rutaInexistente(.*)*',
      name: 'no-encontrada',
      component: () => import('../client/NotFoundView.vue'),
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
