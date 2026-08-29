<template>
  <div
    id="app"
    class="min-h-screen bg-gray-50"
  >
    <!-- Main Content - RouterView handles all page content including headers -->
    <RouterView />

    <!-- Notificaciones globales del sistema (errores de API, avisos, confirmaciones) -->
    <NotificacionesToast />
  </div>
</template>

<script setup lang="ts">
import { RouterView } from 'vue-router'
import NotificacionesToast from '@/components/NotificacionesToast.vue'
</script>

<style>
/* Global styles - removed conflicting universal reset */
html {
  scroll-behavior: smooth;
  width: 100%;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  width: 100%;
  min-height: 100vh;
}

/* Ensure full width layout */
#app {
  width: 100%;
  min-height: 100vh;
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f5f9;
}

::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

/* Utility classes */
.text-balance {
  text-wrap: balance;
}

/* Backdrop blur glass effect */
.backdrop-blur-glass {
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}
</style>
