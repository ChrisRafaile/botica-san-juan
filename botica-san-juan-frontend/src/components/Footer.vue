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
} from 'lucide-vue-next'
import contactService from '@/services/contact'
import { EASE, DUR, STAGGER, prefiereMenosMovimiento } from '@/utils/motion'

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
  if (prefiereMenosMovimiento() || !raiz.value) return

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
            Más de 15 años cuidando de tu salud y la de tu familia. Farmacia certificada
            con servicio de calidad y compromiso.
          </p>

          <a
            href="https://wa.me/51999999999"
            target="_blank"
            rel="noopener noreferrer"
            class="foco-dentro inline-flex items-center gap-2 rounded-lg bg-lateral-fondo-sup px-3 py-2 text-sm font-medium transition-colors duration-200 hover:bg-exito-600 hover:text-white"
          >
            <MessageCircleIcon class="size-5" />
            Escríbenos por WhatsApp
          </a>
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
              <MapPinIcon class="mt-0.5 size-5 shrink-0 text-botica-300" />
              <p class="text-sm">Av. Santa Rosa 103<br />Lima, Perú</p>
            </div>

            <div class="flex items-start gap-3">
              <PhoneIcon class="mt-0.5 size-5 shrink-0 text-botica-300" />
              <div>
                <p class="cifras-tabulares text-sm">
                  <a
                    href="tel:+5116772892"
                    class="foco-dentro rounded-md transition-colors hover:text-botica-300"
                  >
                    (01) 677-2892
                  </a>
                </p>
                <p class="text-xs text-lateral-texto-tenue">Línea principal</p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <MailIcon class="mt-0.5 size-5 shrink-0 text-botica-300" />
              <div class="min-w-0">
                <p class="truncate text-sm">
                  <a
                    href="mailto:Boticassanjuan16@gmail.com"
                    class="foco-dentro rounded-md transition-colors hover:text-botica-300"
                  >
                    Boticassanjuan16@gmail.com
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
                class="min-w-0 flex-1 rounded-l-lg border border-lateral-borde bg-lateral-fondo-sup px-4 py-3 text-white placeholder-lateral-texto-tenue focus:border-botica-500 focus:outline-none"
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
  </footer>
</template>
