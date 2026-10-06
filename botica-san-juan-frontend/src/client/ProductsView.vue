<script setup lang="ts">
/**
 * Catálogo público.
 *
 * TODO SE RESUELVE EN EL SERVIDOR
 *
 * La versión anterior descargaba una página y filtraba y ordenaba sobre ella
 * en el navegador. Con 3 361 productos eso significa tres cosas, las tres
 * invisibles desde la pantalla:
 *
 *  - Buscar sólo encontraba lo que ya se hubiera descargado.
 *  - El desplegable de laboratorios ofrecía los pocos que hubieran caído en
 *    esos productos, de los 83 que hay.
 *  - Y ese filtro de laboratorio no filtraba nada en el servidor: el
 *    parámetro viajaba y la API lo ignoraba en silencio.
 *
 * Ahora la consulta entera viaja a la API y los desplegables se construyen con
 * un endpoint de facetas que cuenta sobre la tabla completa.
 *
 * QUÉ FILTROS SE OFRECEN, Y POR QUÉ NO OTROS
 *
 * Se miraron los datos antes de decidir. Tipo tiene 22 valores bien repartidos
 * (1 261 tabletas, 564 suspensiones…) y laboratorio tiene 83: los dos filtran
 * de verdad. Categoría tiene sólo 3 valores, así que ayuda poco, pero es real
 * y cuesta nada.
 *
 * NO se ofrece "requiere receta", aunque la API lo admite: cero de los 3 361
 * productos lo tienen marcado, así que ese filtro sólo podría devolver una
 * lista vacía. Un filtro que no puede encontrar nada es peor que no tenerlo,
 * porque el visitante concluye que la botica no tiene lo que busca.
 */
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue'
import { useCartStore } from '@/stores/carrito'
import { useProductsStore } from '@/stores/productos'
import type { Product } from '@/services/products'
import { urlDeMedia } from '@/utils/media'
import {
  SearchIcon,
  ShoppingCartIcon,
  PackageIcon,
  SlidersHorizontalIcon,
  XIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  LoaderCircleIcon,
  AlertCircleIcon,
} from 'lucide-vue-next'

const cartStore = useCartStore()
const catalogo = useProductsStore()

/* --- Búsqueda con espera --------------------------------------------------
   Cada pulsación no puede ser una consulta: escribir "paracetamol" lanzaría
   once. Se espera a que la persona deje de escribir. */
const textoBusqueda = ref('')
let temporizador: ReturnType<typeof setTimeout> | null = null

watch(textoBusqueda, (valor) => {
  if (temporizador) clearTimeout(temporizador)
  temporizador = setTimeout(() => {
    catalogo.busqueda = valor
    catalogo.aplicarFiltros()
  }, 350)
})

onBeforeUnmount(() => {
  if (temporizador) clearTimeout(temporizador)
})

/* --- Filtros -------------------------------------------------------------- */
const filtrosAbiertos = ref(false)

function alCambiarFiltro() {
  catalogo.aplicarFiltros()
}

function limpiar() {
  textoBusqueda.value = ''
  catalogo.limpiarFiltros()
}

/* --- Paginación -----------------------------------------------------------
   Ventana de páginas alrededor de la actual: con 141 páginas, pintarlas todas
   es una lista inservible. */
const paginasVisibles = computed(() => {
  const total = catalogo.ultimaPagina
  const actual = catalogo.pagina
  const ventana = 2
  const paginas: (number | '…')[] = []

  const agregar = (n: number) => { if (!paginas.includes(n)) paginas.push(n) }

  agregar(1)
  if (actual - ventana > 2) paginas.push('…')
  for (let n = Math.max(2, actual - ventana); n <= Math.min(total - 1, actual + ventana); n++) agregar(n)
  if (actual + ventana < total - 1) paginas.push('…')
  if (total > 1) agregar(total)

  return paginas
})

async function irA(n: number) {
  await catalogo.irAPagina(n)
  /* Al cambiar de página se vuelve arriba: si no, aparece la página nueva ya
     desplazada por la mitad y parece que no ha pasado nada. */
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

/* --- Carrito -------------------------------------------------------------- */
const agregando = ref<number | null>(null)
const errorCarrito = ref('')

async function agregarAlCarrito(producto: Product) {
  agregando.value = producto.id
  errorCarrito.value = ''
  try {
    await cartStore.addItem(producto.id, 1)
  } catch (e) {
    errorCarrito.value = e instanceof Error ? e.message : 'No se pudo agregar al carrito'
  } finally {
    agregando.value = null
  }
}

/* --- Imágenes que no cargan -----------------------------------------------
   `ocultarSiFalla` esconde el <img>, pero el respaldo estaba en un `v-else`
   mutuamente excluyente con el `v-if`: al ocultarse la imagen no aparecía
   nada, sólo un hueco gris. Registrando el fallo en estado, el respaldo sí
   puede renderizarse. */
const sinImagen = ref(new Set<number>())

function marcarImagenRota(id: number) {
  sinImagen.value.add(id)
}

function muestraImagen(producto: Product): boolean {
  return Boolean(urlDeMedia(producto.imagen)) && !sinImagen.value.has(producto.id)
}

const dinero = (valor: number) =>
  new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(valor)

onMounted(async () => {
  catalogo.orden = 'nombre'
  await catalogo.inicializar()
  await cartStore.loadCart()
})
</script>

<template>
  <main class="min-h-screen bg-superficie-fondo">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
      <header class="text-center">
        <h1 class="text-3xl font-bold tracking-tight text-texto-primario sm:text-4xl">
          Nuestros productos
        </h1>
        <p class="mx-auto mt-3 max-w-2xl text-texto-secundario">
          Busca entre los
          <span class="cifras-tabulares font-semibold text-texto-primario">
            {{ catalogo.facetas?.total.toLocaleString('es-PE') ?? '…' }}
          </span>
          productos de nuestro catálogo.
        </p>
      </header>

      <!-- Buscador y filtros -->
      <section class="mt-8" aria-label="Buscar y filtrar productos">
        <div class="flex flex-col gap-3 sm:flex-row">
          <div class="relative flex-1">
            <label for="buscar" class="sr-only">Buscar productos</label>
            <SearchIcon
              class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-texto-terciario"
              aria-hidden="true"
            />
            <input
              id="buscar"
              v-model="textoBusqueda"
              type="search"
              placeholder="Nombre, laboratorio, presentación…"
              class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-elevada py-2.5 pl-10 pr-4 text-texto-primario placeholder:text-texto-terciario"
            />
          </div>

          <button
            type="button"
            class="foco-dentro inline-flex items-center justify-center gap-2 rounded-xl border border-borde-control bg-superficie-elevada px-4 py-2.5 font-medium text-texto-primario transition-colors hover:bg-superficie-interactiva sm:w-auto"
            :aria-expanded="filtrosAbiertos"
            aria-controls="panel-filtros"
            @click="filtrosAbiertos = !filtrosAbiertos"
          >
            <SlidersHorizontalIcon class="size-5" aria-hidden="true" />
            Filtros
            <span
              v-if="catalogo.hayFiltrosActivos"
              class="ml-1 size-2 rounded-full bg-botica-600"
              aria-label="hay filtros aplicados"
            />
          </button>
        </div>

        <!-- Panel de filtros: plegado en móvil, siempre visible en escritorio -->
        <div
          id="panel-filtros"
          class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
          :class="filtrosAbiertos ? 'grid' : 'hidden lg:grid'"
        >
          <div>
            <label for="f-tipo" class="mb-1.5 block text-sm font-medium text-texto-secundario">
              Tipo
            </label>
            <select
              id="f-tipo"
              v-model="catalogo.tipo"
              class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-elevada px-3 py-2.5 text-texto-primario"
              @change="alCambiarFiltro"
            >
              <option value="">Todos los tipos</option>
              <option v-for="t in catalogo.facetas?.tipos ?? []" :key="t.valor" :value="t.valor">
                {{ t.valor }} ({{ t.total }})
              </option>
            </select>
          </div>

          <div>
            <label for="f-lab" class="mb-1.5 block text-sm font-medium text-texto-secundario">
              Laboratorio
            </label>
            <select
              id="f-lab"
              v-model="catalogo.laboratorio"
              class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-elevada px-3 py-2.5 text-texto-primario"
              @change="alCambiarFiltro"
            >
              <option value="">Todos los laboratorios</option>
              <option v-for="l in catalogo.facetas?.laboratorios ?? []" :key="l.valor" :value="l.valor">
                {{ l.valor }} ({{ l.total }})
              </option>
            </select>
          </div>

          <div>
            <label for="f-orden" class="mb-1.5 block text-sm font-medium text-texto-secundario">
              Ordenar por
            </label>
            <select
              id="f-orden"
              v-model="catalogo.orden"
              class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-elevada px-3 py-2.5 text-texto-primario"
              @change="alCambiarFiltro"
            >
              <option value="nombre">Nombre (A–Z)</option>
              <option value="precio_asc">Precio: de menor a mayor</option>
              <option value="precio_desc">Precio: de mayor a menor</option>
            </select>
          </div>

          <div class="flex items-end gap-3">
            <label class="foco-dentro flex cursor-pointer items-center gap-2 rounded-lg py-2.5 text-sm text-texto-secundario">
              <input
                v-model="catalogo.soloDisponibles"
                type="checkbox"
                class="size-4 rounded border-borde-control text-botica-700"
                @change="alCambiarFiltro"
              />
              Solo disponibles
            </label>

            <button
              v-if="catalogo.hayFiltrosActivos"
              type="button"
              class="foco-dentro inline-flex items-center gap-1.5 rounded-lg px-2 py-2.5 text-sm font-medium text-texto-secundario transition-colors hover:text-texto-primario"
              @click="limpiar"
            >
              <XIcon class="size-4" aria-hidden="true" />
              Limpiar
            </button>
          </div>
        </div>
      </section>

      <!-- Recuento: se anuncia a los lectores de pantalla al cambiar -->
      <p
        class="mt-6 text-sm text-texto-secundario"
        role="status"
        aria-live="polite"
      >
        <template v-if="catalogo.isLoading">Buscando…</template>
        <template v-else-if="catalogo.totalFiltrado === 0">Sin resultados</template>
        <template v-else>
          Mostrando
          <span class="cifras-tabulares font-medium text-texto-primario">{{ catalogo.desde }}–{{ catalogo.hasta }}</span>
          de
          <span class="cifras-tabulares font-medium text-texto-primario">{{ catalogo.totalFiltrado.toLocaleString('es-PE') }}</span>
          productos
        </template>
      </p>

      <p
        v-if="errorCarrito"
        class="mt-3 flex items-center gap-2 rounded-xl bg-peligro-50 px-4 py-3 text-sm text-peligro-700 dark:bg-peligro-500/10 dark:text-peligro-500"
        role="alert"
      >
        <AlertCircleIcon class="size-4 shrink-0" aria-hidden="true" />
        {{ errorCarrito }}
      </p>

      <!-- Error de carga, distinto de "no hay resultados" -->
      <div
        v-if="catalogo.error"
        class="mt-8 rounded-2xl border border-peligro-500/30 bg-peligro-50 p-8 text-center dark:bg-peligro-500/10"
        role="alert"
      >
        <AlertCircleIcon class="mx-auto size-8 text-peligro-600" aria-hidden="true" />
        <p class="mt-3 font-medium text-texto-primario">No se pudo cargar el catálogo</p>
        <p class="mt-1 text-sm text-texto-secundario">{{ catalogo.error }}</p>
        <button
          type="button"
          class="foco-dentro mt-4 rounded-xl bg-botica-600 px-4 py-2 font-medium text-white transition-colors hover:bg-botica-700"
          @click="catalogo.consultar()"
        >
          Reintentar
        </button>
      </div>

      <!-- Sin resultados -->
      <div
        v-else-if="!catalogo.isLoading && catalogo.totalFiltrado === 0"
        class="mt-8 rounded-2xl border border-borde-sutil bg-superficie-elevada p-10 text-center"
      >
        <PackageIcon class="mx-auto size-10 text-texto-terciario" aria-hidden="true" />
        <p class="mt-3 font-medium text-texto-primario">No encontramos productos con esos criterios</p>
        <p class="mt-1 text-sm text-texto-secundario">Prueba con menos filtros o con otro término de búsqueda.</p>
        <button
          v-if="catalogo.hayFiltrosActivos"
          type="button"
          class="foco-dentro mt-4 rounded-xl border border-borde-control px-4 py-2 font-medium text-texto-primario transition-colors hover:bg-superficie-interactiva"
          @click="limpiar"
        >
          Quitar filtros
        </button>
      </div>

      <!-- Rejilla -->
      <div
        v-else
        class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
        :class="{ 'opacity-60 transition-opacity': catalogo.isLoading }"
      >
        <article
          v-for="producto in catalogo.products"
          :key="producto.id"
          class="flex flex-col overflow-hidden rounded-2xl border border-borde-sutil bg-superficie-elevada transition-shadow hover:shadow-md"
        >
          <div class="relative aspect-square bg-superficie-hundida">
            <!-- La condición va sobre la URL RESUELTA y no sobre el campo: la
                 base guarda rutas relativas y `urlDeMedia` devuelve null si no
                 sabe resolverlas, lo que dejaría un src vacío y el icono de
                 imagen rota del navegador. -->
            <img
              v-if="muestraImagen(producto)"
              :src="urlDeMedia(producto.imagen) ?? undefined"
              :alt="producto.nombre"
              loading="lazy"
              decoding="async"
              class="size-full object-cover"
              @error="marcarImagenRota(producto.id)"
            />
            <div v-else class="flex size-full items-center justify-center">
              <PackageIcon class="size-10 text-texto-terciario" aria-hidden="true" />
            </div>

            <span
              v-if="producto.stock <= 0"
              class="absolute left-2 top-2 rounded-full bg-peligro-600 px-2 py-0.5 text-2xs font-semibold text-white"
            >
              Agotado
            </span>
          </div>

          <div class="flex flex-1 flex-col p-3">
            <h2 class="lineas-2 text-sm font-semibold text-texto-primario">
              {{ producto.nombre }}
            </h2>
            <p class="lineas-1 mt-0.5 text-xs text-texto-terciario">
              {{ producto.laboratorio }}
            </p>

            <div class="mt-auto pt-3">
              <p class="cifras-tabulares text-lg font-bold text-texto-marca">
                {{ dinero(producto.precio) }}
              </p>

              <button
                type="button"
                class="foco-dentro mt-2 inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-botica-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-botica-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="producto.stock <= 0 || agregando === producto.id"
                @click="agregarAlCarrito(producto)"
              >
                <LoaderCircleIcon v-if="agregando === producto.id" class="size-4 animate-spin" aria-hidden="true" />
                <ShoppingCartIcon v-else class="size-4" aria-hidden="true" />
                {{ producto.stock > 0 ? 'Agregar' : 'Sin stock' }}
              </button>
            </div>
          </div>
        </article>
      </div>

      <!-- Paginación -->
      <nav
        v-if="catalogo.ultimaPagina > 1"
        class="mt-10 flex items-center justify-center gap-1"
        aria-label="Paginación del catálogo"
      >
        <button
          type="button"
          class="foco-dentro inline-flex items-center gap-1 rounded-lg border border-borde-control px-3 py-2 text-sm font-medium text-texto-primario transition-colors hover:bg-superficie-interactiva disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="catalogo.pagina === 1"
          @click="irA(catalogo.pagina - 1)"
        >
          <ChevronLeftIcon class="size-4" aria-hidden="true" />
          <span class="hidden sm:inline">Anterior</span>
        </button>

        <template v-for="(p, i) in paginasVisibles" :key="`${p}-${i}`">
          <span v-if="p === '…'" class="px-2 text-texto-terciario">…</span>
          <button
            v-else
            type="button"
            class="foco-dentro cifras-tabulares min-w-10 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
            :class="p === catalogo.pagina
              ? 'bg-botica-600 text-white'
              : 'border border-borde-control text-texto-primario hover:bg-superficie-interactiva'"
            :aria-current="p === catalogo.pagina ? 'page' : undefined"
            @click="irA(p as number)"
          >
            {{ p }}
          </button>
        </template>

        <button
          type="button"
          class="foco-dentro inline-flex items-center gap-1 rounded-lg border border-borde-control px-3 py-2 text-sm font-medium text-texto-primario transition-colors hover:bg-superficie-interactiva disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="catalogo.pagina === catalogo.ultimaPagina"
          @click="irA(catalogo.pagina + 1)"
        >
          <span class="hidden sm:inline">Siguiente</span>
          <ChevronRightIcon class="size-4" aria-hidden="true" />
        </button>
      </nav>
    </div>
  </main>
</template>
