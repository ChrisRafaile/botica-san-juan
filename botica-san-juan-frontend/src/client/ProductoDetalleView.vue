<script setup lang="ts">
/**
 * Ficha de detalle de producto en /catalogo/:id.
 *
 * Características:
 * 1. Galería con zoom acelerado por GPU (transform, opacity), respetando prefers-reduced-motion.
 * 2. Bloque de compra completo con precio, stock vendible, selector de cantidad y añadir al carrito.
 * 3. Acordeón accesible (Descripción, Principio Activo, Contraindicaciones/Precauciones).
 * 4. Carrusel de productos recomendados con scroll-snap y controles de navegación.
 * 5. Estados vacíos dignos para campos no presentes en el backend (no se inventan campos).
 */
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import Header from '@/components/Header.vue'
import Footer from '@/components/Footer.vue'
import productsService, { type Product } from '@/services/products'
import { useCartStore } from '@/stores/carrito'
import { urlDeMedia } from '@/utils/media'
import { ilustracionDe, textoAlternativoDe } from '@/utils/formaFarmaceutica'
import { prefiereMenosMovimiento } from '@/utils/motion'
import {
  ShoppingCartIcon,
  CheckIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ChevronDownIcon,
  AlertCircleIcon,
  FileTextIcon,
  ShieldAlertIcon,
  PillIcon,
  ZoomInIcon,
  ZoomOutIcon,
  TruckIcon,
  ShieldCheckIcon,
  ClockIcon,
  MinusIcon,
  PlusIcon,
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const cartStore = useCartStore()

const producto = ref<Product | null>(null)
const cargando = ref(true)
const errorCarga = ref<string | null>(null)
const recomendados = ref<Product[]>([])
const cargandoRecomendados = ref(false)

/* --- Imagen y Zoom GPU --------------------------------------------------- */
const imagenRota = ref(false)
const zoomActivo = ref(false)
const zoomManual = ref(false)
const zoomX = ref(50)
const zoomY = ref(50)
const vistaSeleccionada = ref<'principal' | 'forma'>('principal')

function marcarImagenRota() {
  imagenRota.value = true
}

const urlImagenResuelta = computed(() => {
  if (!producto.value?.imagen || imagenRota.value) return null
  return urlDeMedia(producto.value.imagen)
})

function manejarMovimientoRaton(e: MouseEvent) {
  if (prefiereMenosMovimiento() || !zoomActivo.value) return

  const target = e.currentTarget as HTMLElement
  const rect = target.getBoundingClientRect()
  const x = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100))
  const y = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100))

  zoomX.value = Math.round(x)
  zoomY.value = Math.round(y)
}

function activarZoom() {
  if (prefiereMenosMovimiento()) return
  zoomActivo.value = true
}

function desactivarZoom() {
  zoomActivo.value = false
  zoomX.value = 50
  zoomY.value = 50
}

function alternarZoomManual() {
  zoomManual.value = !zoomManual.value
  zoomActivo.value = zoomManual.value
  zoomX.value = 50
  zoomY.value = 50
}

/* --- Bloque de Compra ----------------------------------------------------- */
const cantidad = ref(1)
const agregadoRecientemente = ref(false)
let temporizadorAviso: ReturnType<typeof setTimeout> | undefined

const vendible = computed(() => {
  if (!producto.value) return 0
  return producto.value.stock_disponible ?? producto.value.stock ?? 0
})

const enCarrito = computed(() => {
  if (!producto.value) return 0
  return cartStore.cantidadDe(producto.value.id)
})

const restanteParaAgregar = computed(() => {
  return Math.max(0, vendible.value - enCarrito.value)
})

function incrementarCantidad() {
  if (cantidad.value < restanteParaAgregar.value && cantidad.value < 99) {
    cantidad.value++
  }
}

function decrementarCantidad() {
  if (cantidad.value > 1) {
    cantidad.value--
  }
}

function agregarAlCarrito() {
  if (!producto.value || vendible.value <= 0 || restanteParaAgregar.value <= 0) return

  const cantidadFinal = Math.min(cantidad.value, restanteParaAgregar.value)
  cartStore.agregar(producto.value.id, cantidadFinal, vendible.value)

  agregadoRecientemente.value = true
  clearTimeout(temporizadorAviso)
  temporizadorAviso = setTimeout(() => {
    agregadoRecientemente.value = false
  }, 2000)

  // Ajustar cantidad si se copó el stock
  if (cantidad.value > restanteParaAgregar.value) {
    cantidad.value = Math.max(1, restanteParaAgregar.value)
  }
}

const dinero = (valor?: number | null) =>
  new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(valor ?? 0)

/* --- Acordeón Accesible --------------------------------------------------- */
type SeccionAcordeon = 'descripcion' | 'principio_activo' | 'contraindicaciones'
const seccionesAbiertas = ref<Record<SeccionAcordeon, boolean>>({
  descripcion: true,
  principio_activo: true,
  contraindicaciones: false,
})

function alternarSeccion(seccion: SeccionAcordeon) {
  seccionesAbiertas.value[seccion] = !seccionesAbiertas.value[seccion]
}

/* --- Carrusel de Recomendados --------------------------------------------- */
const contenedorCarrusel = ref<HTMLElement | null>(null)

function desplazarCarrusel(direccion: 'ant' | 'sig') {
  if (!contenedorCarrusel.value) return
  const desplazamiento = contenedorCarrusel.value.clientWidth * 0.75
  contenedorCarrusel.value.scrollBy({
    left: direccion === 'sig' ? desplazamiento : -desplazamiento,
    behavior: prefiereMenosMovimiento() ? 'auto' : 'smooth',
  })
}

/* --- Carga de Datos ------------------------------------------------------- */
async function cargarProducto(id: number) {
  cargando.value = true
  errorCarga.value = null
  imagenRota.value = false
  vistaSeleccionada.value = 'principal'
  cantidad.value = 1
  zoomActivo.value = false
  zoomManual.value = false

  try {
    const data = await productsService.getProductById(id)
    producto.value = data

    // Cargar productos recomendados por forma farmacéutica o tipo
    void cargarRecomendados(data)
  } catch {
    errorCarga.value = 'No pudimos cargar la información del producto. Es posible que haya sido retirado o que la conexión haya fallado.'
  } finally {
    cargando.value = false
  }
}

async function cargarRecomendados(prod: Product) {
  cargandoRecomendados.value = true
  try {
    const res = await productsService.listar({
      tipo: prod.tipo || undefined,
      porPagina: 10,
    })
    // Excluir el producto actual
    recomendados.value = res.items.filter((item) => item.id !== prod.id).slice(0, 8)
  } catch {
    recomendados.value = []
  } finally {
    cargandoRecomendados.value = false
  }
}

watch(
  () => route.params.id,
  (nuevoId) => {
    if (nuevoId) {
      void cargarProducto(Number(nuevoId))
      window.scrollTo({
        top: 0,
        behavior: prefiereMenosMovimiento() ? 'auto' : 'smooth',
      })
    }
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  if (temporizadorAviso) clearTimeout(temporizadorAviso)
})
</script>

<template>
  <div class="min-h-screen bg-superficie-fondo text-texto-primario flex flex-col">
    <Header />

    <main class="flex-1 mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 w-full">
      <!-- Miga de pan accesible -->
      <nav aria-label="Miga de pan" class="mb-6 text-xs text-texto-secundario">
        <ol class="flex items-center gap-2 flex-wrap">
          <li>
            <RouterLink to="/" class="foco-dentro hover:text-botica-600 transition-colors">
              Inicio
            </RouterLink>
          </li>
          <li aria-hidden="true" class="text-texto-terciario">/</li>
          <li>
            <RouterLink to="/products" class="foco-dentro hover:text-botica-600 transition-colors">
              Catálogo
            </RouterLink>
          </li>
          <li aria-hidden="true" class="text-texto-terciario">/</li>
          <li v-if="producto" class="font-medium text-texto-primario truncate max-w-xs sm:max-w-md" aria-current="page">
            {{ producto.nombre }}
          </li>
        </ol>
      </nav>

      <!-- Estado: Cargando producto -->
      <div
        v-if="cargando"
        class="grid grid-cols-1 gap-8 lg:grid-cols-2 animate-pulse"
        aria-busy="true"
        aria-live="polite"
      >
        <div class="aspect-square w-full rounded-2xl bg-superficie-hundida" />
        <div class="space-y-4">
          <div class="h-6 w-1/3 rounded bg-superficie-hundida" />
          <div class="h-10 w-4/5 rounded bg-superficie-hundida" />
          <div class="h-5 w-1/2 rounded bg-superficie-hundida" />
          <div class="h-12 w-2/5 rounded bg-superficie-hundida" />
          <div class="h-12 w-full rounded bg-superficie-hundida" />
        </div>
      </div>

      <!-- Estado: Error de carga -->
      <div
        v-else-if="errorCarga || !producto"
        class="rounded-2xl border border-peligro-500/30 bg-peligro-50 p-10 text-center dark:bg-peligro-500/10"
        role="alert"
      >
        <AlertCircleIcon class="mx-auto size-12 text-peligro-600" aria-hidden="true" />
        <h1 class="mt-4 text-xl font-bold text-texto-primario">
          Producto no encontrado
        </h1>
        <p class="mt-2 text-sm text-texto-secundario max-w-md mx-auto">
          {{ errorCarga ?? 'El producto solicitado no está disponible en nuestro catálogo.' }}
        </p>
        <div class="mt-6 flex justify-center gap-4">
          <RouterLink
            to="/products"
            class="foco-dentro inline-flex items-center gap-2 rounded-xl bg-botica-600 px-5 py-2.5 font-medium text-white transition-colors hover:bg-botica-700"
          >
            Volver al catálogo
          </RouterLink>
        </div>
      </div>

      <!-- Ficha de Producto Principal -->
      <div v-else class="space-y-16">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:gap-12">
          <!-- =============================================================
               1. GALERÍA CON ZOOM GPU
               ============================================================= -->
          <section aria-label="Galería del producto" class="flex flex-col gap-4">
            <div
              class="relative aspect-square w-full overflow-hidden rounded-2xl border border-borde-sutil bg-superficie-elevada shadow-xs"
              @mousemove="manejarMovimientoRaton"
              @mouseenter="activarZoom"
              @mouseleave="desactivarZoom"
            >
              <!-- Imagen real con zoom GPU -->
              <div
                v-if="vistaSeleccionada === 'principal' && urlImagenResuelta"
                class="size-full overflow-hidden flex items-center justify-center p-4 cursor-crosshair"
              >
                <img
                  data-testid="imagen-producto"
                  :src="urlImagenResuelta"
                  :alt="producto.nombre"
                  width="600"
                  height="600"
                  class="size-full object-contain transition-transform"
                  :style="{
                    transform: (zoomActivo || zoomManual) ? 'scale(1.8)' : 'scale(1)',
                    transformOrigin: `${zoomX}% ${zoomY}%`,
                    willChange: 'transform',
                    transition: (zoomActivo && !zoomManual) ? 'none' : 'transform 200ms ease-out',
                  }"
                  @error="marcarImagenRota"
                />
              </div>

              <!-- Ilustración de la forma farmacéutica si no hay imagen propia o se seleccionó la vista -->
              <div
                v-else
                data-testid="ilustracion-referencial"
                class="flex size-full flex-col items-center justify-center p-8 bg-superficie-hundida"
              >
                <img
                  :src="ilustracionDe(producto.tipo)"
                  :alt="textoAlternativoDe(producto.tipo)"
                  width="192"
                  height="192"
                  class="size-48 max-w-full object-contain"
                />
                <span class="mt-4 rounded-full bg-neutro-900/80 px-3 py-1 text-xs font-medium text-white backdrop-blur-xs">
                  Imagen referencial · {{ producto.tipo || 'Medicamento' }}
                </span>
              </div>

              <!-- Botón accesible de Zoom Manual (para navegación con teclado y accesibilidad) -->
              <button
                type="button"
                class="foco-dentro absolute bottom-3 right-3 flex size-10 items-center justify-center rounded-xl bg-superficie-elevada/90 text-texto-primario shadow-md border border-borde-control hover:bg-superficie-interactiva transition-colors"
                :aria-label="zoomManual ? 'Restablecer tamaño de imagen' : 'Ampliar imagen de producto'"
                :aria-pressed="zoomManual"
                @click="alternarZoomManual"
              >
                <ZoomOutIcon v-if="zoomManual" class="size-5 text-botica-600" aria-hidden="true" />
                <ZoomInIcon v-else class="size-5" aria-hidden="true" />
              </button>
            </div>

            <!-- Miniaturas / Selector de vista (foto real vs ilustración de forma farmacéutica) -->
            <div class="flex items-center gap-3">
              <button
                v-if="urlImagenResuelta"
                type="button"
                class="foco-dentro flex size-16 items-center justify-center rounded-xl border-2 p-1 bg-superficie-elevada transition-colors"
                :class="vistaSeleccionada === 'principal' ? 'border-botica-600 ring-2 ring-botica-500/20' : 'border-borde-sutil hover:border-borde-control'"
                aria-label="Ver fotografía del producto"
                @click="vistaSeleccionada = 'principal'"
              >
                <img
                  :src="urlImagenResuelta"
                  :alt="producto.nombre"
                  class="size-full object-contain rounded-lg"
                />
              </button>

              <button
                type="button"
                class="foco-dentro flex size-16 items-center justify-center rounded-xl border-2 p-1.5 bg-superficie-elevada transition-colors"
                :class="vistaSeleccionada === 'forma' ? 'border-botica-600 ring-2 ring-botica-500/20' : 'border-borde-sutil hover:border-borde-control'"
                aria-label="Ver ilustración referencial de la forma farmacéutica"
                @click="vistaSeleccionada = 'forma'"
              >
                <img
                  :src="ilustracionDe(producto.tipo)"
                  :alt="textoAlternativoDe(producto.tipo)"
                  class="size-full object-contain"
                />
              </button>
            </div>
          </section>

          <!-- =============================================================
               2. BLOQUE DE COMPRA
               ============================================================= -->
          <section aria-labelledby="titulo-producto" class="flex flex-col">
            <!-- Categoría y Laboratorio -->
            <div class="flex items-center gap-2 text-xs uppercase tracking-wider text-texto-secundario">
              <span v-if="producto.categoria?.nombre" class="font-medium text-texto-acento">
                {{ producto.categoria.nombre }}
              </span>
              <span v-if="producto.categoria?.nombre && producto.laboratorio" aria-hidden="true">·</span>
              <span v-if="producto.laboratorio" class="font-semibold text-texto-terciario">
                {{ producto.laboratorio }}
              </span>
            </div>

            <!-- Título y Concentración -->
            <h1 id="titulo-producto" class="mt-2 text-2xl font-bold text-texto-primario sm:text-3xl">
              {{ producto.nombre }}
            </h1>

            <div class="mt-2 flex flex-wrap items-center gap-2">
              <span
                v-if="producto.concentracion"
                class="rounded-lg bg-clinico-50 px-2.5 py-1 text-xs font-semibold text-clinico-700 dark:bg-clinico-500/10 dark:text-clinico-400"
              >
                {{ producto.concentracion }}
              </span>
              <span
                v-if="producto.presentacion"
                class="rounded-lg bg-superficie-hundida px-2.5 py-1 text-xs text-texto-secundario"
              >
                {{ producto.presentacion }}
              </span>
              <span
                v-if="producto.tipo"
                class="rounded-lg bg-superficie-hundida px-2.5 py-1 text-xs text-texto-secundario"
              >
                Forma: {{ producto.tipo }}
              </span>
            </div>

            <!-- Advertencia de Receta Médica -->
            <div
              v-if="producto.requiere_receta"
              class="mt-4 flex items-center gap-2 rounded-xl border border-ambar-500/30 bg-ambar-50 px-3.5 py-2.5 text-xs font-semibold text-ambar-800 dark:bg-ambar-500/10 dark:text-ambar-300"
              role="note"
            >
              <ShieldAlertIcon class="size-4 shrink-0 text-ambar-600" aria-hidden="true" />
              <span>Venta bajo receta médica. Deberás presentarla al recibir tu pedido.</span>
            </div>

            <!-- Estado de Stock y Existencias -->
            <div class="mt-6 flex items-center gap-3">
              <span
                v-if="vendible <= 0"
                class="inline-flex items-center gap-1.5 rounded-full bg-peligro-600 px-3 py-1 text-xs font-bold text-white"
              >
                Agotado
              </span>
              <span
                v-else-if="vendible <= 5"
                class="inline-flex items-center gap-1.5 rounded-full bg-stock-bajo px-3 py-1 text-xs font-bold text-neutro-950"
              >
                Pocas unidades (Quedan {{ vendible }})
              </span>
              <span
                v-else
                class="inline-flex items-center gap-1.5 rounded-full border border-exito-500/30 bg-exito-50 px-3 py-1 text-xs font-semibold text-exito-700 dark:bg-exito-500/10 dark:text-exito-400"
              >
                <span class="size-2 rounded-full bg-exito-600" />
                En stock ({{ vendible }} disponibles)
              </span>
            </div>

            <!-- Precios -->
            <div class="mt-4 rounded-2xl border border-borde-sutil bg-superficie-elevada p-5 shadow-xs">
              <div class="flex items-baseline justify-between">
                <div>
                  <p class="text-xs text-texto-terciario">Precio por unidad</p>
                  <p class="cifras-tabulares text-3xl font-extrabold text-texto-marca">
                    {{ dinero(producto.precio) }}
                  </p>
                </div>

                <!-- Precios fraccionados (blíster / caja) si existen -->
                <div v-if="producto.venta_fraccionada" class="text-right space-y-1">
                  <p v-if="producto.precio_blister" class="text-xs text-texto-secundario">
                    Blíster: <strong class="text-texto-primario">{{ dinero(producto.precio_blister) }}</strong>
                    <span v-if="producto.unidades_por_blister" class="text-2xs text-texto-terciario">
                      ({{ producto.unidades_por_blister }} unid)
                    </span>
                  </p>
                  <p v-if="producto.precio_caja" class="text-xs text-texto-secundario">
                    Caja: <strong class="text-texto-primario">{{ dinero(producto.precio_caja) }}</strong>
                  </p>
                </div>
              </div>

              <!-- Controles de Compra: Cantidad y Botón Añadir -->
              <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                <!-- Selector de Cantidad -->
                <div class="flex items-center justify-between rounded-xl border border-borde-control bg-superficie-base p-1 w-full sm:w-36">
                  <button
                    type="button"
                    class="foco-dentro flex size-9 items-center justify-center rounded-lg text-texto-secundario hover:bg-superficie-interactiva hover:text-texto-primario disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="cantidad <= 1 || vendible <= 0"
                    aria-label="Disminuir cantidad"
                    @click="decrementarCantidad"
                  >
                    <MinusIcon class="size-4" aria-hidden="true" />
                  </button>

                  <span class="cifras-tabulares font-semibold text-sm text-texto-primario">
                    {{ cantidad }}
                  </span>

                  <button
                    type="button"
                    class="foco-dentro flex size-9 items-center justify-center rounded-lg text-texto-secundario hover:bg-superficie-interactiva hover:text-texto-primario disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="cantidad >= restanteParaAgregar || vendible <= 0"
                    aria-label="Aumentar cantidad"
                    @click="incrementarCantidad"
                  >
                    <PlusIcon class="size-4" aria-hidden="true" />
                  </button>
                </div>

                <!-- Botón de Añadir al carrito -->
                <button
                  type="button"
                  data-testid="boton-agregar"
                  class="foco-dentro flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-botica-600 px-6 py-3 font-semibold text-white shadow-md transition-colors hover:bg-botica-700 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="vendible <= 0 || restanteParaAgregar <= 0"
                  @click="agregarAlCarrito"
                >
                  <CheckIcon v-if="agregadoRecientemente" class="size-5" aria-hidden="true" />
                  <ShoppingCartIcon v-else class="size-5" aria-hidden="true" />

                  <template v-if="vendible <= 0">Agotado</template>
                  <template v-else-if="agregadoRecientemente">¡Agregado al carrito!</template>
                  <template v-else-if="restanteParaAgregar <= 0">Tienes todo el stock</template>
                  <template v-else>Añadir al carrito</template>
                </button>
              </div>

              <!-- Indicador de unidades en carrito -->
              <p
                v-if="enCarrito > 0"
                class="mt-3 text-xs text-center sm:text-left text-texto-secundario"
                aria-live="polite"
              >
                Tienes <strong class="text-botica-700 dark:text-botica-300">{{ enCarrito }}</strong> en tu carrito.
                <RouterLink to="/cart" class="foco-dentro underline ml-1 text-botica-600 hover:text-botica-700">
                  Ver carrito
                </RouterLink>
              </p>
            </div>

            <!-- Ventajas de Botica San Juan -->
            <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3 text-xs text-texto-secundario">
              <div class="flex items-center gap-2 rounded-xl bg-superficie-elevada p-3 border border-borde-sutil">
                <TruckIcon class="size-4 text-botica-600 shrink-0" aria-hidden="true" />
                <span>Delivery local en SJL</span>
              </div>
              <div class="flex items-center gap-2 rounded-xl bg-superficie-elevada p-3 border border-borde-sutil">
                <ShieldCheckIcon class="size-4 text-botica-600 shrink-0" aria-hidden="true" />
                <span>Certificado DIGEMID</span>
              </div>
              <div class="flex items-center gap-2 rounded-xl bg-superficie-elevada p-3 border border-borde-sutil">
                <ClockIcon class="size-4 text-botica-600 shrink-0" aria-hidden="true" />
                <span>Atención farmacéutica</span>
              </div>
            </div>
          </section>
        </div>

        <!-- =============================================================
             3. ACORDEÓN DESPLEGABLE ACCESIBLE
             ============================================================= -->
        <section aria-label="Información técnica del medicamento" class="rounded-2xl border border-borde-sutil bg-superficie-elevada shadow-xs overflow-hidden">
          <!-- 3.1 Descripción -->
          <div class="border-b border-borde-sutil">
            <button
              id="acordeon-btn-descripcion"
              type="button"
              class="foco-dentro flex w-full items-center justify-between p-5 text-left font-bold text-texto-primario transition-colors hover:bg-superficie-interactiva"
              :aria-expanded="seccionesAbiertas.descripcion"
              aria-controls="acordeon-panel-descripcion"
              @click="alternarSeccion('descripcion')"
            >
              <span class="flex items-center gap-3">
                <FileTextIcon class="size-5 text-botica-600" aria-hidden="true" />
                <span>Descripción</span>
              </span>
              <ChevronDownIcon
                class="size-5 text-texto-secundario transition-transform duration-200"
                :class="{ 'rotate-180': seccionesAbiertas.descripcion }"
                aria-hidden="true"
              />
            </button>

            <div
              v-show="seccionesAbiertas.descripcion"
              id="acordeon-panel-descripcion"
              role="region"
              aria-labelledby="acordeon-btn-descripcion"
              class="px-5 pb-5 text-sm text-texto-secundario"
            >
              <div v-if="producto.adicional" class="leading-relaxed">
                {{ producto.adicional }}
              </div>
              <!-- Estado vacío digno si el producto no tiene descripción en la base de datos -->
              <div v-else class="rounded-xl bg-superficie-hundida p-4 text-xs leading-relaxed text-texto-terciario">
                <p class="font-medium text-texto-secundario mb-1">Sin descripción adicional registrada</p>
                <p>
                  Este producto se expende en su presentación de {{ producto.presentacion || 'forma estándar' }}. Para mayor información sobre posología, modo de uso o conservación, consulte a nuestro farmacéutico o al profesional médico tratante.
                </p>
              </div>

              <!-- Ficha de atributos disponibles -->
              <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                <div v-if="producto.laboratorio_fabricante || producto.laboratorio">
                  <dt class="font-medium text-texto-terciario">Laboratorio:</dt>
                  <dd class="text-texto-primario">{{ producto.laboratorio_fabricante || producto.laboratorio }}</dd>
                </div>
                <div v-if="producto.codigo_digemid">
                  <dt class="font-medium text-texto-terciario">Código DIGEMID:</dt>
                  <dd class="text-texto-primario font-mono">{{ producto.codigo_digemid }}</dd>
                </div>
                <div v-if="producto.codigo_barras">
                  <dt class="font-medium text-texto-terciario">Código de barras:</dt>
                  <dd class="text-texto-primario font-mono">{{ producto.codigo_barras }}</dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- 3.2 Principio Activo -->
          <div class="border-b border-borde-sutil">
            <button
              id="acordeon-btn-principio-activo"
              type="button"
              class="foco-dentro flex w-full items-center justify-between p-5 text-left font-bold text-texto-primario transition-colors hover:bg-superficie-interactiva"
              :aria-expanded="seccionesAbiertas.principio_activo"
              aria-controls="acordeon-panel-principio-activo"
              @click="alternarSeccion('principio_activo')"
            >
              <span class="flex items-center gap-3">
                <PillIcon class="size-5 text-botica-600" aria-hidden="true" />
                <span>Principio Activo</span>
              </span>
              <ChevronDownIcon
                class="size-5 text-texto-secundario transition-transform duration-200"
                :class="{ 'rotate-180': seccionesAbiertas.principio_activo }"
                aria-hidden="true"
              />
            </button>

            <div
              v-show="seccionesAbiertas.principio_activo"
              id="acordeon-panel-principio-activo"
              role="region"
              aria-labelledby="acordeon-btn-principio-activo"
              class="px-5 pb-5 text-sm text-texto-secundario"
            >
              <div v-if="producto.principio_activo" class="space-y-2">
                <p class="font-semibold text-texto-primario text-base">
                  {{ producto.principio_activo }}
                </p>
                <p v-if="producto.concentracion" class="text-xs text-texto-secundario">
                  Concentración: <strong class="text-texto-primario">{{ producto.concentracion }}</strong>
                </p>
                <p class="text-xs text-texto-terciario leading-relaxed">
                  El principio activo es la sustancia responsable del efecto terapéutico del medicamento. Permite identificar medicamentos equivalentes genéricos de marca o esenciales.
                </p>
              </div>

              <!-- Estado vacío digno si no está registrado el principio activo en la base de datos -->
              <div v-else class="rounded-xl bg-superficie-hundida p-4 text-xs leading-relaxed text-texto-terciario">
                <p class="font-medium text-texto-secundario mb-1">Información de principio activo no especificada</p>
                <p>
                  No se encuentra registrado el principio activo en la ficha digital de este ítem. Puede solicitar asistencia directa a nuestro químico farmacéutico mediante el canal de WhatsApp o en nuestro establecimiento.
                </p>
              </div>
            </div>
          </div>

          <!-- 3.3 Contraindicaciones y Precauciones -->
          <div>
            <button
              id="acordeon-btn-contraindicaciones"
              type="button"
              class="foco-dentro flex w-full items-center justify-between p-5 text-left font-bold text-texto-primario transition-colors hover:bg-superficie-interactiva"
              :aria-expanded="seccionesAbiertas.contraindicaciones"
              aria-controls="acordeon-panel-contraindicaciones"
              @click="alternarSeccion('contraindicaciones')"
            >
              <span class="flex items-center gap-3">
                <ShieldAlertIcon class="size-5 text-botica-600" aria-hidden="true" />
                <span>Contraindicaciones y Precauciones</span>
              </span>
              <ChevronDownIcon
                class="size-5 text-texto-secundario transition-transform duration-200"
                :class="{ 'rotate-180': seccionesAbiertas.contraindicaciones }"
                aria-hidden="true"
              />
            </button>

            <div
              v-show="seccionesAbiertas.contraindicaciones"
              id="acordeon-panel-contraindicaciones"
              role="region"
              aria-labelledby="acordeon-btn-contraindicaciones"
              class="px-5 pb-5 text-sm text-texto-secundario"
            >
              <!-- Estado vacío digno: el esquema de la base de datos no contiene este campo -->
              <div class="rounded-xl bg-superficie-hundida p-4 text-xs leading-relaxed text-texto-terciario space-y-2">
                <p class="font-semibold text-texto-primario">
                  Recomendaciones generales de seguridad
                </p>
                <p>
                  No se registran contraindicaciones específicas en la base de datos digital para este producto.
                </p>
                <ul class="list-disc list-inside space-y-1 text-2xs text-texto-secundario">
                  <li>No administrar en caso de alergia o hipersensibilidad conocida a los componentes de la fórmula.</li>
                  <li>Si está embarazada, en período de lactancia o bajo tratamiento crónico, consulte a su médico antes de consumir.</li>
                  <li>Manténgase fuera del alcance y de la vista de los niños.</li>
                  <li>Conservar a temperatura ambiente adecuada, protegido del calor excesivo y la humedad.</li>
                </ul>
              </div>
            </div>
          </div>
        </section>

        <!-- =============================================================
             4. CARRUSEL DE PRODUCTOS RECOMENDADOS
             ============================================================= -->
        <section
          v-if="recomendados.length > 0"
          aria-labelledby="titulo-recomendados"
          class="space-y-4"
        >
          <div class="flex items-center justify-between">
            <div>
              <h2 id="titulo-recomendados" class="text-xl font-bold text-texto-primario">
                Productos recomendados
              </h2>
              <p class="text-xs text-texto-secundario">
                Otras opciones disponibles en la categoría {{ producto.tipo || 'medicamentos' }}
              </p>
            </div>

            <!-- Botones de navegación del carrusel -->
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="foco-dentro flex size-9 items-center justify-center rounded-xl border border-borde-control bg-superficie-elevada text-texto-primario hover:bg-superficie-interactiva transition-colors"
                aria-label="Ver productos recomendados anteriores"
                @click="desplazarCarrusel('ant')"
              >
                <ChevronLeftIcon class="size-4" aria-hidden="true" />
              </button>
              <button
                type="button"
                class="foco-dentro flex size-9 items-center justify-center rounded-xl border border-borde-control bg-superficie-elevada text-texto-primario hover:bg-superficie-interactiva transition-colors"
                aria-label="Ver productos recomendados siguientes"
                @click="desplazarCarrusel('sig')"
              >
                <ChevronRightIcon class="size-4" aria-hidden="true" />
              </button>
            </div>
          </div>

          <!-- Contenedor con scroll horizontal y snap -->
          <div
            ref="contenedorCarrusel"
            class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scroll-smooth scrollbar-none"
            role="region"
            aria-label="Lista de productos recomendados"
          >
            <article
              v-for="rec in recomendados"
              :key="rec.id"
              class="w-60 shrink-0 snap-start flex flex-col rounded-2xl border border-borde-sutil bg-superficie-elevada overflow-hidden shadow-xs hover:shadow-md transition-shadow"
            >
              <RouterLink
                :to="'/catalogo/' + rec.id"
                class="foco-dentro aspect-square bg-superficie-hundida relative flex items-center justify-center p-4 block"
                :aria-label="'Ver detalle de ' + rec.nombre"
              >
                <img
                  v-if="rec.imagen && urlDeMedia(rec.imagen)"
                  :src="urlDeMedia(rec.imagen) ?? undefined"
                  :alt="rec.nombre"
                  loading="lazy"
                  decoding="async"
                  class="size-full object-contain"
                />
                <img
                  v-else
                  :src="ilustracionDe(rec.tipo)"
                  :alt="textoAlternativoDe(rec.tipo)"
                  loading="lazy"
                  decoding="async"
                  class="size-24 object-contain"
                />
              </RouterLink>

              <div class="flex flex-1 flex-col p-3">
                <h3 class="lineas-2 text-xs font-semibold text-texto-primario">
                  <RouterLink
                    :to="'/catalogo/' + rec.id"
                    class="foco-dentro hover:text-botica-600 transition-colors"
                  >
                    {{ rec.nombre }}
                  </RouterLink>
                </h3>
                <p v-if="rec.concentracion" class="mt-1 lineas-1 text-2xs font-semibold text-texto-acento">
                  {{ rec.concentracion }}
                </p>
                <p class="mt-0.5 lineas-1 text-2xs uppercase text-texto-terciario">
                  {{ rec.laboratorio }}
                </p>

                <div class="mt-auto pt-3 flex items-center justify-between">
                  <span class="cifras-tabulares font-bold text-sm text-texto-marca">
                    {{ dinero(rec.precio) }}
                  </span>

                  <RouterLink
                    :to="'/catalogo/' + rec.id"
                    class="foco-dentro rounded-lg bg-botica-50 px-2 py-1 text-2xs font-medium text-botica-700 hover:bg-botica-100 dark:bg-botica-500/10 dark:text-botica-300 transition-colors"
                  >
                    Ver detalle
                  </RouterLink>
                </div>
              </div>
            </article>
          </div>
        </section>
      </div>
    </main>

    <Footer />
  </div>
</template>
