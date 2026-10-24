<!--
  Ticket interno de contingencia.

  ES UN PAPEL DE CONTROL, NO UN COMPROBANTE DE PAGO

  El documento lo dice en su propia cara, en grande y arriba. No es un
  descargo de responsabilidad metido en letra pequeña: es la información que
  el cliente necesita para no creer que se lleva una boleta, y la que el
  vendedor necesita para saber qué explicar.

  POR QUÉ SE IMPRIME CON CSS Y NO SE GENERA UN PDF

  Sin conexión no hay servidor que genere nada, y meter una librería de PDF en
  el paquete para esto engordaría el instalador de Tauri por un documento de
  quince líneas. Una hoja de impresión con `@media print` sale por la
  impresora de tickets igual de bien y no pesa nada.

  El ancho está fijado en 72 mm porque es el área imprimible de una impresora
  térmica de 80 mm, que es la que hay en el mostrador.
-->
<template>
  <div class="ticket-contingencia mx-auto max-w-[76mm] rounded-lg border border-borde-base bg-white p-4 font-mono text-[11px] leading-snug text-neutro-900">
    <header class="text-center">
      <p class="text-sm font-bold uppercase tracking-wide">Botica San Juan</p>
      <p class="mt-2 border-y border-dashed border-neutro-400 py-1 text-[10px] font-bold uppercase">
        Nota de venta interna<br>
        No es comprobante de pago
      </p>
    </header>

    <dl class="mt-3 space-y-0.5">
      <div class="flex justify-between">
        <dt>Documento</dt>
        <dd class="font-bold">{{ venta.nota_contingencia }}</dd>
      </div>
      <div class="flex justify-between">
        <dt>Fecha</dt>
        <dd>{{ fechaLegible }}</dd>
      </div>
      <div class="flex justify-between">
        <dt>Atendido por</dt>
        <dd>{{ vendedor || '—' }}</dd>
      </div>
      <div v-if="venta.cliente.nombre" class="flex justify-between">
        <dt>Cliente</dt>
        <dd>{{ venta.cliente.nombre }}</dd>
      </div>
    </dl>

    <table class="mt-3 w-full border-t border-dashed border-neutro-400 pt-2">
      <thead>
        <tr class="text-left text-[10px] uppercase">
          <th scope="col" class="pb-1 font-normal">Producto</th>
          <th scope="col" class="pb-1 text-right font-normal">Cant.</th>
          <th scope="col" class="pb-1 text-right font-normal">Importe</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(linea, i) in venta.items" :key="i" class="align-top">
          <td class="py-0.5 pr-2">
            {{ linea.nombre }}
            <span v-if="linea.unidad_venta !== 'unidad'" class="text-[10px]">
              ({{ linea.unidad_venta }})
            </span>
          </td>
          <td class="py-0.5 text-right">{{ linea.cantidad }}</td>
          <td class="py-0.5 text-right">{{ soles(linea.precio_unitario * linea.cantidad) }}</td>
        </tr>
      </tbody>
    </table>

    <p class="mt-2 flex justify-between border-t border-dashed border-neutro-400 pt-2 text-sm font-bold">
      <span>TOTAL</span>
      <span>S/ {{ soles(venta.total) }}</span>
    </p>

    <p v-if="medioDePago" class="mt-1 flex justify-between">
      <span>Pago</span>
      <span>{{ medioDePago }}</span>
    </p>

    <footer class="mt-3 border-t border-dashed border-neutro-400 pt-2 text-center text-[10px]">
      <p class="font-bold">Comprobante pendiente de emisión</p>
      <p class="mt-1">
        La boleta o factura se emitirá al restablecerse la conexión.
        Conserve este documento para recogerla.
      </p>
      <p class="mt-2 break-all text-[8px] text-neutro-500">{{ venta.uuid_local }}</p>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { VentaLocal } from '@/offline/tipos'

const props = defineProps<{
  venta: VentaLocal
  vendedor?: string | null
}>()

const fechaLegible = computed(() =>
  new Date(props.venta.registrada_en).toLocaleString('es-PE', {
    dateStyle: 'short',
    timeStyle: 'short',
  }),
)

const medioDePago = computed(() => {
  const primero = props.venta.pagos[0]
  if (!primero) return null
  const resto = props.venta.pagos.length - 1
  return resto > 0 ? `${primero.medio} +${resto}` : primero.medio
})

function soles(valor: number): string {
  return valor.toFixed(2)
}
</script>

<style>
/* La hoja de impresión vive fuera de `scoped` a propósito: `@page` es una
   regla de documento y el atributo de ámbito que Vue inyecta la dejaría sin
   efecto. */
@media print {
  body * {
    visibility: hidden;
  }

  .ticket-contingencia,
  .ticket-contingencia * {
    visibility: visible;
  }

  .ticket-contingencia {
    position: absolute;
    inset: 0 auto auto 0;
    width: 72mm;
    max-width: 72mm;
    border: 0;
    padding: 0;
    color: #000;
  }

  @page {
    margin: 4mm;
  }
}
</style>
