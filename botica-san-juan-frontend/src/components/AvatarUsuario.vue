<script setup lang="ts">
/**
 * AvatarUsuario · Cascada de tres niveles
 * ---------------------------------------------------------------------------
 *   1. Foto propia, persistida en la base (`avatar_data`). Las anteriores a
 *      v0.9.0 siguen como ruta de archivo en `foto_perfil` o `foto`.
 *   2. Avatar de Google (`avatar_oauth_url`).
 *   3. Iniciales en un SVG con la paleta de la botica.
 *
 * Si la imagen de un nivel no carga se baja al siguiente, nunca a un recuadro
 * roto. Es el único sitio que decide qué cara se muestra: si cada vista lo
 * resolviera por su cuenta, acabarían mostrando cosas distintas de la misma
 * persona.
 */
import { computed, ref, watch } from 'vue'
import { urlDeMedia } from '@/utils/media'

export interface CuentaConAvatar {
  nombre?: string | null
  avatar_data?: string | null
  avatar_oauth_url?: string | null
  foto_perfil?: string | null
  foto?: string | null
}

const props = withDefaults(
  defineProps<{
    usuario?: CuentaConAvatar | null
    /** Imagen recién elegida y aún sin guardar: manda sobre los tres niveles. */
    vistaPrevia?: string | null
    tamano?: 'sm' | 'md' | 'lg'
    /** Anillo de estado alrededor del avatar. */
    estado?: 'en-linea' | 'lenta' | 'sin-conexion' | 'verificando' | null
  }>(),
  { usuario: null, vistaPrevia: null, tamano: 'md', estado: null },
)

const TAMANOS = { sm: 'size-8', md: 'size-10', lg: 'size-12' } as const

interface Candidata {
  nivel: 1 | 2
  src: string
}

const candidatas = computed<Candidata[]>(() => {
  const u = props.usuario
  const propia = props.vistaPrevia || u?.avatar_data || urlDeMedia(u?.foto_perfil || u?.foto)

  return [
    ...(propia ? [{ nivel: 1 as const, src: propia }] : []),
    ...(u?.avatar_oauth_url ? [{ nivel: 2 as const, src: u.avatar_oauth_url }] : []),
  ]
})

/* Se recuerdan por dirección y no por nivel: si la foto cambia, la nueva
   merece su propio intento aunque la anterior fallara. */
const fallidas = ref(new Set<string>())
watch(candidatas, (nuevas, anteriores) => {
  if (nuevas.map((c) => c.src).join() !== anteriores.map((c) => c.src).join()) fallidas.value = new Set()
})

const actual = computed(() => candidatas.value.find((c) => !fallidas.value.has(c.src)) ?? null)

function alFallar(src: string) {
  fallidas.value = new Set(fallidas.value).add(src)
}

const nombre = computed(() => props.usuario?.nombre?.trim() ?? '')

/** Hasta dos iniciales: con tres o más el círculo se vuelve ilegible. */
const iniciales = computed(() => {
  const partes = nombre.value.split(/\s+/).filter(Boolean)
  if (partes.length === 0) return '?'

  /* Por punto de código: `'Ñ'[0]` es seguro, pero un emoji se partiría. */
  return partes.slice(0, 2).map((p) => [...p][0]).join('').toUpperCase()
})

/**
 * El color sale del nombre, no del azar: la misma persona tiene siempre el
 * mismo en todas las pantallas. Todos los tonos son 700 u 800 porque por
 * debajo el texto blanco no alcanza AA.
 */
const PALETA = [
  'fill-botica-700',
  'fill-clinico-700',
  'fill-botica-800',
  'fill-clinico-800',
  'fill-ambar-800',
] as const

const colorFondo = computed(() => {
  let suma = 0
  for (let i = 0; i < nombre.value.length; i++) {
    suma = (suma + nombre.value.charCodeAt(i) * (i + 1)) % 997
  }
  return PALETA[suma % PALETA.length]
})

const COLOR_ESTADO = {
  'en-linea': 'bg-exito-600',
  'lenta': 'bg-alerta-500',
  'sin-conexion': 'bg-peligro-600',
  'verificando': 'bg-neutro-400',
} as const

const claseEstado = computed(() => (props.estado ? COLOR_ESTADO[props.estado] : ''))
</script>

<template>
  <span
    class="relative inline-flex shrink-0"
    data-testid="avatar-usuario"
    :data-nivel="actual?.nivel ?? 3"
  >
    <!-- `no-referrer`: googleusercontent.com responde 403 a algunas peticiones
         con Referer ajeno, y de paso no le contamos qué pantalla se visita.
         Va ANTES de `src`, que es lo que dispara la descarga. -->
    <img
      v-if="actual"
      :key="actual.src"
      referrerpolicy="no-referrer"
      :src="actual.src"
      :alt="`Foto de ${nombre || 'usuario'}`"
      class="rounded-full object-cover ring-2 ring-borde-sutil"
      :class="TAMANOS[tamano]"
      width="48"
      height="48"
      decoding="async"
      @error="alFallar(actual.src)"
    >

    <!-- Las iniciales son decorativas: el nombre va escrito al lado. -->
    <svg
      v-else
      viewBox="0 0 48 48"
      class="rounded-full ring-2 ring-borde-sutil"
      :class="TAMANOS[tamano]"
      aria-hidden="true"
    >
      <circle cx="24" cy="24" r="24" :class="colorFondo" />
      <text
        x="24"
        y="24"
        dy="0.35em"
        text-anchor="middle"
        font-size="17"
        class="fill-white font-semibold"
      >{{ iniciales }}</text>
    </svg>

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
