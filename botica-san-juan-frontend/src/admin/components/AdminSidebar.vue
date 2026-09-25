<script setup lang="ts">
/**
 * AdminSidebar · Botica San Juan
 * ---------------------------------------------------------------------------
 * Barra lateral del panel administrativo.
 *
 * Cambios de fondo respecto a la versión anterior:
 *
 * 1. Navegación real. Antes los elementos eran <div> con @click, lo que los
 *    hacía invisibles para el teclado y para los lectores de pantalla, y
 *    rompía abrir en pestaña nueva con Ctrl+clic. Ahora son <RouterLink> para
 *    navegar y <button> para desplegar, dentro de listas <ul>/<li>.
 * 2. Estado accesible. aria-expanded, aria-controls y aria-current comunican
 *    el estado del menú a quien no puede verlo.
 * 3. Acordeón con GSAP, respetando `prefers-reduced-motion`.
 * 4. Sin colores incrustados: todo sale de los tokens del design system, por
 *    lo que el modo oscuro funciona sin una sola regla adicional.
 */

import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { ChevronDown } from 'lucide-vue-next'
import gsap from 'gsap'
import { useAdminMenu, type ElementoMenu } from '../composables/useAdminMenu'
import { useEstadoConexion } from '../composables/useEstadoConexion'
import BoticaAvatar from './ui/BoticaAvatar.vue'
import { urlDeMedia } from '@/utils/media'
import { EASE, DUR, duracion } from '../../utils/motion'

const props = defineProps<{
  /** Ruta activa actual, para resaltar y auto-desplegar la sección. */
  rutaActiva: string
  usuario?: {
    nombre?: string
    rol?: string
    foto?: string | null
    foto_perfil?: string | null
  } | null
}>()

/* ---------------------------------------------------------------------------
   Estado del sistema
   ---------------------------------------------------------------------------
   El indicador mide si el servidor responde, no si hay sesión abierta. En un
   mostrador es información operativa: sin servidor no se puede cobrar, y eso
   hay que saberlo antes de tener al cliente delante esperando.
*/
const {
  estado: estadoConexion,
  etiqueta: etiquetaConexion,
  animar: animarConexion,
} = useEstadoConexion()

const claseLatido = computed(() => {
  switch (estadoConexion.value) {
    case 'en-linea':
      return 'bg-exito-500'
    case 'lenta':
      return 'bg-alerta-500'
    case 'sin-conexion':
      return 'bg-peligro-500'
    default:
      return 'bg-neutro-400'
  }
})

/**
 * La foto puede venir en dos campos según por dónde se haya subido.
 *
 * Se prefiere `foto_perfil`, que es el que usa la pantalla de perfil, y se cae
 * a `foto` para las cuentas antiguas. La resolución de la ruta vive en
 * `utils/media` porque el mismo problema aparece con cada imagen que sirve la
 * API: una ruta relativa se resolvería contra el frontend y no contra el
 * backend, que en desarrollo son orígenes distintos.
 */
const fotoPerfil = computed(
  () => urlDeMedia(props.usuario?.foto_perfil || props.usuario?.foto),
)

/** Estado colapsado, sincronizado con el layout mediante v-model. */
const colapsada = defineModel<boolean>('colapsada', { default: false })

const { secciones } = useAdminMenu()

/** IDs de los elementos con submenú desplegado. */
const desplegados = ref<Set<string>>(new Set())

/* Referencias a los contenedores de submenú, indexadas por id de elemento.
   GSAP necesita el nodo real para animar su altura. */
const refsSubmenu = ref<Record<string, HTMLElement | null>>({})

function registrarSubmenu(id: string) {
  return (el: unknown) => {
    refsSubmenu.value[id] = (el as HTMLElement) ?? null
  }
}

function esRutaActiva(ruta: string): boolean {
  return props.rutaActiva === ruta || props.rutaActiva.startsWith(ruta + '/')
}

/** Un padre se marca activo si él o alguno de sus hijos lo está. */
function tieneHijoActivo(elemento: ElementoMenu): boolean {
  return elemento.hijos?.some((hijo) => esRutaActiva(hijo.ruta)) ?? false
}

function estaDesplegado(id: string): boolean {
  return desplegados.value.has(id)
}

/**
 * Anima la apertura o cierre de un submenú.
 *
 * `height: auto` en GSAP mide el destino real antes de animar, lo que permite
 * transicionar a una altura desconocida — algo que CSS puro no puede hacer sin
 * fijar un máximo arbitrario.
 */
function animarSubmenu(id: string, abrir: boolean) {
  const nodo = refsSubmenu.value[id]
  if (!nodo) return

  gsap.killTweensOf(nodo)

  if (abrir) {
    gsap.fromTo(
      nodo,
      { height: 0, opacity: 0 },
      {
        height: 'auto',
        opacity: 1,
        duration: duracion(DUR.normal),
        ease: EASE.salida,
        onComplete: () => {
          /* Se libera la altura para que el submenú se adapte si su contenido
             cambia después (por ejemplo, un badge que aparece). */
          gsap.set(nodo, { height: 'auto' })
        },
      },
    )
  } else {
    gsap.to(nodo, {
      height: 0,
      opacity: 0,
      duration: duracion(DUR.rapida),
      ease: EASE.entrada,
    })
  }
}

async function alternarDespliegue(elemento: ElementoMenu) {
  if (!elemento.hijos) return

  const abierto = estaDesplegado(elemento.id)
  if (abierto) {
    desplegados.value.delete(elemento.id)
    animarSubmenu(elemento.id, false)
  } else {
    desplegados.value.add(elemento.id)
    /* Se espera al render para que el nodo exista antes de medirlo. */
    await nextTick()
    animarSubmenu(elemento.id, true)
  }
  /* Set no es reactivo en profundidad: se reasigna para notificar a Vue. */
  desplegados.value = new Set(desplegados.value)
}

/* Al navegar, se despliega automáticamente la sección que contiene la ruta
   activa, para que el usuario nunca pierda de vista dónde está. */
watch(
  () => props.rutaActiva,
  async () => {
    let cambio = false
    for (const seccion of secciones) {
      for (const elemento of seccion.elementos) {
        if (tieneHijoActivo(elemento) && !desplegados.value.has(elemento.id)) {
          desplegados.value.add(elemento.id)
          cambio = true
        }
      }
    }
    if (cambio) {
      desplegados.value = new Set(desplegados.value)
      await nextTick()
      for (const id of desplegados.value) {
        const nodo = refsSubmenu.value[id]
        if (nodo) gsap.set(nodo, { height: 'auto', opacity: 1 })
      }
    }
  },
  { immediate: true },
)

/* Al colapsar la barra no tiene sentido mantener submenús abiertos: el ancho
   no da para mostrarlos. Se cierran sin animación para evitar un salto. */
watch(colapsada, (estaColapsada) => {
  if (estaColapsada) {
    for (const id of desplegados.value) {
      const nodo = refsSubmenu.value[id]
      if (nodo) gsap.set(nodo, { height: 0, opacity: 0 })
    }
  } else {
    nextTick(() => {
      for (const id of desplegados.value) {
        const nodo = refsSubmenu.value[id]
        if (nodo) gsap.set(nodo, { height: 'auto', opacity: 1 })
      }
    })
  }
})

/* ---------------------------------------------------------------------------
   Animación de entrada
   ---------------------------------------------------------------------------
   Sólo en el primer montaje y sólo al cargar el panel. El escalonado tiene un
   propósito: guía la mirada de arriba abajo la primera vez, para que se vea la
   estructura del menú antes de tener que buscar en él. Repetirlo en cada
   navegación sería un adorno que retrasa el trabajo, así que no se repite.

   `duracion()` devuelve 0 si el sistema pidió menos movimiento; en ese caso no
   se anima nada, ni siquiera un fundido.
*/
const raizNav = ref<HTMLElement | null>(null)
const pieUsuario = ref<HTMLElement | null>(null)

onMounted(() => {
  if (duracion(DUR.normal) === 0) return

  const linea = gsap.timeline()

  if (raizNav.value) {
    linea.from(raizNav.value.querySelectorAll('[data-anim-item]'), {
      opacity: 0,
      x: -8,
      duration: DUR.normal,
      ease: EASE.salida,
      stagger: 0.025,
    })
  }

  /* El pie entra al final y desde abajo: cierra el recorrido visual en lugar
     de competir con el menú por la atención. */
  if (pieUsuario.value) {
    linea.from(
      pieUsuario.value,
      {
        opacity: 0,
        y: 8,
        duration: DUR.normal,
        ease: EASE.salida,
      },
      '-=0.15',
    )
  }
})
</script>

<template>
  <aside
    class="flex h-dvh flex-col bg-lateral-fondo transition-[width] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
    :class="colapsada ? 'w-[4.75rem]' : 'w-[17rem]'"
    :style="{ boxShadow: 'var(--shadow-lateral)' }"
  >
    <!-- Identidad -->
    <div
      class="flex h-16 shrink-0 items-center gap-3 border-b border-lateral-borde px-4"
    >
      <div
        class="grid size-10 shrink-0 place-items-center rounded-xl bg-botica-600/20 ring-1 ring-inset ring-botica-400/25"
      >
        <!-- Mortero y pilón: símbolo de la botica -->
        <svg
          class="size-5 text-botica-200"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
        >
          <path d="M4 11h16a1 1 0 0 1 1 1 8 8 0 0 1-8 8h-2a8 8 0 0 1-8-8 1 1 0 0 1 1-1Z" />
          <path d="M12 19v3" />
          <path d="M8 22h8" />
          <path d="m17 8-3.5 3.5" />
          <path d="M14 4h6v6" />
        </svg>
      </div>

      <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="!colapsada"
          class="min-w-0 leading-tight"
        >
          <p class="truncate font-display text-md font-semibold text-lateral-activo-texto">
            Botica San Juan
          </p>
          <p class="truncate text-2xs text-lateral-texto-tenue">
            Panel administrativo
          </p>
        </div>
      </Transition>
    </div>

    <!-- Navegación -->
    <nav
      ref="raizNav"
      class="scroll-fino flex-1 overflow-y-auto overflow-x-hidden px-2.5 py-4"
      aria-label="Navegación principal del panel"
    >
      <div
        v-for="(seccion, indiceSeccion) in secciones"
        :key="seccion.id"
        :class="indiceSeccion > 0 ? 'mt-6' : ''"
      >
        <!-- Encabezado de sección: se oculta al colapsar, pero permanece
             disponible para lectores de pantalla. -->
        <p
          data-anim-item
          class="mb-2 px-2.5 text-2xs font-semibold uppercase tracking-wider text-lateral-texto-tenue"
          :class="colapsada ? 'sr-only' : ''"
        >
          {{ seccion.etiqueta }}
        </p>

        <ul class="space-y-0.5">
          <li
            v-for="elemento in seccion.elementos"
            :key="elemento.id"
            data-anim-item
          >
            <!-- Elemento con submenú -->
            <template v-if="elemento.hijos">
              <button
                type="button"
                class="group relative flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left text-sm font-medium text-lateral-texto transition-colors duration-150 hover:bg-lateral-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-botica-400"
                :class="[
                  tieneHijoActivo(elemento)
                    ? 'bg-lateral-activo-fondo text-lateral-activo-texto'
                    : '',
                  colapsada ? 'justify-center px-0' : '',
                ]"
                :aria-expanded="estaDesplegado(elemento.id)"
                :aria-controls="`submenu-${elemento.id}`"
                @click="alternarDespliegue(elemento)"
              >
                <!-- Indicador de sección activa -->
                <span
                  v-if="tieneHijoActivo(elemento)"
                  class="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-full bg-lateral-indicador"
                  aria-hidden="true"
                />
                <component
                  :is="elemento.icono"
                  class="size-[1.125rem] shrink-0"
                  aria-hidden="true"
                />
                <span
                  v-if="!colapsada"
                  class="flex-1 truncate"
                >{{ elemento.etiqueta }}</span>
                <ChevronDown
                  v-if="!colapsada"
                  class="size-4 shrink-0 text-lateral-texto-tenue transition-transform duration-200"
                  :class="estaDesplegado(elemento.id) ? 'rotate-180' : ''"
                  aria-hidden="true"
                />

                <!-- Etiqueta emergente cuando la barra está colapsada -->
                <span
                  v-if="colapsada"
                  class="pointer-events-none absolute left-full z-50 ml-2 hidden whitespace-nowrap rounded-md bg-superficie-flotante px-2 py-1 text-xs font-medium text-texto-primario shadow-md ring-1 ring-borde-sutil group-hover:block"
                  role="tooltip"
                >{{ elemento.etiqueta }}</span>
              </button>

              <!-- Submenú: height la controla GSAP, por eso arranca en 0 -->
              <div
                :id="`submenu-${elemento.id}`"
                :ref="registrarSubmenu(elemento.id)"
                class="overflow-hidden"
                :style="{ height: 0, opacity: 0 }"
              >
                <ul
                  v-if="!colapsada"
                  class="mt-0.5 space-y-0.5 border-l border-lateral-borde pb-1 pl-3 ml-[1.4rem]"
                >
                  <li
                    v-for="hijo in elemento.hijos"
                    :key="hijo.id"
                  >
                    <RouterLink
                      :to="hijo.ruta"
                      class="block rounded-md px-2.5 py-1.5 text-sm text-lateral-texto-tenue transition-colors duration-150 hover:bg-lateral-hover hover:text-lateral-texto focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-botica-400"
                      :class="
                        esRutaActiva(hijo.ruta)
                          ? 'bg-lateral-activo-fondo font-medium text-lateral-activo-texto'
                          : ''
                      "
                      :aria-current="esRutaActiva(hijo.ruta) ? 'page' : undefined"
                    >
                      {{ hijo.etiqueta }}
                      <span
                        v-if="hijo.descripcion"
                        class="sr-only"
                      >— {{ hijo.descripcion }}</span>
                    </RouterLink>
                  </li>
                </ul>
              </div>
            </template>

            <!-- Elemento sin submenú: enlace directo -->
            <RouterLink
              v-else
              :to="elemento.ruta"
              class="group relative flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium text-lateral-texto transition-colors duration-150 hover:bg-lateral-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-botica-400"
              :class="[
                esRutaActiva(elemento.ruta)
                  ? 'bg-lateral-activo-fondo text-lateral-activo-texto'
                  : '',
                colapsada ? 'justify-center px-0' : '',
              ]"
              :aria-current="esRutaActiva(elemento.ruta) ? 'page' : undefined"
            >
              <span
                v-if="esRutaActiva(elemento.ruta)"
                class="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-full bg-lateral-indicador"
                aria-hidden="true"
              />
              <component
                :is="elemento.icono"
                class="size-[1.125rem] shrink-0"
                aria-hidden="true"
              />
              <span
                v-if="!colapsada"
                class="truncate"
              >{{ elemento.etiqueta }}</span>

              <span
                v-if="colapsada"
                class="pointer-events-none absolute left-full z-50 ml-2 hidden whitespace-nowrap rounded-md bg-superficie-flotante px-2 py-1 text-xs font-medium text-texto-primario shadow-md ring-1 ring-borde-sutil group-hover:block"
                role="tooltip"
              >{{ elemento.etiqueta }}</span>
            </RouterLink>
          </li>
        </ul>
      </div>
    </nav>

    <!-- Usuario y estado del sistema -->
    <div
      ref="pieUsuario"
      class="shrink-0 border-t border-lateral-borde p-3"
    >
      <RouterLink
        to="/admin/profile"
        class="group/perfil flex items-center gap-3 rounded-xl px-1.5 py-1.5 transition-colors duration-200 hover:bg-lateral-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-botica-400"
        :class="colapsada ? 'justify-center' : ''"
        :title="colapsada ? `${usuario?.nombre ?? 'Administrador'} · ${etiquetaConexion}` : undefined"
      >
        <BoticaAvatar
          :nombre="usuario?.nombre"
          :foto="fotoPerfil"
          :estado="estadoConexion"
          tamano="md"
          class="transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] group-hover/perfil:scale-105"
        />

        <div
          v-if="!colapsada"
          class="min-w-0 flex-1 leading-tight"
        >
          <p class="truncate text-sm font-medium text-lateral-activo-texto">
            {{ usuario?.nombre || 'Administrador' }}
          </p>

          <p class="mt-0.5 flex items-center gap-1.5 text-2xs text-lateral-texto-tenue">
            <!-- El punto late sólo cuando todo va bien: una alerta que
                 parpadea igual que el estado normal no alerta de nada. -->
            <span class="relative flex size-1.5 shrink-0" aria-hidden="true">
              <span
                v-if="estadoConexion === 'en-linea' && animarConexion"
                class="absolute inline-flex size-full animate-ping rounded-full opacity-70"
                :class="claseLatido"
              />
              <span
                class="relative inline-flex size-1.5 rounded-full"
                :class="claseLatido"
              />
            </span>
            <span class="truncate">{{ etiquetaConexion }}</span>
          </p>
        </div>
      </RouterLink>

      <!-- Sin conexión el aviso sube de rango: deja de ser un punto pequeño y
           pasa a ocupar sitio, porque a partir de aquí no se puede cobrar. -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0"
      >
        <p
          v-if="estadoConexion === 'sin-conexion' && !colapsada"
          class="mt-2 rounded-lg bg-peligro-600/15 px-2.5 py-2 text-2xs leading-snug text-peligro-200 ring-1 ring-inset ring-peligro-500/30"
          role="status"
        >
          El sistema no responde. Avisa antes de seguir vendiendo.
        </p>
      </Transition>
    </div>
  </aside>
</template>
