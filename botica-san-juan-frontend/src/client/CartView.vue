<!--
  Carrito del portal.

  ESTA PANTALLA NO EXISTÍA.

  El icono del carrito de la cabecera enlazaba a `/cart` desde el principio,
  pero esa ruta nunca se declaró: pulsarlo llevaba a la pantalla de "página no
  encontrada". Es el mismo fallo que ya tuvo el enlace de Productos, y por el
  mismo motivo: el enlace se escribió antes que la vista y nadie volvió.

  TRES DECISIONES QUE SE VEN EN PANTALLA

  1. **La cantidad no puede pasar del stock vendible.** El selector se topa solo
     y el botón de subir se deshabilita al llegar al límite, con el motivo
     escrito al lado. Pero el tope de aquí es cortesía: el servidor vuelve a
     aplicarlo al cotizar, porque este código corre en la máquina del visitante.

  2. **El desglose dice la verdad sobre el IGV.** En Perú el precio de mostrador
     ya lleva el impuesto dentro, así que el IGV se EXTRAE del total, no se suma
     encima. Por eso el subtotal que se muestra es menor que la suma de los
     precios de las fichas: la diferencia es exactamente el impuesto. Si se
     sumara un 18 % al precio visible, el total no coincidiría con lo que se
     paga en el mostrador.

  3. **La receta se avisa antes de salir, no después.** Un medicamento de venta
     bajo receta no se puede entregar sin ella; enterarse al recoger el pedido
     es un viaje perdido.
-->
<template>
  <div class="min-h-screen bg-superficie-base">
    <Header />

    <!-- pt-20: la cabecera del portal es pegajosa y se superpone al contenido.
         Sin este margen la miga de pan queda medio tapada por ella, que es como
         salió la primera captura de esta pantalla. -->
    <main class="mx-auto max-w-6xl px-4 pb-10 pt-20 sm:px-6 lg:pb-14">
      <nav aria-label="Ruta de navegación" class="mb-6 text-sm text-texto-secundario">
        <RouterLink to="/" class="foco-dentro rounded hover:text-texto-marca">Inicio</RouterLink>
        <span class="mx-2" aria-hidden="true">/</span>
        <RouterLink to="/products" class="foco-dentro rounded hover:text-texto-marca">Productos</RouterLink>
        <span class="mx-2" aria-hidden="true">/</span>
        <span class="text-texto-primario">Carrito</span>
      </nav>

      <header class="mb-8">
        <h1 class="text-3xl font-bold text-texto-primario sm:text-4xl">Tu carrito</h1>
        <p class="mt-2 text-texto-secundario" role="status">
          <template v-if="carrito.cargando && carrito.lineas.length === 0">
            Consultando precios y stock…
          </template>
          <template v-else-if="carrito.totalUnidades > 0">
            {{ carrito.totalUnidades }}
            {{ carrito.totalUnidades === 1 ? 'unidad' : 'unidades' }}
            de {{ carrito.lineas.length }}
            {{ carrito.lineas.length === 1 ? 'producto' : 'productos' }}
          </template>
          <template v-else>Todavía no has agregado nada.</template>
        </p>
      </header>

      <!-- Avisos de ajuste. Van arriba y con role=alert porque el carrito ya
           cambió: cambiar una cantidad en silencio es peor que no poder
           servirla. -->
      <div
        v-if="carrito.ajustes.length > 0"
        class="mb-6 rounded-2xl border border-alerta-500/40 bg-alerta-50 p-4 dark:bg-alerta-500/10"
        role="alert"
      >
        <div class="flex items-start gap-3">
          <AlertTriangleIcon class="mt-0.5 size-5 shrink-0 text-alerta-700 dark:text-alerta-500" aria-hidden="true" />
          <div class="flex-1">
            <h2 class="font-semibold text-alerta-700 dark:text-alerta-500">
              Ajustamos tu carrito
            </h2>
            <ul class="mt-2 space-y-1 text-sm text-texto-primario">
              <li v-for="ajuste in carrito.ajustes" :key="`${ajuste.producto_id}-${ajuste.motivo}`">
                {{ ajuste.mensaje }}
              </li>
            </ul>
          </div>
          <button
            type="button"
            class="foco-dentro rounded-lg p-1 text-texto-secundario hover:text-texto-primario"
            aria-label="Descartar avisos"
            @click="carrito.descartarAjustes()"
          >
            <XIcon class="size-4" aria-hidden="true" />
          </button>
        </div>
      </div>

      <!-- Error de red: distinto de carrito vacío, y con salida. -->
      <div
        v-if="carrito.error"
        class="mb-6 flex items-start gap-3 rounded-2xl border border-peligro-500/40 bg-peligro-50 p-4 dark:bg-peligro-500/10"
        role="alert"
      >
        <AlertCircleIcon class="mt-0.5 size-5 shrink-0 text-peligro-600" aria-hidden="true" />
        <div class="flex-1">
          <p class="text-sm text-texto-primario">{{ carrito.error }}</p>
          <button
            type="button"
            class="foco-dentro mt-2 rounded-lg bg-peligro-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-peligro-700"
            @click="carrito.cotizar()"
          >
            Reintentar
          </button>
        </div>
      </div>

      <!-- Esqueleto mientras llega la primera cotización. Reserva el alto de
           las líneas para que el resumen y el pie no salten cuando lleguen. -->
      <div v-if="!carrito.cotizadoAlgunaVez && !carrito.vacio" class="grid gap-8 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-4" aria-hidden="true">
          <div
            v-for="n in carrito.guardadas.length"
            :key="`esqueleto-${n}`"
            class="h-32 animate-pulse rounded-2xl border border-borde-sutil bg-superficie-elevada"
          />
        </div>
        <div class="h-80 animate-pulse rounded-2xl border border-borde-sutil bg-superficie-elevada" aria-hidden="true" />
      </div>

      <!-- Carrito vacío -->
      <div
        v-else-if="carrito.vacio"
        class="rounded-2xl border border-borde-sutil bg-superficie-elevada px-6 py-16 text-center"
      >
        <ShoppingCartIcon class="mx-auto size-12 text-texto-terciario" aria-hidden="true" />
        <h2 class="mt-4 text-xl font-semibold text-texto-primario">Tu carrito está vacío</h2>
        <p class="mx-auto mt-2 max-w-md text-texto-secundario">
          Busca en el catálogo lo que necesitas y agrégalo desde la ficha del producto.
        </p>
        <RouterLink
          to="/products"
          class="foco-dentro mt-6 inline-flex items-center gap-2 rounded-xl bg-botica-600 px-5 py-2.5 font-medium text-white transition-colors hover:bg-botica-700"
        >
          <SearchIcon class="size-4" aria-hidden="true" />
          Ver el catálogo
        </RouterLink>
      </div>

      <div v-else class="grid gap-8 lg:grid-cols-[1fr_22rem] lg:items-start">
        <!-- Líneas -->
        <ul class="space-y-4">
          <li
            v-for="linea in carrito.lineas"
            :key="linea.producto_id"
            class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-4"
          >
            <div class="flex gap-4">
              <!-- El respaldo es un elemento HERMANO que se pinta siempre y
                   queda debajo, no un v-else. Con un v-else, al fallar la carga
                   el <img> desaparece y no aparece nada: queda un hueco gris.
                   Está escrito en utils/media.ts y aquí se repitió igual — la
                   primera captura de esta pantalla salió con dos imágenes rotas
                   enseñando el texto alternativo. -->
              <div
                class="relative flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-superficie-hundida"
              >
                <img
                  :src="ilustracionDe(linea.tipo)"
                  :alt="textoAlternativoDe(linea.tipo)"
                  width="48"
                  height="48"
                  loading="lazy"
                  decoding="async"
                  class="size-12 object-contain"
                />
                <img
                  v-if="muestraImagen(linea)"
                  :src="urlDeMedia(linea.imagen) ?? undefined"
                  :alt="linea.nombre"
                  width="80"
                  height="80"
                  loading="lazy"
                  decoding="async"
                  class="absolute inset-0 size-full object-cover"
                  @error="marcarImagenRota(linea.producto_id)"
                />
              </div>

              <div class="min-w-0 flex-1">
                <h3 class="truncate font-semibold text-texto-primario">{{ linea.nombre }}</h3>
                <p class="mt-0.5 text-sm text-texto-marca">
                  {{ linea.concentracion || '—' }}
                </p>
                <p class="mt-0.5 truncate text-xs text-texto-secundario">
                  {{ [linea.presentacion, linea.laboratorio].filter(Boolean).join(' · ') }}
                </p>

                <!-- Venta bajo receta. Va en la línea, no sólo en el resumen:
                     quien lleva cinco productos necesita saber CUÁL es. -->
                <p
                  v-if="linea.requiere_receta"
                  class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-alerta-50 px-2 py-1 text-xs font-medium text-alerta-700 dark:bg-alerta-500/10 dark:text-alerta-500"
                >
                  <FileTextIcon class="size-3.5" aria-hidden="true" />
                  Requiere receta médica
                </p>
              </div>

              <button
                type="button"
                class="foco-dentro h-fit rounded-lg p-2 text-texto-terciario transition-colors hover:bg-peligro-50 hover:text-peligro-600 dark:hover:bg-peligro-500/10"
                :aria-label="`Quitar ${linea.nombre} del carrito`"
                @click="carrito.quitar(linea.producto_id)"
              >
                <Trash2Icon class="size-4" aria-hidden="true" />
              </button>
            </div>

            <div class="mt-4 flex flex-wrap items-end justify-between gap-4 border-t border-borde-sutil pt-4">
              <div>
                <label
                  :for="`cantidad-${linea.producto_id}`"
                  class="block text-xs font-medium text-texto-secundario"
                >
                  Cantidad
                </label>
                <div class="mt-1 flex items-center gap-1">
                  <button
                    type="button"
                    class="foco-dentro flex size-9 items-center justify-center rounded-lg border border-borde-control text-texto-primario transition-colors hover:bg-superficie-interactiva disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="linea.cantidad <= 1"
                    :aria-label="`Quitar una unidad de ${linea.nombre}`"
                    @click="cambiar(linea, linea.cantidad - 1)"
                  >
                    <MinusIcon class="size-4" aria-hidden="true" />
                  </button>

                  <input
                    :id="`cantidad-${linea.producto_id}`"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    :max="linea.stock_disponible"
                    :value="linea.cantidad"
                    class="foco-dentro h-9 w-16 rounded-lg border border-borde-control bg-superficie-base text-center text-sm text-texto-primario"
                    :aria-describedby="`tope-${linea.producto_id}`"
                    @change="cambiarDesdeCampo(linea, $event)"
                  />

                  <button
                    type="button"
                    class="foco-dentro flex size-9 items-center justify-center rounded-lg border border-borde-control text-texto-primario transition-colors hover:bg-superficie-interactiva disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="linea.cantidad >= linea.stock_disponible"
                    :aria-label="`Agregar una unidad de ${linea.nombre}`"
                    @click="cambiar(linea, linea.cantidad + 1)"
                  >
                    <PlusIcon class="size-4" aria-hidden="true" />
                  </button>
                </div>

                <!-- El motivo del tope, escrito. Un botón deshabilitado sin
                     explicación se lee como una pantalla rota. -->
                <p :id="`tope-${linea.producto_id}`" class="mt-1 h-4 text-xs text-texto-secundario">
                  <template v-if="linea.cantidad >= linea.stock_disponible">
                    Máximo disponible: {{ linea.stock_disponible }}
                  </template>
                  <template v-else-if="linea.stock_disponible <= 5">
                    Quedan {{ linea.stock_disponible }}
                  </template>
                </p>
              </div>

              <div class="text-right">
                <p class="text-xs text-texto-secundario">
                  {{ soles(linea.precio) }} c/u
                </p>
                <p class="text-lg font-semibold text-texto-primario">
                  {{ soles(linea.subtotal) }}
                </p>
              </div>
            </div>
          </li>
        </ul>

        <!-- Resumen -->
        <aside class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-6 lg:sticky lg:top-24">
          <h2 class="text-lg font-semibold text-texto-primario">Resumen</h2>

          <dl class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between">
              <dt class="text-texto-secundario">Subtotal gravado</dt>
              <dd class="font-medium text-texto-primario">{{ soles(carrito.desglose.subtotal_gravado) }}</dd>
            </div>

            <div v-if="carrito.desglose.subtotal_exonerado > 0" class="flex justify-between">
              <dt class="text-texto-secundario">Exonerado de IGV</dt>
              <dd class="font-medium text-texto-primario">{{ soles(carrito.desglose.subtotal_exonerado) }}</dd>
            </div>

            <div v-if="carrito.desglose.subtotal_inafecto > 0" class="flex justify-between">
              <dt class="text-texto-secundario">Inafecto</dt>
              <dd class="font-medium text-texto-primario">{{ soles(carrito.desglose.subtotal_inafecto) }}</dd>
            </div>

            <div class="flex justify-between">
              <dt class="text-texto-secundario">
                IGV ({{ (carrito.desglose.tasa_igv * 100).toFixed(0) }} %)
              </dt>
              <dd class="font-medium text-texto-primario">{{ soles(carrito.desglose.igv) }}</dd>
            </div>

            <div class="flex justify-between border-t border-borde-sutil pt-3">
              <dt class="font-semibold text-texto-primario">Total</dt>
              <dd class="text-xl font-bold text-texto-primario">{{ soles(carrito.desglose.total) }}</dd>
            </div>
          </dl>

          <p class="mt-3 text-xs text-texto-secundario">
            Los precios ya incluyen IGV. El impuesto se muestra desglosado, no se
            suma al total.
          </p>

          <!-- Aviso de receta a nivel de pedido -->
          <div
            v-if="carrito.requiereReceta"
            class="mt-5 rounded-xl bg-alerta-50 p-3 dark:bg-alerta-500/10"
          >
            <p class="flex items-start gap-2 text-xs text-alerta-700 dark:text-alerta-500">
              <FileTextIcon class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
              <span>
                Tu pedido incluye productos de <strong>venta bajo receta médica</strong>.
                Deberás presentar la receta al recogerlo o al recibirlo; sin ella
                no podemos entregarlos.
              </span>
            </p>
          </div>

          <!-- Salida del pedido -->
          <a
            v-if="enlacePedido"
            :href="enlacePedido"
            target="_blank"
            rel="noopener noreferrer"
            class="foco-dentro mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-botica-600 px-4 py-3 font-semibold text-white transition-colors hover:bg-botica-700"
          >
            <MessageSquareIcon class="size-5" aria-hidden="true" />
            Enviar pedido por WhatsApp
          </a>

          <!-- Si el número no está configurado no se enlaza a uno inventado:
               más vale una vía de contacto menos que una que no contesta. -->
          <p
            v-else
            class="mt-6 rounded-xl border border-borde-sutil bg-superficie-hundida p-3 text-xs text-texto-secundario"
          >
            El envío por WhatsApp aún no está habilitado. Puedes copiar tu pedido
            y enviarlo por el canal que prefieras, o llamar al
            <a :href="`tel:${TELEFONO_FIJO_E164}`" class="foco-dentro rounded font-medium text-texto-marca underline">
              {{ TELEFONO_FIJO }}</a>.
          </p>

          <button
            type="button"
            class="foco-dentro mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-borde-control px-4 py-2.5 text-sm font-medium text-texto-primario transition-colors hover:bg-superficie-interactiva"
            @click="copiarPedido"
          >
            <component :is="copiado ? CheckIcon : CopyIcon" class="size-4" aria-hidden="true" />
            {{ copiado ? 'Pedido copiado' : 'Copiar pedido' }}
          </button>

          <div class="mt-4 flex items-center justify-between text-sm">
            <RouterLink
              to="/products"
              class="foco-dentro rounded font-medium text-texto-marca hover:underline"
            >
              Seguir comprando
            </RouterLink>
            <button
              type="button"
              class="foco-dentro rounded text-texto-secundario hover:text-peligro-600"
              @click="confirmarVaciado = true"
            >
              Vaciar carrito
            </button>
          </div>

          <p class="mt-4 border-t border-borde-sutil pt-4 text-xs text-texto-secundario">
            El pedido se confirma con la botica. El stock se reserva al
            confirmarlo, no al agregarlo al carrito.
          </p>
        </aside>
      </div>

      <!-- Confirmación de vaciado. Vaciar sin preguntar pierde trabajo del
           visitante y no hay forma de deshacerlo. -->
      <div
        v-if="confirmarVaciado"
        class="fixed inset-0 z-50 flex items-center justify-center bg-neutro-900/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="titulo-vaciar"
        @click.self="confirmarVaciado = false"
      >
        <div class="w-full max-w-sm rounded-2xl bg-superficie-elevada p-6 shadow-xl">
          <h2 id="titulo-vaciar" class="text-lg font-semibold text-texto-primario">
            ¿Vaciar el carrito?
          </h2>
          <p class="mt-2 text-sm text-texto-secundario">
            Se quitarán los {{ carrito.lineas.length }} productos. No se puede deshacer.
          </p>
          <div class="mt-6 flex justify-end gap-3">
            <button
              type="button"
              class="foco-dentro rounded-xl border border-borde-control px-4 py-2 text-sm font-medium text-texto-primario hover:bg-superficie-interactiva"
              @click="confirmarVaciado = false"
            >
              Cancelar
            </button>
            <button
              type="button"
              class="foco-dentro rounded-xl bg-peligro-600 px-4 py-2 text-sm font-medium text-white hover:bg-peligro-700"
              @click="vaciar"
            >
              Vaciar
            </button>
          </div>
        </div>
      </div>
    </main>

    <Footer />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import Header from '@/components/Header.vue'
import Footer from '@/components/Footer.vue'
import { useCartStore, type LineaCotizada } from '@/stores/carrito'
import { urlDeMedia } from '@/utils/media'
import { ilustracionDe, textoAlternativoDe } from '@/utils/formaFarmaceutica'
import {
  enlaceWhatsApp,
  TELEFONO_FIJO,
  TELEFONO_FIJO_E164,
} from '@/datos/botica'
import {
  ShoppingCartIcon,
  SearchIcon,
  Trash2Icon,
  PlusIcon,
  MinusIcon,
  FileTextIcon,
  AlertTriangleIcon,
  AlertCircleIcon,
  MessageSquareIcon,
  CopyIcon,
  CheckIcon,
  XIcon,
} from 'lucide-vue-next'

const carrito = useCartStore()
const confirmarVaciado = ref(false)
const copiado = ref(false)

/* Imágenes que el servidor dice tener pero que no se pueden cargar: la base
   guarda rutas como `images/default_image.png` que ya no existen en el disco.
   Hay que registrarlo en estado para que el respaldo pueda decidirse; no basta
   con ocultar el <img> al fallar. */
const sinImagen = ref(new Set<number>())

const marcarImagenRota = (id: number) => sinImagen.value.add(id)

const muestraImagen = (linea: LineaCotizada) =>
  Boolean(urlDeMedia(linea.imagen)) && !sinImagen.value.has(linea.producto_id)

/* Al entrar siempre se vuelve a cotizar: el carrito pudo armarse hace días y
   ni el precio ni el stock de entonces sirven hoy. */
onMounted(() => carrito.cotizar())

const soles = (valor: number) =>
  new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(valor || 0)

function cambiar(linea: LineaCotizada, cantidad: number) {
  carrito.fijarCantidad(linea.producto_id, cantidad, linea.stock_disponible)
}

/**
 * Cambio escrito a mano en el campo numérico.
 *
 * El `max` del input es una sugerencia del navegador: escribir 500 y pulsar
 * Enter lo acepta igual. Por eso se vuelve a topar aquí, y se devuelve al campo
 * el valor corregido para que no quede enseñando un número que no es el real.
 */
function cambiarDesdeCampo(linea: LineaCotizada, evento: Event) {
  const campo = evento.target as HTMLInputElement
  const aplicada = carrito.fijarCantidad(
    linea.producto_id,
    Number(campo.value),
    linea.stock_disponible,
  )
  campo.value = String(aplicada || linea.cantidad)
}

function vaciar() {
  carrito.vaciar()
  confirmarVaciado.value = false
}

/** El pedido en texto, legible por una persona en el mostrador. */
const textoPedido = computed(() => {
  const lineas = carrito.lineas.map(
    (l) =>
      `• ${l.cantidad} x ${l.nombre}${l.concentracion ? ` ${l.concentracion}` : ''}` +
      ` — ${soles(l.subtotal)}`,
  )

  const partes = [
    'Hola, quisiera hacer este pedido en Botica San Juan:',
    '',
    ...lineas,
    '',
    `Total: ${soles(carrito.desglose.total)} (IGV incluido: ${soles(carrito.desglose.igv)})`,
  ]

  if (carrito.requiereReceta) {
    partes.push('', 'Nota: el pedido incluye productos de venta bajo receta médica.')
  }

  return partes.join('\n')
})

const enlacePedido = computed(() => enlaceWhatsApp(textoPedido.value))

async function copiarPedido() {
  try {
    await navigator.clipboard.writeText(textoPedido.value)
    copiado.value = true
    setTimeout(() => { copiado.value = false }, 2000)
  } catch {
    /* El portapapeles puede estar bloqueado por permisos o por no estar en un
       contexto seguro. No hay nada que el visitante pueda arreglar, así que no
       se le interrumpe con un error. */
  }
}
</script>
