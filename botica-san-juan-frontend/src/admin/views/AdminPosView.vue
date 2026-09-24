<script setup lang="ts">
/**
 * AdminPosView · Punto de venta de mostrador
 * ---------------------------------------------------------------------------
 * DISEÑADA PARA TECLADO, NO PARA RATÓN
 *
 * El sistema actual de la botica (SYSVGES) se opera con F4, F7, F8 sin soltar
 * el teclado, y quien atiende lo hace con un cliente delante. Un punto de
 * venta que obligue a apuntar y hacer clic es más lento que lo que ya tienen,
 * por muy moderno que se vea — y eso bastaría para que dejaran de usarlo.
 *
 * Atajos, calcados de la lógica del sistema anterior:
 *   F2        buscar producto
 *   ↑ ↓       moverse por los resultados
 *   Enter     agregar el producto marcado
 *   1 2 3     elegir presentación mientras se navega (unidad/blíster/caja)
 *   F4        cobrar
 *   F8        datos del cliente
 *   Supr      quitar la línea seleccionada
 *   Esc       cerrar el panel abierto o limpiar la búsqueda
 *
 * El foco vuelve siempre al cuadro de búsqueda después de agregar: la
 * secuencia natural es escribir, Enter, escribir, Enter, sin tocar nada más.
 */

import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import {
  Search, ShoppingCart, Trash2, User, Receipt, TriangleAlert,
  Package, X, CircleCheck, Keyboard, Loader,
} from 'lucide-vue-next'
import {
  usePuntoVenta, MEDIOS_PAGO, daVuelto,
  type ProductoPos, type FormaVenta, type MedioPago, type LineaPago,
} from '../composables/usePuntoVenta'
import { useAdminToast } from '../composables/useAdminToast'
import BoticaButton from '../components/ui/BoticaButton.vue'
import BoticaBadge from '../components/ui/BoticaBadge.vue'
import BoticaEstadoVacio from '../components/ui/BoticaEstadoVacio.vue'

const {
  lineas, resultados, buscando, registrando, cliente, totales,
  buscar, limpiarBusqueda, agregar, cambiarCantidad, quitar, vaciar,
  verificar, registrar,
} = usePuntoVenta()

const { notifyError, notifySuccess } = useAdminToast()

const termino = ref('')
const campoBusqueda = ref<HTMLInputElement | null>(null)
const indiceMarcado = ref(0)
const lineaSeleccionada = ref<string | null>(null)
const panelCliente = ref(false)
const panelAtajos = ref(false)
const confirmacionParcial = ref<null | { faltantes: number; total: number }>(null)
const ultimaVenta = ref<null | { id: number; total: number }>(null)

let temporizador: number | undefined

/* ---------------------------------------------------------------------------
   Estado del cobro
   ---------------------------------------------------------------------------
   El caso corriente en mostrador es efectivo y exacto, así que el panel abre
   ya resuelto para ese caso: basta pulsar Enter. Todo lo demás —cambiar de
   medio, declarar cuánto entregó el cliente, dividir el pago— está a una tecla
   de distancia, pero no estorba a la venta rápida.
*/
const panelCobro = ref(false)
const medioElegido = ref<MedioPago>('efectivo')
const recibido = ref<number | null>(null)
const referenciaPago = ref('')
const campoRecibido = ref<HTMLInputElement | null>(null)

/* Segundo medio, sólo cuando el cliente divide el pago. */
const dividir = ref(false)
const medioSecundario = ref<MedioPago>('yape')
const montoPrimero = ref<number | null>(null)

const vuelto = computed(() => {
  if (!daVuelto(medioElegido.value) || recibido.value === null) return 0
  return Math.round((recibido.value - importePrimero.value) * 100) / 100
})

/** Lo que cubre el primer medio: todo, o la parte declarada si se divide. */
const importePrimero = computed(() => {
  if (!dividir.value) return totales.value.total
  return Math.min(montoPrimero.value ?? 0, totales.value.total)
})

const importeSegundo = computed(
  () => Math.round((totales.value.total - importePrimero.value) * 100) / 100,
)

/** Billetes habituales, para no teclear el importe recibido. */
const atajosEfectivo = computed(() => {
  const t = importePrimero.value
  return [10, 20, 50, 100, 200].filter((b) => b > t).slice(0, 4)
})

const cobroValido = computed(() => {
  if (dividir.value && importePrimero.value <= 0) return false
  if (dividir.value && importeSegundo.value <= 0) return false
  if (daVuelto(medioElegido.value) && recibido.value !== null
      && recibido.value + 0.005 < importePrimero.value) return false
  return true
})

function abrirCobro(): void {
  medioElegido.value = 'efectivo'
  recibido.value = null
  referenciaPago.value = ''
  dividir.value = false
  medioSecundario.value = 'yape'
  montoPrimero.value = null
  panelCobro.value = true

  nextTick(() => campoRecibido.value?.focus())
}

/** Traduce el estado del panel a lo que espera la API. */
function armarPagos(): LineaPago[] {
  const primero: LineaPago = { medio: medioElegido.value }

  if (dividir.value) primero.monto = importePrimero.value

  if (daVuelto(medioElegido.value) && recibido.value !== null) {
    primero.monto_recibido = recibido.value
  }

  if (referenciaPago.value.trim()) primero.referencia = referenciaPago.value.trim()

  if (!dividir.value) return [primero]

  return [primero, { medio: medioSecundario.value, monto: importeSegundo.value }]
}

/* ---------------------------------------------------------------------------
   Búsqueda con rebote
   --------------------------------------------------------------------------- */

watch(termino, (valor) => {
  if (temporizador) window.clearTimeout(temporizador)

  /* 250 ms: por debajo se lanzan peticiones a cada tecla; por encima el
     vendedor percibe que la lista "va detrás" de lo que escribe. */
  temporizador = window.setTimeout(async () => {
    try {
      await buscar(valor)
      indiceMarcado.value = 0
    } catch {
      notifyError('Búsqueda fallida', 'No se pudo consultar el catálogo.')
    }
  }, 250)
})

const productoMarcado = computed<ProductoPos | undefined>(
  () => resultados.value[indiceMarcado.value],
)

/* ---------------------------------------------------------------------------
   Agregar al carrito
   --------------------------------------------------------------------------- */

function formaPorDefecto(producto: ProductoPos): FormaVenta | undefined {
  /* Se prefiere la presentación más pequeña que tenga stock: en mostrador lo
     habitual es vender suelto, y ofrecer una caja que no alcanza obliga a
     corregir. */
  return producto.formas_venta.find((f) => f.disponible) ?? producto.formas_venta[0]
}

function agregarProducto(producto: ProductoPos, forma?: FormaVenta) {
  const elegida = forma ?? formaPorDefecto(producto)

  if (!elegida) {
    notifyError('Sin presentaciones', 'El producto no tiene forma de venta configurada.')
    return
  }

  if (!elegida.disponible) {
    /* No se bloquea: se avisa y se deja continuar. La regla del negocio es
       que decide el vendedor con el cliente delante, no el software. */
    notifyError(
      'Sin stock suficiente',
      `${producto.nombre}: no alcanza para una ${elegida.unidad_venta} completa.`,
    )
  }

  agregar(producto, elegida, 1)
  termino.value = ''
  limpiarBusqueda()
  enfocarBusqueda()
}

function enfocarBusqueda() {
  nextTick(() => campoBusqueda.value?.focus())
}

/* ---------------------------------------------------------------------------
   Cobro
   --------------------------------------------------------------------------- */

/**
 * Primer paso: comprobar stock y, si alcanza, pedir la forma de pago.
 *
 * El stock se verifica ANTES de preguntar cómo paga: no tiene sentido hacer que
 * el cliente elija medio de pago para después avisarle de que falta producto.
 */
async function cobrar(confirmarParcial = false) {
  if (lineas.value.length === 0) {
    notifyError('Venta vacía', 'Agrega al menos un producto.')
    return
  }

  try {
    if (!confirmarParcial) {
      const { hayFaltantes } = await verificar()

      if (hayFaltantes) {
        const faltantes = lineas.value.filter((l) => (l.disponibilidad?.faltante ?? 0) > 0)
        confirmacionParcial.value = {
          faltantes: faltantes.length,
          total: totales.value.total,
        }
        return
      }
    }

    confirmacionParcial.value = null
    abrirCobro()
  } catch (error: unknown) {
    manejarErrorDeVenta(error)
  }
}

/** Segundo paso: registrar la venta con el cobro ya decidido. */
async function confirmarCobro(confirmarParcial = false) {
  if (!cobroValido.value) return

  try {
    const venta = await registrar(confirmarParcial, armarPagos())

    panelCobro.value = false
    confirmacionParcial.value = null
    ultimaVenta.value = { id: venta.id, total: venta.total }

    /* El vuelto se anuncia en el aviso porque es lo que el vendedor necesita
       leer en ese instante, con el cliente esperando delante. */
    const cambio = vuelto.value > 0
      ? ` Vuelto: S/ ${vuelto.value.toFixed(2)}.`
      : ''

    notifySuccess(
      'Venta registrada',
      `Comprobante interno #${venta.id} por S/ ${Number(venta.total).toFixed(2)}.${cambio}`,
    )

    vaciar()
    termino.value = ''
    enfocarBusqueda()
  } catch (error: unknown) {
    manejarErrorDeVenta(error)
  }
}

/**
 * Traduce la respuesta del servidor a algo accionable en el mostrador.
 *
 * El criterio es dónde deja al vendedor cada error: si puede resolverlo
 * confirmando, se le ofrece el diálogo; si tiene que corregir el carrito, se
 * cierran los paneles para que lo vea; si es el cobro lo que no cuadra, el
 * panel de cobro se queda abierto con lo que ya había tecleado.
 */
function manejarErrorDeVenta(error: unknown): void {
  const respuesta = (error as {
    response?: {
      status?: number
      data?: { message?: string; sin_stock?: boolean; error_pago?: boolean }
    }
  }).response

  if (respuesta?.status === 409) {
    panelCobro.value = false
    confirmacionParcial.value = { faltantes: 1, total: totales.value.total }
    return
  }

  /* No había ni una unidad que entregar. El servidor no registró nada, así que
     se cierra todo y se deja el carrito intacto para corregir cantidades en
     lugar de volver a intentarlo a ciegas. */
  if (respuesta?.status === 422 && respuesta.data?.sin_stock) {
    panelCobro.value = false
    confirmacionParcial.value = null
    notifyError(
      'Sin stock disponible',
      respuesta.data.message ?? 'Ninguno de los productos tiene stock para entregar.',
    )
    return
  }

  /* El cobro no cuadra. El panel sigue abierto: lo que hay que corregir está
     ahí mismo, y cerrarlo obligaría a teclearlo todo de nuevo. */
  if (respuesta?.status === 422 && respuesta.data?.error_pago) {
    notifyError('Revisa el cobro', respuesta.data.message ?? 'Los importes no cuadran.')
    return
  }

  notifyError('No se pudo registrar', respuesta?.data?.message ?? 'Error al guardar la venta.')
}

/* ---------------------------------------------------------------------------
   Atajos de teclado
   --------------------------------------------------------------------------- */

function alPulsar(evento: KeyboardEvent) {
  const enCampo = (evento.target as HTMLElement)?.tagName === 'INPUT'
    || (evento.target as HTMLElement)?.tagName === 'SELECT'

  /* Con el cobro abierto el teclado le pertenece: 1-5 eligen medio y Enter
     confirma. Si los atajos generales siguieran activos, teclear el importe
     recibido dispararía la selección de presentación de un producto. */
  if (panelCobro.value) {
    const medio = MEDIOS_PAGO.find((m) => m.atajo === evento.key)

    if (medio && !enCampo) {
      evento.preventDefault()
      medioElegido.value = medio.valor
      return
    }

    if (evento.key === 'Enter') {
      evento.preventDefault()
      confirmarCobro(confirmacionParcial.value !== null)
      return
    }

    if (evento.key === 'Escape') {
      evento.preventDefault()
      panelCobro.value = false
      enfocarBusqueda()
      return
    }

    return
  }

  switch (evento.key) {
    case 'F2':
      evento.preventDefault()
      enfocarBusqueda()
      break

    case 'F4':
      evento.preventDefault()
      cobrar()
      break

    case 'F8':
      evento.preventDefault()
      panelCliente.value = !panelCliente.value
      break

    case 'F1':
      evento.preventDefault()
      panelAtajos.value = !panelAtajos.value
      break

    case 'ArrowDown':
      if (resultados.value.length) {
        evento.preventDefault()
        indiceMarcado.value = Math.min(indiceMarcado.value + 1, resultados.value.length - 1)
      }
      break

    case 'ArrowUp':
      if (resultados.value.length) {
        evento.preventDefault()
        indiceMarcado.value = Math.max(indiceMarcado.value - 1, 0)
      }
      break

    case 'Enter':
      if (productoMarcado.value && resultados.value.length) {
        evento.preventDefault()
        agregarProducto(productoMarcado.value)
      }
      break

    case 'Delete':
      if (!enCampo && lineaSeleccionada.value) {
        evento.preventDefault()
        quitar(lineaSeleccionada.value)
        lineaSeleccionada.value = null
      }
      break

    case 'Escape':
      if (confirmacionParcial.value) confirmacionParcial.value = null
      else if (panelAtajos.value) panelAtajos.value = false
      else if (panelCliente.value) panelCliente.value = false
      else if (resultados.value.length) { termino.value = ''; limpiarBusqueda() }
      break

    /* Elegir presentación con 1/2/3 mientras se navega por los resultados,
       sin salir del teclado numérico. */
    case '1':
    case '2':
    case '3':
      if (!enCampo && productoMarcado.value) {
        const indice = Number(evento.key) - 1
        const forma = productoMarcado.value.formas_venta[indice]
        if (forma) {
          evento.preventDefault()
          agregarProducto(productoMarcado.value, forma)
        }
      }
      break
  }
}

onMounted(() => {
  window.addEventListener('keydown', alPulsar)
  enfocarBusqueda()
})

onUnmounted(() => {
  window.removeEventListener('keydown', alPulsar)
  if (temporizador) window.clearTimeout(temporizador)
})

const soles = (n: number) => `S/ ${n.toFixed(2)}`

const etiquetaForma: Record<string, string> = {
  unidad: 'Unidad',
  blister: 'Blíster',
  caja: 'Caja',
}
</script>

<template>
  <div class="mx-auto flex h-[calc(100dvh-7rem)] max-w-[110rem] flex-col gap-4 lg:flex-row">
    <!-- ================= Columna izquierda: búsqueda y carrito ============ -->
    <section
      class="flex min-h-0 flex-1 flex-col gap-3"
      aria-label="Productos de la venta"
    >
      <!-- Buscador -->
      <div class="relative shrink-0">
        <Search
          class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-texto-terciario"
          aria-hidden="true"
        />
        <input
          ref="campoBusqueda"
          v-model="termino"
          type="search"
          autocomplete="off"
          placeholder="Buscar producto, principio activo o código de barras…   (F2)"
          aria-label="Buscar producto"
          class="h-14 w-full rounded-xl border border-borde-base bg-superficie-elevada pl-11 pr-11 text-base text-texto-primario shadow-xs outline-none transition placeholder:text-texto-terciario focus:border-borde-marca focus:ring-2 focus:ring-botica-500/20"
        >
        <Loader
          v-if="buscando"
          class="absolute right-4 top-1/2 size-4 -translate-y-1/2 animate-spin text-texto-terciario"
          aria-hidden="true"
        />
      </div>

      <!-- Resultados de búsqueda -->
      <div
        v-if="resultados.length"
        class="max-h-72 shrink-0 overflow-y-auto rounded-xl border border-borde-sutil bg-superficie-elevada"
        role="listbox"
        aria-label="Resultados de búsqueda"
      >
        <ul class="divide-y divide-borde-sutil">
          <li
            v-for="(producto, indice) in resultados"
            :key="producto.id"
            role="option"
            :aria-selected="indice === indiceMarcado"
            class="cursor-pointer px-4 py-2.5 transition-colors"
            :class="indice === indiceMarcado ? 'bg-superficie-interactiva-activa' : 'hover:bg-superficie-interactiva'"
            @mouseenter="indiceMarcado = indice"
            @click="agregarProducto(producto)"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate font-medium text-texto-primario">
                  {{ producto.nombre }}
                  <span
                    v-if="producto.concentracion"
                    class="font-normal text-texto-secundario"
                  >{{ producto.concentracion }}</span>
                </p>
                <p class="mt-0.5 truncate text-xs text-texto-terciario">
                  {{ producto.presentacion }}
                  <template v-if="producto.laboratorio"> · {{ producto.laboratorio }}</template>
                </p>
              </div>

              <div class="flex shrink-0 items-center gap-2">
                <BoticaBadge
                  v-if="producto.requiere_receta"
                  tono="vence-medio"
                >
                  Receta
                </BoticaBadge>
                <!-- El tratamiento es del producto: vale igual se venda
                     suelto, en blíster o en caja. -->
                <BoticaBadge
                  v-if="producto.tipo_afectacion_igv !== '10'"
                  tono="neutro"
                >
                  {{ producto.afectacion }}
                </BoticaBadge>
                <BoticaBadge :tono="producto.stock_disponible > 0 ? 'normal' : 'critico'">
                  {{ producto.stock_disponible }} disp.
                </BoticaBadge>
              </div>
            </div>

            <!-- Presentaciones: se eligen con 1, 2, 3 -->
            <div class="mt-2 flex flex-wrap gap-1.5">
              <button
                v-for="(forma, i) in producto.formas_venta"
                :key="forma.unidad_venta"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 text-xs transition-colors"
                :class="forma.disponible
                  ? 'border-borde-base bg-superficie-hundida text-texto-primario hover:border-borde-marca'
                  : 'border-borde-sutil bg-superficie-hundida text-texto-deshabilitado'"
                @click.stop="agregarProducto(producto, forma)"
              >
                <kbd class="rounded bg-superficie-interactiva px-1 font-mono text-2xs">{{ i + 1 }}</kbd>
                {{ etiquetaForma[forma.unidad_venta] }}
                <span class="font-medium">{{ soles(forma.precio) }}</span>
                <span
                  v-if="forma.factor_unidades > 1"
                  class="text-texto-terciario"
                >×{{ forma.factor_unidades }}</span>
              </button>
            </div>
          </li>
        </ul>
      </div>

      <!-- Carrito -->
      <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-borde-sutil bg-superficie-elevada">
        <div class="flex shrink-0 items-center justify-between border-b border-borde-sutil px-4 py-2.5">
          <h2 class="flex items-center gap-2 font-display text-md font-semibold text-texto-primario">
            <ShoppingCart
              class="size-4 text-texto-secundario"
              aria-hidden="true"
            />
            Venta actual
            <span
              v-if="totales.lineas"
              class="cifras-tabulares text-sm font-normal text-texto-terciario"
            >· {{ totales.lineas }} {{ totales.lineas === 1 ? 'línea' : 'líneas' }}</span>
          </h2>
          <BoticaButton
            v-if="lineas.length"
            tamano="sm"
            variante="fantasma"
            :icono="Trash2"
            @click="vaciar()"
          >
            Vaciar
          </BoticaButton>
        </div>

        <BoticaEstadoVacio
          v-if="!lineas.length"
          :icono="Package"
          titulo="Sin productos"
          descripcion="Busca un producto arriba y pulsa Enter para agregarlo."
          compacto
        />

        <ul
          v-else
          class="min-h-0 flex-1 divide-y divide-borde-sutil overflow-y-auto"
        >
          <li
            v-for="linea in lineas"
            :key="linea.clave"
            class="cursor-default px-4 py-3 transition-colors"
            :class="lineaSeleccionada === linea.clave ? 'bg-superficie-interactiva-activa' : 'hover:bg-superficie-interactiva'"
            @click="lineaSeleccionada = linea.clave"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="truncate font-medium text-texto-primario">
                  {{ linea.producto.nombre }}
                </p>
                <p class="mt-0.5 text-xs text-texto-terciario">
                  {{ etiquetaForma[linea.forma.unidad_venta] }} ·
                  {{ soles(linea.forma.precio) }} c/u
                  <template v-if="linea.forma.factor_unidades > 1">
                    · {{ linea.forma.factor_unidades }} unidades
                  </template>
                </p>

                <p
                  v-if="linea.disponibilidad && linea.disponibilidad.faltante > 0"
                  class="mt-1 flex items-center gap-1 text-xs font-medium text-venc-critico"
                >
                  <TriangleAlert
                    class="size-3.5"
                    aria-hidden="true"
                  />
                  Faltan {{ linea.disponibilidad.faltante }} unidades: se entregarán
                  {{ linea.disponibilidad.cantidad_atendible }}
                </p>
              </div>

              <div class="flex shrink-0 items-center gap-2">
                <input
                  :value="linea.cantidad"
                  type="number"
                  min="1"
                  :aria-label="`Cantidad de ${linea.producto.nombre}`"
                  class="cifras-tabulares h-9 w-16 rounded-lg border border-borde-base bg-superficie-hundida px-2 text-center text-sm text-texto-primario outline-none focus:border-borde-marca focus:ring-2 focus:ring-botica-500/20"
                  @input="cambiarCantidad(linea.clave, Number(($event.target as HTMLInputElement).value))"
                >
                <span class="cifras-tabulares w-20 text-right text-sm font-semibold text-texto-primario">
                  {{ soles(linea.forma.precio * linea.cantidad) }}
                </span>
                <BoticaButton
                  tamano="sm"
                  variante="fantasma"
                  :icono="X"
                  :etiqueta-accesible="`Quitar ${linea.producto.nombre}`"
                  @click.stop="quitar(linea.clave)"
                />
              </div>
            </div>
          </li>
        </ul>
      </div>
    </section>

    <!-- ================= Columna derecha: totales y cobro ================= -->
    <aside
      class="flex w-full shrink-0 flex-col gap-3 lg:w-80"
      aria-label="Resumen y cobro"
    >
      <!-- Cliente -->
      <div class="rounded-xl border border-borde-sutil bg-superficie-elevada p-3">
        <button
          type="button"
          class="flex w-full items-center justify-between gap-2 text-left"
          :aria-expanded="panelCliente"
          @click="panelCliente = !panelCliente"
        >
          <span class="flex items-center gap-2 text-sm font-medium text-texto-primario">
            <User
              class="size-4 text-texto-secundario"
              aria-hidden="true"
            />
            {{ cliente.nombre || 'Cliente eventual' }}
          </span>
          <kbd class="rounded bg-superficie-interactiva px-1.5 py-0.5 font-mono text-2xs text-texto-terciario">F8</kbd>
        </button>

        <div
          v-if="panelCliente"
          class="mt-3 space-y-2 border-t border-borde-sutil pt-3"
        >
          <input
            v-model="cliente.nombre"
            type="text"
            placeholder="Nombre o razón social"
            aria-label="Nombre del cliente"
            class="h-9 w-full rounded-lg border border-borde-base bg-superficie-hundida px-2.5 text-sm text-texto-primario outline-none focus:border-borde-marca"
          >
          <div class="flex gap-2">
            <select
              v-model="cliente.tipo_documento"
              aria-label="Tipo de documento"
              class="h-9 w-28 rounded-lg border border-borde-base bg-superficie-hundida px-2 text-sm text-texto-primario outline-none focus:border-borde-marca"
            >
              <option value="sin_documento">
                Ninguno
              </option>
              <option value="dni">
                DNI
              </option>
              <option value="ruc">
                RUC
              </option>
              <option value="ce">
                C.E.
              </option>
            </select>
            <input
              v-model="cliente.documento"
              type="text"
              placeholder="Número"
              aria-label="Número de documento"
              class="h-9 flex-1 rounded-lg border border-borde-base bg-superficie-hundida px-2.5 text-sm text-texto-primario outline-none focus:border-borde-marca"
            >
          </div>
        </div>
      </div>

      <!-- Totales -->
      <div class="flex-1 rounded-xl border border-borde-sutil bg-superficie-elevada p-4">
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between">
            <dt class="text-texto-secundario">
              Valor de venta
            </dt>
            <dd class="cifras-tabulares text-texto-primario">
              {{ soles(totales.base) }}
            </dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-texto-secundario">
              IGV (18 %)
            </dt>
            <dd class="cifras-tabulares text-texto-primario">
              {{ soles(totales.igv) }}
            </dd>
          </div>
          <div
            v-if="totales.exonerado > 0"
            class="flex justify-between"
          >
            <dt class="text-texto-secundario">
              Exonerado de IGV
            </dt>
            <dd class="cifras-tabulares text-texto-primario">
              {{ soles(totales.exonerado) }}
            </dd>
          </div>
          <div
            v-if="totales.inafecto > 0"
            class="flex justify-between"
          >
            <dt class="text-texto-secundario">
              Inafecto
            </dt>
            <dd class="cifras-tabulares text-texto-primario">
              {{ soles(totales.inafecto) }}
            </dd>
          </div>

          <div class="flex items-baseline justify-between border-t border-borde-base pt-3">
            <dt class="font-display text-md font-semibold text-texto-primario">
              Total
            </dt>
            <dd class="cifras-tabulares font-display text-2xl font-semibold text-botica-700 dark:text-botica-300">
              {{ soles(totales.total) }}
            </dd>
          </div>
        </dl>

        <p
          v-if="totales.unidades"
          class="mt-2 text-xs text-texto-terciario"
        >
          {{ totales.unidades }} unidades en total
        </p>
      </div>

      <!-- Cobrar -->
      <div class="shrink-0 space-y-2">
        <button
          type="button"
          class="flex h-14 w-full items-center justify-center gap-2 rounded-xl bg-botica-700 text-base font-semibold text-white shadow-sm transition-colors hover:bg-botica-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco disabled:cursor-not-allowed disabled:opacity-55"
          :disabled="!lineas.length || registrando"
          @click="cobrar()"
        >
          <Receipt
            class="size-5"
            aria-hidden="true"
          />
          {{ registrando ? 'Registrando…' : 'Cobrar' }}
          <kbd class="ml-1 rounded bg-superficie-elevada/20 px-1.5 py-0.5 font-mono text-xs">F4</kbd>
        </button>

        <button
          type="button"
          class="flex w-full items-center justify-center gap-1.5 text-xs text-texto-terciario transition-colors hover:text-texto-secundario"
          @click="panelAtajos = !panelAtajos"
        >
          <Keyboard
            class="size-3.5"
            aria-hidden="true"
          />
          Atajos de teclado (F1)
        </button>
      </div>

      <!-- Última venta -->
      <div
        v-if="ultimaVenta"
        class="flex shrink-0 items-center gap-2 rounded-xl border border-exito-500/30 bg-exito-50 px-3 py-2 text-sm dark:bg-exito-500/10"
      >
        <CircleCheck
          class="size-4 shrink-0 text-exito-600"
          aria-hidden="true"
        />
        <span class="text-texto-primario">
          Última venta #{{ ultimaVenta.id }} · {{ soles(Number(ultimaVenta.total)) }}
        </span>
      </div>
    </aside>

    <!-- ================= Confirmación de venta parcial ==================== -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-150"
        leave-to-class="opacity-0"
      >
        <div
          v-if="confirmacionParcial"
          class="fixed inset-0 z-50 flex items-center justify-center bg-neutro-950/60 p-4 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          aria-labelledby="titulo-parcial"
          @click.self="confirmacionParcial = null"
        >
          <div class="w-full max-w-md rounded-2xl border border-borde-sutil bg-superficie-flotante p-5 shadow-2xl">
            <div class="flex items-start gap-3">
              <span
                class="grid size-10 shrink-0 place-items-center rounded-xl bg-alerta-50 text-alerta-700 dark:bg-alerta-500/15 dark:text-alerta-500"
                aria-hidden="true"
              >
                <TriangleAlert class="size-5" />
              </span>
              <div>
                <h2
                  id="titulo-parcial"
                  class="font-display text-lg font-semibold text-texto-primario"
                >
                  No hay stock para todo
                </h2>
                <p class="mt-1 text-sm text-texto-secundario">
                  Hay {{ confirmacionParcial.faltantes }}
                  {{ confirmacionParcial.faltantes === 1 ? 'producto' : 'productos' }}
                  con menos existencias de las pedidas.
                </p>
              </div>
            </div>

            <ul class="mt-4 space-y-1.5 rounded-lg bg-superficie-hundida p-3 text-sm">
              <li
                v-for="linea in lineas.filter(l => (l.disponibilidad?.faltante ?? 0) > 0)"
                :key="linea.clave"
                class="flex justify-between gap-3"
              >
                <span class="truncate text-texto-primario">{{ linea.producto.nombre }}</span>
                <span class="cifras-tabulares shrink-0 text-texto-secundario">
                  se entregan {{ linea.disponibilidad?.cantidad_atendible }}
                </span>
              </li>
            </ul>

            <p class="mt-3 text-xs text-texto-terciario">
              Si el cliente acepta, la venta se registra por lo que se entrega y queda
              anotado el faltante para reponer.
            </p>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <BoticaButton @click="confirmacionParcial = null">
                Cancelar
              </BoticaButton>
              <BoticaButton
                variante="primario"
                :cargando="registrando"
                @click="cobrar(true)"
              >
                Cobrar lo disponible
              </BoticaButton>
            </div>
          </div>
        </div>
      </Transition>

      <!-- ================= Panel de atajos =============================== -->
      <Transition
        enter-active-class="transition duration-150"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="panelAtajos"
          class="fixed inset-0 z-50 flex items-center justify-center bg-neutro-950/60 p-4 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          @click.self="panelAtajos = false"
        >
          <div class="w-full max-w-sm rounded-2xl border border-borde-sutil bg-superficie-flotante p-5 shadow-2xl">
            <h2 class="font-display text-lg font-semibold text-texto-primario">
              Atajos de teclado
            </h2>
            <p class="mt-1 text-xs text-texto-secundario">
              Pensados para atender sin soltar el teclado.
            </p>
            <dl class="mt-4 space-y-2 text-sm">
              <div
                v-for="atajo in [
                  ['F2', 'Ir al buscador'],
                  ['↑ ↓', 'Moverse por los resultados'],
                  ['Enter', 'Agregar el producto marcado'],
                  ['1 2 3', 'Elegir presentación'],
                  ['F4', 'Cobrar'],
                  ['F8', 'Datos del cliente'],
                  ['Supr', 'Quitar la línea seleccionada'],
                  ['Esc', 'Cerrar o limpiar'],
                ]"
                :key="atajo[0]"
                class="flex items-center justify-between gap-3"
              >
                <dt>
                  <kbd class="rounded border border-borde-base bg-superficie-hundida px-2 py-0.5 font-mono text-xs text-texto-primario">{{ atajo[0] }}</kbd>
                </dt>
                <dd class="text-texto-secundario">
                  {{ atajo[1] }}
                </dd>
              </div>
            </dl>
            <BoticaButton
              class="mt-5 w-full"
              @click="panelAtajos = false"
            >
              Cerrar
            </BoticaButton>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ========================= Cobro ==================================== -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-150"
        leave-to-class="opacity-0"
      >
        <div
          v-if="panelCobro"
          class="fixed inset-0 z-50 flex items-center justify-center bg-neutro-950/60 p-4 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          aria-labelledby="titulo-cobro"
          @click.self="panelCobro = false"
        >
          <div class="w-full max-w-lg rounded-2xl border border-borde-sutil bg-superficie-flotante p-5 shadow-2xl">
            <div class="flex items-baseline justify-between gap-4">
              <h2
                id="titulo-cobro"
                class="font-display text-lg font-semibold text-texto-primario"
              >
                Cobrar
              </h2>
              <p class="font-display text-3xl font-semibold tabular-nums text-botica-700 dark:text-botica-500">
                S/ {{ totales.total.toFixed(2) }}
              </p>
            </div>

            <!-- Medio de pago -->
            <fieldset class="mt-4">
              <legend class="text-xs font-medium uppercase tracking-wide text-texto-terciario">
                Medio de pago
              </legend>

              <div class="mt-2 flex flex-wrap gap-2">
                <button
                  v-for="medio in MEDIOS_PAGO"
                  :key="medio.valor"
                  type="button"
                  class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
                  :class="medioElegido === medio.valor
                    ? 'border-botica-700 bg-botica-700 text-white'
                    : 'border-borde-base bg-superficie-elevada text-texto-primario hover:bg-superficie-interactiva'"
                  :aria-pressed="medioElegido === medio.valor"
                  @click="medioElegido = medio.valor"
                >
                  {{ medio.etiqueta }}
                  <kbd
                    class="rounded px-1 text-[10px] font-semibold"
                    :class="medioElegido === medio.valor ? 'bg-superficie-elevada/20' : 'bg-superficie-hundida text-texto-terciario'"
                  >{{ medio.atajo }}</kbd>
                </button>
              </div>
            </fieldset>

            <!-- Efectivo: cuánto entregó el cliente -->
            <div v-if="daVuelto(medioElegido)" class="mt-4">
              <label
                for="cobro-recibido"
                class="text-xs font-medium uppercase tracking-wide text-texto-terciario"
              >
                Recibido
              </label>

              <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <input
                  id="cobro-recibido"
                  ref="campoRecibido"
                  v-model.number="recibido"
                  type="number"
                  step="0.10"
                  min="0"
                  :placeholder="importePrimero.toFixed(2)"
                  class="h-11 w-36 rounded-lg border border-borde-base bg-superficie-elevada px-3 text-right font-display text-lg tabular-nums text-texto-primario focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
                >

                <button
                  v-for="billete in atajosEfectivo"
                  :key="billete"
                  type="button"
                  class="h-11 rounded-lg border border-borde-base bg-superficie-elevada px-3 text-sm font-medium tabular-nums text-texto-primario transition-colors hover:bg-superficie-interactiva"
                  @click="recibido = billete"
                >
                  {{ billete }}
                </button>
              </div>

              <p class="mt-1 text-xs text-texto-terciario">
                Déjalo vacío si paga con el importe exacto.
              </p>
            </div>

            <!-- Tarjeta / billetera: referencia para conciliar -->
            <div v-else class="mt-4">
              <label
                for="cobro-referencia"
                class="text-xs font-medium uppercase tracking-wide text-texto-terciario"
              >
                Referencia <span class="normal-case text-texto-terciario">(opcional)</span>
              </label>

              <input
                id="cobro-referencia"
                v-model="referenciaPago"
                type="text"
                maxlength="60"
                :placeholder="medioElegido === 'tarjeta' ? 'Últimos 4 dígitos' : 'Código de operación'"
                class="mt-1.5 h-11 w-full rounded-lg border border-borde-base bg-superficie-elevada px-3 text-sm text-texto-primario focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
              >
            </div>

            <!-- Pago dividido -->
            <div class="mt-4 rounded-xl border border-borde-sutil bg-superficie-hundida p-3">
              <label class="flex items-center gap-2 text-sm font-medium text-texto-primario">
                <input v-model="dividir" type="checkbox" class="size-4 accent-botica-700">
                El cliente divide el pago
              </label>

              <div v-if="dividir" class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                  <label for="cobro-monto-1" class="text-xs text-texto-terciario">
                    Con {{ MEDIOS_PAGO.find((m) => m.valor === medioElegido)?.etiqueta }}
                  </label>
                  <input
                    id="cobro-monto-1"
                    v-model.number="montoPrimero"
                    type="number"
                    step="0.10"
                    min="0"
                    :max="totales.total"
                    class="mt-1 h-10 w-full rounded-lg border border-borde-base bg-superficie-elevada px-3 text-right tabular-nums text-texto-primario"
                  >
                </div>

                <div>
                  <label for="cobro-medio-2" class="text-xs text-texto-terciario">
                    Resto: S/ {{ importeSegundo.toFixed(2) }} con
                  </label>
                  <select
                    id="cobro-medio-2"
                    v-model="medioSecundario"
                    class="mt-1 h-10 w-full rounded-lg border border-borde-base bg-superficie-elevada px-2 text-sm text-texto-primario"
                  >
                    <option
                      v-for="medio in MEDIOS_PAGO.filter((m) => m.valor !== medioElegido)"
                      :key="medio.valor"
                      :value="medio.valor"
                    >
                      {{ medio.etiqueta }}
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Vuelto: la cifra que el vendedor necesita leer de un vistazo -->
            <div
              v-if="daVuelto(medioElegido) && recibido !== null && recibido > 0"
              class="mt-4 flex items-baseline justify-between rounded-xl px-4 py-3"
              :class="vuelto < 0
                ? 'bg-peligro-50 dark:bg-peligro-500/10'
                : 'bg-botica-50 dark:bg-botica-500/10'"
            >
              <span class="text-sm font-medium text-texto-secundario">
                {{ vuelto < 0 ? 'Falta' : 'Vuelto' }}
              </span>
              <span
                class="font-display text-2xl font-semibold tabular-nums"
                :class="vuelto < 0
                  ? 'text-peligro-700 dark:text-peligro-500'
                  : 'text-botica-700 dark:text-botica-500'"
              >
                S/ {{ Math.abs(vuelto).toFixed(2) }}
              </span>
            </div>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <BoticaButton @click="panelCobro = false">
                Cancelar
              </BoticaButton>
              <BoticaButton
                variante="primario"
                :cargando="registrando"
                :disabled="!cobroValido"
                @click="confirmarCobro(confirmacionParcial !== null)"
              >
                Confirmar venta
              </BoticaButton>
            </div>

            <p class="mt-3 text-center text-xs text-texto-terciario">
              Enter confirma · 1-5 eligen medio · Esc cancela
            </p>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
