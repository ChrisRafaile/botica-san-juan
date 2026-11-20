<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import {
  Bell,
  FileText,
  Home,
  LogOut,
  Menu,
  Package,
  PanelLeftClose,
  PanelLeftOpen,
  ShoppingCart,
  Store,
  User,
  X,
} from 'lucide-vue-next'
import BoticaAvatar from '@/admin/components/ui/BoticaAvatar.vue'
import AsistenteBoticaWidget from '@/components/AsistenteBoticaWidget.vue'
import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'
import SelectorTema from '@/components/SelectorTema.vue'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/carrito'
import { urlDeMedia } from '@/utils/media'
import {
  cargarPedidosCliente,
  formatearMoneda,
  pedidoRequierePago,
  type PedidoCliente,
} from '@/services/pedidosCliente'

const CLAVE_COLAPSADA = 'botica:cliente:lateral-colapsada'

interface EnlacePortal {
  nombre: string
  ruta: string
  icono: typeof Home
}

const ENLACES_PORTAL: EnlacePortal[] = [
  { nombre: 'Inicio', ruta: '/client/home', icono: Home },
  { nombre: 'Mis Pedidos', ruta: '/client/pedidos', icono: Package },
  { nombre: 'Mis Comprobantes', ruta: '/client/comprobantes', icono: FileText },
  { nombre: 'Mi Perfil', ruta: '/client/perfil', icono: User },
]

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const carrito = useCartStore()

function leerColapsada(): boolean {
  try {
    return localStorage.getItem(CLAVE_COLAPSADA) === '1'
  } catch {
    return false
  }
}

const lateralColapsada = ref(leerColapsada())
const drawerAbierto = ref(false)
const mostrarNotificaciones = ref(false)
const confirmarSalida = ref(false)
const pedidosPendientes = ref<PedidoCliente[]>([])

const drawerRef = ref<HTMLElement | null>(null)
const botonMenuRef = ref<HTMLButtonElement | null>(null)

let mediaDesktop: MediaQueryList | null = null

const fotoPerfil = computed(() => urlDeMedia(authStore.user?.foto_perfil) || undefined)
const primerNombre = computed(() => {
  const nombre = authStore.user?.nombre?.trim()
  if (!nombre) return 'Cliente'
  return nombre.split(/\s+/)[0] ?? 'Cliente'
})

const tituloSeccion = computed(() => {
  const metaTitulo = route.meta?.titulo
  if (typeof metaTitulo === 'string' && metaTitulo.length > 0) {
    return metaTitulo
  }
  const match = ENLACES_PORTAL.find((e) => route.path.startsWith(e.ruta))
  return match?.nombre ?? 'Portal del cliente'
})

function esRutaActiva(ruta: string): boolean {
  return route.path === ruta || route.path.startsWith(`${ruta}/`)
}

function alternarColapsoLateral(): void {
  lateralColapsada.value = !lateralColapsada.value
  try {
    localStorage.setItem(CLAVE_COLAPSADA, lateralColapsada.value ? '1' : '0')
  } catch {
    /* Almacenamiento restringido */
  }
}

async function abrirDrawer(): Promise<void> {
  drawerAbierto.value = true
  await nextTick()
  const primerFoco = drawerRef.value?.querySelector<HTMLElement>(
    'a[href], button:not([disabled])',
  )
  primerFoco?.focus()
}

function cerrarDrawer(restaurarFoco = false): void {
  if (!drawerAbierto.value) return
  drawerAbierto.value = false
  if (restaurarFoco) {
    nextTick(() => botonMenuRef.value?.focus())
  }
}

function atraparFocoDrawer(evento: KeyboardEvent): void {
  if (!drawerAbierto.value || evento.key !== 'Tab' || !drawerRef.value) return
  const focables = Array.from(
    drawerRef.value.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  )
  if (focables.length === 0) return
  const primero = focables[0]!
  const ultimo = focables[focables.length - 1]!
  if (evento.shiftKey && document.activeElement === primero) {
    evento.preventDefault()
    ultimo.focus()
  } else if (!evento.shiftKey && document.activeElement === ultimo) {
    evento.preventDefault()
    primero.focus()
  }
}

function alPulsarTecla(evento: KeyboardEvent): void {
  if (evento.key === 'Escape') {
    if (mostrarNotificaciones.value) {
      mostrarNotificaciones.value = false
      return
    }
    if (drawerAbierto.value) {
      cerrarDrawer(true)
    }
  }
}

function alCambiarTamano(evento: MediaQueryListEvent): void {
  if (evento.matches) {
    drawerAbierto.value = false
  }
}

async function cargarNotificacionesPago(): Promise<void> {
  const usuarioId = authStore.user?.id
  if (!usuarioId) return
  try {
    const lista = await cargarPedidosCliente(usuarioId)
    pedidosPendientes.value = lista.filter(pedidoRequierePago)
  } catch {
    pedidosPendientes.value = []
  }
}

function confirmarCerrarSesion(): void {
  confirmarSalida.value = false
  drawerAbierto.value = false
  mostrarNotificaciones.value = false
  void router.push('/logout')
}

watch(
  () => route.fullPath,
  () => {
    drawerAbierto.value = false
    mostrarNotificaciones.value = false
  },
)

onMounted(() => {
  void cargarNotificacionesPago()
  document.addEventListener('keydown', alPulsarTecla)
  if (typeof window !== 'undefined' && window.matchMedia) {
    mediaDesktop = window.matchMedia('(min-width: 1024px)')
    mediaDesktop.addEventListener('change', alCambiarTamano)
  }
})

onUnmounted(() => {
  document.removeEventListener('keydown', alPulsarTecla)
  mediaDesktop?.removeEventListener('change', alCambiarTamano)
})
</script>

<template>
  <div class="flex min-h-screen bg-superficie-base text-texto-primario">
    <!-- Barra lateral fija de escritorio -->
    <aside
      aria-label="Navegación del portal cliente"
      :class="[
        'hidden lg:flex lg:shrink-0 lg:flex-col border-r border-borde-sutil bg-superficie-elevada transition-[width] duration-200',
        lateralColapsada ? 'lg:w-20' : 'lg:w-64',
      ]"
    >
      <div class="flex h-16 items-center justify-between border-b border-borde-sutil px-4">
        <RouterLink
          to="/client/home"
          class="foco-dentro flex min-w-0 items-center gap-2.5 rounded-lg py-1"
        >
          <img
            src="@/assets/images/logo botica.jpg"
            alt="Botica San Juan"
            class="size-9 shrink-0 rounded-lg object-cover"
          />
          <div v-if="!lateralColapsada" class="min-w-0">
            <p class="truncate text-sm font-bold text-texto-primario">Botica San Juan</p>
            <p class="truncate text-xs text-texto-terciario">Portal del cliente</p>
          </div>
        </RouterLink>
      </div>

      <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto p-3" aria-label="Módulos del cliente">
        <RouterLink
          v-for="item in ENLACES_PORTAL"
          :key="item.ruta"
          :to="item.ruta"
          :title="lateralColapsada ? item.nombre : undefined"
          :class="[
            'foco-dentro flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
            lateralColapsada ? 'justify-center' : '',
            esRutaActiva(item.ruta)
              ? 'bg-botica-50 text-texto-marca dark:bg-botica-500/15 dark:text-botica-300'
              : 'text-texto-secundario hover:bg-superficie-interactiva hover:text-texto-primario',
          ]"
        >
          <component :is="item.icono" class="size-5 shrink-0" aria-hidden="true" />
          <span v-if="!lateralColapsada" class="truncate">{{ item.nombre }}</span>
        </RouterLink>

        <div class="my-3 border-t border-borde-sutil" />

        <RouterLink
          to="/products"
          :title="lateralColapsada ? 'Ir a la tienda' : undefined"
          :class="[
            'foco-dentro flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-texto-secundario transition-colors hover:bg-superficie-interactiva hover:text-texto-primario',
            lateralColapsada ? 'justify-center' : '',
          ]"
        >
          <Store class="size-5 shrink-0" aria-hidden="true" />
          <span v-if="!lateralColapsada" class="truncate">Ir a la tienda</span>
        </RouterLink>
      </nav>

      <div class="border-t border-borde-sutil p-3">
        <button
          type="button"
          :title="lateralColapsada ? 'Cerrar sesión' : undefined"
          :class="[
            'foco-dentro flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-texto-secundario transition-colors hover:bg-peligro-50 hover:text-peligro-700 dark:hover:bg-peligro-500/15 dark:hover:text-peligro-400',
            lateralColapsada ? 'justify-center' : '',
          ]"
          @click="confirmarSalida = true"
        >
          <LogOut class="size-5 shrink-0" aria-hidden="true" />
          <span v-if="!lateralColapsada">Cerrar sesión</span>
        </button>
      </div>
    </aside>

    <!-- Cajón móvil con trampa de foco -->
    <div
      v-if="drawerAbierto"
      class="fixed inset-0 z-50 flex lg:hidden"
      role="presentation"
    >
      <div
        class="fixed inset-0 bg-black/50 backdrop-blur-xs"
        aria-hidden="true"
        @click="cerrarDrawer(true)"
      />
      <aside
        id="drawer-portal-cliente"
        ref="drawerRef"
        role="dialog"
        aria-modal="true"
        aria-label="Menú del portal cliente"
        class="relative z-10 flex w-72 max-w-[85vw] flex-col bg-superficie-elevada shadow-xl"
        @keydown.tab="atraparFocoDrawer"
      >
        <div class="flex h-16 items-center justify-between border-b border-borde-sutil px-4">
          <RouterLink
            to="/client/home"
            class="foco-dentro flex items-center gap-2.5 rounded-lg"
            @click="cerrarDrawer(false)"
          >
            <img
              src="@/assets/images/logo botica.jpg"
              alt="Botica San Juan"
              class="size-9 rounded-lg object-cover"
            />
            <div>
              <p class="text-sm font-bold text-texto-primario">Botica San Juan</p>
              <p class="text-xs text-texto-terciario">Portal del cliente</p>
            </div>
          </RouterLink>
          <button
            type="button"
            class="foco-dentro rounded-lg p-2 text-texto-secundario hover:bg-superficie-interactiva hover:text-texto-primario"
            aria-label="Cerrar menú"
            @click="cerrarDrawer(true)"
          >
            <X class="size-5" aria-hidden="true" />
          </button>
        </div>

        <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto p-3" aria-label="Navegación móvil">
          <RouterLink
            v-for="item in ENLACES_PORTAL"
            :key="item.ruta"
            :to="item.ruta"
            :class="[
              'foco-dentro flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
              esRutaActiva(item.ruta)
                ? 'bg-botica-50 text-texto-marca dark:bg-botica-500/15 dark:text-botica-300'
                : 'text-texto-secundario hover:bg-superficie-interactiva hover:text-texto-primario',
            ]"
            @click="cerrarDrawer(false)"
          >
            <component :is="item.icono" class="size-5 shrink-0" aria-hidden="true" />
            <span>{{ item.nombre }}</span>
          </RouterLink>

          <div class="my-3 border-t border-borde-sutil" />

          <RouterLink
            to="/products"
            class="foco-dentro flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-texto-secundario transition-colors hover:bg-superficie-interactiva hover:text-texto-primario"
            @click="cerrarDrawer(false)"
          >
            <Store class="size-5 shrink-0" aria-hidden="true" />
            <span>Ir a la tienda</span>
          </RouterLink>
        </nav>

        <div class="border-t border-borde-sutil p-3">
          <button
            type="button"
            class="foco-dentro flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-texto-secundario transition-colors hover:bg-peligro-50 hover:text-peligro-700 dark:hover:bg-peligro-500/15 dark:hover:text-peligro-400"
            @click="confirmarSalida = true"
          >
            <LogOut class="size-5 shrink-0" aria-hidden="true" />
            <span>Cerrar sesión</span>
          </button>
        </div>
      </aside>
    </div>

    <!-- Columna principal -->
    <div class="flex min-w-0 flex-1 flex-col min-h-0">
      <header class="sticky top-0 z-30 border-b border-borde-sutil bg-superficie-elevada/95 backdrop-blur-md">
        <div class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
          <div class="flex min-w-0 items-center gap-2 sm:gap-3">
            <button
              ref="botonMenuRef"
              type="button"
              class="foco-dentro inline-flex items-center justify-center rounded-lg p-2 text-texto-secundario hover:bg-superficie-interactiva hover:text-texto-primario lg:hidden"
              aria-label="Abrir menú"
              aria-controls="drawer-portal-cliente"
              :aria-expanded="drawerAbierto"
              @click="abrirDrawer"
            >
              <Menu class="size-5" aria-hidden="true" />
            </button>

            <button
              type="button"
              class="foco-dentro hidden items-center justify-center rounded-lg p-2 text-texto-secundario hover:bg-superficie-interactiva hover:text-texto-primario lg:inline-flex"
              :aria-label="lateralColapsada ? 'Expandir barra lateral' : 'Colapsar barra lateral'"
              :aria-expanded="!lateralColapsada"
              @click="alternarColapsoLateral"
            >
              <PanelLeftOpen v-if="lateralColapsada" class="size-5" aria-hidden="true" />
              <PanelLeftClose v-else class="size-5" aria-hidden="true" />
            </button>

            <p class="truncate text-sm font-semibold text-texto-primario sm:text-base">
              {{ tituloSeccion }}
            </p>
          </div>

          <div class="flex items-center gap-1.5 sm:gap-2.5">
            <SelectorTema />

            <!-- Notificación de pedidos con pago pendiente -->
            <div class="relative">
              <button
                type="button"
                class="foco-dentro relative inline-flex items-center justify-center rounded-lg p-2 text-texto-secundario transition-colors hover:bg-superficie-interactiva hover:text-texto-primario"
                :aria-label="
                  pedidosPendientes.length > 0
                    ? `Pedidos pendientes de pago: ${pedidosPendientes.length}`
                    : 'Notificaciones de pedidos'
                "
                :aria-expanded="mostrarNotificaciones"
                @click="mostrarNotificaciones = !mostrarNotificaciones"
              >
                <Bell class="size-5" aria-hidden="true" />
                <span
                  v-if="pedidosPendientes.length > 0"
                  class="absolute -right-0.5 -top-0.5 inline-flex min-w-4.5 items-center justify-center rounded-full bg-ambar-600 px-1 text-[11px] font-bold leading-4 text-white"
                >
                  {{ pedidosPendientes.length }}
                </span>
              </button>

              <div
                v-if="mostrarNotificaciones"
                role="region"
                aria-label="Pedidos pendientes de pago"
                class="absolute right-0 mt-2 w-72 rounded-2xl border border-borde-sutil bg-superficie-elevada p-3 shadow-lg z-40"
              >
                <div class="mb-2 flex items-center justify-between">
                  <p class="text-xs font-semibold uppercase tracking-wide text-texto-terciario">
                    Pagos pendientes
                  </p>
                  <RouterLink
                    to="/client/pedidos?estado=pendiente"
                    class="text-xs font-semibold text-texto-marca hover:underline"
                    @click="mostrarNotificaciones = false"
                  >
                    Ver todos
                  </RouterLink>
                </div>

                <div v-if="pedidosPendientes.length === 0" class="py-3 text-center text-xs text-texto-terciario">
                  No tienes pedidos pendientes de pago.
                </div>
                <ul v-else class="space-y-2">
                  <li
                    v-for="pedido in pedidosPendientes.slice(0, 4)"
                    :key="pedido.id"
                    class="flex items-center justify-between gap-2 rounded-xl border border-borde-sutil bg-superficie-hundida px-3 py-2"
                  >
                    <div class="min-w-0">
                      <p class="text-xs font-semibold text-texto-primario">Pedido #{{ pedido.id }}</p>
                      <p class="text-xs text-texto-terciario">{{ formatearMoneda(pedido.total) }}</p>
                    </div>
                    <RouterLink
                      :to="`/checkout/pago?pedido=${pedido.id}`"
                      class="foco-dentro shrink-0 rounded-lg bg-botica-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-botica-700"
                      @click="mostrarNotificaciones = false"
                    >
                      Pagar
                    </RouterLink>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Indicador de carrito -->
            <RouterLink
              to="/cart"
              class="foco-dentro relative inline-flex items-center justify-center rounded-lg p-2 text-texto-secundario transition-colors hover:bg-superficie-interactiva hover:text-texto-primario"
              :aria-label="`Carrito de compras (${carrito.totalUnidades} productos)`"
            >
              <ShoppingCart class="size-5" aria-hidden="true" />
              <span
                v-if="carrito.totalUnidades > 0"
                data-testid="contador-carrito-cliente"
                class="absolute -right-0.5 -top-0.5 inline-flex min-w-4.5 items-center justify-center rounded-full bg-botica-600 px-1 text-[11px] font-bold leading-4 text-white"
              >
                {{ carrito.totalUnidades }}
              </span>
            </RouterLink>

            <!-- Acceso rápido a perfil -->
            <RouterLink
              to="/client/perfil"
              class="foco-dentro flex items-center gap-2 rounded-xl px-2 py-1 transition-colors hover:bg-superficie-interactiva"
              aria-label="Ir a mi perfil"
            >
              <BoticaAvatar :nombre="authStore.user?.nombre" :foto="fotoPerfil" tamano="sm" />
              <span class="hidden max-w-28 truncate text-sm font-medium text-texto-primario sm:inline">
                {{ primerNombre }}
              </span>
            </RouterLink>

            <!-- Botón de salida visible en móvil/escritorio -->
            <button
              type="button"
              class="foco-dentro inline-flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-sm font-medium text-texto-secundario transition-colors hover:bg-peligro-50 hover:text-peligro-700 dark:hover:bg-peligro-500/15 dark:hover:text-peligro-400"
              aria-label="Cerrar sesión"
              @click="confirmarSalida = true"
            >
              <LogOut class="size-4" aria-hidden="true" />
              <span class="hidden md:inline">Salir</span>
            </button>
          </div>
        </div>
      </header>

      <main id="contenido-principal" class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-6xl">
          <RouterView />
        </div>
      </main>
    </div>

    <DialogoConfirmacion
      id="cerrar-sesion-portal"
      :abierto="confirmarSalida"
      titulo="¿Cerrar sesión?"
      texto="Se cerrará tu sesión actual en este navegador."
      etiqueta-confirmar="Sí, cerrar sesión"
      etiqueta-cancelar="Seguir aquí"
      :peligroso="true"
      @confirmar="confirmarCerrarSesion"
      @cancelar="confirmarSalida = false"
    />
    <AsistenteBoticaWidget :rol="authStore.user?.rol ?? 'cliente'" />
  </div>
</template>
