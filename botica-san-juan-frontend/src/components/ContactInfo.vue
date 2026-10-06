<!--
  Información operativa de la botica.

  QUÉ SE QUITÓ, Y POR QUÉ IMPORTA

  Esta ficha prometía cosas que el negocio no presta:

    · "Urgencias 24/7 — Disponible", "Entregas a Domicilio — 24 Horas" y
      "Consulta en Línea — 24/7", justo debajo de un horario que dice que la
      botica cierra a las diez. Las dos afirmaciones no pueden ser ciertas a la
      vez, y la que un cliente recuerda es la que le conviene: aparecerse a la
      una de la mañana.
    · Tres correos en `boticasan-juan.com`, un dominio que no es el del sistema.
      Un correo que rebota es peor que no publicar correo.
    · Un segundo teléfono, "800-BOTICA-01", que no es un número marcable.

  Se queda lo que se puede sostener: dónde está, cuándo abre, a qué número se
  llama y por dónde se escribe. Y todo sale de `datos/botica.ts`, que es la
  única fuente: antes el WhatsApp estaba escrito a mano en siete archivos con
  cuatro números distintos.

  LO QUE SE AÑADIÓ

  Los teléfonos ahora son enlaces `tel:` y `wa.me`: en un móvil, que es desde
  donde se mira una página de contacto, un número que no se puede pulsar obliga
  a copiarlo a mano. Y un indicador de si está abierta AHORA, porque esa es la
  pregunta real de quien entra a esta página, no cuál es la tabla de horarios.
-->
<template>
  <section class="bg-superficie-hundida py-20">
    <div class="container mx-auto px-6">
      <div class="mx-auto max-w-6xl">
        <div class="mb-16 text-center">
          <h2 class="mb-6 text-4xl font-bold text-texto-primario md:text-5xl">
            Información de <span class="text-texto-acento">Contacto</span>
          </h2>
          <p class="mx-auto max-w-3xl text-xl text-texto-secundario">
            Dónde estamos, cuándo atendemos y por dónde escribirnos.
          </p>
        </div>

        <div class="mb-12 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
          <!-- Sede -->
          <article class="contact-info-card rounded-2xl border border-borde-sutil bg-superficie-elevada p-8 shadow-lg">
            <div class="mb-6 flex items-center">
              <div class="mr-4 flex size-12 items-center justify-center rounded-xl bg-clinico-100 dark:bg-clinico-500/15">
                <MapPinIcon class="size-6 text-clinico-700 dark:text-clinico-300" aria-hidden="true" />
              </div>
              <h3 class="text-xl font-bold text-texto-primario">Nuestra sede</h3>
            </div>

            <address class="space-y-1 not-italic text-texto-secundario">
              <p class="font-medium text-texto-primario">Botica San Juan</p>
              <p>{{ DIRECCION.calle }}</p>
              <p>{{ DIRECCION.distrito }} {{ DIRECCION.codigoPostal }}</p>
              <p>{{ DIRECCION.ciudad }}, {{ DIRECCION.pais }}</p>
            </address>

            <a
              :href="MAPA_COMO_LLEGAR"
              target="_blank"
              rel="noopener noreferrer"
              class="foco-dentro mt-4 inline-flex items-center gap-2 rounded-lg text-sm font-medium text-texto-marca hover:underline"
            >
              <NavigationIcon class="size-4" aria-hidden="true" />
              Cómo llegar
            </a>
          </article>

          <!-- Teléfono y WhatsApp -->
          <article class="contact-info-card rounded-2xl border border-borde-sutil bg-superficie-elevada p-8 shadow-lg">
            <div class="mb-6 flex items-center">
              <div class="mr-4 flex size-12 items-center justify-center rounded-xl bg-botica-100 dark:bg-botica-500/15">
                <PhoneIcon class="size-6 text-botica-700 dark:text-botica-300" aria-hidden="true" />
              </div>
              <h3 class="text-xl font-bold text-texto-primario">Llámanos o escríbenos</h3>
            </div>

            <ul class="space-y-3">
              <li>
                <a
                  :href="`tel:${TELEFONO_FIJO_E164}`"
                  class="foco-dentro flex items-center gap-3 rounded-lg text-texto-secundario transition-colors hover:text-texto-marca"
                >
                  <PhoneIcon class="size-4 shrink-0 text-botica-600" aria-hidden="true" />
                  <span>{{ TELEFONO_FIJO }}</span>
                </a>
              </li>
              <li v-if="enlaceWa">
                <a
                  :href="enlaceWa"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="foco-dentro flex items-center gap-3 rounded-lg text-texto-secundario transition-colors hover:text-texto-marca"
                >
                  <MessageSquareIcon class="size-4 shrink-0 text-exito-600" aria-hidden="true" />
                  <span>Escribir por WhatsApp</span>
                </a>
              </li>
              <li>
                <a
                  :href="`mailto:${CORREO}`"
                  class="foco-dentro flex items-center gap-3 rounded-lg text-texto-secundario transition-colors hover:text-texto-marca"
                >
                  <MailIcon class="size-4 shrink-0 text-clinico-600" aria-hidden="true" />
                  <span class="truncate">{{ CORREO }}</span>
                </a>
              </li>
            </ul>

            <p class="mt-4 text-sm text-texto-secundario">
              Para consultas sobre un pedido, ten a mano el nombre con el que lo
              hiciste: así lo encontramos sin pedirte más datos.
            </p>
          </article>

          <!-- Horario, con el estado de ahora mismo -->
          <article class="contact-info-card rounded-2xl border border-borde-sutil bg-superficie-elevada p-8 shadow-lg">
            <div class="mb-6 flex items-center">
              <div class="mr-4 flex size-12 items-center justify-center rounded-xl bg-ambar-100 dark:bg-ambar-500/15">
                <ClockIcon class="size-6 text-ambar-700 dark:text-ambar-300" aria-hidden="true" />
              </div>
              <h3 class="text-xl font-bold text-texto-primario">Horario</h3>
            </div>

            <!-- Altura fija: el estado se calcula al montar y si apareciera de
                 golpe empujaría la tabla de horarios hacia abajo. -->
            <p class="mb-4 flex h-7 items-center gap-2">
              <span
                class="inline-block size-2 shrink-0 rounded-full"
                :class="atencion.abierta ? 'bg-exito-600' : 'bg-neutro-400'"
                aria-hidden="true"
              ></span>
              <span
                class="text-sm font-semibold"
                :class="atencion.abierta ? 'text-exito-700 dark:text-exito-500' : 'text-texto-secundario'"
              >
                {{ atencion.texto }}
              </span>
            </p>

            <dl class="space-y-2 text-sm">
              <div
                v-for="franja in HORARIO"
                :key="franja.etiqueta"
                class="flex items-center justify-between border-b border-borde-sutil py-1.5 last:border-0"
                :class="franja === atencion.franja ? 'font-medium' : ''"
              >
                <dt class="text-texto-secundario">{{ franja.etiqueta }}</dt>
                <dd class="text-texto-primario">{{ franja.texto }}</dd>
              </div>
            </dl>
          </article>
        </div>

        <!-- Mapa -->
        <div class="map-section rounded-2xl border border-borde-sutil bg-superficie-elevada p-8 shadow-lg">
          <div class="mb-6 flex items-center">
            <div class="mr-4 flex size-12 items-center justify-center rounded-xl bg-peligro-50 dark:bg-peligro-500/15">
              <MapPinIcon class="size-6 text-peligro-600" aria-hidden="true" />
            </div>
            <h3 class="text-2xl font-bold text-texto-primario">Dónde encontrarnos</h3>
          </div>

          <!-- aspect-video reserva el alto antes de que cargue el iframe; sin
               eso el mapa aparece de golpe y empuja todo lo que tiene debajo. -->
          <div class="aspect-video overflow-hidden rounded-xl border-2 border-borde-sutil bg-superficie-interactiva">
            <iframe
              :src="MAPA_EMBED"
              title="Ubicación de Botica San Juan en el mapa"
              width="100%"
              height="100%"
              style="border: 0"
              allowfullscreen
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
            />
          </div>

          <div class="mt-8 grid gap-6 md:grid-cols-2">
            <div>
              <h4 class="mb-3 text-lg font-semibold text-texto-primario">Cómo llegar</h4>
              <ul class="space-y-2 text-texto-secundario">
                <li v-for="via in VIAS" :key="via" class="flex items-start">
                  <span class="mr-3 mt-2 size-2 shrink-0 rounded-full bg-clinico-700 dark:bg-clinico-400" aria-hidden="true"></span>
                  <span>{{ via }}</span>
                </li>
              </ul>
            </div>

            <div>
              <h4 class="mb-3 text-lg font-semibold text-texto-primario">Puntos de referencia</h4>
              <ul class="space-y-2 text-texto-secundario">
                <li v-for="punto in REFERENCIAS" :key="punto" class="flex items-start">
                  <span class="mr-3 mt-2 size-2 shrink-0 rounded-full bg-clinico-700 dark:bg-clinico-400" aria-hidden="true"></span>
                  <span>{{ punto }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { gsap } from 'gsap'
import {
  MapPinIcon,
  PhoneIcon,
  ClockIcon,
  MessageSquareIcon,
  NavigationIcon,
  MailIcon,
} from 'lucide-vue-next'
import { prefiereMenosMovimiento } from '@/utils/motion'
import {
  CORREO,
  DIRECCION,
  HORARIO,
  TELEFONO_FIJO,
  TELEFONO_FIJO_E164,
  MAPA_EMBED,
  MAPA_COMO_LLEGAR,
  enlaceWhatsApp,
  estadoAtencion,
} from '@/datos/botica'

const VIAS = [
  'Metro: Estación Santa Rosa (Línea 1), a 10 minutos en transporte público',
  'Corredores complementarios: rutas 405 y 406',
  'Estacionamiento gratuito en las inmediaciones',
]

const REFERENCIAS = [
  'Cerca del Hospital de San Juan de Lurigancho',
  'A 3 cuadras de la Estación Santa Rosa',
  'Cerca del Mercado Modelo de SJL',
]

const enlaceWa = enlaceWhatsApp(
  'Hola, tengo una consulta sobre los productos de Botica San Juan.',
)

/* Se calcula al montar y no en el renderizado del servidor: la hora del
   dispositivo de quien mira es la que importa aquí. */
const atencion = ref(estadoAtencion())

onMounted(() => {
  atencion.value = estadoAtencion()

  /* Si la persona pidió reducir el movimiento, no se anima: el contenido ya
     está en su estado final y visible, que es justamente lo que se quiere. */
  if (prefiereMenosMovimiento()) return

  gsap.from('.contact-info-card', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    stagger: 0.15,
    ease: 'power3.out',
  })

  gsap.from('.map-section', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    ease: 'power3.out',
    delay: 0.4,
  })
})
</script>
