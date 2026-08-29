<template>
  <div class="space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-3xl font-bold text-slate-900">
          {{ isSunatView ? 'SUNAT Electronica' : 'Facturacion Electronica' }}
        </h1>
        <p class="mt-2 text-slate-600">
          {{ isSunatView
            ? 'Panel exclusivo de estados, ticket y envio SUNAT.'
            : 'Gestion de boletas y facturas con estado de envio a SUNAT.' }}
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button
          v-if="!isSunatView"
          class="inline-flex items-center rounded-xl bg-slate-800 px-4 py-3 text-white transition hover:bg-slate-900"
          @click="generateDocuments"
        >
          <FilePlus2 class="mr-2 h-4 w-4" />
          Generar desde pedidos
        </button>
        <button
          class="inline-flex items-center rounded-xl bg-linear-to-r from-cyan-600 to-blue-600 px-4 py-3 text-white shadow-lg shadow-blue-600/20 transition hover:from-cyan-700 hover:to-blue-700"
          @click="loadDocuments"
        >
          <RefreshCw class="mr-2 h-4 w-4" />
          Actualizar documentos
        </button>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-2xl bg-blue-600 p-5 text-white">
        <p class="text-sm text-blue-100">
          Total documentos
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ docs.length }}
        </p>
      </div>
      <div class="rounded-2xl bg-emerald-600 p-5 text-white">
        <p class="text-sm text-emerald-100">
          Aceptadas SUNAT
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ acceptedCount }}
        </p>
      </div>
      <div class="rounded-2xl bg-amber-500 p-5 text-white">
        <p class="text-sm text-amber-100">
          Pendientes
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ pendingCount }}
        </p>
      </div>
      <div class="rounded-2xl bg-rose-600 p-5 text-white">
        <p class="text-sm text-rose-100">
          Rechazadas
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ rejectedCount }}
        </p>
      </div>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow ring-1 ring-slate-200/80 sm:p-6">
      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_200px]">
        <input
          v-model="search"
          type="search"
          placeholder="Buscar por numero, cliente o pedido..."
          class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
        />
        <select
          v-model="docTypeFilter"
          class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
        >
          <option value="all">
            Todos los tipos
          </option>
          <option value="boleta">
            Boletas
          </option>
          <option value="factura">
            Facturas
          </option>
        </select>
        <select
          v-model="statusFilter"
          class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
        >
          <option value="all">
            Todos los estados
          </option>
          <option value="pendiente">
            Pendiente
          </option>
          <option value="aceptada">
            Aceptada
          </option>
          <option value="rechazada">
            Rechazada
          </option>
        </select>
      </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-slate-200/80">
      <div
        v-if="loading"
        class="py-16 text-center text-slate-600"
      >
        Cargando documentos...
      </div>

      <div
        v-else-if="filteredDocs.length === 0"
        class="py-16 text-center text-slate-500"
      >
        No hay documentos para los filtros seleccionados.
      </div>

      <div
        v-else
        class="overflow-x-auto"
      >
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Documento
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Pedido
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Cliente
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                Monto
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                SUNAT
              </th>
              <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="doc in filteredDocs"
              :key="doc.id"
              class="hover:bg-slate-50"
            >
              <td class="px-6 py-4">
                <p class="font-semibold text-slate-900">
                  {{ doc.number }}
                </p>
                <p class="text-xs text-slate-500 uppercase">
                  {{ doc.type }}
                </p>
                <p
                  v-if="doc.sunatTicket"
                  class="text-xs text-slate-500"
                >
                  Ticket: {{ doc.sunatTicket }}
                </p>
              </td>
              <td class="px-6 py-4 text-sm text-slate-700">
                #{{ doc.orderId }}
              </td>
              <td class="px-6 py-4">
                <p class="text-sm font-medium text-slate-900">
                  {{ doc.customerName }}
                </p>
                <p class="text-xs text-slate-500">
                  {{ doc.customerDoc }}
                </p>
              </td>
              <td class="px-6 py-4 text-sm font-semibold text-slate-900">
                S/ {{ doc.total.toFixed(2) }}
                <p
                  class="text-xs font-medium"
                  :class="doc.commissionStatus === 'liquidada' ? 'text-emerald-700' : 'text-amber-700'"
                >
                  Comision: S/ {{ (doc.commissionAmount || 0).toFixed(2) }} · {{ doc.commissionStatus || 'sin_comision' }}
                </p>
              </td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                  :class="statusClass(doc.sunatStatus)"
                >
                  {{ doc.sunatStatus }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="inline-flex items-center gap-2">
                  <button
                    class="rounded-lg p-2 text-cyan-700 transition hover:bg-cyan-50 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="doc.sunatStatus === 'aceptada'"
                    @click="sendToSunat(doc.id)"
                  >
                    <Send class="h-4 w-4" />
                  </button>
                  <button
                    class="rounded-lg p-2 text-blue-700 transition hover:bg-blue-50"
                    @click="downloadXml(doc.id)"
                  >
                    <FileDown class="h-4 w-4" />
                  </button>
                  <button
                    class="rounded-lg p-2 text-slate-700 transition hover:bg-slate-100"
                    title="Descargar PDF"
                    @click="downloadPdf(doc.id)"
                  >
                    <FileText class="h-4 w-4" />
                  </button>
                  <button
                    v-if="!isSunatView"
                    class="rounded-lg p-2 text-violet-700 transition hover:bg-violet-50"
                    title="Registrar comision"
                    @click="registerCommission(doc)"
                  >
                    <BadgeDollarSign class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div
      v-if="!isSunatView"
      class="rounded-2xl bg-white p-6 shadow ring-1 ring-slate-200/80"
    >
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-900">
          Comisiones registradas
        </h2>
        <button
          class="inline-flex items-center rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200"
          @click="loadCommissions"
        >
          <RefreshCw class="mr-2 h-4 w-4" /> Recargar
        </button>
      </div>

      <div
        v-if="commissions.length === 0"
        class="text-sm text-slate-500"
      >
        Sin comisiones por ahora.
      </div>

      <div
        v-else
        class="space-y-2"
      >
        <div
          v-for="commission in commissions"
          :key="commission.id"
          class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3"
        >
          <div>
            <p class="text-sm font-semibold text-slate-900">
              {{ commission.agente_nombre }} · {{ commission.tipo_agente }}
            </p>
            <p class="text-xs text-slate-500">
              Documento {{ commission.comprobante_number }} · {{ commission.porcentaje.toFixed(2) }}%
            </p>
          </div>
          <div class="flex items-center gap-3">
            <p class="text-sm font-semibold text-slate-900">
              S/ {{ commission.monto.toFixed(2) }}
            </p>
            <button
              class="rounded-lg px-3 py-1 text-xs font-semibold"
              :class="commission.estado === 'liquidada' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800 hover:bg-amber-200'"
              :disabled="commission.estado === 'liquidada'"
              @click="liquidateCommission(commission.id)"
            >
              {{ commission.estado === 'liquidada' ? 'Liquidada' : 'Liquidar' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-900">
      {{ isSunatView
        ? 'Vista SUNAT: enfoca estados, envio y XML de comprobantes electronicos.'
        : 'Fase 3 activa: facturacion conectada a API real del backend para generar comprobantes desde pedidos, enviar a SUNAT (simulado con trazabilidad) y descargar XML.' }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { BadgeDollarSign, FileDown, FilePlus2, FileText, RefreshCw, Send } from 'lucide-vue-next'
import api from '@/services/api'
import { useAdminToast } from '../composables/useAdminToast'

interface BillingDoc {
  id: number
  orderId: number
  number: string
  type: 'boleta' | 'factura'
  customerName: string
  customerDoc: string
  total: number
  sunatStatus: 'pendiente' | 'aceptada' | 'rechazada'
  sunatCode?: string | null
  sunatTicket?: string | null
  sunatMessage?: string | null
  commissionAmount?: number
  commissionStatus?: 'sin_comision' | 'pendiente' | 'liquidada'
}

interface CommissionItem {
  id: number
  tipo_agente: 'medico' | 'vendedor' | 'referido'
  agente_nombre: string
  porcentaje: number
  monto: number
  estado: 'pendiente' | 'liquidada'
  comprobante_number: string
}

interface ApiCommissionRow {
  id: number
  tipo_agente: 'medico' | 'vendedor' | 'referido'
  agente_nombre: string
  porcentaje: number | string
  monto: number | string
  estado: 'pendiente' | 'liquidada'
  comprobante?: {
    serie?: string
    numero?: number | string
  }
}

const { notifySuccess, notifyError } = useAdminToast()
const route = useRoute()

const docs = ref<BillingDoc[]>([])
const loading = ref(false)
const search = ref('')
const docTypeFilter = ref<'all' | 'boleta' | 'factura'>('all')
const statusFilter = ref<'all' | 'pendiente' | 'aceptada' | 'rechazada'>('all')
const commissions = ref<CommissionItem[]>([])

const isSunatView = computed(() => route.path.endsWith('/billing/sunat'))

const acceptedCount = computed(() => docs.value.filter(item => item.sunatStatus === 'aceptada').length)
const pendingCount = computed(() => docs.value.filter(item => item.sunatStatus === 'pendiente').length)
const rejectedCount = computed(() => docs.value.filter(item => item.sunatStatus === 'rechazada').length)

const filteredDocs = computed(() => {
  return docs.value.filter(doc => {
    const q = search.value.toLowerCase().trim()
    const searchMatch = !q ||
      doc.number.toLowerCase().includes(q) ||
      doc.customerName.toLowerCase().includes(q) ||
      String(doc.orderId).includes(q)

    const typeMatch = docTypeFilter.value === 'all' || doc.type === docTypeFilter.value
    const statusMatch = statusFilter.value === 'all' || doc.sunatStatus === statusFilter.value

    return searchMatch && typeMatch && statusMatch
  })
})

const statusClass = (status: BillingDoc['sunatStatus']) => {
  if (status === 'aceptada') return 'bg-emerald-100 text-emerald-800'
  if (status === 'rechazada') return 'bg-rose-100 text-rose-800'
  return 'bg-amber-100 text-amber-800'
}

const loadDocuments = async () => {
  loading.value = true
  try {
    const response = await api.get('/facturacion/documentos', {
      params: {
        per_page: 200,
        tipo_comprobante: docTypeFilter.value,
        estado_sunat: statusFilter.value,
        q: search.value || undefined
      }
    })

    docs.value = response.data.data || response.data || []
  } catch (error) {
    console.error('Error loading billing docs:', error)
    notifyError('Facturacion', 'No se pudieron cargar boletas/facturas.')
  } finally {
    loading.value = false
  }
}

const generateDocuments = async () => {
  try {
    const response = await api.post('/facturacion/generar-desde-pedidos', { limit: 300 })
    notifySuccess('Facturacion', `${response.data.created || 0} comprobantes generados desde pedidos.`)
    await loadDocuments()
    await loadCommissions()
  } catch (error) {
    console.error('Error generating billing docs:', error)
    notifyError('Facturacion', 'No se pudieron generar comprobantes desde pedidos.')
  }
}

const sendToSunat = async (docId: number) => {
  try {
    const response = await api.post(`/facturacion/documentos/${docId}/enviar-sunat`)
    const updated = response.data.documento as BillingDoc
    const index = docs.value.findIndex(item => item.id === docId)
    if (index >= 0) {
      docs.value[index] = updated
    }

    if (updated.sunatStatus === 'aceptada') {
      notifySuccess('SUNAT', `Documento ${updated.number} aceptado.`)
    } else {
      notifyError('SUNAT', `Documento ${updated.number} rechazado.`)
    }
    await loadCommissions()
  } catch (error) {
    console.error('Error sending to SUNAT:', error)
    notifyError('SUNAT', 'No se pudo enviar el documento a SUNAT.')
  }
}

const downloadXml = async (docId: number) => {
  const doc = docs.value.find(item => item.id === docId)
  if (!doc) return

  try {
    const response = await api.get(`/facturacion/documentos/${docId}/xml`, { responseType: 'blob' })
    const blob = new Blob([response.data], { type: 'application/xml' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${doc.number}.xml`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
    notifySuccess('Descarga', `XML descargado para ${doc.number}.`)
  } catch (error) {
    console.error('Error downloading XML:', error)
    notifyError('Descarga', `No se pudo descargar el XML de ${doc.number}.`)
  }
}

const downloadPdf = async (docId: number) => {
  const doc = docs.value.find(item => item.id === docId)
  if (!doc) return

  try {
    const response = await api.get(`/facturacion/documentos/${docId}/pdf`, { responseType: 'blob' })
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${doc.number}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
    notifySuccess('Descarga', `PDF descargado para ${doc.number}.`)
  } catch (error) {
    console.error('Error downloading PDF:', error)
    notifyError('Descarga', `No se pudo descargar el PDF de ${doc.number}.`)
  }
}

const loadCommissions = async () => {
  try {
    const response = await api.get('/facturacion/comisiones', { params: { per_page: 50 } })
    const rows = (response.data.data || []) as ApiCommissionRow[]
    commissions.value = rows.map((item) => ({
      id: item.id,
      tipo_agente: item.tipo_agente,
      agente_nombre: item.agente_nombre,
      porcentaje: Number(item.porcentaje || 0),
      monto: Number(item.monto || 0),
      estado: item.estado,
      comprobante_number: `${item.comprobante?.serie || ''}-${String(item.comprobante?.numero || '').padStart(8, '0')}`
    }))
  } catch (error) {
    console.error('Error loading commissions:', error)
  }
}

const registerCommission = async (doc: BillingDoc) => {
  const nombre = window.prompt('Nombre del agente de comision (medico/vendedor/referido):')
  if (!nombre) return

  const tipo = (window.prompt('Tipo de agente: medico, vendedor o referido', 'medico') || 'medico').trim()
  if (!['medico', 'vendedor', 'referido'].includes(tipo)) {
    notifyError('Comision', 'Tipo de agente invalido.')
    return
  }

  const porcentajeRaw = window.prompt('Porcentaje de comision (ej. 5):', '5')
  const porcentaje = Number(porcentajeRaw)
  if (!Number.isFinite(porcentaje) || porcentaje <= 0) {
    notifyError('Comision', 'Porcentaje invalido.')
    return
  }

  try {
    await api.post(`/facturacion/documentos/${doc.id}/comision`, {
      tipo_agente: tipo,
      agente_nombre: nombre,
      porcentaje
    })
    notifySuccess('Comision', `Comision registrada para ${doc.number}.`)
    await loadDocuments()
    await loadCommissions()
  } catch (error) {
    console.error('Error creating commission:', error)
    notifyError('Comision', 'No se pudo registrar la comision.')
  }
}

const liquidateCommission = async (id: number) => {
  try {
    await api.post(`/facturacion/comisiones/${id}/liquidar`)
    notifySuccess('Comision', 'Comision liquidada correctamente.')
    await loadCommissions()
    await loadDocuments()
  } catch (error) {
    console.error('Error liquidating commission:', error)
    notifyError('Comision', 'No se pudo liquidar la comision.')
  }
}

onMounted(async () => {
  await loadDocuments()
  await loadCommissions()
})
</script>
