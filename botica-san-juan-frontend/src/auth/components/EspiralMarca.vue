<script setup lang="ts">
/**
 * Espiral de marca de la pantalla de acceso.
 *
 * QUÉ ES
 * Una espiral dibujada como SVG, no una imagen. Se eligió así por tres razones
 * concretas: pesa unos 2,5 kB en lugar de los cientos de kB que ocuparía un
 * PNG al tamaño de media ventana, es nítida a cualquier resolución, y sus
 * colores salen de los tokens del sistema, así que el modo oscuro no necesita
 * una segunda versión del archivo.
 *
 * CÓMO SE DIBUJÓ, Y UN ERROR QUE MERECE LA PENA DEJAR ESCRITO
 * Las dos curvas son espirales de Arquímedes (r = a + b·θ), generadas con un
 * script y no a ojo: a mano salen bultos que delatan la curva.
 *
 * El primer intento las dibujó como CINTA: un contorno que iba hacia fuera por
 * el borde exterior y volvía por el interior. No funciona. En una espiral, el
 * borde exterior de una vuelta se cruza con el interior de la siguiente, así
 * que el contorno se autointersecta y `fill-rule: nonzero` termina rellenando
 * el disco entero: en pantalla salía un borrón, no una espiral.
 *
 * Lo correcto es no rellenar nada y TRAZAR la línea central con grosor
 * (`fill="none"` + `stroke-width`). El grosor lo resuelve el motor de SVG sin
 * que el trazado se cruce consigo mismo, y de paso el archivo es la mitad
 * porque hay un solo recorrido en vez de dos.
 *
 * ACCESIBILIDAD
 * Es decoración: `aria-hidden` y sin texto alternativo. Lo que la pantalla
 * significa ya lo dicen el encabezado y el formulario; anunciarla sería ruido
 * para quien usa un lector de pantalla.
 */
import { onMounted, onBeforeUnmount, ref, useId } from 'vue'
import { gsap } from 'gsap'
import { EASE, prefiereMenosMovimiento } from '@/utils/motion'

/* Los degradados viven en <defs> y se referencian por id. Si hubiera dos
   instancias en la misma página, unos ids fijos chocarían y la segunda
   heredaría el degradado de la primera: `useId` lo evita. */
const uid = useId()
const idTrazo = `espiral-trazo-${uid}`
const idEco = `espiral-eco-${uid}`

const raiz = ref<SVGSVGElement | null>(null)
let contexto: gsap.Context | null = null

onMounted(() => {
  if (prefiereMenosMovimiento() || !raiz.value) return

  contexto = gsap.context(() => {
    /* El barrido de la referencia, resuelto girando y escalando desde el
       centro de la propia espiral: son transformaciones que el compositor
       resuelve en la GPU y no obligan a recalcular el diseño de la página. */
    gsap.from('.espiral-trazo', {
      rotate: -70,
      scale: 0.86,
      opacity: 0,
      transformOrigin: '300px 300px',
      duration: 1.2,
      stagger: 0.14,
      ease: EASE.salida,
    })
  }, raiz.value)
})

onBeforeUnmount(() => contexto?.revert())
</script>

<template>
  <svg
    ref="raiz"
    class="size-full"
    viewBox="0 0 600 600"
    preserveAspectRatio="xMidYMid slice"
    aria-hidden="true"
    focusable="false"
  >
    <defs>
      <linearGradient :id="idTrazo" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="var(--color-clinico-400)" />
        <stop offset="55%" stop-color="var(--color-clinico-600)" />
        <stop offset="100%" stop-color="var(--color-botica-600)" />
      </linearGradient>

      <linearGradient :id="idEco" x1="1" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="var(--color-botica-400)" />
        <stop offset="100%" stop-color="var(--color-clinico-300)" />
      </linearGradient>
    </defs>

    <path
      class="espiral-trazo"
      fill="none"
      :stroke="`url(#${idTrazo})`"
      stroke-width="46"
      stroke-linecap="round"
      d="M314 300C315 301,317 303,318 305C319 307,320 309,320 312C320 314,320 317,319 320C319 322,317 325,316 328C314 331,312 333,309 336C306 338,303 340,299 341C295 342,291 343,287 344C283 344,278 344,274 343C269 342,264 340,260 337C256 335,251 331,248 328C244 324,240 319,238 314C235 309,233 303,232 297C231 291,230 284,231 278C231 271,233 264,235 258C238 251,241 245,246 239C250 233,256 227,262 223C268 218,275 214,283 211C290 208,298 206,307 205C315 204,324 204,333 206C342 207,351 210,359 214C368 218,376 223,383 230C390 236,397 244,403 252C408 261,413 270,416 280C419 290,421 301,422 312C422 323,421 334,419 345C416 356,412 367,406 377C401 387,394 397,385 406C377 415,367 423,356 429C345 435,333 440,321 444C309 447,295 449,282 448C269 448,255 446,242 443C229 439,216 433,204 426C192 419,180 410,170 399C161 389,152 376,145 363C138 350,132 336,129 321C126 306,124 290,125 274C126 259,129 243,134 228C139 213,146 197,155 184C164 170,176 157,188 146C201 135,216 125,231 118C246 110,264 105,281 102C298 99,316 98,334 99C352 101,371 105,388 111C405 117,422 126,437 137C452 148,467 162,479 177C491 191,501 209,509 226C517 244,523 264,526 284C529 303,529 324,527 344C524 364,519 385,511 404C503 423,492 442,480 459C467 476,451 492,434 504C417 517,386 531,377 537"
    />
    <path
      class="espiral-trazo"
      fill="none"
      :stroke="`url(#${idEco})`"
      stroke-width="13"
      stroke-linecap="round"
      opacity="0.65"
      d="M306 333C304 334,301 337,298 338C295 339,292 340,288 340C285 340,281 340,277 340C274 339,270 338,266 336C263 335,259 332,256 330C252 327,249 324,246 320C244 317,241 312,239 308C237 304,236 299,235 294C234 289,234 283,235 278C235 272,236 267,238 261C240 256,243 251,246 246C249 240,253 236,258 231C262 227,268 223,273 220C279 216,285 214,292 212C298 210,306 209,313 208C320 208,327 209,335 210C342 212,349 214,356 217C363 220,370 225,377 230C383 234,389 240,394 247C399 253,404 261,408 269C411 277,414 285,416 294C417 303,418 312,418 321C418 330,416 340,414 349C411 358,407 367,403 376C398 384,392 393,385 400C378 408,370 415,362 421C353 426,344 431,334 435C324 439,313 442,302 443C291 445,280 445,268 444C257 443,246 440,235 436C224 432,213 427,203 421C193 414,183 407,175 398C166 389,159 379,152 369C146 358,141 346,137 334C133 322,130 309,129 296C128 283,129 269,131 256C133 243,137 229,142 217C147 204,154 192,162 180C171 169,180 158,191 149C202 140,214 131,227 124C240 118,254 112,268 109C282 105,298 103,313 102C328 102,344 104,359 107C374 110,389 115,403 122C417 129,431 137,444 147C456 157,468 169,478 182C488 195,497 209,504 224C511 239,517 264,520 272"
    />
  </svg>
</template>
