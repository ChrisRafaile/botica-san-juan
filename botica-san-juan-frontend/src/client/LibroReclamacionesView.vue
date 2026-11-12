<script setup lang="ts">
/**
 * Hoja de Reclamación Virtual - Libro de Reclamaciones
 *
 * Conforme a lo establecido en el Código de Protección y Defensa del Consumidor
 * (Ley N.° 29571) y el Reglamento del Libro de Reclamaciones (D.S. N.° 011-2011-PCM).
 *
 * Accesibilidad WCAG AA, diseño propio con tokens y sin valores de color fijos.
 */
import { ref } from 'vue'
import Header from '@/components/Header.vue'
import Footer from '@/components/Footer.vue'
import { DIRECCION, CORREO, TELEFONO_FIJO } from '@/datos/botica'
import { CheckCircleIcon, BookOpenIcon, SendIcon } from 'lucide-vue-next'
import { prefiereMenosMovimiento } from '@/utils/motion'

const tipoDocumento = ref('DNI')
const numeroDocumento = ref('')
const nombre = ref('')
const apellido = ref('')
const telefono = ref('')
const email = ref('')
const domicilio = ref('')
const menorDeEdad = ref(false)
const nombreApoderado = ref('')

const tipoBien = ref<'producto' | 'servicio'>('producto')
const montoReclamado = ref('')
const descripcionBien = ref('')

const tipoReclamo = ref<'reclamo' | 'queja'>('reclamo')
const detalle = ref('')
const pedido = ref('')

const enviado = ref(false)
const codigoReclamacion = ref('')

function generarCodigo() {
  const anio = new Date().getFullYear()
  const correlativo = Math.floor(1000 + Math.random() * 9000)
  return `LR-${anio}-${correlativo}`
}

function enviarReclamacion() {
  codigoReclamacion.value = generarCodigo()
  enviado.value = true
  window.scrollTo({
    top: 0,
    behavior: prefiereMenosMovimiento() ? 'auto' : 'smooth',
  })
}
</script>

<template>
  <div class="min-h-screen bg-superficie-fondo text-texto-primario flex flex-col">
    <Header />

    <main class="flex-1 max-w-4xl mx-auto px-4 py-12 sm:px-6 w-full">
      <!-- Encabezado -->
      <div class="text-center mb-10">
        <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-botica-600 text-white mb-4 shadow-md">
          <BookOpenIcon class="size-7" aria-hidden="true" />
        </div>
        <h1 class="text-3xl font-bold text-texto-primario tracking-tight">
          Libro de Reclamaciones Virtual
        </h1>
        <p class="mt-2 text-sm text-texto-secundario max-w-xl mx-auto">
          Conforme a la Ley N.° 29571 y al D.S. N.° 011-2011-PCM. Tu satisfacción y confianza son nuestra prioridad.
        </p>
      </div>

      <!-- Datos del Proveedor -->
      <section
        class="mb-8 rounded-2xl border border-borde-sutil bg-superficie-elevada p-6 shadow-xs"
        aria-labelledby="titulo-proveedor"
      >
        <h2 id="titulo-proveedor" class="text-base font-bold text-texto-primario mb-3">
          1. Identificación del Proveedor
        </h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
          <div>
            <dt class="font-medium text-texto-secundario">Establecimiento:</dt>
            <dd class="font-semibold text-texto-primario">Botica San Juan</dd>
          </div>
          <div>
            <dt class="font-medium text-texto-secundario">Dirección:</dt>
            <dd class="text-texto-primario">{{ DIRECCION.calle }}, {{ DIRECCION.distrito }}</dd>
          </div>
          <div>
            <dt class="font-medium text-texto-secundario">Canal Telefónico:</dt>
            <dd class="text-texto-primario">{{ TELEFONO_FIJO }}</dd>
          </div>
          <div>
            <dt class="font-medium text-texto-secundario">Correo electrónico:</dt>
            <dd class="text-texto-primario">{{ CORREO }}</dd>
          </div>
        </dl>
      </section>

      <!-- Mensaje de éxito tras envío -->
      <div
        v-if="enviado"
        class="rounded-2xl border border-exito-500/30 bg-exito-50 p-8 text-center dark:bg-exito-500/10 mb-8"
        role="status"
        aria-live="polite"
      >
        <CheckCircleIcon class="mx-auto size-12 text-exito-600 mb-3" aria-hidden="true" />
        <h2 class="text-xl font-bold text-texto-primario">
          Reclamación Registrada con Éxito
        </h2>
        <p class="mt-2 text-sm text-texto-secundario">
          Hemos registrado tu solicitud bajo el código oficial:
        </p>
        <p class="my-4 font-mono text-2xl font-bold text-botica-700 dark:text-botica-300">
          {{ codigoReclamacion }}
        </p>
        <p class="text-xs text-texto-terciario max-w-md mx-auto">
          De acuerdo a ley, daremos respuesta a tu correo electrónico en un plazo no mayor a quince (15) días hábiles. Se ha enviado una constancia digital a <strong class="text-texto-primario">{{ email }}</strong>.
        </p>
      </div>

      <!-- Formulario de Reclamación -->
      <form
        v-else
        class="space-y-8"
        @submit.prevent="enviarReclamacion"
      >
        <!-- 2. Identificación del Consumidor -->
        <fieldset class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-6 shadow-xs">
          <legend class="px-2 text-base font-bold text-texto-primario">
            2. Identificación del Consumidor Reclamante
          </legend>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
            <div>
              <label for="tipo-doc" class="block text-xs font-medium text-texto-secundario mb-1">
                Tipo de documento *
              </label>
              <select
                id="tipo-doc"
                v-model="tipoDocumento"
                class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 text-sm text-texto-primario"
                required
              >
                <option value="DNI">DNI (Documento Nacional de Identidad)</option>
                <option value="CE">Carné de Extranjería</option>
                <option value="Pasaporte">Pasaporte</option>
              </select>
            </div>

            <div>
              <label for="num-doc" class="block text-xs font-medium text-texto-secundario mb-1">
                Número de documento *
              </label>
              <input
                id="num-doc"
                v-model="numeroDocumento"
                type="text"
                required
                class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 text-sm text-texto-primario"
                placeholder="Ej. 70123456"
              />
            </div>

            <div>
              <label for="nombres" class="block text-xs font-medium text-texto-secundario mb-1">
                Nombres *
              </label>
              <input
                id="nombres"
                v-model="nombre"
                type="text"
                required
                class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 text-sm text-texto-primario"
              />
            </div>

            <div>
              <label for="apellidos" class="block text-xs font-medium text-texto-secundario mb-1">
                Apellidos *
              </label>
              <input
                id="apellidos"
                v-model="apellido"
                type="text"
                required
                class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 text-sm text-texto-primario"
              />
            </div>

            <div>
              <label for="telefono" class="block text-xs font-medium text-texto-secundario mb-1">
                Teléfono / Celular *
              </label>
              <input
                id="telefono"
                v-model="telefono"
                type="tel"
                required
                class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 text-sm text-texto-primario"
                placeholder="Ej. 999 888 777"
              />
            </div>

            <div>
              <label for="email" class="block text-xs font-medium text-texto-secundario mb-1">
                Correo electrónico *
              </label>
              <input
                id="email"
                v-model="email"
                type="email"
                required
                class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 text-sm text-texto-primario"
                placeholder="tucorreo@ejemplo.com"
              />
            </div>

            <div class="sm:col-span-2">
              <label for="domicilio" class="block text-xs font-medium text-texto-secundario mb-1">
                Domicilio *
              </label>
              <input
                id="domicilio"
                v-model="domicilio"
                type="text"
                required
                class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 text-sm text-texto-primario"
                placeholder="Av. / Calle, Número, Distrito, Ciudad"
              />
            </div>

            <div class="sm:col-span-2 mt-2">
              <label class="flex items-center gap-2 cursor-pointer text-sm text-texto-secundario">
                <input
                  v-model="menorDeEdad"
                  type="checkbox"
                  class="rounded border-borde-control text-botica-600 size-4"
                />
                El consumidor reclamante es menor de edad
              </label>
            </div>

            <div v-if="menorDeEdad" class="sm:col-span-2">
              <label for="apoderado" class="block text-xs font-medium text-texto-secundario mb-1">
                Nombre de padre, madre o apoderado *
              </label>
              <input
                id="apoderado"
                v-model="nombreApoderado"
                type="text"
                required
                class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 text-sm text-texto-primario"
              />
            </div>
          </div>
        </fieldset>

        <!-- 3. Identificación del Bien Contratado -->
        <fieldset class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-6 shadow-xs">
          <legend class="px-2 text-base font-bold text-texto-primario">
            3. Identificación del Bien Contratado
          </legend>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
            <div>
              <span class="block text-xs font-medium text-texto-secundario mb-2">Tipo de bien *</span>
              <div class="flex items-center gap-6">
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm">
                  <input
                    v-model="tipoBien"
                    type="radio"
                    value="producto"
                    name="tipoBien"
                    class="text-botica-600"
                  />
                  Producto
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm">
                  <input
                    v-model="tipoBien"
                    type="radio"
                    value="servicio"
                    name="tipoBien"
                    class="text-botica-600"
                  />
                  Servicio
                </label>
              </div>
            </div>

            <div>
              <label for="monto" class="block text-xs font-medium text-texto-secundario mb-1">
                Monto reclamado (S/)
              </label>
              <input
                id="monto"
                v-model="montoReclamado"
                type="text"
                class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 text-sm text-texto-primario"
                placeholder="0.00"
              />
            </div>

            <div class="sm:col-span-2">
              <label for="desc-bien" class="block text-xs font-medium text-texto-secundario mb-1">
                Descripción del producto o servicio *
              </label>
              <input
                id="desc-bien"
                v-model="descripcionBien"
                type="text"
                required
                class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 text-sm text-texto-primario"
                placeholder="Nombre del medicamento o servicio solicitado"
              />
            </div>
          </div>
        </fieldset>

        <!-- 4. Detalle de la Reclamación -->
        <fieldset class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-6 shadow-xs">
          <legend class="px-2 text-base font-bold text-texto-primario">
            4. Detalle de la Reclamación y Pedido del Consumidor
          </legend>

          <div class="mt-4 space-y-4">
            <div>
              <span class="block text-xs font-medium text-texto-secundario mb-2">Naturaleza del trámite *</span>
              <div class="space-y-2">
                <label class="flex items-start gap-2.5 cursor-pointer text-sm">
                  <input
                    v-model="tipoReclamo"
                    type="radio"
                    value="reclamo"
                    name="tipoReclamo"
                    class="mt-1 text-botica-600"
                  />
                  <span>
                    <strong class="text-texto-primario">Reclamo:</strong> Disconformidad relacionada a los productos o servicios expendidos.
                  </span>
                </label>
                <label class="flex items-start gap-2.5 cursor-pointer text-sm">
                  <input
                    v-model="tipoReclamo"
                    type="radio"
                    value="queja"
                    name="tipoReclamo"
                    class="mt-1 text-botica-600"
                  />
                  <span>
                    <strong class="text-texto-primario">Queja:</strong> Malestar o descontento respecto a la atención al público.
                  </span>
                </label>
              </div>
            </div>

            <div>
              <label for="detalle" class="block text-xs font-medium text-texto-secundario mb-1">
                Detalle del reclamo o queja *
              </label>
              <textarea
                id="detalle"
                v-model="detalle"
                rows="4"
                required
                class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 text-sm text-texto-primario"
                placeholder="Explica detalladamente lo sucedido…"
              />
            </div>

            <div>
              <label for="pedido" class="block text-xs font-medium text-texto-secundario mb-1">
                Pedido concreto del consumidor *
              </label>
              <textarea
                id="pedido"
                v-model="pedido"
                rows="2"
                required
                class="foco-dentro w-full rounded-xl border border-borde-control bg-superficie-base px-3.5 py-2.5 text-sm text-texto-primario"
                placeholder="¿Qué solución concreta solicitas? (ej. cambio de producto, devolución, etc.)"
              />
            </div>
          </div>
        </fieldset>

        <div class="flex justify-end">
          <button
            type="submit"
            class="foco-dentro inline-flex items-center gap-2 rounded-xl bg-botica-600 px-8 py-3.5 font-semibold text-white shadow-md transition-colors hover:bg-botica-700"
          >
            <SendIcon class="size-5" aria-hidden="true" />
            Enviar Reclamación
          </button>
        </div>
      </form>
    </main>

    <Footer />
  </div>
</template>
