<template>
  <section class="py-24 bg-superficie-elevada">
    <div class="container mx-auto px-6">
      <!-- History Section -->
      <div class="mb-24">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
          <div class="history-content">
            <div class="inline-flex items-center gap-2 bg-clinico-100 text-clinico-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
              <Heart class="w-4 h-4" />
              Nuestra Historia
            </div>
            <h2 class="text-4xl md:text-5xl font-bold text-texto-primario mb-6">
              Más de 8 años cuidando tu salud
            </h2>
            <p class="text-lg text-texto-secundario mb-6 leading-relaxed">
              Fundada en 2018, Botica San Juan comenzó como un pequeño establecimiento familiar
              con el sueño de proporcionar medicamentos y productos de salud de calidad a nuestra comunidad.
            </p>
            <p class="text-lg text-texto-secundario mb-8 leading-relaxed">
              A lo largo de los años, hemos crecido manteniendo nuestros valores fundamentales:
              atención personalizada, productos de calidad y compromiso con el bienestar de nuestros clientes.
            </p>
            <div class="grid grid-cols-2 gap-6">
              <div class="text-center">
                <div class="text-3xl font-bold text-botica-600 mb-2">
                  2018
                </div>
                <div class="text-texto-secundario">
                  Inicio de actividades
                </div>
              </div>
              <!-- Antes «50K+ Clientes», sin registro que lo respaldara. -->
              <div class="text-center">
                <div class="text-3xl font-bold text-botica-600 mb-2">
                  2022
                </div>
                <div class="text-texto-secundario">
                  Emisor electrónico
                </div>
              </div>
            </div>
          </div>
          <div class="history-image min-w-0">
            <!-- Antes: una tarjeta vacía con un corazón y una insignia
                 «Premio Calidad 2023» sin premio conocido detrás. Ahora,
                 cuatro hechos que el sistema puede respaldar. -->
            <div class="relative rounded-3xl bg-clinico-700 p-6 sm:p-8 text-white shadow-xl overflow-hidden isolate">
              <div class="absolute -right-16 -top-16 -z-10 size-56 rounded-full bg-clinico-500/40" aria-hidden="true" />
              <div class="absolute -bottom-20 -left-10 -z-10 size-48 rounded-full bg-clinico-900/50" aria-hidden="true" />

              <div class="flex items-center gap-4 mb-8">
                <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white/15">
                  <PillIcon class="size-7" aria-hidden="true" />
                </div>
                <div>
                  <p class="text-xl font-bold">Botica San Juan</p>
                  <p class="text-white/85">{{ DIRECCION.calle }} · SJL</p>
                </div>
              </div>

              <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <li
                  v-for="insignia in insignias"
                  :key="insignia.titulo"
                  class="flex items-start gap-3 rounded-2xl bg-white/10 p-4 backdrop-blur-sm"
                >
                  <component :is="insignia.icono" class="mt-0.5 size-6 shrink-0 text-clinico-200" aria-hidden="true" />
                  <div class="min-w-0">
                    <p class="font-semibold leading-snug">{{ insignia.titulo }}</p>
                    <p class="text-sm text-white/80">{{ insignia.detalle }}</p>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      <!-- Vision & Mission Section -->
      <div class="mb-24">
        <div class="text-center mb-16">
          <h2 class="text-4xl md:text-5xl font-bold text-texto-primario mb-6">
            Nuestra Visión y Misión
          </h2>
          <p class="text-xl text-texto-secundario max-w-3xl mx-auto">
            Guiados por principios que nos han acompañado desde nuestros inicios
          </p>
        </div>

        <div class="grid md:grid-cols-2 gap-12">
          <!-- Vision Card -->
          <div class="vision-card bg-linear-to-br from-clinico-500/10 to-clinico-500/15 rounded-3xl p-8 border border-clinico-200">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-16 h-16 bg-clinico-500 rounded-2xl flex items-center justify-center">
                <Eye class="w-8 h-8 text-white" />
              </div>
              <h3 class="text-2xl font-bold text-texto-primario">
                Visión
              </h3>
            </div>
            <p class="text-texto-secundario leading-relaxed">
              Ser la botica de referencia en la comunidad, reconocida por nuestra excelencia
              en el servicio, innovación en productos de salud y compromiso inquebrantable
              con el bienestar de nuestros clientes.
            </p>
          </div>

          <!-- Mission Card -->
          <div class="mission-card bg-linear-to-br from-botica-500/10 to-botica-500/15 rounded-3xl p-8 border border-botica-200">
            <div class="flex items-center gap-4 mb-6">
              <div class="w-16 h-16 bg-botica-500 rounded-2xl flex items-center justify-center">
                <Target class="w-8 h-8 text-white" />
              </div>
              <h3 class="text-2xl font-bold text-texto-primario">
                Misión
              </h3>
            </div>
            <p class="text-texto-secundario leading-relaxed">
              Proporcionar productos farmacéuticos y de salud de la más alta calidad,
              ofreciendo asesoramiento profesional y atención personalizada para mejorar
              la calidad de vida de nuestros clientes.
            </p>
          </div>
        </div>
      </div>

      <!-- Values Section -->
      <div class="text-center">
        <div class="inline-flex items-center gap-2 bg-clinico-100 text-clinico-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
          <Shield class="w-4 h-4" />
          Nuestros Valores
        </div>
        <h2 class="text-4xl md:text-5xl font-bold text-texto-primario mb-16">
          Lo que nos define
        </h2>

        <div class="grid md:grid-cols-3 gap-8">
          <div class="values-card text-center">
            <div class="w-20 h-20 bg-clinico-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
              <Shield class="w-10 h-10 text-clinico-600" />
            </div>
            <h3 class="text-xl font-bold text-texto-primario mb-4">
              Confianza
            </h3>
            <p class="text-texto-secundario">
              Construimos relaciones duraderas basadas en la honestidad,
              transparencia y compromiso con nuestros clientes.
            </p>
          </div>

          <div class="values-card text-center">
            <div class="w-20 h-20 bg-clinico-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
              <Users class="w-10 h-10 text-clinico-600" />
            </div>
            <h3 class="text-xl font-bold text-texto-primario mb-4">
              Atención Personalizada
            </h3>
            <p class="text-texto-secundario">
              Cada cliente es único. Ofrecemos asesoramiento personalizado
              adaptado a las necesidades individuales de cada persona.
            </p>
          </div>

          <div class="values-card text-center">
            <div class="w-20 h-20 bg-clinico-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
              <Award class="w-10 h-10 text-clinico-600" />
            </div>
            <h3 class="text-xl font-bold text-texto-primario mb-4">
              Excelencia
            </h3>
            <p class="text-texto-secundario">
              Mantenemos los más altos estándares de calidad en nuestros
              productos y servicios, superando las expectativas.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { gsap } from 'gsap'
import { Heart, Eye, Target, Shield, Users, Award, Pill as PillIcon, BadgeCheck, Snowflake, ReceiptText, MapPin } from 'lucide-vue-next'
import { DIRECCION } from '@/datos/botica'

const insignias = [
  { titulo: 'Autorizado por DIGEMID', detalle: 'Establecimiento farmacéutico registrado', icono: BadgeCheck },
  { titulo: 'Cadena de frío controlada', detalle: 'Insulinas y vacunas refrigeradas', icono: Snowflake },
  { titulo: 'Comprobantes SUNAT', detalle: 'Boletas y facturas electrónicas', icono: ReceiptText },
  { titulo: '+8 años en SJL', detalle: 'Atendiendo al distrito desde 2018', icono: MapPin },
]
import { prefiereMenosMovimiento } from '@/utils/motion'

onMounted(() => {
  /* Si la persona pidió reducir el movimiento, no se anima: el contenido ya
     está en su estado final y visible, que es justamente lo que se quiere. */
  if (prefiereMenosMovimiento()) return
  // Animate history section
  gsap.from('.history-content', {
    duration: 1,
    x: -50,
    opacity: 0,
    ease: 'power3.out'
  })

  gsap.from('.history-image', {
    duration: 1,
    x: 50,
    opacity: 0,
    ease: 'power3.out',
    delay: 0.3
  })

  // Animate vision and mission cards
  gsap.from('.vision-card', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    ease: 'power3.out',
    delay: 0.6
  })

  gsap.from('.mission-card', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    ease: 'power3.out',
    delay: 0.8
  })

  // Animate values cards with stagger
  gsap.from('.values-card', {
    duration: 0.8,
    y: 30,
    opacity: 0,
    stagger: 0.2,
    ease: 'power3.out',
    delay: 1.0
  })
})
</script>

<style scoped>
/* Component styles */
</style>