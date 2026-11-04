<script setup lang="ts">
/**
 * RecetaDelPedido · Lo que ve el cliente sobre la receta de su pedido
 * ---------------------------------------------------------------------------
 * CUÁNDO APARECE Y CUÁNDO NO
 *
 * Solo si el pedido lleva algún producto de venta bajo receta. En los demás
 * —que son la mayoría: paracetamol, pañales, alcohol— no se enseña nada.
 * Un bloque que diga «este pedido no necesita receta» es ruido que obliga a
 * leer para descubrir que no hay nada que hacer.
 *
 * LO QUE NUNCA DICE
 *
 * Ni «recibido correctamente» ni ningún tilde verde al subir. Subir no es
 * acreditar: entre lo uno y lo otro está la persona del mostrador que la
 * mira. El único verde de esta pantalla aparece cuando alguien con título ha
 * decidido, y entonces lleva su nombre al lado.
 *
 * EL RECHAZO LLEGA CON SU MOTIVO
 *
 * Un «rechazada» a secas deja al cliente adivinando qué repetir, y acaba en
 * una segunda receta igual de inservible. El motivo lo escribe el boticario y
 * se muestra entero, no recortado.
 */
import { computed, onMounted, ref } from 'vue'
import { Stethoscope } from 'lucide-vue-next'

import DistintivoReceta from './DistintivoReceta.vue'
import ZonaDeReceta from './ZonaDeReceta.vue'
import { estadoDeReceta, subirReceta, type Receta } from '@/services/recetas'

const props = defineProps<{ pedidoId: number }>()
const emit = defineEmits<{ (e: 'cambio', receta: Receta | null): void }>()

const cargando = ref(true)
const subiendo = ref(false)
const requiere = ref(false)
const receta = ref<Receta | null>(null)
const aviso = ref('')

const validada = computed(() => receta.value?.estado === 'validada')

const fechas = new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short' })

function fecha(valor: string | null): string {
  return valor ? fechas.format(new Date(valor)) : ''
}

async function cargar() {
  try {
    const estado = await estadoDeReceta(props.pedidoId)
    requiere.value = estado.requiere_receta
    receta.value = estado.receta
  } catch {
    /* Si no se puede consultar, no se finge que no hace falta receta: se
       deja el bloque visible con el aviso. Dar por bueno el silencio sería
       decirle al cliente que su pedido puede salir sin receta. */
    requiere.value = true
    aviso.value = 'No pudimos consultar el estado de tu receta. Vuelve a intentarlo en un momento.'
  } finally {
    cargando.value = false
  }
}

async function enviar(archivo: File) {
  subiendo.value = true
  aviso.value = ''

  try {
    receta.value = await subirReceta(props.pedidoId, archivo)
    emit('cambio', receta.value)
  } catch (error: unknown) {
    const respuesta = (error as { response?: { status?: number; data?: { message?: string } } }).response

    aviso.value =
      respuesta?.status === 429
        ? 'Has enviado varias recetas seguidas. Espera unos minutos antes de volver a intentarlo.'
        : (respuesta?.data?.message ?? 'No pudimos enviar la receta. Inténtalo de nuevo.')
  } finally {
    subiendo.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <!-- Altura mínima reservada: la tarjeta no crece de golpe al resolverse la
       consulta y empujar el resto de la página. -->
  <section
    v-if="cargando || requiere"
    class="min-h-44 rounded-2xl border border-borde-sutil bg-superficie-elevada p-5"
    aria-labelledby="titulo-receta"
  >
    <header class="mb-4 flex items-start gap-2.5">
      <Stethoscope class="mt-0.5 size-5 shrink-0 text-texto-acento" />
      <div class="min-w-0 flex-1">
        <h2
          id="titulo-receta"
          class="font-semibold text-texto-primario"
        >
          Receta médica
        </h2>
        <p class="text-sm text-texto-secundario">
          Este pedido incluye medicamentos de venta bajo receta.
        </p>
      </div>
      <DistintivoReceta
        v-if="!cargando"
        :estado="receta?.estado ?? null"
      />
    </header>

    <p
      v-if="cargando"
      class="text-sm text-texto-secundario"
    >
      Consultando…
    </p>

    <template v-else>
      <!-- Validada: no hay nada que hacer, y se dice quién lo decidió. -->
      <div
        v-if="validada"
        class="rounded-xl bg-superficie-hundida p-4"
      >
        <p class="text-sm text-texto-primario">
          Tu receta quedó validada{{ receta?.revisada_por ? ` por ${receta.revisada_por}` : '' }}.
          Ya podemos preparar tu pedido.
        </p>
        <p
          v-if="receta?.revisada_en"
          class="mt-1 text-xs text-texto-terciario"
        >
          {{ fecha(receta.revisada_en) }}
        </p>
      </div>

      <template v-else>
        <!-- Rechazo: el motivo va completo y antes de la zona de subida, que
             es el orden en que hace falta leerlo. -->
        <div
          v-if="receta?.estado === 'rechazada'"
          class="mb-4 rounded-xl border border-peligro-500/30 bg-peligro-50 p-4 dark:bg-peligro-600/10"
        >
          <p class="text-sm font-medium text-texto-primario">
            El químico farmacéutico no pudo aceptar esta receta:
          </p>
          <p class="mt-1 text-sm text-texto-secundario">
            {{ receta.motivo_rechazo }}
          </p>
          <p class="mt-2 text-xs text-texto-terciario">
            Puedes adjuntar otra aquí mismo.
          </p>
        </div>

        <!-- Enviada y en espera. Sin tilde verde: subir no es acreditar. -->
        <div
          v-else-if="receta"
          class="mb-4 rounded-xl bg-superficie-hundida p-4"
        >
          <p class="text-sm text-texto-primario">
            Recibimos «{{ receta.nombre_original }}». El químico farmacéutico la revisará
            antes de preparar tu pedido.
          </p>
          <p class="mt-1 text-xs text-texto-terciario">
            Enviada el {{ fecha(receta.created_at) }}<span v-if="receta.version > 1">
              · intento {{ receta.version }}</span>
          </p>
        </div>

        <ZonaDeReceta
          :subiendo="subiendo"
          @elegir="enviar"
        />
      </template>

      <p
        class="mt-2 min-h-5 text-sm text-peligro-700"
        role="alert"
      >
        {{ aviso }}
      </p>

      <p class="mt-2 text-xs leading-relaxed text-texto-terciario">
        Tu receta solo la ve el personal de la botica. No se publica en ninguna
        dirección y no se comparte con terceros.
      </p>
    </template>
  </section>
</template>
