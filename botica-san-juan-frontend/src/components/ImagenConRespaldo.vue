<script setup lang="ts">
/**
 * Imagen que nunca muestra el icono de archivo roto del navegador.
 * ---------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *
 * Este mismo fallo se ha cometido ya tres veces en el proyecto: catálogo
 * público, carrito y el modal de editar producto. Siempre por la misma razón,
 * y la razón merece escribirse porque es contraintuitiva:
 *
 *     :src="urlDeMedia(x) || respaldo"        ← NO FUNCIONA
 *
 * `urlDeMedia` devuelve una URL bien formada aunque el archivo ya no exista en
 * el servidor. El `||` solo cae al respaldo cuando el primer valor es nulo, y
 * una URL que responde 404 no es nula: es una cadena perfectamente válida. El
 * navegador la pide, recibe 404 y pinta su icono de imagen rota.
 *
 * El respaldo tampoco puede ser un `v-else`, porque `v-if`/`v-else` son
 * mutuamente excluyentes: cuando el `@error` oculta la imagen, el `v-else` ya
 * decidió no renderizarse y queda un hueco vacío.
 *
 * LO QUE SÍ FUNCIONA, y es lo que encapsula este componente: el respaldo es un
 * elemento HERMANO que se pinta SIEMPRE y queda debajo; la imagen va encima en
 * posición absoluta y, si falla, se retira. Debajo nunca hay un hueco.
 *
 * SOBRE EL SALTO DE MAQUETACIÓN
 *
 * El contenedor reserva su tamaño por CSS antes de que la imagen cargue, así
 * que el fallo de carga no mueve nada de lo que hay alrededor. Esto no es un
 * detalle estético: el salto de maquetación del catálogo llegó a 0.44 por no
 * reservar el espacio de las tarjetas, y costó rehacerlo.
 */
import { computed, ref, watch } from 'vue'

import { urlDeMedia } from '@/utils/media'

const props = withDefaults(
  defineProps<{
    /** Ruta tal como la guarda la base; se resuelve contra el origen de la API. */
    ruta?: string | null
    /** Texto alternativo. Obligatorio: una imagen sin alt no existe para quien no ve. */
    alt: string
    /** Clases del contenedor. Debe fijar el tamaño para no provocar saltos. */
    claseContenedor?: string
    /** Clases de la imagen y del respaldo. */
    claseImagen?: string
    /** `loading` de la imagen. En listas conviene `lazy`; en un modal, `eager`. */
    carga?: 'lazy' | 'eager'
  }>(),
  {
    ruta: null,
    claseContenedor: '',
    claseImagen: 'h-full w-full object-cover',
    carga: 'lazy',
  },
)

const fallo = ref(false)

const fuente = computed(() => urlDeMedia(props.ruta))

/* Si cambia la ruta hay que volver a intentarlo: sin esto, una imagen que
   falló deja marcado el componente para siempre y la siguiente —que puede
   existir perfectamente— ya no se pinta nunca. Pasa al navegar entre
   productos con el mismo modal abierto. */
watch(
  () => props.ruta,
  () => {
    fallo.value = false
  },
)

/** La imagen se pinta solo si hay ruta y todavía no ha fallado. */
const mostrarImagen = computed(() => Boolean(fuente.value) && !fallo.value)
</script>

<template>
  <div :class="['relative overflow-hidden', claseContenedor]">
    <!-- RESPALDO: hermano, siempre presente, debajo. No es un v-else. -->
    <div
      :class="[
        'flex items-center justify-center bg-botica-50 text-texto-marca',
        claseImagen,
      ]"
    >
      <slot name="respaldo">
        <!-- Decorativo: el texto accesible ya lo aporta el `alt` de la imagen,
             o el propio contenido cuando no hay imagen. -->
        <svg
          class="h-1/2 w-1/2 opacity-60"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          aria-hidden="true"
        >
          <rect x="3" y="3" width="18" height="18" rx="2" />
          <circle cx="8.5" cy="8.5" r="1.5" />
          <path d="m21 15-5-5L5 21" />
        </svg>
      </slot>
    </div>

    <!-- IMAGEN: encima, en absoluto. Al fallar se retira y queda el respaldo. -->
    <img
      v-if="mostrarImagen"
      :src="fuente!"
      :alt="alt"
      :loading="carga"
      :class="['absolute inset-0', claseImagen]"
      decoding="async"
      @error="fallo = true"
    />
  </div>
</template>
