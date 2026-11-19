<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import { useAdminToast } from '../composables/useAdminToast'
import {
  ArrowDownCircle,
  ArrowUpCircle,
  Calculator,
  History,
  Lock,
  LockOpen,
  RefreshCw,
  Wallet,
} from 'lucide-vue-next'
import BoticaBadge from '../components/ui/BoticaBadge.vue'
import BoticaButton from '../components/ui/BoticaButton.vue'
import BoticaEstadoVacio from '../components/ui/BoticaEstadoVacio.vue'
import BoticaStat from '../components/ui/BoticaStat.vue'

interface MovimientoCaja {
  id: number
  tipo: 'venta_efectivo' | 'ingreso' | 'egreso'
  monto: number
  motivo: string
  referencia: string | null
  pedido_id: number | null
  created_at: string | null
}

interface SesionCaja {
  id: number
  usuario_id: number
  cajero: string | null
  estado: 'abierta' | 'cerrada'
  monto_inicial: number
  ventas_efectivo: number
  total_ingresos: number
  total_egresos: number
  monto_esperado: number | null
  monto_contado: number | null
  diferencia: number | null
  observacion_apertura: string | null
  observacion_cierre: string | null
  abierta_en: string | null
  cerrada_en: string | null
  movimientos?: MovimientoCaja[]
}

interface ArqueoCaja {
  monto_inicial: number
  ventas_efectivo: number
  ingresos: number
  egresos: number
  monto_esperado: number
  movimientos_count: number
}

interface HistorialKpis {
  total_sesiones: number
  total_ventas_efectivo: number
  diferencia_acumulada: number
}

const { notifyError, notifySuccess } = useAdminToast()

const tab = ref<'actual' | 'historial'>('actual')
const cargando = ref(false)
const enviando = ref(false)

const sesion = ref<SesionCaja | null>(null)
const arqueo = ref<ArqueoCaja | null>(null)
const historial = ref<SesionCaja[]>([])
const historialKpis = ref<HistorialKpis>({
  total_sesiones: 0,
  total_ventas_efectivo: 0,
  diferencia_acumulada: 0,
})

// Formulario de apertura
const montoInicial = ref<string>('100.00')
const observacionApertura = ref('')

// Formulario de movimiento manual
const tipoMovimiento = ref<'ingreso' | 'egreso'>('ingreso')
const montoMovimiento = ref<string>('')
const motivoMovimiento = ref('')

// Formulario de cierre
const montoContado = ref<string>('')
const observacionCierre = ref('')

const diferenciaPreview = computed<number | null>(() => {
  if (!arqueo.value || montoContado.value === '') return null
  const contado = Number(montoContado.value)
  if (Number.isNaN(contado)) return null
  return Math.round((contado - arqueo.value.monto_esperado) * 100) / 100
})

function soles(valor: number | null | undefined): string {
  const num = Number(valor ?? 0)
  return `S/ ${num.toFixed(2)}`
}

function formatoFecha(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('es-PE', {
    dateStyle: 'short',
    timeStyle: 'short',
  })
}

async function cargarActual() {
  cargando.value = true
  try {
    const { data } = await api.get<{ sesion: SesionCaja | null; arqueo: ArqueoCaja | null }>('/caja/actual')
    sesion.value = data.sesion
    arqueo.value = data.arqueo
    if (data.arqueo && montoContado.value === '') {
      montoContado.value = data.arqueo.monto_esperado.toFixed(2)
    }
  } catch {
    notifyError('No se pudo cargar el estado de caja.')
  } finally {
    cargando.value = false
  }
}

async function cargarHistorial() {
  cargando.value = true
  try {
    const { data } = await api.get<{ kpis: HistorialKpis; sesiones: SesionCaja[] }>('/caja/historial')
    historialKpis.value = data.kpis
    historial.value = data.sesiones
  } catch {
    notifyError('No se pudo cargar el historial de caja.')
  } finally {
    cargando.value = false
  }
}

async function abrirCaja() {
  const monto = Number(montoInicial.value)
  if (Number.isNaN(monto) || monto < 0) {
    notifyError('Ingresa un monto inicial válido.')
    return
  }
  enviando.value = true
  try {
    const { data } = await api.post<{ sesion: SesionCaja; arqueo: ArqueoCaja }>('/caja/abrir', {
      monto_inicial: monto,
      observacion_apertura: observacionApertura.value.trim() || null,
    })
    sesion.value = data.sesion
    arqueo.value = data.arqueo
    montoContado.value = data.arqueo.monto_esperado.toFixed(2)
    observacionApertura.value = ''
    notifySuccess('Sesión de caja abierta.')
  } catch (err: unknown) {
    const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    notifyError(msg ?? 'No se pudo abrir la caja.')
  } finally {
    enviando.value = false
  }
}

async function registrarMovimiento() {
  const monto = Number(montoMovimiento.value)
  if (Number.isNaN(monto) || monto <= 0) {
    notifyError('Ingresa un monto mayor a cero.')
    return
  }
  if (motivoMovimiento.value.trim().length < 3) {
    notifyError('Indica el motivo del movimiento.')
    return
  }

  enviando.value = true
  try {
    await api.post('/caja/movimientos', {
      tipo: tipoMovimiento.value,
      monto,
      motivo: motivoMovimiento.value.trim(),
    })
    montoMovimiento.value = ''
    motivoMovimiento.value = ''
    notifySuccess('Movimiento registrado.')
    await cargarActual()
    if (arqueo.value) {
      montoContado.value = arqueo.value.monto_esperado.toFixed(2)
    }
  } catch (err: unknown) {
    const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    notifyError(msg ?? 'No se pudo registrar el movimiento.')
  } finally {
    enviando.value = false
  }
}

async function cerrarCaja() {
  if (!sesion.value) return
  const contado = Number(montoContado.value)
  if (Number.isNaN(contado) || contado < 0) {
    notifyError('Ingresa el monto contado en efectivo.')
    return
  }

  enviando.value = true
  try {
    await api.post(`/caja/${sesion.value.id}/cerrar`, {
      monto_contado: contado,
      observacion_cierre: observacionCierre.value.trim() || null,
    })
    sesion.value = null
    arqueo.value = null
    montoContado.value = ''
    observacionCierre.value = ''
    notifySuccess('Sesión de caja cerrada correctamente.')
    await cargarHistorial()
    tab.value = 'historial'
  } catch (err: unknown) {
    const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    notifyError(msg ?? 'No se pudo cerrar la caja.')
  } finally {
    enviando.value = false
  }
}

function cambiarTab(nuevo: 'actual' | 'historial') {
  tab.value = nuevo
  if (nuevo === 'historial') {
    void cargarHistorial()
  } else {
    void cargarActual()
  }
}

onMounted(() => {
  void cargarActual()
})
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-texto-primario">
          Control de caja
        </h1>
        <p class="mt-1 text-sm text-texto-secundario">
          Apertura de turno, movimientos de efectivo, arqueo y cierre diario.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <BoticaButton
          :icono="RefreshCw"
          :cargando="cargando"
          @click="tab === 'actual' ? cargarActual() : cargarHistorial()"
        >
          Actualizar
        </BoticaButton>
      </div>
    </header>

    <div
      class="flex flex-wrap gap-2 border-b border-borde-sutil pb-3"
      role="tablist"
      aria-label="Secciones de caja"
    >
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'actual'"
        class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition-colors"
        :class="tab === 'actual'
          ? 'bg-botica-700 text-white'
          : 'bg-superficie-elevada text-texto-secundario hover:text-texto-primario'"
        @click="cambiarTab('actual')"
      >
        <Wallet class="size-4" aria-hidden="true" />
        Turno actual
      </button>
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'historial'"
        class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition-colors"
        :class="tab === 'historial'
          ? 'bg-botica-700 text-white'
          : 'bg-superficie-elevada text-texto-secundario hover:text-texto-primario'"
        @click="cambiarTab('historial')"
      >
        <History class="size-4" aria-hidden="true" />
        Historial
      </button>
    </div>

    <!-- Turno actual -->
    <template v-if="tab === 'actual'">
      <!-- Sin sesión abierta: Apertura -->
      <section
        v-if="!sesion"
        class="mx-auto max-w-lg rounded-xl border border-borde-sutil bg-superficie-elevada p-5 sm:p-6"
        aria-label="Apertura de caja"
      >
        <div class="flex items-center gap-3">
          <div class="flex size-10 items-center justify-center rounded-lg bg-botica-50 text-botica-700 dark:bg-botica-500/15 dark:text-botica-300">
            <LockOpen class="size-5" aria-hidden="true" />
          </div>
          <div>
            <h2 class="text-base font-semibold text-texto-primario">
              Abrir sesión de caja
            </h2>
            <p class="text-xs text-texto-secundario">
              Las ventas en efectivo del POS se vincularán automáticamente a este turno.
            </p>
          </div>
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="abrirCaja">
          <div>
            <label for="monto-inicial" class="block text-xs font-medium text-texto-secundario">
              Monto inicial (S/)
            </label>
            <input
              id="monto-inicial"
              v-model="montoInicial"
              type="number"
              step="0.01"
              min="0"
              required
              class="mt-1.5 h-10 w-full rounded-lg border border-borde-base bg-superficie-hundida px-3 text-sm text-texto-primario outline-none focus:border-borde-marca focus:ring-2 focus:ring-botica-500/20"
            >
          </div>

          <div>
            <label for="obs-apertura" class="block text-xs font-medium text-texto-secundario">
              Observación de apertura (opcional)
            </label>
            <input
              id="obs-apertura"
              v-model="observacionApertura"
              type="text"
              maxlength="500"
              placeholder="Ej. Turno mañana — sencillo completo"
              class="mt-1.5 h-10 w-full rounded-lg border border-borde-base bg-superficie-hundida px-3 text-sm text-texto-primario outline-none focus:border-borde-marca focus:ring-2 focus:ring-botica-500/20"
            >
          </div>

          <BoticaButton
            tipo="submit"
            variante="primario"
            :icono="LockOpen"
            :cargando="enviando"
            class="w-full justify-center"
          >
            Abrir caja
          </BoticaButton>
        </form>
      </section>

      <!-- Con sesión abierta -->
      <div v-else class="space-y-6">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <BoticaStat
            etiqueta="Monto inicial"
            :valor="soles(arqueo?.monto_inicial)"
            :icono="Wallet"
            tono="neutro"
            :detalle="`Abierta ${formatoFecha(sesion.abierta_en)}`"
          />
          <BoticaStat
            etiqueta="Ventas efectivo"
            :valor="soles(arqueo?.ventas_efectivo)"
            :icono="ArrowUpCircle"
            tono="exito"
            detalle="Vinculadas desde el POS"
          />
          <BoticaStat
            etiqueta="Otros ingresos"
            :valor="soles(arqueo?.ingresos)"
            :icono="ArrowUpCircle"
            tono="info"
          />
          <BoticaStat
            etiqueta="Egresos"
            :valor="soles(arqueo?.egresos)"
            :icono="ArrowDownCircle"
            tono="alerta"
          />
          <BoticaStat
            etiqueta="Esperado en caja"
            :valor="soles(arqueo?.monto_esperado)"
            :icono="Calculator"
            tono="marca"
            detalle="Inicial + ventas + ingresos - egresos"
          />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <!-- Registrar ingreso/egreso -->
          <section
            class="rounded-xl border border-borde-sutil bg-superficie-elevada p-4 sm:p-5"
            aria-label="Registrar movimiento manual"
          >
            <h2 class="text-base font-semibold text-texto-primario">
              Registrar movimiento manual
            </h2>
            <form class="mt-4 space-y-3" @submit.prevent="registrarMovimiento">
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label for="tipo-mov" class="block text-xs font-medium text-texto-secundario">
                    Tipo
                  </label>
                  <select
                    id="tipo-mov"
                    v-model="tipoMovimiento"
                    class="mt-1 h-10 w-full rounded-lg border border-borde-base bg-superficie-hundida px-3 text-sm text-texto-primario outline-none focus:border-borde-marca"
                  >
                    <option value="ingreso">Ingreso</option>
                    <option value="egreso">Egreso</option>
                  </select>
                </div>
                <div>
                  <label for="monto-mov" class="block text-xs font-medium text-texto-secundario">
                    Monto (S/)
                  </label>
                  <input
                    id="monto-mov"
                    v-model="montoMovimiento"
                    type="number"
                    step="0.01"
                    min="0.01"
                    required
                    class="mt-1 h-10 w-full rounded-lg border border-borde-base bg-superficie-hundida px-3 text-sm text-texto-primario outline-none focus:border-borde-marca"
                  >
                </div>
              </div>
              <div>
                <label for="motivo-mov" class="block text-xs font-medium text-texto-secundario">
                  Motivo
                </label>
                <input
                  id="motivo-mov"
                  v-model="motivoMovimiento"
                  type="text"
                  required
                  minlength="3"
                  maxlength="255"
                  placeholder="Ej. Reposición de sencillo / Pago de delivery"
                  class="mt-1 h-10 w-full rounded-lg border border-borde-base bg-superficie-hundida px-3 text-sm text-texto-primario outline-none focus:border-borde-marca"
                >
              </div>
              <BoticaButton
                tipo="submit"
                :cargando="enviando"
              >
                Registrar movimiento
              </BoticaButton>
            </form>
          </section>

          <!-- Arqueo y cierre -->
          <section
            class="rounded-xl border border-borde-sutil bg-superficie-elevada p-4 sm:p-5"
            aria-label="Arqueo y cierre de caja"
          >
            <h2 class="text-base font-semibold text-texto-primario">
              Arqueo y cierre de turno
            </h2>
            <form class="mt-4 space-y-3" @submit.prevent="cerrarCaja">
              <div>
                <label for="monto-contado" class="block text-xs font-medium text-texto-secundario">
                  Efectivo contado en gaveta (S/)
                </label>
                <input
                  id="monto-contado"
                  v-model="montoContado"
                  type="number"
                  step="0.01"
                  min="0"
                  required
                  class="mt-1 h-10 w-full rounded-lg border border-borde-base bg-superficie-hundida px-3 text-sm text-texto-primario outline-none focus:border-borde-marca"
                >
              </div>

              <div
                v-if="diferenciaPreview !== null"
                class="flex items-center justify-between rounded-lg border border-borde-sutil bg-superficie-hundida px-3 py-2 text-sm"
              >
                <span class="text-texto-secundario">Diferencia calculada</span>
                <BoticaBadge
                  :tono="diferenciaPreview === 0 ? 'normal' : diferenciaPreview > 0 ? 'bajo' : 'critico'"
                >
                  {{ diferenciaPreview === 0 ? 'Cuadra exacto' : `${diferenciaPreview > 0 ? '+' : ''}${soles(diferenciaPreview)}` }}
                </BoticaBadge>
              </div>

              <div>
                <label for="obs-cierre" class="block text-xs font-medium text-texto-secundario">
                  Observación de cierre (opcional)
                </label>
                <input
                  id="obs-cierre"
                  v-model="observacionCierre"
                  type="text"
                  maxlength="500"
                  placeholder="Notas del arqueo"
                  class="mt-1 h-10 w-full rounded-lg border border-borde-base bg-superficie-hundida px-3 text-sm text-texto-primario outline-none focus:border-borde-marca"
                >
              </div>

              <BoticaButton
                tipo="submit"
                variante="primario"
                :icono="Lock"
                :cargando="enviando"
              >
                Cerrar caja
              </BoticaButton>
            </form>
          </section>
        </div>

        <!-- Movimientos del turno -->
        <section
          class="overflow-hidden rounded-xl border border-borde-sutil bg-superficie-elevada"
          aria-label="Movimientos del turno"
        >
          <div class="border-b border-borde-sutil px-4 py-3">
            <h2 class="text-sm font-semibold text-texto-primario">
              Movimientos del turno ({{ sesion.movimientos?.length ?? 0 }})
            </h2>
          </div>

          <BoticaEstadoVacio
            v-if="!sesion.movimientos || sesion.movimientos.length === 0"
            :icono="Wallet"
            titulo="Sin movimientos aún"
            descripcion="Las ventas en efectivo y los ingresos o egresos manuales aparecerán aquí."
          />

          <ul v-else class="divide-y divide-borde-sutil">
            <li
              v-for="mov in sesion.movimientos"
              :key="mov.id"
              class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
            >
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <BoticaBadge
                    :tono="mov.tipo === 'egreso' ? 'critico' : mov.tipo === 'ingreso' ? 'bajo' : 'normal'"
                  >
                    {{ mov.tipo === 'venta_efectivo' ? 'Venta efectivo' : mov.tipo === 'ingreso' ? 'Ingreso' : 'Egreso' }}
                  </BoticaBadge>
                  <span class="truncate font-medium text-texto-primario">{{ mov.motivo }}</span>
                </div>
                <p class="mt-0.5 text-xs text-texto-terciario">
                  {{ formatoFecha(mov.created_at) }}
                </p>
              </div>
              <span
                class="font-semibold tabular-nums"
                :class="mov.tipo === 'egreso' ? 'text-peligro-600 dark:text-peligro-500' : 'text-texto-primario'"
              >
                {{ mov.tipo === 'egreso' ? '−' : '+' }} {{ soles(mov.monto) }}
              </span>
            </li>
          </ul>
        </section>
      </div>
    </template>

    <!-- Historial -->
    <template v-else>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <BoticaStat
          etiqueta="Total sesiones"
          :valor="historialKpis.total_sesiones"
          :icono="History"
          tono="neutro"
          :cargando="cargando"
        />
        <BoticaStat
          etiqueta="Ventas en efectivo"
          :valor="soles(historialKpis.total_ventas_efectivo)"
          :icono="Wallet"
          tono="exito"
          :cargando="cargando"
        />
        <BoticaStat
          etiqueta="Diferencia acumulada"
          :valor="soles(historialKpis.diferencia_acumulada)"
          :icono="Calculator"
          :tono="historialKpis.diferencia_acumulada === 0 ? 'marca' : 'alerta'"
          :cargando="cargando"
        />
      </div>

      <section
        class="overflow-hidden rounded-xl border border-borde-sutil bg-superficie-elevada"
        aria-label="Historial de sesiones de caja"
      >
        <BoticaEstadoVacio
          v-if="historial.length === 0"
          :icono="History"
          titulo="Sin sesiones registradas"
          descripcion="Cuando abras y cierres un turno de caja, aparecerá en este historial."
        />

        <div v-else>
          <!-- Vista móvil (360 px) -->
          <ul class="divide-y divide-borde-sutil md:hidden">
            <li v-for="s in historial" :key="s.id" class="space-y-2 p-4 text-sm">
              <div class="flex items-center justify-between gap-2">
                <span class="font-medium text-texto-primario">{{ s.cajero ?? `Usuario #${s.usuario_id}` }}</span>
                <BoticaBadge :tono="s.estado === 'abierta' ? 'normal' : 'neutro'">
                  {{ s.estado === 'abierta' ? 'Abierta' : 'Cerrada' }}
                </BoticaBadge>
              </div>
              <p class="text-xs text-texto-terciario">
                {{ formatoFecha(s.abierta_en) }}
              </p>
              <div class="flex justify-between text-xs text-texto-secundario">
                <span>Ventas: {{ soles(s.ventas_efectivo) }}</span>
                <span>Diferencia: {{ s.diferencia !== null ? soles(s.diferencia) : '—' }}</span>
              </div>
            </li>
          </ul>

          <!-- Vista escritorio -->
          <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-borde-sutil text-sm">
              <thead class="bg-superficie-hundida text-left text-xs font-medium uppercase tracking-wide text-texto-terciario">
                <tr>
                  <th class="px-4 py-3">Cajero</th>
                  <th class="px-4 py-3">Apertura</th>
                  <th class="px-4 py-3">Cierre</th>
                  <th class="px-4 py-3 text-right">Inicial</th>
                  <th class="px-4 py-3 text-right">Ventas ef.</th>
                  <th class="px-4 py-3 text-right">Esperado</th>
                  <th class="px-4 py-3 text-right">Contado</th>
                  <th class="px-4 py-3 text-right">Diferencia</th>
                  <th class="px-4 py-3 text-right">Estado</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-borde-sutil">
                <tr v-for="s in historial" :key="s.id" class="hover:bg-superficie-interactiva/50">
                  <td class="px-4 py-3 font-medium text-texto-primario">
                    {{ s.cajero ?? `Usuario #${s.usuario_id}` }}
                  </td>
                  <td class="px-4 py-3 text-xs text-texto-secundario">{{ formatoFecha(s.abierta_en) }}</td>
                  <td class="px-4 py-3 text-xs text-texto-secundario">{{ formatoFecha(s.cerrada_en) }}</td>
                  <td class="px-4 py-3 text-right tabular-nums">{{ soles(s.monto_inicial) }}</td>
                  <td class="px-4 py-3 text-right tabular-nums">{{ soles(s.ventas_efectivo) }}</td>
                  <td class="px-4 py-3 text-right tabular-nums">{{ s.monto_esperado !== null ? soles(s.monto_esperado) : '—' }}</td>
                  <td class="px-4 py-3 text-right tabular-nums">{{ s.monto_contado !== null ? soles(s.monto_contado) : '—' }}</td>
                  <td class="px-4 py-3 text-right tabular-nums">{{ s.diferencia !== null ? soles(s.diferencia) : '—' }}</td>
                  <td class="px-4 py-3 text-right">
                    <BoticaBadge :tono="s.estado === 'abierta' ? 'normal' : 'neutro'">
                      {{ s.estado === 'abierta' ? 'Abierta' : 'Cerrada' }}
                    </BoticaBadge>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>
