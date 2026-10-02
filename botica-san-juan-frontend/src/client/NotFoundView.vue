<script setup lang="ts">
/**
 * Pagina para una direccion que no existe.
 *
 * POR QUE HACIA FALTA
 *
 * El enrutador no tenia ruta comodin: cualquier direccion equivocada dejaba
 * la pantalla EN BLANCO, sin cabecera, sin mensaje y sin salida. Para quien
 * llega desde un enlace viejo o escribe mal la direccion, una pagina en
 * blanco es indistinguible de un sistema caido.
 *
 * Esta pantalla dice que la direccion no existe, cual era, y ofrece las dos
 * salidas que de verdad sirven en una botica: el catalogo y el inicio.
 */
import { useRoute, RouterLink } from 'vue-router'
import { Home, Search, ArrowLeft } from 'lucide-vue-next'

const route = useRoute()

function volver() {
  if (window.history.length > 1) window.history.back()
  else window.location.assign('/')
}
</script>

<template>
  <main class="flex min-h-[70vh] items-center justify-center px-4 py-16">
    <div class="w-full max-w-xl text-center">
      <p class="cifras-tabulares text-7xl font-bold text-botica-600 sm:text-8xl">404</p>

      <h1 class="mt-4 text-2xl font-semibold text-texto-primario sm:text-3xl">
        Esta página no existe
      </h1>

      <p class="mt-3 text-texto-secundario">
        No encontramos nada en
        <code
          class="rounded-md border border-borde-sutil bg-superficie-elevada px-1.5 py-0.5 text-sm text-texto-primario"
        >{{ route.fullPath }}</code>.
        Puede que el enlace esté desactualizado o que la dirección tenga una errata.
      </p>

      <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
        <RouterLink
          to="/products"
          class="foco-dentro inline-flex w-full items-center justify-center gap-2 rounded-xl bg-botica-600 px-5 py-2.5 font-medium text-white transition-colors hover:bg-botica-700 sm:w-auto"
        >
          <Search class="size-4" />
          Ver productos
        </RouterLink>

        <RouterLink
          to="/"
          class="foco-dentro inline-flex w-full items-center justify-center gap-2 rounded-xl border border-borde-base px-5 py-2.5 font-medium text-texto-primario transition-colors hover:bg-superficie-elevada sm:w-auto"
        >
          <Home class="size-4" />
          Ir al inicio
        </RouterLink>
      </div>

      <button
        type="button"
        class="foco-dentro mt-6 inline-flex items-center gap-1.5 text-sm text-texto-secundario underline-offset-4 hover:underline"
        @click="volver"
      >
        <ArrowLeft class="size-4" />
        Volver a la página anterior
      </button>
    </div>
  </main>
</template>
