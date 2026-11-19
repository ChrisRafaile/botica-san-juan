<script setup lang="ts">
/**
 * Tablero · Botica San Juan
 * ---------------------------------------------------------------------------
 * QUÉ PREGUNTAS RESPONDE
 * Un tablero que cuenta productos, pedidos y clientes sirve para cualquier
 * tienda y no ayuda a ninguna. Quien abre esta pantalla por la mañana es el
 * dueño de una botica, y lo que necesita saber es concreto:
 *
 *   ¿cómo va el día comparado con ayer?
 *   ¿qué se me está por vencer?
 *   ¿qué tengo que reponer?
 *
 * La segunda es la que el sistema anterior nunca pudo responder, y es la que
 * evita tirar medicamento caducado.
 *
 * JERARQUÍA
 * La venta del día ocupa el lugar principal porque es la cifra que se mira
 * primero. El resto acompaña. La versión anterior ponía cuatro tarjetas de
 * color saturado del mismo tamaño —tres verdes idénticas y una ámbar sin
 * motivo—, de modo que ninguna destacaba y el color no significaba nada.
 *
 * ESTADOS VACÍOS QUE ENSEÑAN
 * Cuando no hay datos de vencimiento, la tarjeta no dice "sin datos": dice qué
 * hacer para tenerlos y enlaza al conteo. Un panel vacío que no explica nada
 * es espacio desperdiciado en la pantalla más vista del sistema.
 */

import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  TrendingUp, TrendingDown, CalendarClock, PackageSearch, Receipt,
  ArrowRight, ClipboardList, Minus, Eye, EyeOff, BarChart3, Wallet,
} from 'lucide-vue-next'
import api from '@/services/api'
import { useAdminToast } from './composables/useAdminToast'
import BoticaBadge from './components/ui/BoticaBadge.vue'
import BoticaEstadoVacio from './components/ui/BoticaEstadoVacio.vue'

interface Tablero {
  ventas: {
    hoy: { importe: number; comprobantes: number }
    ayer: { importe: number; comprobantes: number }
    diferencia: number
  }
  vencimientos: {
    vencidos: { lotes: number; unidades: number }
    en_30_dias: { lotes: number; unidades: number }
    en_90_dias: { lotes: number; unidades: number }
    lotes_con_fecha: number
    lotes_totales: number
  }
  reposicion: { agotados: number; criticos: number; bajos: number }
  catalogo: { productos: number; lotes: number }
  ultimas: Array<{
    id: number
    fecha: string | null
    total: number
    medio_pago: string
    origen: string
    cliente: string | null
  }>
  mas_vendidos: Array<{
    id: number
    nombre: string
    concentracion: string | null
    unidades: number
    importe: number
  }>
  comparativa_semanal?: {
    dias: Array<{
      etiqueta: string
      fecha: string
      actual: number
      anterior: number
    }>
    total_actual: number
    total_anterior: number
    variacion_absoluta: number
  }
  metodos_pago?: Array<{
    medio: string
    comprobantes: number
    importe: number
    porcentaje: number
  }>
}

const CLAVE_OCULTAR_MONTOS = 'botica_ocultar_montos'

function leerPreferenciaOcultar(): boolean {
  try {
    return localStorage.getItem(CLAVE_OCULTAR_MONTOS) === '1'
  } catch {
    return false
  }
}

const ocultarMontos = ref(leerPreferenciaOcultar())

function alternarOcultarMontos() {
  ocultarMontos.value = !ocultarMontos.value
  try {
    localStorage.setItem(CLAVE_OCULTAR_MONTOS, ocultarMontos.value ? '1' : '0')
  } catch {
    /* Sin persistencia; se mantiene en memoria durante la sesión. */
  }
}

const { notifyError } = useAdminToast()

const datos = ref<Tablero | null>(null)
const cargando = ref(true)

async function cargar() {
  cargando.value = true

  try {
    const { data } = await api.get<Tablero>('/tablero')
    datos.value = data
  } catch {
    notifyError('No se pudo cargar el tablero', 'Revisa la conexión con el servidor.')
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

const soles = (n: number) =>
  ocultarMontos.value
    ? 'S/ ••••'
    : `S/ ${n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const maxComparativa = computed(() => {
  const dias = datos.value?.comparativa_semanal?.dias ?? []
  const max = Math.max(...dias.flatMap((d) => [d.actual, d.anterior]), 0)
  return max > 0 ? max : 1
})

function altoBarra(valor: number): string {
  if (valor <= 0) return '4%'
  return `${Math.max(8, Math.round((valor / maxComparativa.value) * 100))}%`
}

const hora = (iso: string | null) =>
  iso
    ? new Date(iso).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })
    : '—'

/**
 * Cuánta información de vencimiento hay realmente.
 *
 * Con el catálogo recién migrado casi ningún lote tiene fecha, así que las
 * cifras de "por vencer" serían cero y darían una falsa tranquilidad. Mejor
 * decir que el dato falta.
 */
const cobertura = computed(() => {
  const v = datos.value?.vencimientos
  if (!v || v.lotes_totales === 0) return 0
  return Math.round((v.lotes_con_fecha / v.lotes_totales) * 100)
})

const hayDatosDeVencimiento = computed(() => cobertura.value >= 5)

/**
 * Si casi todo el catálogo aparece en alerta, el umbral está mal puesto, no el
 * inventario. Avisarlo es más útil que mostrar una cifra alarmante que nadie
 * puede accionar.
 */
const umbralesSospechosos = computed(() => {
  const r = datos.value?.reposicion
  const total = datos.value?.catalogo.productos ?? 0
  if (!r || total === 0) return false
  return (r.criticos + r.bajos) / total > 0.6
})

const tendencia = computed(() => {
  const d = datos.value?.ventas.diferencia ?? 0
  if (Math.abs(d) < 0.01) return { icono: Minus, texto: 'igual que ayer', tono: 'text-texto-terciario' }
  if (d > 0) return { icono: TrendingUp, texto: `${soles(d)} más que ayer`, tono: 'text-exito-700 dark:text-exito-500' }
  return { icono: TrendingDown, texto: `${soles(Math.abs(d))} menos que ayer`, tono: 'text-texto-secundario' }
})

const etiquetaMedio: Record<string, string> = {
  efectivo: 'Efectivo', tarjeta: 'Tarjeta', yape: 'Yape',
  plin: 'Plin', transferencia: 'Transferencia', mixto: 'Mixto',
}
</script>

<template>
  <div data-admin class="space-y-5">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="font-display text-2xl font-semibold text-texto-primario">
          Tablero
        </h1>
        <p class="mt-1 text-sm text-texto-secundario">
          Cómo va el día y qué necesita atención.
        </p>
      </div>
      <button
        type="button"
        class="inline-flex shrink-0 items-center gap-2 self-start rounded-xl border border-borde-base bg-superficie-elevada px-3.5 py-2 text-xs font-medium text-texto-primario transition-colors hover:bg-superficie-interactiva focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco sm:self-auto"
        :aria-pressed="ocultarMontos"
        @click="alternarOcultarMontos"
      >
        <EyeOff v-if="ocultarMontos" class="size-4 text-texto-terciario" aria-hidden="true" />
        <Eye v-else class="size-4 text-texto-terciario" aria-hidden="true" />
        {{ ocultarMontos ? 'Mostrar montos' : 'Ocultar montos' }}
      </button>
    </header>

    <!-- Esqueleto mientras carga: mantiene la forma de la pantalla para que
         no salte el contenido al aparecer. -->
    <div v-if="cargando" class="grid gap-4 lg:grid-cols-3">
      <div
        v-for="n in 3"
        :key="n"
        class="h-36 animate-pulse rounded-2xl bg-superficie-hundida"
      />
    </div>

    <template v-else-if="datos">
      <!-- ================= Cifras del día ============================== -->
      <section class="grid gap-4 lg:grid-cols-3">
        <!-- La venta del día ocupa el doble: es lo que se mira primero. -->
        <article class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-6 lg:col-span-2">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-medium uppercase tracking-wide text-texto-terciario">
                Vendido hoy
              </p>
              <p class="mt-2 font-display text-4xl font-semibold tabular-nums text-texto-primario">
                {{ soles(datos.ventas.hoy.importe) }}
              </p>
              <p class="mt-1.5 flex items-center gap-1.5 text-sm" :class="tendencia.tono">
                <component :is="tendencia.icono" class="size-4 shrink-0" aria-hidden="true" />
                {{ tendencia.texto }}
              </p>
            </div>

            <RouterLink
              to="/admin/pos"
              class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-botica-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-botica-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
            >
              <Receipt class="size-4" aria-hidden="true" />
              Vender
            </RouterLink>
          </div>

          <dl class="mt-5 flex gap-8 border-t border-borde-sutil pt-4 text-sm">
            <div>
              <dt class="text-texto-terciario">Comprobantes hoy</dt>
              <dd class="mt-0.5 font-display text-lg font-semibold tabular-nums text-texto-primario">
                {{ datos.ventas.hoy.comprobantes }}
              </dd>
            </div>
            <div>
              <dt class="text-texto-terciario">Ayer</dt>
              <dd class="mt-0.5 font-display text-lg font-semibold tabular-nums text-texto-secundario">
                {{ soles(datos.ventas.ayer.importe) }}
              </dd>
            </div>
            <div>
              <dt class="text-texto-terciario">Productos</dt>
              <dd class="mt-0.5 font-display text-lg font-semibold tabular-nums text-texto-secundario">
                {{ datos.catalogo.productos.toLocaleString('es-PE') }}
              </dd>
            </div>
          </dl>
        </article>

        <!-- Vencimientos: la pregunta que el sistema anterior no respondía -->
        <article class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-6">
          <div class="flex items-center gap-2">
            <CalendarClock class="size-4 text-texto-terciario" aria-hidden="true" />
            <h2 class="text-xs font-medium uppercase tracking-wide text-texto-terciario">
              Por vencer
            </h2>
          </div>

          <template v-if="hayDatosDeVencimiento">
            <dl class="mt-4 space-y-3">
              <div v-if="datos.vencimientos.vencidos.lotes" class="flex items-baseline justify-between">
                <dt class="text-sm text-texto-secundario">Ya vencidos</dt>
                <dd class="flex items-baseline gap-2">
                  <span class="font-display text-xl font-semibold tabular-nums text-peligro-600">
                    {{ datos.vencimientos.vencidos.unidades }}
                  </span>
                  <BoticaBadge tono="critico">unidades</BoticaBadge>
                </dd>
              </div>
              <div class="flex items-baseline justify-between">
                <dt class="text-sm text-texto-secundario">En 30 días</dt>
                <dd class="font-display text-xl font-semibold tabular-nums text-alerta-700 dark:text-alerta-500">
                  {{ datos.vencimientos.en_30_dias.unidades }}
                </dd>
              </div>
              <div class="flex items-baseline justify-between">
                <dt class="text-sm text-texto-secundario">En 90 días</dt>
                <dd class="font-display text-xl font-semibold tabular-nums text-texto-primario">
                  {{ datos.vencimientos.en_90_dias.unidades }}
                </dd>
              </div>
            </dl>

            <p class="mt-4 text-xs text-texto-terciario">
              A 90 días todavía se puede negociar la devolución con el proveedor.
            </p>
          </template>

          <!-- Sin fechas cargadas: en vez de mostrar ceros tranquilizadores,
               se explica por qué y se enlaza a lo que lo resuelve. -->
          <template v-else>
            <p class="mt-4 text-sm leading-relaxed text-texto-secundario">
              Sólo {{ datos.vencimientos.lotes_con_fecha }} de
              {{ datos.vencimientos.lotes_totales.toLocaleString('es-PE') }} lotes
              tienen fecha de vencimiento registrada, así que todavía no se puede
              avisar de caducidades.
            </p>

            <RouterLink
              to="/admin/inventory/conteo"
              class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-texto-marca hover:underline"
            >
              <ClipboardList class="size-4" aria-hidden="true" />
              Capturarlas con el conteo por ciclos
              <ArrowRight class="size-3.5" aria-hidden="true" />
            </RouterLink>
          </template>
        </article>
      </section>

      <!-- ================= Reposición ================================== -->
      <section class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-6">
        <div class="flex items-center gap-2">
          <PackageSearch class="size-4 text-texto-terciario" aria-hidden="true" />
          <h2 class="text-xs font-medium uppercase tracking-wide text-texto-terciario">
            Reposición
          </h2>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
          <RouterLink
            v-for="grupo in [
              { clave: 'agotados', etiqueta: 'Agotados', valor: datos.reposicion.agotados, tono: 'text-peligro-600' },
              { clave: 'criticos', etiqueta: 'Bajo el mínimo', valor: datos.reposicion.criticos, tono: 'text-alerta-700 dark:text-alerta-500' },
              { clave: 'bajos', etiqueta: 'Cerca del mínimo', valor: datos.reposicion.bajos, tono: 'text-texto-primario' },
            ]"
            :key="grupo.clave"
            to="/admin/inventory/alerts"
            class="rounded-xl border border-borde-sutil bg-superficie-base p-4 transition-colors hover:bg-superficie-interactiva focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
          >
            <p class="text-sm text-texto-secundario">{{ grupo.etiqueta }}</p>
            <p class="mt-1 font-display text-2xl font-semibold tabular-nums" :class="grupo.tono">
              {{ grupo.valor.toLocaleString('es-PE') }}
            </p>
          </RouterLink>
        </div>

        <!-- Si casi todo el catálogo está en alerta, el problema es el umbral. -->
        <p
          v-if="umbralesSospechosos"
          class="mt-4 rounded-lg bg-alerta-50 px-3 py-2.5 text-xs leading-relaxed text-alerta-700 dark:bg-alerta-500/10 dark:text-alerta-500"
        >
          Casi todo el catálogo aparece bajo el mínimo. Eso suele significar que
          el umbral por defecto no encaja con este inventario, no que falte
          producto: conviene fijar el mínimo real de cada artículo antes de usar
          esta cifra para comprar.
        </p>
      </section>

      <!-- ================= Detalle ===================================== -->
      <div class="grid gap-5 lg:grid-cols-2">
        <!-- Últimas ventas -->
        <section class="rounded-2xl border border-borde-sutil bg-superficie-elevada">
          <div class="flex items-center justify-between border-b border-borde-sutil px-5 py-3">
            <h2 class="text-sm font-semibold text-texto-primario">Últimas ventas</h2>
            <button
              type="button"
              class="text-xs font-medium text-texto-marca hover:underline"
              @click="cargar"
            >
              Actualizar
            </button>
          </div>

          <BoticaEstadoVacio
            v-if="datos.ultimas.length === 0"
            :icono="Receipt"
            titulo="Todavía no hay ventas"
            descripcion="Las ventas del mostrador aparecerán aquí."
            class="py-10"
          />

          <ul v-else class="divide-y divide-borde-sutil">
            <li
              v-for="venta in datos.ultimas"
              :key="venta.id"
              class="flex items-center justify-between gap-3 px-5 py-3"
            >
              <div class="min-w-0">
                <p class="text-sm font-medium text-texto-primario">
                  #{{ venta.id }}
                  <span class="font-normal text-texto-terciario">· {{ hora(venta.fecha) }}</span>
                </p>
                <p class="truncate text-xs text-texto-terciario">
                  {{ venta.cliente || 'Cliente eventual' }}
                  · {{ etiquetaMedio[venta.medio_pago] ?? venta.medio_pago }}
                </p>
              </div>
              <span class="shrink-0 font-display text-base font-semibold tabular-nums text-texto-primario">
                {{ soles(venta.total) }}
              </span>
            </li>
          </ul>
        </section>

        <!-- Más vendidos -->
        <section class="rounded-2xl border border-borde-sutil bg-superficie-elevada">
          <div class="border-b border-borde-sutil px-5 py-3">
            <h2 class="text-sm font-semibold text-texto-primario">Top 5 productos</h2>
            <p class="text-xs text-texto-terciario">
              Últimos 30 días, en unidades entregadas.
            </p>
          </div>

          <BoticaEstadoVacio
            v-if="datos.mas_vendidos.length === 0"
            :icono="PackageSearch"
            titulo="Sin movimiento aún"
            descripcion="Hace falta registrar ventas para calcular el ranking."
            class="py-10"
          />

          <ol v-else class="divide-y divide-borde-sutil">
            <li
              v-for="(producto, indice) in datos.mas_vendidos"
              :key="producto.id"
              class="flex items-center gap-3 px-5 py-3"
            >
              <span
                class="grid size-6 shrink-0 place-items-center rounded-md bg-superficie-hundida text-xs font-semibold tabular-nums text-texto-secundario"
                aria-hidden="true"
              >
                {{ indice + 1 }}
              </span>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-texto-primario">
                  {{ producto.nombre }}
                  <span v-if="producto.concentracion" class="font-normal text-texto-secundario">
                    {{ producto.concentracion }}
                  </span>
                </p>
                <p class="text-xs text-texto-terciario">
                  {{ soles(producto.importe) }}
                </p>
              </div>
              <span class="shrink-0 text-sm tabular-nums text-texto-secundario">
                {{ producto.unidades }} u.
              </span>
            </li>
          </ol>
        </section>
      </div>

      <!-- ================= Comparativa semanal y métodos de pago ======== -->
      <div class="grid gap-5 lg:grid-cols-2">
        <section
          v-if="datos.comparativa_semanal"
          class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-5"
          aria-label="Comparativa últimos 7 días contra semana anterior"
        >
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <BarChart3 class="size-4 text-texto-terciario" aria-hidden="true" />
              <h2 class="text-sm font-semibold text-texto-primario">
                Últimos 7 días vs. semana anterior
              </h2>
            </div>
            <div class="flex items-center gap-3 text-xs text-texto-secundario">
              <span class="inline-flex items-center gap-1.5">
                <span class="size-2.5 rounded-sm bg-botica-600" aria-hidden="true" />
                Actual ({{ soles(datos.comparativa_semanal.total_actual) }})
              </span>
              <span class="inline-flex items-center gap-1.5">
                <span class="size-2.5 rounded-sm bg-clinico-300 dark:bg-clinico-700" aria-hidden="true" />
                Anterior ({{ soles(datos.comparativa_semanal.total_anterior) }})
              </span>
            </div>
          </div>

          <div class="mt-5 grid h-40 grid-cols-7 items-end gap-2 border-b border-borde-sutil pb-2">
            <div
              v-for="dia in datos.comparativa_semanal.dias"
              :key="dia.fecha"
              class="flex h-full flex-col items-center justify-end gap-1"
            >
              <div class="flex h-full w-full items-end justify-center gap-1">
                <div
                  class="w-2.5 rounded-t bg-clinico-300 transition-[height] duration-300 dark:bg-clinico-700 sm:w-3"
                  :style="{ height: altoBarra(dia.anterior) }"
                  :title="`Semana anterior (${dia.etiqueta}): ${soles(dia.anterior)}`"
                />
                <div
                  class="w-2.5 rounded-t bg-botica-600 transition-[height] duration-300 sm:w-3"
                  :style="{ height: altoBarra(dia.actual) }"
                  :title="`Esta semana (${dia.etiqueta}): ${soles(dia.actual)}`"
                />
              </div>
              <span class="text-2xs font-medium text-texto-terciario">{{ dia.etiqueta }}</span>
            </div>
          </div>
        </section>

        <section
          v-if="datos.metodos_pago"
          class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-5"
          aria-label="Distribución por método de pago"
        >
          <div class="flex items-center gap-2">
            <Wallet class="size-4 text-texto-terciario" aria-hidden="true" />
            <h2 class="text-sm font-semibold text-texto-primario">
              Métodos de pago (30 días)
            </h2>
          </div>

          <BoticaEstadoVacio
            v-if="datos.metodos_pago.length === 0"
            :icono="Wallet"
            titulo="Sin pagos registrados"
            descripcion="Los cobros por efectivo, Yape, Plin o tarjeta aparecerán aquí."
            class="py-8"
          />

          <ul v-else class="mt-4 space-y-3">
            <li
              v-for="m in datos.metodos_pago"
              :key="m.medio"
              class="space-y-1"
            >
              <div class="flex items-center justify-between text-xs">
                <span class="font-medium text-texto-primario">
                  {{ etiquetaMedio[m.medio] ?? m.medio }}
                  <span class="font-normal text-texto-terciario">({{ m.comprobantes }})</span>
                </span>
                <span class="tabular-nums text-texto-secundario">
                  {{ soles(m.importe) }} · {{ m.porcentaje }}%
                </span>
              </div>
              <div class="h-2 w-full overflow-hidden rounded-full bg-superficie-hundida">
                <div
                  class="h-full rounded-full bg-clinico-500 transition-[width] duration-300"
                  :style="{ width: `${Math.max(2, m.porcentaje)}%` }"
                />
              </div>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </div>
</template>
