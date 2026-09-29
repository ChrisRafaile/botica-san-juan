<script setup lang="ts">
/**
 * Conteo físico por ciclos
 * ---------------------------------------------------------------------------
 * Pensada para usarse de pie frente al anaquel, con el teclado o con el dedo.
 * De ahí tres decisiones:
 *
 * 1. Una sola línea activa a la vez. Contar es secuencial: se coge el producto,
 *    se cuenta, se anota, se pasa al siguiente. Una tabla editable entera
 *    invita a perder el sitio y a teclear la cantidad en la fila equivocada.
 *
 * 2. La fecha de vencimiento está a la vista, no escondida tras un botón. Es el
 *    dato que más falta le hace al sistema: el FEFO ya está construido pero hoy
 *    no tiene ninguna fecha con la que ordenar.
 *
 * 3. Nada se aplica hasta cerrar. Mientras se cuenta, el inventario no se toca;
 *    así una venta ocurrida a media mañana no se confunde con un descuadre.
 */

import { computed, onMounted, nextTick, ref } from 'vue'
import {
  ClipboardList, Package, CalendarClock, Plus, Trash2, Check,
  TriangleAlert, ArrowRight, CircleCheck,
} from 'lucide-vue-next'
import { useConteo, CRITERIOS, type LineaConteo, type LoteContado, type CriterioConteo } from '../composables/useConteo'
import { useAdminToast } from '../composables/useAdminToast'
import BoticaButton from '../components/ui/BoticaButton.vue'
import BoticaBadge from '../components/ui/BoticaBadge.vue'
import BoticaStat from '../components/ui/BoticaStat.vue'
import BoticaEstadoVacio from '../components/ui/BoticaEstadoVacio.vue'

const {
  sesion, cargando, guardando, cerrando,
  haySesion, pendientes, contadas, conDiferencia, conFechaCapturada, progreso,
  cargarAbierto, abrir, registrar, cerrar, anular,
} = useConteo()

const { notifyError, notifySuccess } = useAdminToast()

/* Configuración para abrir una sesión nueva. */
const criterioElegido = ref<CriterioConteo>('sin_lote')
const cantidadElegida = ref(25)

/* Línea que se está contando ahora mismo. */
const lineaActiva = ref<LineaConteo | null>(null)
const cantidad = ref<number | null>(null)
const lotes = ref<LoteContado[]>([])
const observacion = ref('')
const campoCantidad = ref<HTMLInputElement | null>(null)

const panelCierre = ref(false)

onMounted(async () => {
  try {
    await cargarAbierto()
  } catch {
    notifyError('No se pudo cargar', 'Revisa la conexión con el servidor.')
  }
})

/* ---------------------------------------------------------------------------
   Abrir sesión
   --------------------------------------------------------------------------- */

async function abrirSesion() {
  try {
    await abrir(criterioElegido.value, cantidadElegida.value)
    notifySuccess('Conteo abierto', `${sesion.value?.total} productos por contar.`)
    siguientePendiente()
  } catch (error: unknown) {
    const mensaje = (error as { response?: { data?: { message?: string } } })
      .response?.data?.message
    notifyError('No se pudo abrir', mensaje ?? 'Inténtalo de nuevo.')
  }
}

/* ---------------------------------------------------------------------------
   Contar
   --------------------------------------------------------------------------- */

function seleccionar(linea: LineaConteo) {
  lineaActiva.value = linea
  cantidad.value = linea.cantidad_contada
  observacion.value = linea.observacion ?? ''

  /* Si ya se contó, se reabre con lo anotado. Si no, se precarga con los lotes
     que el sistema cree tener: lo habitual es confirmarlos o corregir la fecha,
     no teclearlos de cero. */
  lotes.value = linea.lotes_contados.length > 0
    ? linea.lotes_contados.map((l) => ({ ...l }))
    : linea.lotes_sistema.map((l) => ({ ...l }))

  nextTick(() => campoCantidad.value?.focus())
}

function siguientePendiente() {
  const siguiente = pendientes.value[0]

  if (siguiente) {
    seleccionar(siguiente)
    return
  }

  lineaActiva.value = null

  if (sesion.value && contadas.value.length === sesion.value.lineas.length) {
    panelCierre.value = true
  }
}

const sumaLotes = computed(
  () => lotes.value.reduce((n, l) => n + (Number(l.cantidad) || 0), 0),
)

/** El desglose por lotes, si se usa, tiene que cuadrar con el total contado. */
const desgloseCuadra = computed(() => {
  if (lotes.value.every((l) => !l.codigo_lote.trim() || !l.cantidad)) return true
  return sumaLotes.value === (cantidad.value ?? 0)
})

const puedeGuardar = computed(
  () => cantidad.value !== null && cantidad.value >= 0 && desgloseCuadra.value,
)

function agregarLote() {
  lotes.value.push({ codigo_lote: '', fecha_vencimiento: null, cantidad: 0 })
}

function quitarLote(indice: number) {
  lotes.value.splice(indice, 1)
}

/** Cuadra el desglose de un tirón: pone en el último lote lo que falta. */
function completarConDiferencia() {
  if (cantidad.value === null) return

  const falta = cantidad.value - sumaLotes.value
  if (falta === 0) return

  if (lotes.value.length === 0) {
    lotes.value.push({ codigo_lote: '', fecha_vencimiento: null, cantidad: falta })
    return
  }

  const ultimo = lotes.value.at(-1)
  if (ultimo) ultimo.cantidad = Math.max(0, Number(ultimo.cantidad) + falta)
}

async function guardarLinea() {
  if (!lineaActiva.value || !puedeGuardar.value) return

  try {
    await registrar(
      lineaActiva.value,
      cantidad.value!,
      lotes.value.filter((l) => l.codigo_lote.trim() && Number(l.cantidad) > 0),
      observacion.value.trim() || null,
    )

    siguientePendiente()
  } catch (error: unknown) {
    const mensaje = (error as { response?: { data?: { message?: string } } })
      .response?.data?.message
    notifyError('No se pudo anotar', mensaje ?? 'Revisa las cantidades.')
  }
}

/* ---------------------------------------------------------------------------
   Cierre
   --------------------------------------------------------------------------- */

async function confirmarCierre() {
  try {
    const resumen = await cerrar()
    panelCierre.value = false
    lineaActiva.value = null

    const partes = [
      `${resumen.ajustados} con ajuste de cantidad`,
      `${resumen.lotes_capturados} con fecha de vencimiento capturada`,
    ]

    if (resumen.no_contados > 0) partes.push(`${resumen.no_contados} sin contar`)

    notifySuccess('Conteo aplicado al inventario', partes.join(' · '))
  } catch (error: unknown) {
    const mensaje = (error as { response?: { data?: { message?: string } } })
      .response?.data?.message
    notifyError('No se pudo cerrar', mensaje ?? 'Inténtalo de nuevo.')
  }
}

async function confirmarAnular() {
  try {
    await anular()
    panelCierre.value = false
    lineaActiva.value = null
    notifySuccess('Conteo descartado', 'El inventario no se modificó.')
  } catch {
    notifyError('No se pudo anular', 'Inténtalo de nuevo.')
  }
}

/* ---------------------------------------------------------------------------
   Presentación
   --------------------------------------------------------------------------- */

/**
 * El color del resultado usa los mismos tonos que el resto del inventario:
 * que falte producto se lee igual que un stock crítico, y que sobre se lee
 * como un aviso, no como un error.
 */
function tonoDiferencia(diferencia: number | null) {
  if (diferencia === null) return 'neutro' as const
  if (diferencia === 0) return 'normal' as const
  return diferencia < 0 ? ('critico' as const) : ('bajo' as const)
}

function textoDiferencia(diferencia: number | null): string {
  if (diferencia === null) return 'Sin contar'
  if (diferencia === 0) return 'Cuadra'
  return diferencia > 0 ? `Sobran ${diferencia}` : `Faltan ${Math.abs(diferencia)}`
}
</script>

<template>
  <div data-admin class="space-y-5">
    <!-- ==================== Sin sesión abierta ======================== -->
    <section
      v-if="!haySesion && !cargando"
      class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-6"
    >
      <div class="flex items-start gap-3">
        <span
          class="grid size-11 shrink-0 place-items-center rounded-xl bg-botica-50 text-botica-700 dark:bg-botica-500/15 dark:text-botica-500"
          aria-hidden="true"
        >
          <ClipboardList class="size-5" />
        </span>

        <div>
          <h1 class="font-display text-xl font-semibold text-texto-primario">
            Conteo por ciclos
          </h1>
          <p class="mt-1 max-w-2xl text-sm text-texto-secundario">
            Contar un puñado de productos cada día, en lugar de parar la botica una vez al año.
            Lo que se cuenta no se toca hasta cerrar la sesión, así que puedes seguir vendiendo
            mientras tanto.
          </p>
        </div>
      </div>

      <fieldset class="mt-6">
        <legend class="text-xs font-medium uppercase tracking-wide text-texto-terciario">
          ¿Qué conviene contar hoy?
        </legend>

        <div class="mt-3 grid gap-3 md:grid-cols-3">
          <label
            v-for="criterio in CRITERIOS"
            :key="criterio.valor"
            class="cursor-pointer rounded-xl border p-4 transition-colors"
            :class="criterioElegido === criterio.valor
              ? 'border-botica-700 bg-botica-50 dark:bg-botica-500/10'
              : 'border-borde-base bg-superficie-base hover:bg-superficie-interactiva'"
          >
            <input
              v-model="criterioElegido"
              type="radio"
              :value="criterio.valor"
              class="sr-only"
            >
            <span class="block text-sm font-semibold text-texto-primario">
              {{ criterio.etiqueta }}
            </span>
            <span class="mt-1 block text-xs leading-relaxed text-texto-secundario">
              {{ criterio.explicacion }}
            </span>
          </label>
        </div>
      </fieldset>

      <div class="mt-5 flex flex-wrap items-end gap-4">
        <div>
          <label for="cantidad-conteo" class="text-xs font-medium uppercase tracking-wide text-texto-terciario">
            Cuántos productos
          </label>
          <input
            id="cantidad-conteo"
            v-model.number="cantidadElegida"
            type="number"
            min="1"
            max="200"
            class="mt-1.5 h-11 w-28 rounded-lg border border-borde-base bg-superficie-base px-3 text-right tabular-nums text-texto-primario"
          >
        </div>

        <BoticaButton
          variante="primario"
          :cargando="cargando"
          :icono="ClipboardList"
          @click="abrirSesion"
        >
          Abrir conteo
        </BoticaButton>

        <p class="text-xs text-texto-terciario">
          Entre 20 y 30 al día bastan para revisar todo el catálogo varias veces al año.
        </p>
      </div>
    </section>

    <!-- ====================== Sesión en curso ========================= -->
    <template v-if="haySesion && sesion">
      <!-- Cabecera y progreso -->
      <section class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h1 class="font-display text-lg font-semibold text-texto-primario">
              Conteo {{ sesion.codigo }}
            </h1>
            <p class="text-sm text-texto-secundario">
              {{ contadas.length }} de {{ sesion.lineas.length }} productos contados
            </p>
          </div>

          <div class="flex gap-2">
            <BoticaButton @click="confirmarAnular">
              Descartar
            </BoticaButton>
            <BoticaButton
              variante="primario"
              :disabled="contadas.length === 0"
              @click="panelCierre = true"
            >
              Cerrar y aplicar
            </BoticaButton>
          </div>
        </div>

        <div
          class="mt-4 h-2 overflow-hidden rounded-full bg-superficie-hundida"
          role="progressbar"
          :aria-valuenow="progreso"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-label="`Progreso del conteo: ${progreso} por ciento`"
        >
          <div
            class="h-full rounded-full bg-botica-700 transition-[width] duration-300"
            :style="{ width: `${progreso}%` }"
          />
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
          <BoticaStat etiqueta="Por contar" :valor="String(pendientes.length)" />
          <BoticaStat etiqueta="Con diferencia" :valor="String(conDiferencia.length)" />
          <BoticaStat etiqueta="Fechas capturadas" :valor="String(conFechaCapturada)" />
        </div>
      </section>

      <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_380px]">
        <!-- Lista de productos -->
        <section class="rounded-2xl border border-borde-sutil bg-superficie-elevada">
          <h2 class="border-b border-borde-sutil px-5 py-3 text-sm font-semibold text-texto-primario">
            Productos de esta sesión
          </h2>

          <ul class="divide-y divide-borde-sutil">
            <li v-for="linea in sesion.lineas" :key="linea.id">
              <button
                type="button"
                class="flex w-full items-center gap-3 px-5 py-3 text-left transition-colors hover:bg-superficie-interactiva"
                :class="lineaActiva?.id === linea.id ? 'bg-botica-50 dark:bg-botica-500/10' : ''"
                @click="seleccionar(linea)"
              >
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-sm font-medium text-texto-primario">
                    {{ linea.nombre }}
                    <span v-if="linea.concentracion" class="font-normal text-texto-secundario">
                      {{ linea.concentracion }}
                    </span>
                  </span>
                  <span class="block truncate text-xs text-texto-terciario">
                    Sistema: {{ linea.stock_sistema }} u.
                    <template v-if="linea.cantidad_contada !== null">
                      · Contado: {{ linea.cantidad_contada }} u.
                    </template>
                  </span>
                </span>

                <BoticaBadge :tono="tonoDiferencia(linea.diferencia)">
                  {{ textoDiferencia(linea.diferencia) }}
                </BoticaBadge>
              </button>
            </li>
          </ul>
        </section>

        <!-- Panel de conteo -->
        <section class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-5 lg:sticky lg:top-4 lg:self-start">
          <BoticaEstadoVacio
            v-if="!lineaActiva"
            :icono="CircleCheck"
            titulo="Todo contado"
            descripcion="Cierra la sesión para aplicar los ajustes al inventario."
          />

          <template v-else>
            <h2 class="font-display text-base font-semibold text-texto-primario">
              {{ lineaActiva.nombre }}
            </h2>
            <p class="text-sm text-texto-secundario">
              {{ lineaActiva.concentracion }} · {{ lineaActiva.presentacion }}
            </p>

            <p class="mt-3 rounded-lg bg-superficie-hundida px-3 py-2 text-sm text-texto-secundario">
              El sistema cree tener
              <strong class="text-texto-primario">{{ lineaActiva.stock_sistema }} unidades</strong>
            </p>

            <!-- Cantidad contada -->
            <div class="mt-4">
              <label for="conteo-cantidad" class="text-xs font-medium uppercase tracking-wide text-texto-terciario">
                ¿Cuántas hay en el anaquel?
              </label>
              <input
                id="conteo-cantidad"
                ref="campoCantidad"
                v-model.number="cantidad"
                type="number"
                min="0"
                class="mt-1.5 h-12 w-full rounded-lg border border-borde-base bg-superficie-base px-3 text-right font-display text-xl tabular-nums text-texto-primario focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
                @keydown.enter.prevent="guardarLinea"
              >
            </div>

            <!-- Lotes -->
            <div class="mt-4">
              <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wide text-texto-terciario">
                  Lotes y vencimiento
                </span>
                <button
                  type="button"
                  class="inline-flex items-center gap-1 text-xs font-medium text-botica-700 hover:underline dark:text-botica-500"
                  @click="agregarLote"
                >
                  <Plus class="size-3.5" aria-hidden="true" />
                  Añadir
                </button>
              </div>

              <p class="mt-1 text-xs text-texto-terciario">
                Anota la fecha del envase. Es lo que permite avisar antes de que venza.
              </p>

              <div v-for="(lote, indice) in lotes" :key="indice" class="mt-2 flex gap-2">
                <input
                  v-model="lote.codigo_lote"
                  type="text"
                  placeholder="Lote"
                  class="h-10 min-w-0 flex-1 rounded-lg border border-borde-base bg-superficie-base px-2 text-sm text-texto-primario"
                  :aria-label="`Código del lote ${indice + 1}`"
                >
                <input
                  v-model="lote.fecha_vencimiento"
                  type="date"
                  class="h-10 w-36 rounded-lg border border-borde-base bg-superficie-base px-2 text-sm text-texto-primario"
                  :aria-label="`Fecha de vencimiento del lote ${indice + 1}`"
                >
                <input
                  v-model.number="lote.cantidad"
                  type="number"
                  min="0"
                  class="h-10 w-16 rounded-lg border border-borde-base bg-superficie-base px-2 text-right tabular-nums text-sm text-texto-primario"
                  :aria-label="`Cantidad del lote ${indice + 1}`"
                >
                <button
                  type="button"
                  class="grid size-10 shrink-0 place-items-center rounded-lg text-texto-terciario transition-colors hover:bg-superficie-interactiva hover:text-peligro-600"
                  :aria-label="`Quitar lote ${indice + 1}`"
                  @click="quitarLote(indice)"
                >
                  <Trash2 class="size-4" aria-hidden="true" />
                </button>
              </div>

              <p
                v-if="!desgloseCuadra"
                class="mt-2 flex items-center gap-2 rounded-lg bg-alerta-50 px-3 py-2 text-xs text-alerta-700 dark:bg-alerta-500/10 dark:text-alerta-500"
              >
                <TriangleAlert class="size-4 shrink-0" aria-hidden="true" />
                <span>
                  Los lotes suman {{ sumaLotes }} y contaste {{ cantidad }}.
                  <button type="button" class="font-semibold underline" @click="completarConDiferencia">
                    Cuadrar
                  </button>
                </span>
              </p>
            </div>

            <!-- Observación -->
            <div class="mt-4">
              <label for="conteo-obs" class="text-xs font-medium uppercase tracking-wide text-texto-terciario">
                Observación <span class="normal-case">(opcional)</span>
              </label>
              <input
                id="conteo-obs"
                v-model="observacion"
                type="text"
                maxlength="300"
                placeholder="Envase dañado, producto mal ubicado…"
                class="mt-1.5 h-10 w-full rounded-lg border border-borde-base bg-superficie-base px-3 text-sm text-texto-primario"
              >
            </div>

            <BoticaButton
              variante="primario"
              class="mt-5 w-full"
              :cargando="guardando"
              :disabled="!puedeGuardar"
              :icono="Check"
              @click="guardarLinea"
            >
              Anotar y siguiente
            </BoticaButton>

            <p class="mt-2 text-center text-xs text-texto-terciario">
              Enter también anota y pasa al siguiente
            </p>
          </template>
        </section>
      </div>
    </template>

    <!-- ===================== Confirmar cierre ========================= -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-150"
        leave-to-class="opacity-0"
      >
        <div
          v-if="panelCierre && sesion"
          class="fixed inset-0 z-50 flex items-center justify-center bg-neutro-950/60 p-4 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          aria-labelledby="titulo-cierre"
          @click.self="panelCierre = false"
        >
          <div class="w-full max-w-md rounded-2xl border border-borde-sutil bg-superficie-flotante p-5 shadow-2xl">
            <h2 id="titulo-cierre" class="font-display text-lg font-semibold text-texto-primario">
              Cerrar el conteo
            </h2>

            <p class="mt-1 text-sm text-texto-secundario">
              Se aplicarán los ajustes al inventario. Cada cambio queda registrado con el
              código {{ sesion.codigo }}, así que después se puede rastrear de dónde salió.
            </p>

            <dl class="mt-4 space-y-2 text-sm">
              <div class="flex justify-between">
                <dt class="text-texto-secundario">Productos contados</dt>
                <dd class="font-medium tabular-nums text-texto-primario">{{ contadas.length }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-texto-secundario">Con diferencia de cantidad</dt>
                <dd class="font-medium tabular-nums text-texto-primario">{{ conDiferencia.length }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-texto-secundario">Con fecha de vencimiento capturada</dt>
                <dd class="font-medium tabular-nums text-texto-primario">{{ conFechaCapturada }}</dd>
              </div>
              <div v-if="pendientes.length" class="flex justify-between">
                <dt class="text-alerta-700 dark:text-alerta-500">Quedan sin contar</dt>
                <dd class="font-medium tabular-nums text-alerta-700 dark:text-alerta-500">
                  {{ pendientes.length }}
                </dd>
              </div>
            </dl>

            <p v-if="pendientes.length" class="mt-3 text-xs text-texto-terciario">
              Los productos sin contar no se tocan: su stock queda como está.
            </p>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <BoticaButton @click="panelCierre = false">
                Seguir contando
              </BoticaButton>
              <BoticaButton
                variante="primario"
                :cargando="cerrando"
                :icono="ArrowRight"
                @click="confirmarCierre"
              >
                Aplicar al inventario
              </BoticaButton>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
