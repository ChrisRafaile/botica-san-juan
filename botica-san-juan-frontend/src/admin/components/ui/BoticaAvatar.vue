<script setup lang="ts">
/**
 * BoticaAvatar · Foto de perfil con respaldo
 * ---------------------------------------------------------------------------
 * Muestra la foto del usuario y, si no hay o no carga, sus iniciales sobre un
 * color derivado del nombre.
 *
 * Por qué el color se calcula del nombre y no se elige al azar: así la misma
 * persona tiene siempre el mismo color, en esta pantalla y en cualquier otra.
 * Un color aleatorio en cada render convierte el avatar en ruido visual en
 * lugar de en algo que se reconoce de un vistazo.
 *
 * El respaldo se activa también cuando la foto existe en la base pero el
 * archivo ya no está en el servidor: sin `@error` quedaría un recuadro roto,
 * que es peor que unas iniciales.
 */

import { computed, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    nombre?: string | null
    foto?: string | null
    tamano?: 'sm' | 'md' | 'lg'
    /** Anillo de estado alrededor del avatar. */
    estado?: 'en-linea' | 'lenta' | 'sin-conexion' | 'verificando' | null
  }>(),
  { tamano: 'md', estado: null },
)

const fotoFallida = ref(false)

/* Si cambia la foto, se vuelve a intentar: puede que la anterior fallara y la
   nueva sí exista. */
watch(() => props.foto, () => { fotoFallida.value = false })

const TAMANOS = {
  sm: 'size-8 text-[11px]',
  md: 'size-10 text-xs',
  lg: 'size-12 text-sm',
} as const

/**
 * Hasta dos iniciales: la del nombre y la del primer apellido.
 * Con tres o más, el círculo se vuelve ilegible.
 */
const iniciales = computed(() => {
  const partes = (props.nombre ?? '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)

  if (partes.length === 0) return '?'

  const primera = partes[0]?.[0] ?? ''
  const segunda = partes.length > 1 ? (partes[1]?.[0] ?? '') : ''

  return (primera + segunda).toUpperCase()
})

/**
 * Color estable a partir del nombre.
 *
 * Se recorre la cadena acumulando un entero y se reparte sobre una paleta
 * fija en vez de generar un tono libre: una paleta elegida garantiza contraste
 * suficiente con el texto blanco, cosa que un color arbitrario no.
 *
 * Todos los tonos son del escalón 700 u 800 por ese motivo. Un ámbar 600, que
 * es el que había aquí antes, sólo alcanza 3.2:1 con texto blanco: se veía
 * bien de lejos y era ilegible de cerca.
 */
const PALETA = [
  'bg-botica-700',
  'bg-clinico-700',
  'bg-botica-800',
  'bg-clinico-800',
  'bg-ambar-800',
] as const

const colorFondo = computed(() => {
  const nombre = props.nombre ?? ''
  let suma = 0

  for (let i = 0; i < nombre.length; i++) {
    suma = (suma + nombre.charCodeAt(i) * (i + 1)) % 997
  }

  return PALETA[suma % PALETA.length]
})

const mostrarFoto = computed(() => Boolean(props.foto) && !fotoFallida.value)

const COLOR_ESTADO = {
  'en-linea': 'bg-exito-600',
  'lenta': 'bg-alerta-500',
  'sin-conexion': 'bg-peligro-600',
  'verificando': 'bg-neutro-400',
} as const

const claseEstado = computed(
  () => (props.estado ? COLOR_ESTADO[props.estado] : ''),
)
</script>

<template>
  <span class="relative inline-flex shrink-0">
    <img
      v-if="mostrarFoto"
      :src="foto!"
      :alt="`Foto de ${nombre ?? 'usuario'}`"
      class="rounded-full object-cover ring-2 ring-borde-sutil"
      :class="TAMANOS[tamano]"
      loading="lazy"
      decoding="async"
      @error="fotoFallida = true"
    >

    <span
      v-else
      class="grid place-items-center rounded-full font-semibold text-white ring-2 ring-borde-sutil"
      :class="[TAMANOS[tamano], colorFondo]"
      aria-hidden="true"
    >
      {{ iniciales }}
    </span>

    <!-- El punto de estado es decorativo: su significado se escribe en texto
         al lado, que es lo que leen los lectores de pantalla. -->
    <span
      v-if="estado"
      class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full ring-2 ring-superficie-elevada"
      :class="claseEstado"
      aria-hidden="true"
    />
  </span>
</template>
