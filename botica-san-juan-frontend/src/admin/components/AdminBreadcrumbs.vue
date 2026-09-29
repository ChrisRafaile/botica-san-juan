<script setup lang="ts">
/**
 * AdminBreadcrumbs · Botica San Juan
 * ---------------------------------------------------------------------------
 * Migas de pan derivadas del menú, no escritas a mano en cada vista.
 *
 * La estructura sigue el patrón accesible estándar: <nav aria-label> con una
 * lista ordenada y `aria-current="page"` en el último tramo. Los separadores
 * son decorativos y quedan fuera del árbol de accesibilidad, para que un
 * lector de pantalla anuncie "Inventario, Alertas" y no "Inventario barra
 * Alertas".
 */

import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { ChevronRight, House } from 'lucide-vue-next'
import { useAdminMenu } from '../composables/useAdminMenu'

const props = defineProps<{ rutaActual: string }>()

const { migasPara } = useAdminMenu()

const migas = computed(() => migasPara(props.rutaActual))
</script>

<template>
  <nav
    v-if="migas.length"
    aria-label="Ruta de navegación"
    class="min-w-0"
  >
    <ol class="flex items-center gap-1 text-sm">
      <li class="flex items-center">
        <RouterLink
          to="/admin/home"
          class="flex items-center rounded-md p-1 text-texto-terciario transition-colors hover:text-texto-primario focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
          aria-label="Inicio del panel"
        >
          <House
            class="size-4"
            aria-hidden="true"
          />
        </RouterLink>
      </li>

      <li
        v-for="(miga, indice) in migas"
        :key="indice"
        class="flex min-w-0 items-center gap-1"
      >
        <ChevronRight
          class="size-3.5 shrink-0 text-texto-deshabilitado"
          aria-hidden="true"
        />

        <RouterLink
          v-if="miga.ruta && indice < migas.length - 1"
          :to="miga.ruta"
          class="truncate rounded-md px-1 py-0.5 text-texto-secundario transition-colors hover:text-texto-primario focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
        >
          {{ miga.etiqueta }}
        </RouterLink>

        <span
          v-else
          class="truncate px-1 py-0.5 font-medium text-texto-primario"
          aria-current="page"
        >
          {{ miga.etiqueta }}
        </span>
      </li>
    </ol>
  </nav>
</template>
