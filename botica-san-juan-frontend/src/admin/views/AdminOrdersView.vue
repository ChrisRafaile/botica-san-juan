<!--
  Pedidos
  ===========================================================================
  QUÉ CAMBIÓ Y POR QUÉ

  La versión anterior estaba construida sobre un ciclo de vida de comercio
  electrónico —pendiente, procesando, enviado, entregado— que este sistema
  nunca implementó. Los estados reales en la base son otros:

      web   pendiente (32)   confirmado (3)
      pos   completado (7)

  Es decir: dos de las tres tarjetas de cabecera mostraban siempre cero, dos
  de las cinco opciones del filtro no devolvían nada nunca, y 'completado' —el
  estado con el que nace TODA venta de mostrador, que es la función principal
  de la botica— no tenía sitio en la pantalla.

  La pantalla ahora separa lo que el negocio ya tenía separado:

    · PORTAL — cola de trabajo. Alguien encargó algo por la web y espera.
      Hay acciones que tomar: confirmar, completar, anular.
    · MOSTRADOR — historial. La venta ya ocurrió, ya se cobró, ya se entregó.
      No hay nada que hacer con ella salvo consultarla, así que es de solo
      lectura.

  Mezclarlas producía listas donde el boticario no distinguía qué requería su
  atención de lo que ya estaba cerrado.
-->
<template>
  <div class="space-y-6 p-4 sm:p-6 lg:p-8">
    <!-- Cabecera -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-texto-primario">
          Pedidos
        </h1>
        <p class="mt-2 max-w-2xl text-texto-secundario">
          Los encargos del portal esperan atención; las ventas de mostrador ya
          están cerradas y solo se consultan.
        </p>
      </div>
      <BoticaButton
        :icono="RefreshCw"
        :cargando="cargando"
        @click="recargarTodo"
      >
        Actualizar
      </BoticaButton>
    </div>

    <!-- Cifras de catálogo (vienen del servidor, no de la página cargada) -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <BoticaStat
        etiqueta="Por atender"
        :valor="resumen.portal.pendientes.pedidos"
        :icono="Clock"
        :tono="resumen.portal.pendientes.pedidos > 0 ? 'alerta' : 'neutro'"
        :detalle="`S/ ${dinero(resumen.portal.pendientes.importe)} en encargos del portal`"
        :cargando="cargandoResumen"
      />
      <BoticaStat
        etiqueta="Confirmados"
        :valor="resumen.portal.confirmados.pedidos"
        :icono="PackageCheck"
        tono="info"
        :detalle="`S/ ${dinero(resumen.portal.confirmados.importe)} listos para entregar`"
        :cargando="cargandoResumen"
      />
      <BoticaStat
        etiqueta="Mostrador hoy"
        :valor="resumen.mostrador.hoy.pedidos"
        :icono="Store"
        tono="exito"
        :detalle="`S/ ${dinero(resumen.mostrador.hoy.importe)} cobrados`"
        :cargando="cargandoResumen"
      />
      <BoticaStat
        etiqueta="Registros"
        :valor="resumen.total_registros"
        :icono="ShoppingCart"
        tono="marca"
        detalle="Portal y mostrador, histórico completo"
        :cargando="cargandoResumen"
      />
    </div>

    <!--
      Aviso de cola estancada.
      "32 pendientes" no dice nada por sí solo. "El más antiguo lleva 18 días"
      dice que la cola dejó de atenderse, que es la información accionable.
    -->
    <div
      v-if="colaEstancada"
      class="flex items-start gap-3 rounded-2xl border border-alerta-500/25 bg-alerta-50 p-4 dark:bg-alerta-500/10"
    >
      <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-alerta-700 dark:text-alerta-500" />
      <div class="text-sm">
        <p class="font-semibold text-alerta-700 dark:text-alerta-500">
          Esta cola probablemente no describe trabajo real
        </p>
        <ul class="mt-2 space-y-1 text-texto-secundario">
          <li v-if="resumen.portal.mas_antiguo_dias !== null">
            El encargo abierto más antiguo lleva
            <strong class="text-texto-primario">{{ resumen.portal.mas_antiguo_dias }} días</strong>
            sin atenderse.
          </li>
          <li v-if="resumen.portal.abiertos_sin_lineas > 0">
            <strong class="text-texto-primario">{{ resumen.portal.abiertos_sin_lineas }}</strong>
            de los encargos abiertos no tienen ninguna línea de producto, así
            que el importe de la cabecera no corresponde a nada vendible.
          </li>
        </ul>
        <p class="mt-2 text-texto-secundario">
          Conviene depurarlos —confirmar con el cliente o anular— antes de
          tomar estas cifras como pendientes del negocio.
        </p>
      </div>
    </div>

    <!-- Selector de origen: son dos trabajos distintos, no un filtro más -->
    <div
      class="inline-flex rounded-xl border border-borde-base bg-superficie-elevada p-1"
      role="tablist"
    >
      <button
        v-for="pestana in PESTANAS"
        :key="pestana.origen"
        role="tab"
        :aria-selected="origen === pestana.origen"
        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition"
        :class="origen === pestana.origen
          ? 'bg-botica-700 text-white shadow-xs'
          : 'text-texto-secundario hover:bg-superficie-interactiva hover:text-texto-primario'"
        @click="cambiarOrigen(pestana.origen)"
      >
        <component
          :is="pestana.icono"
          class="h-4 w-4"
        />
        {{ pestana.rotulo }}
      </button>
    </div>

    <!-- Filtros -->
    <div class="rounded-2xl bg-superficie-elevada p-4 shadow-xs ring-1 ring-borde-sutil/80 sm:p-6">
      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_200px_120px]">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-texto-terciario" />
          <input
            v-model="busqueda"
            type="search"
            :placeholder="esPortal ? 'Buscar por cliente, pedido o producto…' : 'Buscar por venta, cliente o producto…'"
            class="w-full rounded-xl border border-borde-base bg-superficie-elevada py-3 pl-10 pr-4 text-texto-primario outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
          />
        </div>
        <select
          v-model="filtroEstado"
          class="rounded-xl border border-borde-base bg-superficie-elevada px-4 py-3 text-texto-primario outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
        >
          <option value="all">
            Todos los estados
          </option>
          <!-- Solo los estados que este origen puede tener de verdad -->
          <option
            v-for="estado in estadosDelOrigen"
            :key="estado"
            :value="estado"
          >
            {{ ESTADOS[estado].rotulo }}
          </option>
        </select>
        <input
          v-model="filtroFecha"
          type="date"
          class="rounded-xl border border-borde-base bg-superficie-elevada px-4 py-3 text-texto-primario outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
        />
        <select
          v-model.number="porPagina"
          class="rounded-xl border border-borde-base bg-superficie-elevada px-4 py-3 text-texto-primario outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
        >
          <option :value="10">
            10
          </option>
          <option :value="20">
            20
          </option>
          <option :value="50">
            50
          </option>
        </select>
      </div>
    </div>

    <!-- Listado -->
    <div class="overflow-hidden rounded-2xl bg-superficie-elevada shadow-xs ring-1 ring-borde-sutil/80">
      <div
        v-if="cargando"
        class="flex items-center justify-center py-16 text-texto-secundario"
      >
        <LoaderCircle class="h-8 w-8 animate-spin text-botica-700" />
        <span class="ml-3">Cargando pedidos…</span>
      </div>

      <BoticaEstadoVacio
        v-else-if="pedidos.length === 0"
        :titulo="tituloVacio"
        :descripcion="descripcionVacio"
        :icono="esPortal ? PackageOpen : Store"
      />

      <div v-else>
        <!-- Tarjetas (móvil) -->
        <div class="space-y-3 p-4 md:hidden">
          <article
            v-for="pedido in pedidos"
            :key="pedido.id"
            class="rounded-2xl border border-borde-base p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="font-semibold text-texto-primario">
                  {{ folio(pedido) }}
                </p>
                <p class="text-xs text-texto-terciario">
                  {{ fecha(pedido) }}
                </p>
              </div>
              <BoticaBadge :tono="ESTADOS[pedido.estado]?.tono ?? 'neutro'">
                {{ ESTADOS[pedido.estado]?.rotulo ?? pedido.estado }}
              </BoticaBadge>
            </div>

            <div class="mt-3 space-y-1 text-sm text-texto-secundario">
              <p>{{ nombreCliente(pedido) }}</p>
              <p>{{ pedido.pedidoDetalles?.length || 0 }} producto(s)</p>
              <p class="font-semibold text-texto-primario">
                S/ {{ dinero(pedido.total) }}
              </p>
            </div>

            <div class="mt-4 flex flex-wrap justify-end gap-2">
              <BoticaButton
                tamano="sm"
                :icono="Eye"
                @click="abrirDetalle(pedido)"
              >
                Ver
              </BoticaButton>
              <BoticaButton
                v-for="accion in accionesDe(pedido)"
                :key="accion.estado"
                tamano="sm"
                :variante="accion.variante"
                :icono="accion.icono"
                :cargando="guardando === pedido.id"
                @click="pedirConfirmacion(pedido, accion)"
              >
                {{ accion.rotulo }}
              </BoticaButton>
            </div>
          </article>
        </div>

        <!-- Tabla (escritorio) -->
        <div class="hidden overflow-x-auto md:block">
          <table class="min-w-full divide-y divide-borde-sutil">
            <thead class="bg-superficie-hundida">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  {{ esPortal ? 'Pedido' : 'Venta' }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Cliente
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Productos
                </th>
                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Total
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Estado
                </th>
                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Acciones
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-borde-sutil bg-superficie-elevada">
              <tr
                v-for="pedido in pedidos"
                :key="pedido.id"
                class="transition hover:bg-superficie-hundida"
              >
                <td class="whitespace-nowrap px-6 py-4">
                  <p class="font-semibold text-texto-primario">
                    {{ folio(pedido) }}
                  </p>
                  <p class="text-sm text-texto-terciario">
                    {{ fecha(pedido) }}
                  </p>
                </td>
                <td class="px-6 py-4">
                  <p class="font-medium text-texto-primario">
                    {{ nombreCliente(pedido) }}
                  </p>
                  <p class="text-sm text-texto-terciario">
                    {{ contactoCliente(pedido) }}
                  </p>
                </td>
                <td class="px-6 py-4">
                  <p class="text-sm font-medium text-texto-primario">
                    {{ pedido.pedidoDetalles?.length || 0 }} producto(s)
                  </p>
                  <p class="max-w-md truncate text-sm text-texto-terciario">
                    {{ resumenProductos(pedido) }}
                  </p>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right font-semibold tabular-nums text-texto-primario">
                  S/ {{ dinero(pedido.total) }}
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <BoticaBadge :tono="ESTADOS[pedido.estado]?.tono ?? 'neutro'">
                    {{ ESTADOS[pedido.estado]?.rotulo ?? pedido.estado }}
                  </BoticaBadge>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right">
                  <div class="inline-flex items-center gap-2">
                    <BoticaButton
                      tamano="sm"
                      variante="fantasma"
                      :icono="Eye"
                      etiqueta-accesible="Ver detalle"
                      @click="abrirDetalle(pedido)"
                    />
                    <BoticaButton
                      v-for="accion in accionesDe(pedido)"
                      :key="accion.estado"
                      tamano="sm"
                      :variante="accion.variante"
                      :icono="accion.icono"
                      :cargando="guardando === pedido.id"
                      @click="pedirConfirmacion(pedido, accion)"
                    >
                      {{ accion.rotulo }}
                    </BoticaButton>
                    <!--
                      Una venta cerrada no ofrece acciones: es historial. Se
                      dice explícitamente en vez de dejar la celda vacía, que
                      parecería un fallo de carga.
                    -->
                    <span
                      v-if="accionesDe(pedido).length === 0"
                      class="text-xs text-texto-terciario"
                    >
                      Cerrada
                    </span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Paginación -->
    <div
      v-if="totalPaginas > 1"
      class="flex flex-col items-center justify-between gap-3 rounded-2xl bg-superficie-elevada px-4 py-3 shadow-xs ring-1 ring-borde-sutil/80 sm:flex-row"
    >
      <p class="text-sm text-texto-secundario">
        Mostrando {{ pedidos.length }} de {{ totalRegistros }}
        {{ esPortal ? 'pedidos del portal' : 'ventas de mostrador' }}
      </p>
      <div class="inline-flex items-center gap-2">
        <BoticaButton
          tamano="sm"
          :disabled="paginaActual <= 1"
          @click="irAPagina(paginaActual - 1)"
        >
          Anterior
        </BoticaButton>
        <span class="text-sm font-semibold tabular-nums text-texto-secundario">
          {{ paginaActual }} / {{ totalPaginas }}
        </span>
        <BoticaButton
          tamano="sm"
          :disabled="paginaActual >= totalPaginas"
          @click="irAPagina(paginaActual + 1)"
        >
          Siguiente
        </BoticaButton>
      </div>
    </div>

    <!-- Detalle -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="detalle"
          class="fixed inset-0 z-50 flex items-center justify-center bg-neutro-950/50 px-4 py-6 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          @click.self="detalle = null"
        >
          <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-3xl bg-superficie-elevada shadow-2xl ring-1 ring-borde-sutil">
            <div class="flex items-start justify-between gap-4 border-b border-borde-sutil px-6 py-5">
              <div>
                <h2 class="text-2xl font-bold text-texto-primario">
                  {{ detalle.origen === 'pos' ? 'Venta' : 'Pedido' }} {{ folio(detalle) }}
                </h2>
                <p class="mt-1 text-sm text-texto-terciario">
                  {{ nombreCliente(detalle) }} · {{ fecha(detalle) }}
                </p>
              </div>
              <BoticaBadge :tono="ESTADOS[detalle.estado]?.tono ?? 'neutro'">
                {{ ESTADOS[detalle.estado]?.rotulo ?? detalle.estado }}
              </BoticaBadge>
            </div>

            <div class="space-y-6 px-6 py-6">
              <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl bg-superficie-hundida p-4">
                  <p class="text-xs uppercase tracking-wider text-texto-terciario">
                    Total
                  </p>
                  <p class="mt-2 text-xl font-bold tabular-nums text-texto-primario">
                    S/ {{ dinero(detalle.total) }}
                  </p>
                </div>
                <div class="rounded-2xl bg-superficie-hundida p-4">
                  <p class="text-xs uppercase tracking-wider text-texto-terciario">
                    Cobro
                  </p>
                  <p class="mt-2 text-xl font-bold text-texto-primario">
                    {{ detalle.estado_pago === 'pagado' ? 'Pagado' : 'Pendiente' }}
                  </p>
                </div>
                <div class="rounded-2xl bg-superficie-hundida p-4">
                  <p class="text-xs uppercase tracking-wider text-texto-terciario">
                    Ítems
                  </p>
                  <p class="mt-2 text-xl font-bold tabular-nums text-texto-primario">
                    {{ detalle.pedidoDetalles?.length || 0 }}
                  </p>
                </div>
              </div>

              <div>
                <h3 class="mb-3 text-lg font-semibold text-texto-primario">
                  Productos
                </h3>
                <div
                  v-if="(detalle.pedidoDetalles?.length || 0) === 0"
                  class="rounded-2xl border border-dashed border-borde-base px-4 py-6 text-center text-sm text-texto-terciario"
                >
                  Este registro no tiene líneas de detalle.
                </div>
                <div
                  v-else
                  class="space-y-3"
                >
                  <div
                    v-for="linea in detalle.pedidoDetalles || []"
                    :key="linea.id"
                    class="flex items-center justify-between gap-4 rounded-2xl border border-borde-base px-4 py-3"
                  >
                    <div class="min-w-0">
                      <p class="truncate font-medium text-texto-primario">
                        {{ linea.producto?.nombre || 'Producto' }}
                      </p>
                      <p class="text-sm text-texto-terciario">
                        Cantidad: {{ linea.cantidad }}
                      </p>
                    </div>
                    <p class="shrink-0 font-semibold tabular-nums text-texto-primario">
                      S/ {{ dinero(linea.subtotal) }}
                    </p>
                  </div>
                </div>
              </div>

              <div class="flex justify-end">
                <BoticaButton @click="detalle = null">
                  Cerrar
                </BoticaButton>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Confirmación de cambio de estado -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="confirmacion"
          class="fixed inset-0 z-50 flex items-center justify-center bg-neutro-950/50 px-4 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          @click.self="confirmacion = null"
        >
          <div class="w-full max-w-md rounded-3xl bg-superficie-elevada p-6 shadow-2xl ring-1 ring-borde-sutil">
            <h2 class="text-lg font-bold text-texto-primario">
              {{ confirmacion.accion.confirmacionTitulo }}
            </h2>
            <p class="mt-2 text-sm text-texto-secundario">
              {{ confirmacion.accion.confirmacionTexto }}
            </p>
            <p class="mt-3 rounded-xl bg-superficie-hundida px-4 py-3 text-sm text-texto-primario">
              {{ folio(confirmacion.pedido) }} ·
              {{ nombreCliente(confirmacion.pedido) }} ·
              S/ {{ dinero(confirmacion.pedido.total) }}
            </p>
            <div class="mt-6 flex justify-end gap-2">
              <BoticaButton @click="confirmacion = null">
                Cancelar
              </BoticaButton>
              <BoticaButton
                :variante="confirmacion.accion.variante"
                :cargando="guardando === confirmacion.pedido.id"
                @click="aplicarAccion()"
              >
                {{ confirmacion.accion.rotulo }}
              </BoticaButton>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import type { Component } from 'vue'
import {
  AlertTriangle,
  Ban,
  CheckCheck,
  Clock,
  Eye,
  LoaderCircle,
  PackageCheck,
  PackageOpen,
  RefreshCw,
  Search,
  ShoppingCart,
  Store,
} from 'lucide-vue-next'
import api from '@/services/api'
import { useAdminToast } from '../composables/useAdminToast'
import BoticaBadge from '../components/ui/BoticaBadge.vue'
import BoticaButton from '../components/ui/BoticaButton.vue'
import BoticaEstadoVacio from '../components/ui/BoticaEstadoVacio.vue'
import BoticaStat from '../components/ui/BoticaStat.vue'

/* ---------------------------------------------------------------------------
 * ESTADOS
 * Espejo del vocabulario que fija App\Models\Pedido::ESTADOS. Si aquí aparece
 * un estado que el backend no acepta, la petición se rechaza con un 422 en vez
 * de guardarse en silencio: esa validación no existía y por eso convivieron
 * durante meses dos vocabularios distintos.
 * ------------------------------------------------------------------------- */
type Estado = 'pendiente' | 'confirmado' | 'completado' | 'anulado'
type Origen = 'web' | 'pos'

const ESTADOS: Record<Estado, { rotulo: string; tono: 'bajo' | 'vigente' | 'normal' | 'agotado' }> = {
  pendiente:  { rotulo: 'Por atender', tono: 'bajo' },
  confirmado: { rotulo: 'Confirmado',  tono: 'vigente' },
  completado: { rotulo: 'Completado',  tono: 'normal' },
  anulado:    { rotulo: 'Anulado',     tono: 'agotado' },
}

/* Qué estados puede tener realmente cada origen. El filtro no ofrece los
   demás porque devolverían siempre una lista vacía. */
const ESTADOS_POR_ORIGEN: Record<Origen, Estado[]> = {
  web: ['pendiente', 'confirmado', 'completado', 'anulado'],
  pos: ['completado', 'anulado'],
}

interface Accion {
  estado: Estado
  rotulo: string
  icono: Component
  variante: 'primario' | 'secundario' | 'peligro'
  confirmacionTitulo: string
  confirmacionTexto: string
}

/**
 * Transiciones permitidas.
 *
 * La pantalla anterior usaba un `<select>` abierto con los cinco estados, así
 * que era posible devolver a "pendiente" una venta ya cobrada o saltarse pasos
 * sin que nada lo impidiera. Aquí solo se ofrece lo que tiene sentido hacer a
 * continuación, y el estado final no ofrece nada.
 */
const TRANSICIONES: Record<Estado, Accion[]> = {
  pendiente: [
    {
      estado: 'confirmado',
      rotulo: 'Confirmar',
      icono: PackageCheck,
      variante: 'primario',
      confirmacionTitulo: '¿Confirmar el encargo?',
      confirmacionTexto:
        'El pedido pasa a preparación y queda reservado para el cliente.',
    },
    {
      estado: 'anulado',
      rotulo: 'Anular',
      icono: Ban,
      variante: 'peligro',
      confirmacionTitulo: '¿Anular el encargo?',
      confirmacionTexto:
        'El pedido deja la cola de trabajo pero se conserva en el historial.',
    },
  ],
  confirmado: [
    {
      estado: 'completado',
      rotulo: 'Entregar',
      icono: CheckCheck,
      variante: 'primario',
      confirmacionTitulo: '¿Marcar como entregado?',
      confirmacionTexto:
        'Se da por cerrado: el cliente recibió los productos. Después ya no podrá reabrirse desde esta pantalla.',
    },
    {
      estado: 'anulado',
      rotulo: 'Anular',
      icono: Ban,
      variante: 'peligro',
      confirmacionTitulo: '¿Anular el encargo?',
      confirmacionTexto:
        'El pedido deja la cola de trabajo pero se conserva en el historial.',
    },
  ],
  /* Terminales: una venta cerrada es historial contable, no un registro
     editable. El backend además rechaza borrarla. */
  completado: [],
  anulado: [],
}

const PESTANAS: { origen: Origen; rotulo: string; icono: Component }[] = [
  { origen: 'web', rotulo: 'Portal web', icono: PackageOpen },
  { origen: 'pos', rotulo: 'Mostrador', icono: Store },
]

interface LineaPedido {
  id: number
  cantidad: number
  subtotal: string | number
  producto?: { id: number; nombre: string } | null
}

interface Pedido {
  id: number
  usuario_id: number | null
  origen?: Origen | null
  fecha?: string | null
  fecha_pedido?: string | null
  total: string | number
  estado: Estado
  estado_pago?: string | null
  cliente_nombre?: string | null
  cliente_documento?: string | null
  usuario?: { id: number; nombre: string; email: string } | null
  pedidoDetalles?: LineaPedido[]
}

interface RespuestaPaginada<T> {
  data: T[]
  current_page: number
  last_page: number
  total: number
}

interface Resumen {
  portal: {
    pendientes: { pedidos: number; importe: number }
    confirmados: { pedidos: number; importe: number }
    mas_antiguo_dias: number | null
    abiertos_sin_lineas: number
  }
  mostrador: { hoy: { pedidos: number; importe: number } }
  total_registros: number
}

const RESUMEN_VACIO: Resumen = {
  portal: {
    pendientes: { pedidos: 0, importe: 0 },
    confirmados: { pedidos: 0, importe: 0 },
    mas_antiguo_dias: null,
    abiertos_sin_lineas: 0,
  },
  mostrador: { hoy: { pedidos: 0, importe: 0 } },
  total_registros: 0,
}

const { notifyError, notifySuccess } = useAdminToast()

const pedidos = ref<Pedido[]>([])
const resumen = ref<Resumen>({ ...RESUMEN_VACIO })
const cargando = ref(false)
const cargandoResumen = ref(false)
const guardando = ref<number | null>(null)

const origen = ref<Origen>('web')
const busqueda = ref('')
const filtroEstado = ref<'all' | Estado>('all')
const filtroFecha = ref('')

const paginaActual = ref(1)
const porPagina = ref(10)
const totalPaginas = ref(1)
const totalRegistros = ref(0)

const detalle = ref<Pedido | null>(null)
const confirmacion = ref<{ pedido: Pedido; accion: Accion } | null>(null)

let temporizadorBusqueda: number | undefined

const esPortal = computed(() => origen.value === 'web')
const estadosDelOrigen = computed(() => ESTADOS_POR_ORIGEN[origen.value])

/**
 * La cola dejó de describir trabajo real.
 *
 * Dos señales, cualquiera de ellas basta: encargos abiertos desde hace más de
 * una semana, o encargos sin una sola línea de producto. En los datos actuales
 * se cumplen ambas —el más antiguo es de julio de 2024 y 30 de 42 registros no
 * tienen detalle—, así que presentar esos importes como pendientes del negocio
 * sería inventar una cifra.
 */
const colaEstancada = computed(() => {
  const { mas_antiguo_dias: dias, abiertos_sin_lineas: huecos } = resumen.value.portal
  return (dias !== null && dias >= 7) || huecos > 0
})

const tituloVacio = computed(() =>
  esPortal.value ? 'No hay encargos del portal' : 'No hay ventas de mostrador',
)

const descripcionVacio = computed(() => {
  const hayFiltros = busqueda.value.trim() !== '' || filtroEstado.value !== 'all' || filtroFecha.value !== ''
  if (hayFiltros) return 'Ningún registro coincide con los filtros aplicados.'

  return esPortal.value
    ? 'Cuando un cliente encargue productos desde la web, aparecerán aquí para atenderlos.'
    : 'Las ventas registradas en el punto de venta aparecerán aquí una vez cobradas.'
})

const dinero = (valor: string | number | undefined) =>
  Number(valor ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const folio = (pedido: Pedido) => `#${String(pedido.id).padStart(4, '0')}`

const fecha = (pedido: Pedido) => {
  /* `fecha_pedido` se añadió en una migración posterior a `fecha`, así que los
     registros antiguos solo tienen la segunda. Sin este respaldo la tabla
     mostraba "Invalid Date" en las filas más viejas. */
  const valor = pedido.fecha_pedido ?? pedido.fecha
  if (!valor) return 'Sin fecha'

  const dato = new Date(valor)
  if (Number.isNaN(dato.getTime())) return 'Sin fecha'

  return dato.toLocaleDateString('es-PE', { year: 'numeric', month: 'short', day: 'numeric' })
}

/* En mostrador el cliente casi nunca está registrado como usuario: se guarda
   suelto en `cliente_nombre`, o no se guarda. "Cliente eventual" es el término
   que usa el contador en su registro de ventas. */
const nombreCliente = (pedido: Pedido) =>
  pedido.usuario?.nombre?.trim() || pedido.cliente_nombre?.trim() || 'Cliente eventual'

const contactoCliente = (pedido: Pedido) =>
  pedido.usuario?.email?.trim() || pedido.cliente_documento?.trim() || 'Sin documento'

const resumenProductos = (pedido: Pedido) =>
  (pedido.pedidoDetalles || []).map(linea => linea.producto?.nombre || 'Producto').join(', ') || 'Sin productos'

const accionesDe = (pedido: Pedido): Accion[] => TRANSICIONES[pedido.estado] ?? []

const cargarResumen = async () => {
  cargandoResumen.value = true
  try {
    const { data } = await api.get<{ data: Resumen }>('/pedidos/resumen')
    resumen.value = data.data
  } catch (error) {
    console.error('Error cargando el resumen de pedidos:', error)
    /* Sin resumen la pantalla sigue siendo usable: el listado es la parte
       esencial. Se dejan las cifras en cero antes que mostrar las de la
       página cargada haciéndolas pasar por totales. */
    resumen.value = { ...RESUMEN_VACIO }
  } finally {
    cargandoResumen.value = false
  }
}

const cargarPedidos = async (pagina = paginaActual.value) => {
  cargando.value = true
  try {
    const { data } = await api.get<RespuestaPaginada<Pedido>>('/pedidos', {
      params: {
        paginate: 1,
        page: pagina,
        per_page: porPagina.value,
        origen: origen.value,
        q: busqueda.value.trim() || undefined,
        status: filtroEstado.value,
        date: filtroFecha.value || undefined,
      },
    })
    pedidos.value = data.data
    paginaActual.value = data.current_page
    totalPaginas.value = data.last_page
    totalRegistros.value = data.total
  } catch (error) {
    console.error('Error cargando pedidos:', error)
    notifyError('Error de carga', 'No se pudieron cargar los pedidos.')
  } finally {
    cargando.value = false
  }
}

const recargarTodo = async () => {
  await Promise.all([cargarPedidos(paginaActual.value), cargarResumen()])
}

const irAPagina = async (pagina: number) => {
  if (pagina < 1 || pagina > totalPaginas.value || pagina === paginaActual.value) return
  await cargarPedidos(pagina)
}

const cambiarOrigen = async (nuevo: Origen) => {
  if (origen.value === nuevo) return
  origen.value = nuevo
  /* El filtro de estado puede no existir en el otro origen (buscar
     "pendiente" entre ventas de mostrador no devuelve nada nunca). */
  filtroEstado.value = 'all'
  paginaActual.value = 1
  await cargarPedidos(1)
}

const abrirDetalle = (pedido: Pedido) => {
  detalle.value = pedido
}

const pedirConfirmacion = (pedido: Pedido, accion: Accion) => {
  confirmacion.value = { pedido, accion }
}

/**
 * Aplica el cambio de estado.
 *
 * A diferencia de la versión anterior, la fila NO se modifica antes de que el
 * servidor conteste: allí el `<select>` estaba enlazado con `v-model` al
 * registro, así que la pantalla ya mostraba el estado nuevo aunque la petición
 * fallara, y solo volvía a la realidad tras una recarga completa.
 */
const aplicarAccion = async () => {
  if (!confirmacion.value) return

  const { pedido, accion } = confirmacion.value
  guardando.value = pedido.id

  try {
    await api.put(`/pedidos/${pedido.id}`, { estado: accion.estado })

    pedido.estado = accion.estado
    confirmacion.value = null
    notifySuccess(
      `${ESTADOS[accion.estado].rotulo}`,
      `${esPortal.value ? 'Pedido' : 'Venta'} ${folio(pedido)} actualizado.`,
    )

    /* Las cifras de cabecera cuentan por estado, así que cambian con esto. */
    await cargarResumen()
  } catch (error) {
    console.error('Error actualizando el estado del pedido:', error)
    notifyError('No se pudo actualizar', 'El estado del pedido no cambió.')
    await cargarPedidos(paginaActual.value)
  } finally {
    guardando.value = null
  }
}

watch([filtroEstado, filtroFecha, porPagina], async () => {
  paginaActual.value = 1
  await cargarPedidos(1)
})

watch(busqueda, () => {
  if (temporizadorBusqueda) window.clearTimeout(temporizadorBusqueda)
  temporizadorBusqueda = window.setTimeout(async () => {
    paginaActual.value = 1
    await cargarPedidos(1)
  }, 300)
})

onMounted(recargarTodo)
</script>
