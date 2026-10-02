<template>
  <Teleport to="body">
    <div
      class="pointer-events-none fixed inset-x-0 top-4 z-[9999] flex flex-col items-center gap-3 px-4 sm:items-end sm:pr-6"
      role="region"
      aria-label="Notificaciones del sistema"
    >
      <TransitionGroup name="toast">
        <div
          v-for="n in notificaciones.items"
          :key="n.id"
          class="pointer-events-auto w-full max-w-md overflow-hidden rounded-xl border bg-superficie-elevada shadow-lg ring-1 ring-black/5"
          :class="estilo[n.tipo].borde"
          role="alert"
          :aria-live="n.tipo === 'error' ? 'assertive' : 'polite'"
        >
          <div class="flex gap-3 p-4">
            <div
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
              :class="estilo[n.tipo].fondoIcono"
              aria-hidden="true"
            >
              <component
                :is="estilo[n.tipo].icono"
                class="h-5 w-5"
                :class="estilo[n.tipo].colorIcono"
              />
            </div>

            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold text-texto-primario">{{ n.titulo }}</p>

              <p
                v-if="n.detalle"
                class="mt-1 text-sm leading-snug text-texto-secundario"
              >
                {{ n.detalle }}
              </p>

              <ul
                v-if="n.errores && n.errores.length"
                class="mt-2 space-y-1 text-sm text-texto-secundario"
              >
                <li
                  v-for="(e, i) in n.errores"
                  :key="i"
                  class="flex gap-1.5"
                >
                  <span class="text-texto-terciario">&bull;</span>
                  <span>{{ e }}</span>
                </li>
              </ul>
            </div>

            <button
              type="button"
              class="shrink-0 rounded-md p-1 text-texto-terciario transition hover:bg-superficie-interactiva hover:text-texto-secundario focus:outline-none focus:ring-2 focus:ring-botica-500"
              :aria-label="`Cerrar notificacion: ${n.titulo}`"
              @click="notificaciones.descartar(n.id)"
            >
              <XMarkIcon class="h-4 w-4" />
            </button>
          </div>

          <div
            v-if="n.duracion > 0"
            class="h-0.5 w-full origin-left"
            :class="estilo[n.tipo].barra"
            :style="{ animation: `toast-progreso ${n.duracion}ms linear forwards` }"
          />
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { markRaw } from 'vue'
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XCircleIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import notificaciones, { type TipoNotificacion } from '@/composables/useNotificaciones'

interface EstiloNotificacion {
  icono: unknown
  borde: string
  fondoIcono: string
  colorIcono: string
  barra: string
}

const estilo: Record<TipoNotificacion, EstiloNotificacion> = {
  exito: {
    icono: markRaw(CheckCircleIcon),
    borde: 'border-botica-200',
    fondoIcono: 'bg-botica-50',
    colorIcono: 'text-botica-600',
    barra: 'bg-botica-500',
  },
  error: {
    icono: markRaw(XCircleIcon),
    borde: 'border-peligro-500/30',
    fondoIcono: 'bg-peligro-50',
    colorIcono: 'text-peligro-600',
    barra: 'bg-peligro-500',
  },
  aviso: {
    icono: markRaw(ExclamationTriangleIcon),
    borde: 'border-ambar-200',
    fondoIcono: 'bg-ambar-50',
    colorIcono: 'text-ambar-600',
    barra: 'bg-ambar-500',
  },
  info: {
    icono: markRaw(InformationCircleIcon),
    borde: 'border-clinico-200',
    fondoIcono: 'bg-clinico-50',
    colorIcono: 'text-clinico-600',
    barra: 'bg-clinico-500',
  },
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast-enter-from {
  opacity: 0;
  transform: translateY(-12px) scale(0.97);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(24px) scale(0.97);
}

.toast-move {
  transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes toast-progreso {
  from {
    transform: scaleX(1);
  }
  to {
    transform: scaleX(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .toast-enter-active,
  .toast-leave-active,
  .toast-move {
    transition: none;
  }
}
</style>
