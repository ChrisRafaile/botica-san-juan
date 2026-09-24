import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // Se cargan las variables del entorno actual para poder configurar el proxy
  // sin fijar el puerto del backend en el código.
  const env = loadEnv(mode, process.cwd(), '')
  const destinoApi = env.VITE_API_PROXY_TARGET || 'http://127.0.0.1:8083'

  return {
    plugins: [vue(), vueDevTools()],

    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },

    server: {
      host: '0.0.0.0',
      port: Number(env.VITE_PORT) || 5173,

      // Si el puerto está ocupado, falla en lugar de desplazarse en silencio.
      //
      // No es una manía: cuando Vite se movía solo a otro puerto, el origen
      // dejaba de coincidir con CORS_ALLOWED_ORIGINS del backend y el inicio de
      // sesión fallaba con un error de red poco informativo. Es preferible un
      // fallo ruidoso al arrancar que un fallo silencioso al autenticarse.
      strictPort: true,

      // El proxy hace que, para el navegador, la API viva en el mismo origen
      // que la aplicación. Sin origen cruzado no hay comprobación CORS.
      proxy: {
        '/api': {
          target: destinoApi,
          changeOrigin: true,
          secure: false,
        },
      },
    },
  }
})
