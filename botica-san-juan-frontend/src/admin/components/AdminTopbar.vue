<script setup lang="ts">
/**
 * AdminTopbar · Botica San Juan
 * ---------------------------------------------------------------------------
 * Barra superior del panel: control de la lateral, migas de pan, buscador,
 * selector de tema, notificaciones y menú de usuario.
 *
 * Detalles que importan:
 * - El menú de usuario se cierra con Escape y al hacer clic fuera, y devuelve
 *   el foco al botón que lo abrió. Sin esto, quien navega con teclado queda
 *   atrapado al final del documento.
 * - El selector de tema ofrece los tres estados reales (claro, oscuro,
 *   sistema), no un interruptor de dos posiciones que ignora la preferencia
 *   del sistema operativo.
 * - `sticky` con `backdrop-blur` para que el contenido se desplace por debajo
 *   sin que la barra pierda legibilidad.
 */

import { ref, computed, onMounted, onUnmounted, useTemplateRef } from 'vue'
import {
  PanelLeft,
  Search,
  Bell,
  Sun,
  Moon,
  Monitor,
  LogOut,
  User as IconoUsuario,
  Settings as IconoAjustes,
} from 'lucide-vue-next'
import AdminBreadcrumbs from './AdminBreadcrumbs.vue'
import { useTheme, type PreferenciaTema } from '../../composables/useTheme'

const props = defineProps<{
  rutaActual: string
  colapsada: boolean
  usuario?: { nombre?: string; rol?: string } | null
  notificaciones?: number
}>()

const emit = defineEmits<{
  alternarLateral: []
  irPerfil: []
  irAjustes: []
  cerrarSesion: []
}>()

const { preferencia, esOscuro, establecerTema } = useTheme()

const menuAbierto = ref(false)
const contenedorMenu = useTemplateRef<HTMLElement>('contenedorMenu')
const botonMenu = useTemplateRef<HTMLButtonElement>('botonMenu')

const iniciales = computed(() => {
  const nombre = props.usuario?.nombre?.trim()
  if (!nombre) return 'AD'
  return nombre
    .split(/\s+/)
    .slice(0, 2)
    .map((parte) => parte.charAt(0).toUpperCase())
    .join('')
})

const opcionesTema: { valor: PreferenciaTema; etiqueta: string; icono: typeof Sun }[] = [
  { valor: 'claro', etiqueta: 'Claro', icono: Sun },
  { valor: 'oscuro', etiqueta: 'Oscuro', icono: Moon },
  { valor: 'sistema', etiqueta: 'Sistema', icono: Monitor },
]

function cerrarMenu(devolverFoco = false) {
  if (!menuAbierto.value) return
  menuAbierto.value = false
  if (devolverFoco) botonMenu.value?.focus()
}

function alClicFuera(evento: MouseEvent) {
  if (!menuAbierto.value) return
  if (!contenedorMenu.value?.contains(evento.target as Node)) {
    cerrarMenu()
  }
}

function alPulsarTecla(evento: KeyboardEvent) {
  if (evento.key === 'Escape') cerrarMenu(true)
}

onMounted(() => {
  document.addEventListener('click', alClicFuera)
  document.addEventListener('keydown', alPulsarTecla)
})

onUnmounted(() => {
  document.removeEventListener('click', alClicFuera)
  document.removeEventListener('keydown', alPulsarTecla)
})
</script>

<template>
  <header
    class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-borde-sutil bg-superficie-base/85 px-4 backdrop-blur-md"
  >
    <!-- Control de la barra lateral -->
    <button
      type="button"
      class="grid size-9 shrink-0 place-items-center rounded-lg text-texto-secundario transition-colors hover:bg-superficie-interactiva hover:text-texto-primario focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
      :aria-label="colapsada ? 'Expandir barra lateral' : 'Contraer barra lateral'"
      :aria-pressed="colapsada"
      @click="emit('alternarLateral')"
    >
      <PanelLeft
        class="size-[1.125rem]"
        aria-hidden="true"
      />
    </button>

    <div class="hidden min-w-0 md:block">
      <AdminBreadcrumbs :ruta-actual="rutaActual" />
    </div>

    <div class="flex-1" />

    <!-- Buscador -->
    <div class="relative hidden lg:block">
      <Search
        class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-texto-terciario"
        aria-hidden="true"
      />
      <input
        type="search"
        placeholder="Buscar producto, lote o comprobante…"
        class="h-9 w-72 rounded-lg border border-borde-sutil bg-superficie-hundida pl-9 pr-3 text-sm text-texto-primario placeholder:text-texto-terciario focus:border-borde-marca focus:outline-none focus:ring-2 focus:ring-botica-500/20"
        aria-label="Buscar en el panel"
      >
    </div>

    <!-- Selector de tema: grupo de tres opciones -->
    <div
      class="hidden items-center gap-0.5 rounded-lg border border-borde-sutil bg-superficie-hundida p-0.5 sm:flex"
      role="group"
      aria-label="Tema de la interfaz"
    >
      <button
        v-for="opcion in opcionesTema"
        :key="opcion.valor"
        type="button"
        class="grid size-7 place-items-center rounded-md transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
        :class="
          preferencia === opcion.valor
            ? 'bg-superficie-elevada text-texto-primario shadow-xs'
            : 'text-texto-terciario hover:text-texto-secundario'
        "
        :aria-pressed="preferencia === opcion.valor"
        :title="`Tema ${opcion.etiqueta.toLowerCase()}`"
        @click="establecerTema(opcion.valor)"
      >
        <component
          :is="opcion.icono"
          class="size-4"
          aria-hidden="true"
        />
        <span class="sr-only">Tema {{ opcion.etiqueta }}</span>
      </button>
    </div>

    <!-- Alterna claro/oscuro en pantallas pequeñas -->
    <button
      type="button"
      class="grid size-9 place-items-center rounded-lg text-texto-secundario transition-colors hover:bg-superficie-interactiva hover:text-texto-primario focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco sm:hidden"
      :aria-label="esOscuro ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
      @click="establecerTema(esOscuro ? 'claro' : 'oscuro')"
    >
      <component
        :is="esOscuro ? Sun : Moon"
        class="size-[1.125rem]"
        aria-hidden="true"
      />
    </button>

    <!-- Notificaciones -->
    <button
      type="button"
      class="relative grid size-9 place-items-center rounded-lg text-texto-secundario transition-colors hover:bg-superficie-interactiva hover:text-texto-primario focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
      :aria-label="
        notificaciones
          ? `Notificaciones: ${notificaciones} sin leer`
          : 'Notificaciones'
      "
    >
      <Bell
        class="size-[1.125rem]"
        aria-hidden="true"
      />
      <span
        v-if="notificaciones"
        class="absolute right-1.5 top-1.5 grid min-w-4 place-items-center rounded-full bg-venc-critico px-1 text-[0.625rem] font-semibold leading-4 text-white"
        aria-hidden="true"
      >{{ notificaciones > 9 ? '9+' : notificaciones }}</span>
    </button>

    <!-- Menú de usuario -->
    <div
      ref="contenedorMenu"
      class="relative"
    >
      <button
        ref="botonMenu"
        type="button"
        class="flex items-center gap-2 rounded-lg p-1 pr-2 transition-colors hover:bg-superficie-interactiva focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
        :aria-expanded="menuAbierto"
        aria-haspopup="menu"
        @click="menuAbierto = !menuAbierto"
      >
        <span
          class="grid size-8 place-items-center rounded-full bg-botica-700 text-xs font-semibold text-white"
          aria-hidden="true"
        >{{ iniciales }}</span>
        <span class="hidden text-left leading-tight lg:block">
          <span class="block max-w-32 truncate text-sm font-medium text-texto-primario">
            {{ usuario?.nombre || 'Administrador' }}
          </span>
          <span class="block max-w-32 truncate text-2xs text-texto-terciario">
            {{ usuario?.rol || 'Sin rol' }}
          </span>
        </span>
      </button>

      <Transition
        enter-active-class="transition duration-150 ease-[cubic-bezier(0.22,1,0.36,1)]"
        enter-from-class="opacity-0 scale-95 -translate-y-1"
        leave-active-class="transition duration-100 ease-in"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="menuAbierto"
          class="absolute right-0 top-full z-50 mt-2 w-56 origin-top-right overflow-hidden rounded-xl border border-borde-sutil bg-superficie-flotante shadow-lg"
          role="menu"
        >
          <div class="border-b border-borde-sutil px-3 py-2.5">
            <p class="truncate text-sm font-medium text-texto-primario">
              {{ usuario?.nombre || 'Administrador' }}
            </p>
            <p class="truncate text-xs text-texto-terciario">
              {{ usuario?.rol || 'Sin rol asignado' }}
            </p>
          </div>

          <div class="p-1">
            <button
              type="button"
              role="menuitem"
              class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-texto-secundario transition-colors hover:bg-superficie-interactiva hover:text-texto-primario focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-anillo-foco"
              @click="cerrarMenu(); emit('irPerfil')"
            >
              <IconoUsuario
                class="size-4"
                aria-hidden="true"
              />
              Mi perfil
            </button>
            <button
              type="button"
              role="menuitem"
              class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-texto-secundario transition-colors hover:bg-superficie-interactiva hover:text-texto-primario focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-anillo-foco"
              @click="cerrarMenu(); emit('irAjustes')"
            >
              <IconoAjustes
                class="size-4"
                aria-hidden="true"
              />
              Configuración
            </button>
          </div>

          <div class="border-t border-borde-sutil p-1">
            <button
              type="button"
              role="menuitem"
              class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-peligro-600 transition-colors hover:bg-peligro-50 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-anillo-foco dark:hover:bg-peligro-600/10"
              @click="cerrarMenu(); emit('cerrarSesion')"
            >
              <LogOut
                class="size-4"
                aria-hidden="true"
              />
              Cerrar sesión
            </button>
          </div>
        </div>
      </Transition>
    </div>
  </header>
</template>
