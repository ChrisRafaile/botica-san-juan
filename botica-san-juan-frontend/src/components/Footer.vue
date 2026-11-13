<script setup lang="ts">
/**
 * Pie del portal público.
 *
 * SUPERFICIE OSCURA COMPARTIDA CON EL PANEL
 *
 * Usa los mismos tokens `lateral-*` que la barra lateral del administrador.
 * No es un detalle de gusto: el pie estaba en `bg-gray-900` y la barra en
 * `--lateral-fondo` (botica-950), de modo que el portal y el panel eran dos
 * negros distintos. Compartir el token es lo que hace que parezcan un solo
 * producto, y que un cambio de marca llegue a los dos sitios a la vez.
 *
 * POR QUÉ NO SE USAN AQUÍ LOS TOKENS DE TEXTO NORMALES
 *
 * `--texto-secundario` se aclara en modo oscuro y se oscurece en modo claro,
 * que es exactamente lo que se quiere sobre una superficie que cambia. Esta no
 * cambia: es oscura siempre. Si se escribiera `text-texto-secundario` el pie
 * quedaría con texto oscuro sobre fondo oscuro en modo claro. De ahí
 * `--lateral-texto`, que está definido para vivir sobre esta superficie.
 *
 * CUATRO AVERÍAS CORREGIDAS
 *
 * 1. El R.U.C. del titular estaba impreso aquí, en todas las páginas del sitio
 *    público. Un R.U.C. peruano de persona natural empieza por 10 y lleva
 *    dentro el DNI, así que publicarlo expone un documento de identidad. Se
 *    retira: la certificación se puede afirmar sin el número.
 *
 * 2. `/privacy`, `/terms` y `/cookies` no existen en el enrutador. Los tres
 *    enlaces caían en la pantalla de 404 desde cualquier página. Se retiran en
 *    lugar de apuntar a una página inexistente; cuando esos textos existan,
 *    vuelven. Lo mismo con los `href="#"` de las redes sociales: un enlace que
 *    no lleva a ninguna parte es un control averiado, no un adorno. Queda
 *    WhatsApp, que es el único con destino real.
 *
 * 3. El boletín era un formulario que no enviaba nada. Prometía algo en voz
 *    alta y fallaba en silencio. Ahora escribe en `POST /contacto`, que existe
 *    y es público, con su motivo propio para que el administrador distinga una
 *    suscripción de una consulta.
 *
 * 4. La animación ponía `opacity: 0` en todo el pie y lo revelaba 500 ms
 *    después de montar. Dos problemas: si GSAP no cargaba, el pie quedaba
 *    invisible para siempre; y como el pie está bajo el pliegue, la cascada se
 *    consumía antes de que nadie llegase a verla. Ahora el contenido es
 *    visible por omisión, la cascada la dispara ScrollTrigger al asomar, y si
 *    hay `prefers-reduced-motion` no se mueve nada.
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'
import { gsap } from 'gsap'
import {
  PillIcon,
  MessageCircleIcon,
  HomeIcon,
  PackageIcon,
  StethoscopeIcon,
  InfoIcon,
  PhoneIcon,
  VideoIcon,
  TruckIcon,
  UsersIcon,
  ShieldIcon,
  HeartIcon,
  MapPinIcon,
  MailIcon,
  ClockIcon,
  SendIcon,
  AwardIcon,
  LoaderCircleIcon,
  CheckIcon,
  BookOpenIcon,
} from 'lucide-vue-next'
import imgLibroReclamaciones from '@/assets/images/LibroReclamaciones.jpg'
import contactService from '@/services/contact'
import { EASE, DUR, STAGGER, prefiereMenosMovimiento } from '@/utils/motion'
import {
  CORREO,
  DIRECCION,
  TELEFONO_FIJO,
  TELEFONO_FIJO_E164,
  enlaceWhatsApp,
} from '@/datos/botica'

const enlaceWa = enlaceWhatsApp('Hola, quisiera consultar por un producto.')

const anioActual = computed(() => new Date().getFullYear())

const raiz = ref<HTMLElement | null>(null)

/* --- Navegación ---------------------------------------------------------- */

/* Declarados como datos y no repetidos en la plantilla: así es imposible que
   un enlace apunte a una ruta que no existe sin que se vea en una sola lista.
   Todas las rutas de aquí están en `router/index.ts`. */
const enlacesSitio = [
  { ruta: '/', texto: 'Inicio', icono: HomeIcon },
  { ruta: '/products', texto: 'Productos', icono: PackageIcon },
  { ruta: '/services', texto: 'Servicios', icono: StethoscopeIcon },
  { ruta: '/about', texto: 'Sobre nosotros', icono: InfoIcon },
  { ruta: '/contact', texto: 'Contacto', icono: PhoneIcon },
  { ruta: '/coverage', texto: 'Cobertura', icono: MapPinIcon },
  { ruta: '/libro-de-reclamaciones', texto: 'Libro de Reclamaciones', icono: BookOpenIcon },
] as const

/* Los servicios no tienen página propia: todos llevan a /services, que es
   donde se describen. Antes eran `href="#"`, que no lleva a ninguna parte. */
const servicios = [
  { texto: 'Telemedicina Aliviamed', icono: VideoIcon },
  { texto: 'Delivery Express', icono: TruckIcon },
  { texto: 'Asesoría farmacéutica', icono: UsersIcon },
  { texto: 'Vacunación', icono: ShieldIcon },
  { texto: 'Programa Agora', icono: HeartIcon },
] as const

/* --- Boletín ------------------------------------------------------------- */

const correo = ref('')
const enviando = ref(false)
const suscrito = ref(false)
const errorBoletin = ref<string | null>(null)

async function suscribir() {
  const valor = correo.value.trim()
  if (!valor || enviando.value) return

  enviando.value = true
  errorBoletin.value = null

  try {
    /* `nombre` y `mensaje` son obligatorios en la API; aquí no se le pide el
       nombre a nadie por una suscripción, así que se rellenan de forma que el
       administrador entienda qué está leyendo. */
    await contactService.enviar({
      nombre: 'Suscripción al boletín',
      email: valor,
      motivo: 'Boletín',
      mensaje: `Solicita recibir el boletín en ${valor}.`,
    })
    suscrito.value = true
    correo.value = ''
  } catch {
    /* El limitador de la API responde 429 tras varios intentos seguidos. */
    errorBoletin.value = 'No se pudo registrar el correo. Inténtalo más tarde.'
  } finally {
    enviando.value = false
  }
}

/* --- Movimiento ---------------------------------------------------------- */

/**
 * Se dispara con IntersectionObserver y no con ScrollTrigger.
 *
 * ScrollTrigger haría exactamente lo mismo, pero el pie se monta en TODAS las
 * páginas del portal, así que importarlo aquí lo saca de los fragmentos de
 * ruta y lo mete en el camino crítico: 24 kB comprimidos en cada carga para un
 * fundido de entrada. `IntersectionObserver` es nativo, cuesta cero, y el
 * `gsap` del núcleo ya está cargado para la animación en sí.
 */
let observador: IntersectionObserver | null = null

onMounted(() => {
  if (prefiereMenosMovimiento() || !raiz.value || typeof IntersectionObserver === 'undefined') return

  const bloques = raiz.value.querySelectorAll('.bloque-pie')
  if (!bloques.length) return

  observador = new IntersectionObserver(
    (entradas) => {
      if (!entradas.some((e) => e.isIntersecting)) return

      /* Una sola vez: volver a animar al subir y bajar marea. */
      observador?.disconnect()
      observador = null

      gsap.from(bloques, {
        y: 24,
        opacity: 0,
        duration: DUR.pausada,
        stagger: STAGGER.amplio,
        ease: EASE.salida,
      })
    },
    { rootMargin: '0px 0px -8% 0px' },
  )

  observador.observe(raiz.value)
})

onBeforeUnmount(() => {
  observador?.disconnect()
  observador = null
})
</script>

<template>
  <footer ref="raiz" class="bg-lateral-fondo text-lateral-texto">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-4">
        <!-- Identidad -->
        <div class="bloque-pie">
          <div class="mb-6 flex items-center gap-3">
            <div class="flex size-12 items-center justify-center rounded-xl bg-botica-600">
              <PillIcon class="size-7 text-white" />
            </div>
            <div>
              <h3 class="text-xl font-bold text-white">Boticas San Juan</h3>
              <p class="text-sm text-lateral-texto-tenue">Tu salud, nuestra prioridad</p>
            </div>
          </div>

          <p class="mb-6 leading-relaxed">
            Más de 8 años de servicio en San Juan de Lurigancho. Atención directa en
            nuestro local principal y delivery local.
          </p>

          <!-- El número era 51999999999, relleno: el enlace abría una
               conversación con un número que no existe. Ahora sale de
               datos/botica.ts y, si no está configurado, se enlaza a Contacto
               en lugar de prometer un canal que no responde. -->
          <a
            v-if="enlaceWa"
            :href="enlaceWa"
            target="_blank"
            rel="noopener noreferrer"
            class="foco-dentro inline-flex items-center gap-2 rounded-lg bg-lateral-fondo-sup px-3 py-2 text-sm font-medium transition-colors duration-200 hover:bg-exito-600 hover:text-white"
          >
            <MessageCircleIcon class="size-5" aria-hidden="true" />
            Escríbenos por WhatsApp
          </a>
          <RouterLink
            v-else
            to="/contact"
            class="foco-dentro inline-flex items-center gap-2 rounded-lg bg-lateral-fondo-sup px-3 py-2 text-sm font-medium transition-colors duration-200 hover:bg-exito-600 hover:text-white"
          >
            <MessageCircleIcon class="size-5" aria-hidden="true" />
            Cómo contactarnos
          </RouterLink>
        </div>

        <!-- Enlaces -->
        <nav class="bloque-pie" aria-label="Enlaces del sitio">
          <h4 class="mb-6 text-lg font-semibold text-white">Enlaces rápidos</h4>
          <ul class="space-y-3">
            <li v-for="enlace in enlacesSitio" :key="enlace.ruta">
              <RouterLink
                :to="enlace.ruta"
                class="foco-dentro flex items-center gap-2 rounded-md transition-colors duration-200 hover:text-botica-300"
              >
                <component :is="enlace.icono" class="size-4 shrink-0" />
                {{ enlace.texto }}
              </RouterLink>
            </li>
          </ul>
        </nav>

        <!-- Servicios -->
        <nav class="bloque-pie" aria-label="Nuestros servicios">
          <h4 class="mb-6 text-lg font-semibold text-white">Nuestros servicios</h4>
          <ul class="space-y-3">
            <li v-for="servicio in servicios" :key="servicio.texto">
              <RouterLink
                to="/services"
                class="foco-dentro flex items-center gap-2 rounded-md transition-colors duration-200 hover:text-botica-300"
              >
                <component :is="servicio.icono" class="size-4 shrink-0" />
                {{ servicio.texto }}
              </RouterLink>
            </li>
          </ul>
        </nav>

        <!-- Contacto -->
        <div class="bloque-pie">
          <h4 class="mb-6 text-lg font-semibold text-white">Contáctanos</h4>
          <div class="space-y-4">
            <div class="flex items-start gap-3">
              <MapPinIcon class="mt-0.5 size-5 shrink-0 text-botica-300" aria-hidden="true" />
              <p class="text-sm">{{ DIRECCION.calle }}<br />{{ DIRECCION.distrito }}, {{ DIRECCION.ciudad }}</p>
            </div>

            <div class="flex items-start gap-3">
              <PhoneIcon class="mt-0.5 size-5 shrink-0 text-botica-300" aria-hidden="true" />
              <div>
                <p class="cifras-tabulares text-sm">
                  <a
                    :href="`tel:${TELEFONO_FIJO_E164}`"
                    class="foco-dentro rounded-md transition-colors hover:text-botica-300"
                  >
                    {{ TELEFONO_FIJO }}
                  </a>
                </p>
                <p class="text-xs text-lateral-texto-tenue">Línea principal</p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <MailIcon class="mt-0.5 size-5 shrink-0 text-botica-300" aria-hidden="true" />
              <div class="min-w-0">
                <p class="truncate text-sm">
                  <a
                    :href="`mailto:${CORREO}`"
                    class="foco-dentro rounded-md transition-colors hover:text-botica-300"
                  >
                    {{ CORREO }}
                  </a>
                </p>
                <p class="text-xs text-lateral-texto-tenue">Atención al cliente</p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <ClockIcon class="mt-0.5 size-5 shrink-0 text-botica-300" />
              <div>
                <p class="text-sm">Lun - Dom: 24/7</p>
                <p class="text-xs text-lateral-texto-tenue">Servicio continuo</p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <AwardIcon class="mt-0.5 size-5 shrink-0 text-botica-300" />
              <div>
                <p class="text-sm">Establecimiento certificado</p>
                <p class="text-xs text-lateral-texto-tenue">Autorizado por DIGEMID</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Boletín -->
    <div class="border-t border-lateral-borde">
      <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="bloque-pie flex flex-col items-start justify-between gap-6 md:flex-row md:items-center">
          <div>
            <h4 class="mb-2 text-lg font-semibold text-white">Suscríbete a nuestro boletín</h4>
            <p class="text-sm text-lateral-texto-tenue">
              Recibe ofertas exclusivas, consejos de salud y novedades de nuestros productos.
            </p>
          </div>

          <div class="w-full md:w-auto md:min-w-80">
            <p
              v-if="suscrito"
              class="flex items-center gap-2 rounded-lg bg-lateral-fondo-sup px-4 py-3 text-sm"
            >
              <CheckIcon class="size-5 shrink-0 text-botica-300" />
              Gracias. Te escribiremos a ese correo.
            </p>

            <form v-else class="flex" @submit.prevent="suscribir">
              <label for="correo-boletin" class="sr-only">Tu correo electrónico</label>
              <input
                id="correo-boletin"
                v-model="correo"
                type="email"
                required
                autocomplete="email"
                placeholder="Tu correo electrónico"
                :aria-describedby="errorBoletin ? 'error-boletin' : undefined"
                class="min-w-0 flex-1 rounded-l-lg border border-white/40 bg-lateral-fondo-sup px-4 py-3 text-white placeholder-lateral-texto-tenue focus:border-botica-500 focus:outline-none"
              />
              <button
                type="submit"
                :disabled="enviando"
                class="foco-dentro inline-flex items-center justify-center rounded-r-lg bg-botica-600 px-6 py-3 font-semibold text-white transition-colors duration-200 hover:bg-botica-500 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <LoaderCircleIcon v-if="enviando" class="size-5 animate-spin" />
                <SendIcon v-else class="size-5" />
                <span class="sr-only">Suscribirme</span>
              </button>
            </form>

            <p v-if="errorBoletin" id="error-boletin" class="mt-2 text-sm text-ambar-300">
              {{ errorBoletin }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Medios de Pago y Libro de Reclamaciones -->
    <div class="border-t border-lateral-borde">
      <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="bloque-pie flex flex-col items-center justify-between gap-6 md:flex-row">
          <!-- Métodos de pago aceptados -->
          <div class="flex flex-col items-center md:items-start gap-2.5">
            <span class="text-xs font-medium uppercase tracking-wider text-lateral-texto-tenue">
              Métodos de pago aceptados
            </span>
            <div class="flex flex-wrap items-center justify-center gap-2" role="group" aria-label="Tarjetas y billeteras digitales aceptadas">
              <!-- Visa -->
              <div class="relative" title="Visa">
                <span class="sr-only">Visa</span>
                <svg viewBox="0 0 48 32" class="h-8 w-12 rounded border border-white/20 bg-white p-1" aria-hidden="true">
                  <path d="M18.8 21.6l2.4-11.2h3.9l-2.4 11.2h-3.9zm14.7-10.9c-.8-.3-2-.6-3.5-.6-3.8 0-6.5 2-6.5 4.9 0 2.1 1.9 3.3 3.4 4 1.5.8 2 1.3 2 2 0 1.1-1.3 1.6-2.5 1.6-1.7 0-2.6-.3-4-.9l-.6-.3-.6 3.1c.9.4 2.6.8 4.4.8 4.1 0 6.8-2 6.8-5.1 0-1.7-1.1-3-3.5-4.1-1.4-.7-2.3-1.2-2.3-1.9 0-.7.7-1.4 2.4-1.4 1.3 0 2.3.3 3 .6l.4.2.6-2.9zm8.5 0h-3c-.9 0-1.6.3-2 1.3l-5.7 10.9h4.1l.8-2.3h5l.5 2.3h3.6l-3.3-11.2zm-4.7 6.3l1.8-4.9.9 4.9h-2.7zM14.6 10.4l-3.7 7.7-.4-2c-.7-2.3-2.9-4.8-5.3-6.1l3.5 11.6h4.1l6.1-11.2h-4.3z" fill="#1A1F71"/>
                  <path d="M8.2 10.4H2.4l-.1.4c4.6 1.1 7.6 3.8 8.8 7.3l-1.3-6.4c-.2-.7-.8-1.2-1.6-1.3z" fill="#F7B600"/>
                </svg>
              </div>

              <!-- Mastercard -->
              <div class="relative" title="Mastercard">
                <span class="sr-only">Mastercard</span>
                <svg viewBox="0 0 48 32" class="h-8 w-12 rounded border border-white/20 bg-white p-1" aria-hidden="true">
                  <circle cx="18" cy="16" r="10" fill="#EB001B"/>
                  <circle cx="30" cy="16" r="10" fill="#F79E1B"/>
                  <path d="M24 8.7a9.96 9.96 0 0 1 3.8 7.3 9.96 9.96 0 0 1-3.8 7.3 9.96 9.96 0 0 1-3.8-7.3c0-2.8 1.5-5.3 3.8-7.3z" fill="#FF5F00"/>
                </svg>
              </div>

              <!-- American Express -->
              <div class="relative" title="American Express">
                <span class="sr-only">American Express</span>
                <svg viewBox="0 0 48 32" class="h-8 w-12 rounded border border-white/20 bg-[#006FCF] p-1 text-white" aria-hidden="true">
                  <rect width="48" height="32" rx="4" fill="#006FCF"/>
                  <text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-weight="900" font-size="8.5" fill="#FFFFFF" letter-spacing="0.5">AMEX</text>
                </svg>
              </div>

              <!-- Yape -->
              <div class="relative" title="Yape">
                <span class="sr-only">Yape</span>
                <svg viewBox="0 0 48 32" class="h-8 w-12 rounded border border-white/20 bg-[#742284] p-1 text-white" aria-hidden="true">
                  <rect width="48" height="32" rx="4" fill="#742284"/>
                  <circle cx="15" cy="16" r="6.5" fill="#00D4B8"/>
                  <text x="32" y="20" font-family="sans-serif" font-weight="900" font-size="9" fill="#FFFFFF" letter-spacing="0.5">YAPE</text>
                </svg>
              </div>

              <!-- Plin -->
              <div class="relative" title="Plin">
                <span class="sr-only">Plin</span>
                <svg viewBox="0 0 48 32" class="h-8 w-12 rounded border border-white/20 bg-[#00D4B8] p-1 text-white" aria-hidden="true">
                  <rect width="48" height="32" rx="4" fill="#00D4B8"/>
                  <text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-weight="900" font-size="11" fill="#FFFFFF" letter-spacing="0.5">plin</text>
                </svg>
              </div>
            </div>
          </div>

          <!-- Libro de Reclamaciones -->
          <div class="flex items-center">
            <RouterLink
              to="/libro-de-reclamaciones"
              class="foco-dentro group inline-flex items-center gap-3 rounded-xl border border-lateral-borde bg-lateral-fondo-sup p-3 transition-colors hover:border-botica-400 hover:bg-lateral-hover"
              aria-label="Ir al Libro de Reclamaciones Virtual"
            >
              <img
                :src="imgLibroReclamaciones"
                alt="Libro de Reclamaciones"
                width="96"
                height="54"
                loading="lazy"
                decoding="async"
                class="h-10 w-auto rounded-md object-contain"
              />
              <div class="text-left">
                <p class="text-xs font-bold text-white group-hover:text-botica-300">Libro de Reclamaciones</p>
                <p class="text-2xs text-lateral-texto-tenue">Conforme a la Ley N.° 29571</p>
              </div>
            </RouterLink>
          </div>
        </div>
      </div>
    </div>

    <!-- Línea final -->
    <div class="border-t border-lateral-borde">
      <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="bloque-pie flex flex-col items-center justify-between gap-4 text-sm md:flex-row">
          <p class="text-lateral-texto-tenue">
            © {{ anioActual }} Boticas San Juan. Todos los derechos reservados.
          </p>
          <p class="flex items-center gap-1.5 text-lateral-texto-tenue">
            <AwardIcon class="size-4 text-botica-300" />
            Establecimiento autorizado por DIGEMID
          </p>
        </div>
      </div>
    </div>

    <!-- Botón flotante de WhatsApp: posición fija, accesible, sin alterar CLS -->
    <a
      v-if="enlaceWa"
      :href="enlaceWa"
      target="_blank"
      rel="noopener noreferrer"
      class="foco-dentro fixed bottom-6 right-6 z-40 flex size-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-xl shadow-black/30 transition-transform duration-200 hover:scale-105 active:scale-95"
      aria-label="Contactar a Botica San Juan por WhatsApp (abre en nueva ventana)"
    >
      <svg class="size-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm.01 1.67c2.2 0 4.26.86 5.82 2.41a8.17 8.17 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.19 8.19 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24zm4.52 11.53c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.01-1.24-.74-.66-1.25-1.48-1.39-1.73-.14-.25-.02-.39.11-.51.11-.11.25-.29.37-.43.13-.15.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.03 2.61.13.17 1.78 2.71 4.3 3.8.6.26 1.07.41 1.43.53.6.19 1.15.16 1.58.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.07-.12-.23-.19-.48-.31z" />
      </svg>
    </a>
  </footer>
</template>
