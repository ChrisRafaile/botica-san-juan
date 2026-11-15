<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import {
  AlertCircle,
  Download,
  Eye,
  FileText,
  LoaderCircle,
  Search,
  X,
} from 'lucide-vue-next'
import {
  descargarPdfComprobante,
  listarMisComprobantes,
  obtenerDetalleComprobante,
  type ComprobanteCliente,
  type DetalleImpresionComprobante,
} from '@/services/comprobantes'
import { formatearFecha, formatearMoneda } from '@/services/pedidosCliente'

const comprobantes = ref<ComprobanteCliente[]>([])
const cargando = ref(true)
const error = ref('')
const errorPdf = ref('')
const descargandoId = ref<number | null>(null)

const busqueda = ref('')
const filtroTipo = ref<'todos' | 'boleta' | 'factura'>('todos')

const vistaPreviaAbierta = ref(false)
const cargandoVistaPrevia = ref(false)
const errorVistaPrevia = ref('')
const detalleActivo = ref<DetalleImpresionComprobante | null>(null)
const comprobanteActivo = ref<ComprobanteCliente | null>(null)

const comprobantesFiltrados = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  return comprobantes.value.filter((c) => {
    if (filtroTipo.value !== 'todos' && String(c.tipo).toLowerCase() !== filtroTipo.value) {
      return false
    }
    if (!q) return true
    const texto = [
      c.correlativo,
      c.tipo,
      c.estado_sunat ?? '',
      c.pedido?.id ? `pedido #${c.pedido.id}` : '',
      formatearFecha(c.fecha_emision),
    ]
      .join(' ')
      .toLowerCase()
    return texto.includes(q)
  })
})

function etiquetaTipo(tipo: string): string {
  return String(tipo).toLowerCase() === 'factura' ? 'Factura' : 'Boleta'
}

function etiquetaSunat(estado?: string | null): string {
  if (!estado) return 'Emitido'
  const normal = estado.toLowerCase()
  if (normal === 'aceptado') return 'Aceptado SUNAT'
  if (normal === 'rechazado') return 'Rechazado'
  if (normal === 'pendiente') return 'Por enviar'
  return estado
}

async function descargarPdf(c: ComprobanteCliente): Promise<void> {
  errorPdf.value = ''
  descargandoId.value = c.id
  try {
    await descargarPdfComprobante(c.id, c.correlativo)
  } catch {
    errorPdf.value = `No pudimos descargar el comprobante ${c.correlativo}. Inténtalo nuevamente en un momento.`
  } finally {
    descargandoId.value = null
  }
}

async function abrirVistaPrevia(c: ComprobanteCliente): Promise<void> {
  comprobanteActivo.value = c
  detalleActivo.value = null
  errorVistaPrevia.value = ''
  vistaPreviaAbierta.value = true
  cargandoVistaPrevia.value = true

  try {
    detalleActivo.value = await obtenerDetalleComprobante(c.id)
  } catch {
    errorVistaPrevia.value = `No pudimos cargar el detalle de ${c.correlativo}.`
  } finally {
    cargandoVistaPrevia.value = false
  }
}

function cerrarVistaPrevia(): void {
  vistaPreviaAbierta.value = false
}

function alPulsarEscape(evento: KeyboardEvent): void {
  if (evento.key === 'Escape' && vistaPreviaAbierta.value) {
    cerrarVistaPrevia()
  }
}

onMounted(async () => {
  document.addEventListener('keydown', alPulsarEscape)
  try {
    comprobantes.value = await listarMisComprobantes()
  } catch {
    error.value = 'No pudimos cargar tus comprobantes electrónicos.'
  } finally {
    cargando.value = false
  }
})

onUnmounted(() => {
  document.removeEventListener('keydown', alPulsarEscape)
})
</script>

<template>
  <section class="space-y-6" aria-labelledby="titulo-mis-comprobantes">
    <header>
      <h1 id="titulo-mis-comprobantes" class="text-2xl font-bold tracking-tight text-texto-primario sm:text-3xl">
        Mis comprobantes
      </h1>
      <p class="mt-1 text-sm text-texto-secundario">
        Busca tus boletas y facturas electrónicas, revisa el detalle o descárgalas en PDF.
      </p>
    </header>

    <!-- Buscador y filtro por tipo -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="relative flex-1 sm:max-w-sm">
        <Search
          class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-texto-terciario"
          aria-hidden="true"
        />
        <input
          v-model="busqueda"
          type="search"
          aria-label="Buscar por serie, correlativo o número de pedido"
          placeholder="Buscar por serie, correlativo o pedido…"
          class="w-full rounded-xl border border-borde-control bg-superficie-elevada py-2.5 pl-10 pr-4 text-sm text-texto-primario placeholder:text-texto-terciario focus:border-botica-500 focus:outline-none focus:ring-2 focus:ring-botica-500/20"
        />
      </div>

      <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por tipo de comprobante">
        <button
          v-for="op in [
            { valor: 'todos', etiqueta: 'Todos' },
            { valor: 'boleta', etiqueta: 'Boletas' },
            { valor: 'factura', etiqueta: 'Facturas' },
          ]"
          :key="op.valor"
          type="button"
          :aria-pressed="filtroTipo === op.valor"
          :class="[
            'foco-dentro rounded-full border px-4 py-1.5 text-sm font-medium transition-colors',
            filtroTipo === op.valor
              ? 'border-botica-600 bg-botica-600 text-white'
              : 'border-borde-control bg-superficie-elevada text-texto-secundario hover:bg-superficie-interactiva hover:text-texto-primario',
          ]"
          @click="filtroTipo = op.valor as 'todos' | 'boleta' | 'factura'"
        >
          {{ op.etiqueta }}
        </button>
      </div>
    </div>

    <p
      v-if="errorPdf"
      role="alert"
      class="flex items-start gap-2 rounded-2xl border border-peligro-500/30 bg-peligro-50 p-4 text-sm text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-300"
    >
      <AlertCircle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
      {{ errorPdf }}
    </p>

    <p
      v-if="error"
      role="alert"
      class="flex items-start gap-2 rounded-2xl border border-peligro-500/30 bg-peligro-50 p-4 text-sm text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-300"
    >
      <AlertCircle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
      {{ error }}
    </p>

    <div v-else-if="cargando" class="space-y-3">
      <div
        v-for="n in 4"
        :key="n"
        class="h-20 animate-pulse rounded-2xl border border-borde-sutil bg-superficie-elevada"
      />
    </div>

    <div
      v-else-if="comprobantesFiltrados.length === 0"
      class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-borde-sutil bg-superficie-elevada px-6 py-14 text-center"
    >
      <FileText class="size-10 text-texto-terciario" aria-hidden="true" />
      <p class="text-base font-semibold text-texto-primario">
        {{ comprobantes.length === 0 ? 'Aún no tienes comprobantes emitidos' : 'Sin coincidencias para tu búsqueda' }}
      </p>
      <p class="text-sm text-texto-secundario">
        {{
          comprobantes.length === 0
            ? 'Cuando finalices una compra podrás consultar y descargar tus boletas o facturas aquí.'
            : 'Prueba otro correlativo o quita el filtro actual.'
        }}
      </p>
    </div>

    <div
      v-else
      class="overflow-hidden rounded-2xl border border-borde-sutil bg-superficie-elevada"
    >
      <ul class="divide-y divide-borde-sutil">
        <li
          v-for="c in comprobantesFiltrados"
          :key="c.id"
          class="flex flex-wrap items-center justify-between gap-4 px-5 py-4"
        >
          <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded-full bg-clinico-50 px-2.5 py-0.5 text-xs font-semibold text-clinico-700 dark:bg-clinico-500/15 dark:text-clinico-300">
                {{ etiquetaTipo(c.tipo) }}
              </span>
              <p class="cifras-tabulares text-sm font-bold text-texto-primario">
                {{ c.correlativo }}
              </p>
              <span class="rounded-full bg-botica-50 px-2 py-0.5 text-2xs font-medium text-texto-marca dark:bg-botica-500/15 dark:text-botica-300">
                {{ etiquetaSunat(c.estado_sunat) }}
              </span>
            </div>
            <p class="cifras-tabulares text-xs text-texto-secundario">
              {{ formatearFecha(c.fecha_emision) }}
              <template v-if="c.pedido"> · Pedido #{{ c.pedido.id }}</template>
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2.5">
            <span class="cifras-tabulares mr-2 text-base font-bold text-texto-primario">
              {{ formatearMoneda(c.total) }}
            </span>

            <button
              type="button"
              class="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-borde-control px-3.5 py-2 text-xs font-semibold text-texto-primario transition hover:bg-superficie-interactiva focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
              @click="abrirVistaPrevia(c)"
            >
              <Eye class="size-3.5" aria-hidden="true" />
              <span>Vista previa</span>
              <span class="sr-only">de {{ c.correlativo }}</span>
            </button>

            <button
              type="button"
              :disabled="descargandoId === c.id"
              class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-botica-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-botica-700 disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
              @click="descargarPdf(c)"
            >
              <LoaderCircle v-if="descargandoId === c.id" class="size-3.5 animate-spin" aria-hidden="true" />
              <Download v-else class="size-3.5" aria-hidden="true" />
              <span>{{ descargandoId === c.id ? 'Descargando…' : 'PDF' }}</span>
              <span class="sr-only">Descargar {{ c.correlativo }}</span>
            </button>
          </div>
        </li>
      </ul>
    </div>

    <!-- Modal de vista previa del comprobante -->
    <div
      v-if="vistaPreviaAbierta"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
      @click.self="cerrarVistaPrevia"
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="titulo-preview-comprobante"
        class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-borde-sutil bg-superficie-elevada shadow-xl"
      >
        <header class="flex items-center justify-between border-b border-borde-sutil px-6 py-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-texto-terciario">
              {{ comprobanteActivo ? etiquetaTipo(comprobanteActivo.tipo) : 'Comprobante electrónico' }}
            </p>
            <h2 id="titulo-preview-comprobante" class="cifras-tabulares text-lg font-bold text-texto-primario">
              {{ detalleActivo?.comprobante.identificador ?? comprobanteActivo?.correlativo }}
            </h2>
          </div>

          <button
            type="button"
            class="foco-dentro rounded-lg p-2 text-texto-secundario hover:bg-superficie-interactiva hover:text-texto-primario"
            aria-label="Cerrar vista previa"
            @click="cerrarVistaPrevia"
          >
            <X class="size-5" aria-hidden="true" />
          </button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto p-6">
          <div v-if="cargandoVistaPrevia" class="space-y-3">
            <div class="h-16 animate-pulse rounded-xl bg-superficie-hundida" />
            <div class="h-32 animate-pulse rounded-xl bg-superficie-hundida" />
          </div>

          <p
            v-else-if="errorVistaPrevia"
            role="alert"
            class="flex items-start gap-2 rounded-xl bg-peligro-50 p-4 text-sm text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-300"
          >
            <AlertCircle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {{ errorVistaPrevia }}
          </p>

          <div v-else-if="detalleActivo" class="space-y-5">
            <div class="grid grid-cols-1 gap-3 rounded-xl border border-borde-sutil bg-superficie-hundida p-4 text-sm sm:grid-cols-2">
              <div>
                <p class="text-xs text-texto-terciario">Adquirente</p>
                <p class="font-semibold text-texto-primario">{{ detalleActivo.cliente.nombre }}</p>
                <p class="cifras-tabulares text-xs text-texto-secundario">
                  Doc. {{ detalleActivo.cliente.documento }}
                </p>
              </div>
              <div class="sm:text-right">
                <p class="text-xs text-texto-terciario">Emisión</p>
                <p class="font-semibold text-texto-primario">
                  {{ formatearFecha(detalleActivo.comprobante.fecha_emision) }}
                </p>
                <p class="text-xs text-texto-marca">
                  {{ etiquetaSunat(detalleActivo.comprobante.estado_sunat) }}
                </p>
              </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-borde-sutil">
              <table class="w-full text-left text-sm">
                <thead class="bg-superficie-hundida text-xs uppercase text-texto-terciario">
                  <tr>
                    <th class="px-4 py-2.5">Descripción</th>
                    <th class="px-3 py-2.5 text-right">Cant.</th>
                    <th class="px-3 py-2.5 text-right">P. Unit.</th>
                    <th class="px-4 py-2.5 text-right">Importe</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-borde-sutil">
                  <tr v-for="(item, idx) in detalleActivo.items" :key="idx">
                    <td class="px-4 py-2.5 text-texto-primario">{{ item.descripcion }}</td>
                    <td class="cifras-tabulares px-3 py-2.5 text-right text-texto-secundario">
                      {{ item.cantidad }}
                    </td>
                    <td class="cifras-tabulares px-3 py-2.5 text-right text-texto-secundario">
                      {{ formatearMoneda(item.precio_unitario) }}
                    </td>
                    <td class="cifras-tabulares px-4 py-2.5 text-right font-semibold text-texto-primario">
                      {{ formatearMoneda(item.subtotal) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <dl class="space-y-1.5 rounded-xl border border-borde-sutil bg-superficie-hundida p-4 text-sm">
              <div class="flex justify-between text-texto-secundario">
                <dt>Operaciones gravadas</dt>
                <dd class="cifras-tabulares">{{ formatearMoneda(detalleActivo.totales.subtotal_gravado) }}</dd>
              </div>
              <div v-if="detalleActivo.totales.subtotal_exonerado > 0" class="flex justify-between text-texto-secundario">
                <dt>Exonerado</dt>
                <dd class="cifras-tabulares">{{ formatearMoneda(detalleActivo.totales.subtotal_exonerado) }}</dd>
              </div>
              <div class="flex justify-between text-texto-secundario">
                <dt>IGV ({{ Math.round((detalleActivo.totales.tasa_igv || 0.18) * 100) }}%)</dt>
                <dd class="cifras-tabulares">{{ formatearMoneda(detalleActivo.totales.igv) }}</dd>
              </div>
              <div class="flex justify-between border-t border-borde-sutil pt-2 text-base font-bold text-texto-primario">
                <dt>Total</dt>
                <dd class="cifras-tabulares">{{ formatearMoneda(detalleActivo.totales.total) }}</dd>
              </div>
            </dl>

            <p class="text-center text-xs text-texto-terciario">
              {{ detalleActivo.leyenda }}
            </p>
          </div>
        </div>

        <footer class="flex items-center justify-end gap-3 border-t border-borde-sutil px-6 py-3">
          <button
            type="button"
            class="rounded-full border border-borde-control px-4 py-2 text-sm font-semibold text-texto-primario hover:bg-superficie-interactiva"
            @click="cerrarVistaPrevia"
          >
            Cerrar
          </button>
          <button
            v-if="comprobanteActivo"
            type="button"
            :disabled="descargandoId === comprobanteActivo.id"
            class="inline-flex items-center gap-1.5 rounded-full bg-botica-600 px-4 py-2 text-sm font-semibold text-white hover:bg-botica-700 disabled:opacity-60"
            @click="descargarPdf(comprobanteActivo)"
          >
            <Download class="size-4" aria-hidden="true" />
            Descargar PDF
          </button>
        </footer>
      </div>
    </div>
  </section>
</template>
