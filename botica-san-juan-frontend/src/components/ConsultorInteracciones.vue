<script setup lang="ts">
/**
 * ConsultorInteracciones · Revisión del carrito
 * ---------------------------------------------------------------------------
 * LO QUE ESTE COMPONENTE NO PUEDE HACER
 *
 * No puede decir que una combinación sea segura. Ni con esas palabras ni con
 * un tilde verde que signifique lo mismo.
 *
 * El motivo está en los datos: la tabla de interacciones cubre una parte
 * pequeña de las combinaciones posibles, y solo el 15 % del catálogo tiene
 * principio activo mapeado. En esas condiciones, la ausencia de aviso
 * significa «no lo sé», y presentarla como tranquilidad sería mentir sobre
 * medicamentos.
 *
 * De ahí tres reglas de interfaz:
 *
 *   1. La cobertura —«revisados 2 de 4»— se enseña SIEMPRE, también cuando
 *      no hay ningún aviso.
 *   2. Los productos sin mapear se listan con su nombre, no se resumen en un
 *      número que nadie mira.
 *   3. La derivación al químico farmacéutico no es un enlace discreto al pie:
 *      es parte del resultado.
 */
import { computed, ref, watch } from 'vue'
import { ShieldAlert, TriangleAlert, Info, Stethoscope, Loader2 } from 'lucide-vue-next'
import {
  evaluarInteracciones,
  type ResultadoInteracciones,
} from '@/services/interacciones'

const props = defineProps<{
  /** Identificadores de los productos del carrito. */
  productoIds: number[]
}>()

const resultado = ref<ResultadoInteracciones | null>(null)
const cargando = ref(false)
const fallo = ref(false)

const ESTILO_SEVERIDAD = {
  grave: {
    marco: 'border-peligro-600/40 bg-peligro-50 dark:bg-peligro-600/10',
    texto: 'text-peligro-700 dark:text-peligro-500',
    etiqueta: 'Interacción grave',
  },
  moderada: {
    marco: 'border-alerta-500/40 bg-alerta-50 dark:bg-alerta-500/10',
    texto: 'text-alerta-700 dark:text-alerta-500',
    etiqueta: 'Interacción moderada',
  },
  leve: {
    marco: 'border-ambar-600/40 bg-ambar-50 dark:bg-ambar-600/10',
    texto: 'text-ambar-700 dark:text-ambar-500',
    etiqueta: 'Interacción leve',
  },
} as const

const cobertura = computed(() => {
  if (!resultado.value) return null
  const revisados = resultado.value.revisados.length
  const total = revisados + resultado.value.no_identificados.length
  return { revisados, total }
})

async function revisar() {
  if (props.productoIds.length === 0) {
    resultado.value = null
    return
  }

  cargando.value = true
  fallo.value = false
  try {
    resultado.value = await evaluarInteracciones(props.productoIds)
  } catch {
    fallo.value = true
    resultado.value = null
  } finally {
    cargando.value = false
  }
}

/* Se vuelve a revisar cuando cambia el carrito. `immediate` para que la
   primera carga no necesite que el usuario toque nada. */
watch(() => props.productoIds, revisar, { immediate: true, deep: true })
</script>

<template>
  <section
    v-if="productoIds.length > 0"
    class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-5"
    aria-labelledby="titulo-consultor"
  >
    <header class="mb-4 flex items-start gap-2.5">
      <Stethoscope class="mt-0.5 size-5 shrink-0 text-texto-acento" />
      <div>
        <h2
          id="titulo-consultor"
          class="font-semibold text-texto-primario"
        >
          Revisión de tu carrito
        </h2>
        <p class="text-sm text-texto-secundario">
          Comprobamos si hay interacciones registradas entre lo que llevas.
        </p>
      </div>
    </header>

    <p
      v-if="cargando"
      class="flex items-center gap-2 text-sm text-texto-secundario"
    >
      <Loader2 class="size-4 animate-spin" />
      Revisando…
    </p>

    <p
      v-else-if="fallo"
      class="text-sm text-texto-secundario"
    >
      No se pudo completar la revisión. Consulta al químico farmacéutico antes
      de combinar tus medicamentos.
    </p>

    <div
      v-else-if="resultado"
      class="space-y-4"
    >
      <!-- Avisos: lo grave primero, que es como llega del servidor. -->
      <article
        v-for="(aviso, i) in resultado.avisos"
        :key="i"
        class="rounded-xl border p-4"
        :class="ESTILO_SEVERIDAD[aviso.severidad].marco"
      >
        <p
          class="mb-1.5 flex items-center gap-2 text-sm font-semibold"
          :class="ESTILO_SEVERIDAD[aviso.severidad].texto"
        >
          <ShieldAlert class="size-4 shrink-0" />
          {{ ESTILO_SEVERIDAD[aviso.severidad].etiqueta }}
        </p>
        <p class="text-sm text-texto-primario">
          {{ aviso.productos.join(' + ') }}
        </p>
        <p class="mt-1.5 text-sm text-texto-secundario">
          {{ aviso.efecto }}
        </p>
        <p class="mt-1.5 text-sm font-medium text-texto-primario">
          {{ aviso.recomendacion }}
        </p>
      </article>

      <!-- Cobertura. Se enseña SIEMPRE, también sin avisos: es la diferencia
           entre «no encontré nada» y «no hay nada». -->
      <div
        v-if="cobertura"
        class="rounded-xl bg-superficie-hundida p-4"
      >
        <p class="flex items-center gap-2 text-sm font-medium text-texto-primario">
          <Info class="size-4 shrink-0 text-texto-terciario" />
          Revisados {{ cobertura.revisados }} de {{ cobertura.total }} productos
        </p>
        <p class="mt-1.5 text-sm text-texto-secundario">
          {{ resultado.mensaje }}
        </p>

        <div
          v-if="resultado.no_identificados.length"
          class="mt-3"
        >
          <p class="mb-1.5 text-xs font-medium text-texto-secundario">
            Sin principio activo mapeado:
          </p>
          <ul class="flex flex-wrap gap-1.5">
            <li
              v-for="producto in resultado.no_identificados"
              :key="producto.producto_id"
              class="rounded-full bg-superficie-elevada px-2.5 py-1 text-xs text-texto-secundario ring-1 ring-borde-sutil"
            >
              {{ producto.nombre }}
            </li>
          </ul>
          <p class="mt-2 text-xs leading-relaxed text-texto-terciario">
            Los productos con nombre comercial no mapeado requieren validación
            directa del químico farmacéutico.
          </p>
        </div>
      </div>

      <!-- Derivación. No es un enlace al pie: es parte del resultado. -->
      <div
        v-if="resultado.derivar"
        class="flex items-start gap-2.5 rounded-xl border border-clinico-600/30 bg-clinico-50 p-4 dark:bg-clinico-600/10"
      >
        <TriangleAlert class="mt-0.5 size-4 shrink-0 text-texto-acento" />
        <p class="text-sm text-texto-primario">
          <strong>Consulta al químico farmacéutico</strong> antes de tomar estos
          productos juntos, sobre todo si sigues algún tratamiento. En el
          mostrador pueden revisarlo contigo.
        </p>
      </div>

      <p class="text-xs leading-relaxed text-texto-terciario">
        {{ resultado.aviso_legal }}
      </p>
    </div>
  </section>
</template>
