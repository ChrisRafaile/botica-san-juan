import { fileURLToPath, URL } from 'node:url'

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

/**
 * Configuración de las pruebas de componente.
 * ---------------------------------------------------------------------------
 * POR QUÉ UN ARCHIVO APARTE Y NO DENTRO DE `vite.config.ts`
 *
 * La configuración de desarrollo trae el plugin de devtools y un proxy hacia
 * la API con `strictPort`. Nada de eso tiene sentido ejecutando pruebas, y el
 * puerto estricto haría fallar la suite si el servidor de desarrollo está
 * levantado en paralelo, que es justo lo habitual mientras se programa.
 *
 * Aquí solo se reproduce lo imprescindible: el plugin de Vue —sin él los `.vue`
 * no se compilan— y el alias `@`, que usan todos los imports del proyecto.
 */
export default defineConfig({
  plugins: [vue()],

  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },

  test: {
    /* jsdom da un DOM de verdad: se puede montar un componente, buscar nodos y
       disparar eventos como `error` sobre una imagen, que es exactamente lo
       que hay que poder comprobar aquí. */
    environment: 'jsdom',

    include: ['src/**/__tests__/**/*.spec.ts'],

    /* Sin `globals`. `describe`, `it` y `expect` se importan explícitamente en
       cada archivo: cuesta una línea y a cambio el verificador de tipos no
       necesita tipos globales inyectados, de modo que una prueba mal escrita
       la detecta `vue-tsc` y no solo el ejecutor. */
    globals: false,

    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      include: ['src/components/**/*.vue', 'src/utils/**/*.ts'],
    },
  },
})
