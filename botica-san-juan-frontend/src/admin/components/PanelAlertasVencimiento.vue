<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import { useAdminToast } from '../composables/useAdminToast'
import { AlertTriangle, CalendarClock, CircleCheck, PackageOpen } from 'lucide-vue-next'
import BoticaBadge from './ui/BoticaBadge.vue'
import BoticaStat from './ui/BoticaStat.vue'
import BoticaEstadoVacio from './ui/BoticaEstadoVacio.vue'
import { etiquetaVencimiento, tonoVencimiento } from '../utils/vencimientos'

export type BucketVencimiento = 'todos' | 'vencido' | '<=30' | '<=60' | '<=90' | 'vigente'

interface LoteVencimiento {
  id: number
  producto_id: number
  codigo_lote: string
  fecha_vencimiento: string | null
  dias_para_vencer: number | null
  bucket: Exclude<BucketVencimiento, 'todos'>
  nivel_vencimiento: string
  cantidad_actual: number
  costo_unitario: number
  valor_en_riesgo: number
  estado: string
  producto: {
    id: number
    nombre: string
    concentracion: string | null
    presentacion: string | null
    laboratorio: string | null
  } | null
}

interface ResumenVencimientos {
  total: number
  vencido: number
  '<=30': number
  '<=60': number
  '<=90': number
  vigente: number
  valor_en_riesgo_90d: number
  valor_vencido: number
}

const { notifyError } = useAdminToast()

const loading = ref(false)
const bucketActivo = ref<BucketVencimiento>('todos')
const lotes = ref<LoteVencimiento[]>([])
const resumen = ref<ResumenVencimientos>({
  total: 0,
  vencido: 0,
  '<=30': 0,
  '<=60': 0,
  '<=90': 0,
  vigente: 0,
  valor_en_riesgo_90d: 0,
  valor_vencido: 0,
})

const buckets = computed(() => [
  { id: 'todos' as const, etiqueta: 'Todos', conteo: resumen.value.total },
  { id: 'vencido' as const, etiqueta: 'Vencidos', conteo: resumen.value.vencido },
  { id: '<=30' as const, etiqueta: '≤ 30 días', conteo: resumen.value['<=30'] },
  { id: '<=60' as const, etiqueta: '≤ 60 días', conteo: resumen.value['<=60'] },
  { id: '<=90' as const, etiqueta: '≤ 90 días', conteo: resumen.value['<=90'] },
  { id: 'vigente' as const, etiqueta: 'Vigentes', conteo: resumen.value.vigente },
])

function soles(valor: number): string {
  return `S/ ${valor.toFixed(2)}`
}

async function cargar(bucket: BucketVencimiento = bucketActivo.value) {
  loading.value = true
  try {
    const params = bucket === 'todos' ? undefined : { bucket }
    const { data } = await api.get<{ resumen: ResumenVencimientos; lotes: LoteVencimiento[] }>(
      '/inventario/vencimientos',
      { params },
    )
    resumen.value = data.resumen
    lotes.value = data.lotes
  } catch {
    notifyError('No se pudieron cargar las alertas de vencimiento.')
  } finally {
    loading.value = false
  }
}

function seleccionarBucket(bucket: BucketVencimiento) {
  bucketActivo.value = bucket
  void cargar(bucket)
}

defineExpose({ cargar })

onMounted(() => {
  void cargar()
})
</script>

<template>
  <div class="space-y-5">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <BoticaStat
        etiqueta="Valor en riesgo (90d)"
        :valor="soles(resumen.valor_en_riesgo_90d)"
        :icono="AlertTriangle"
        tono="alerta"
        detalle="Stock × costo de lotes que vencen en ≤ 90 días"
        :cargando="loading"
      />
      <BoticaStat
        etiqueta="Lotes vencidos"
        :valor="resumen.vencido"
        :icono="AlertTriangle"
        tono="critico"
        :detalle="`Valor retenido: ${soles(resumen.valor_vencido)}`"
        :cargando="loading"
      />
      <BoticaStat
        etiqueta="Críticos (≤ 30 días)"
        :valor="resumen['<=30']"
        :icono="CalendarClock"
        tono="critico"
        detalle="Priorizar salida FEFO o promoción"
        :cargando="loading"
      />
      <BoticaStat
        etiqueta="Próximos (31–90 días)"
        :valor="resumen['<=60'] + resumen['<=90']"
        :icono="CircleCheck"
        tono="info"
        detalle="Seguimiento preventivo de rotación"
        :cargando="loading"
      />
    </div>

    <div
      class="flex flex-wrap gap-2 rounded-xl border border-borde-sutil bg-superficie-elevada p-3"
      role="tablist"
      aria-label="Filtros por ventana de vencimiento"
    >
      <button
        v-for="b in buckets"
        :key="b.id"
        type="button"
        role="tab"
        :aria-selected="bucketActivo === b.id"
        class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors"
        :class="bucketActivo === b.id
          ? 'border-borde-marca bg-botica-50 text-botica-700 dark:bg-botica-500/15 dark:text-botica-300'
          : 'border-borde-base bg-superficie-hundida text-texto-secundario hover:text-texto-primario'"
        @click="seleccionarBucket(b.id)"
      >
        <span>{{ b.etiqueta }}</span>
        <span class="rounded-full bg-superficie-elevada px-1.5 py-0.5 text-2xs font-semibold tabular-nums">
          {{ b.conteo }}
        </span>
      </button>
    </div>

    <section
      class="overflow-hidden rounded-xl border border-borde-sutil bg-superficie-elevada"
      aria-label="Lotes por fecha de vencimiento"
    >
      <div
        v-if="loading"
        class="divide-y divide-borde-sutil"
        aria-hidden="true"
      >
        <div
          v-for="n in 5"
          :key="n"
          class="flex items-center gap-4 px-4 py-4 sm:px-6"
        >
          <div class="flex-1 space-y-2">
            <div class="h-3.5 w-1/3 animate-pulse rounded bg-superficie-interactiva" />
            <div class="h-3 w-1/4 animate-pulse rounded bg-superficie-hundida" />
          </div>
          <div class="h-6 w-16 animate-pulse rounded-full bg-superficie-interactiva" />
        </div>
      </div>

      <BoticaEstadoVacio
        v-else-if="lotes.length === 0"
        :icono="PackageOpen"
        titulo="Sin lotes en esta ventana"
        descripcion="No hay lotes con existencias para el filtro seleccionado."
      />

      <div v-else>
        <!-- Vista móvil (360 px) -->
        <ul class="divide-y divide-borde-sutil md:hidden">
          <li
            v-for="lote in lotes"
            :key="lote.id"
            class="space-y-2 p-4"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-texto-primario">
                  {{ lote.producto?.nombre ?? `Producto #${lote.producto_id}` }}
                </p>
                <p class="text-xs text-texto-terciario">
                  Lote {{ lote.codigo_lote }} · {{ lote.fecha_vencimiento ?? 'Sin fecha' }}
                </p>
              </div>
              <BoticaBadge :tono="tonoVencimiento(lote.dias_para_vencer, lote.nivel_vencimiento)">
                {{ etiquetaVencimiento(lote.dias_para_vencer) }}
              </BoticaBadge>
            </div>
            <div class="flex items-center justify-between text-xs text-texto-secundario">
              <span>{{ lote.cantidad_actual }} uds.</span>
              <span class="font-medium tabular-nums text-texto-primario">
                {{ soles(lote.valor_en_riesgo) }}
              </span>
            </div>
          </li>
        </ul>

        <!-- Vista escritorio -->
        <div class="hidden overflow-x-auto md:block">
          <table class="min-w-full divide-y divide-borde-sutil text-sm">
            <thead class="bg-superficie-hundida text-left text-xs font-medium uppercase tracking-wide text-texto-terciario">
              <tr>
                <th class="px-4 py-3">Producto</th>
                <th class="px-4 py-3">Lote</th>
                <th class="px-4 py-3">Vencimiento</th>
                <th class="px-4 py-3 text-right">Stock</th>
                <th class="px-4 py-3 text-right">Valor en riesgo</th>
                <th class="px-4 py-3 text-right">Semáforo</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-borde-sutil">
              <tr
                v-for="lote in lotes"
                :key="lote.id"
                class="transition-colors hover:bg-superficie-interactiva/50"
              >
                <td class="px-4 py-3">
                  <p class="font-medium text-texto-primario">
                    {{ lote.producto?.nombre ?? `Producto #${lote.producto_id}` }}
                  </p>
                  <p class="text-xs text-texto-terciario">
                    {{ [lote.producto?.concentracion, lote.producto?.presentacion].filter(Boolean).join(' · ') }}
                  </p>
                </td>
                <td class="px-4 py-3 font-mono text-xs text-texto-secundario">
                  {{ lote.codigo_lote }}
                </td>
                <td class="px-4 py-3 tabular-nums text-texto-secundario">
                  {{ lote.fecha_vencimiento ?? 'Sin fecha' }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums text-texto-primario">
                  {{ lote.cantidad_actual }}
                </td>
                <td class="px-4 py-3 text-right font-medium tabular-nums text-texto-primario">
                  {{ soles(lote.valor_en_riesgo) }}
                </td>
                <td class="px-4 py-3 text-right">
                  <BoticaBadge :tono="tonoVencimiento(lote.dias_para_vencer, lote.nivel_vencimiento)">
                    {{ etiquetaVencimiento(lote.dias_para_vencer) }}
                  </BoticaBadge>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</template>
