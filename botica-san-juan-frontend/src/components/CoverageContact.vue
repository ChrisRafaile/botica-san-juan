<template>
  <section class="py-24 bg-clinico-700">
    <div class="container mx-auto px-6">
      <div class="max-w-5xl mx-auto text-center">
        <div class="inline-flex items-center gap-2 bg-black/15 backdrop-blur-sm text-white px-4 py-2 rounded-full text-sm font-medium mb-6">
          <MessageCircleIcon class="size-4" aria-hidden="true" />
          ¿Tienes dudas?
        </div>

        <h2 class="text-4xl md:text-5xl font-bold text-white mb-6">
          Te confirmamos si llegamos a tu calle
        </h2>

        <p class="text-xl text-white/90 mb-12 max-w-3xl mx-auto">
          Si tu dirección queda en el límite entre dos zonas, escríbenos y te decimos la tarifa
          exacta antes de pedir.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
          <div v-if="enlaceWa" class="bg-black/15 backdrop-blur-sm rounded-2xl p-8">
            <MessageCircleIcon class="size-10 mx-auto mb-4 text-white" aria-hidden="true" />
            <h3 class="text-xl font-bold text-white mb-3">WhatsApp</h3>
            <p class="text-white/90 mb-6">Respuesta en el horario de atención.</p>
            <a
              :href="enlaceWa"
              target="_blank"
              rel="noopener noreferrer"
              class="foco-dentro inline-flex items-center justify-center w-full bg-botica-600 text-white py-3 px-6 rounded-lg hover:bg-botica-700 transition-colors font-medium"
            >
              Escribir por WhatsApp
            </a>
          </div>

          <div class="bg-black/15 backdrop-blur-sm rounded-2xl p-8">
            <PhoneIcon class="size-10 mx-auto mb-4 text-white" aria-hidden="true" />
            <h3 class="text-xl font-bold text-white mb-3">Teléfono</h3>
            <p class="text-white/90 mb-6">Línea fija del local.</p>
            <a
              :href="`tel:${TELEFONO_FIJO_E164}`"
              class="foco-dentro inline-flex items-center justify-center w-full bg-clinico-900 text-white py-3 px-6 rounded-lg hover:bg-clinico-800 transition-colors font-medium cifras-tabulares"
            >
              Llamar al {{ TELEFONO_FIJO }}
            </a>
          </div>

          <div class="bg-black/15 backdrop-blur-sm rounded-2xl p-8">
            <MailIcon class="size-10 mx-auto mb-4 text-white" aria-hidden="true" />
            <h3 class="text-xl font-bold text-white mb-3">Correo</h3>
            <p class="text-white/90 mb-6">Para pedidos grandes o institucionales.</p>
            <a
              :href="`mailto:${CORREO}`"
              class="foco-dentro inline-flex items-center justify-center w-full bg-clinico-900 text-white py-3 px-6 rounded-lg hover:bg-clinico-800 transition-colors font-medium"
            >
              Enviar correo
            </a>
          </div>
        </div>

        <div class="bg-black/15 backdrop-blur-sm rounded-2xl p-6 sm:p-8 text-left">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
              <h3 class="flex items-center gap-2 text-lg font-semibold text-white mb-4">
                <ClockIcon class="size-5" aria-hidden="true" />
                Horario de reparto
              </h3>
              <dl class="space-y-2 text-white/90">
                <div v-for="franja in HORARIO" :key="franja.etiqueta" class="flex justify-between gap-4">
                  <dt>{{ franja.etiqueta }}</dt>
                  <dd class="cifras-tabulares">{{ franja.texto }}</dd>
                </div>
              </dl>
            </div>

            <div class="min-w-0">
              <h3 class="flex items-center gap-2 text-lg font-semibold text-white mb-4">
                <WalletIcon class="size-5" aria-hidden="true" />
                Tarifas por zona
              </h3>
              <table class="w-full text-sm text-white/90">
                <caption class="sr-only">Tiempo y tarifa de envío por zona de San Juan de Lurigancho</caption>
                <thead>
                  <tr class="text-left text-white">
                    <th scope="col" class="pb-2 font-semibold">Zona</th>
                    <th scope="col" class="pb-2 font-semibold">Tiempo</th>
                    <th scope="col" class="pb-2 font-semibold text-right">Tarifa</th>
                  </tr>
                </thead>
                <tbody class="cifras-tabulares">
                  <tr v-for="zona in ZONAS" :key="zona.id" class="border-t border-white/15">
                    <th scope="row" class="py-1.5 font-normal">{{ zona.nombre }}</th>
                    <td class="py-1.5">{{ rangoMinutos(zona) }}</td>
                    <td class="py-1.5 text-right">{{ soles(zona.tarifa) }}</td>
                  </tr>
                </tbody>
              </table>
              <p class="mt-3 text-xs text-white/80">Pedido mínimo {{ soles(PEDIDO_MINIMO) }}. La tarifa se confirma al tomar el pedido.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
/* Esta sección tenía un segundo mapa de Leaflet con rectángulos de Surco,
   San Borja, Cercado y Callao. Con la cobertura reducida a SJL, el mapa del
   selector ya lo cuenta todo; un segundo mapa solo duplicaba peso y teselas. */
import { MessageCircleIcon, PhoneIcon, MailIcon, ClockIcon, WalletIcon } from 'lucide-vue-next'
import { CORREO, HORARIO, TELEFONO_FIJO, TELEFONO_FIJO_E164, enlaceWhatsApp } from '@/datos/botica'
import { PEDIDO_MINIMO, ZONAS, rangoMinutos, soles } from '@/datos/cobertura'

const enlaceWa = enlaceWhatsApp('Hola, quisiera confirmar si llegan a mi dirección en SJL.')
</script>
