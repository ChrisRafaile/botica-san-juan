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
            class="foco-dentro mt-2 rounded-lg bg-peligro-600 px-3 py-1.5 text-sm font-medium text-neutro-0 hover:bg-peligro-700"
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
          class="foco-dentro mt-6 inline-flex items-center gap-2 rounded-xl bg-botica-600 px-5 py-2.5 font-medium text-neutro-0 transition-colors hover:bg-botica-700"
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

        <!-- Revisión de interacciones. Va bajo las líneas y no en el resumen
             a propósito: pertenece a lo que la persona lleva, no al dinero, y
             tiene que leerse antes de mirar el total. -->
        <ConsultorInteracciones
          :producto-ids="idsDelCarrito"
          class="lg:col-start-1"
        />

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

          <!-- Confirmar el pedido. Es la acción principal: deja el encargo
               registrado en el sistema y emite el comprobante. -->
          <button
            type="button"
            class="foco-dentro mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-botica-600 px-4 py-3 font-semibold text-neutro-0 transition-colors hover:bg-botica-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="enviando"
            @click="abrirCheckout"
          >
            <LoaderCircleIcon v-if="enviando" class="size-5 animate-spin" aria-hidden="true" />
            <ReceiptIcon v-else class="size-5" aria-hidden="true" />
            {{ enviando ? 'Registrando…' : 'Continuar al checkout' }}
          </button>

          <!-- Salida alternativa por WhatsApp, para quien prefiere hablar. -->
          <a
            v-if="enlacePedido"
            :href="enlacePedido"
            target="_blank"
            rel="noopener noreferrer"
            class="foco-dentro mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-exito-600 px-4 py-2.5 text-sm font-medium text-exito-700 transition-colors hover:bg-exito-50 dark:text-exito-500 dark:hover:bg-exito-500/10"
          >
            <MessageSquareIcon class="size-4" aria-hidden="true" />
            Prefiero coordinarlo por WhatsApp
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

      <!--
        Checkout.

        DOS CAMINOS, UNA SOLA PANTALLA

        Quien tiene sesión no vuelve a escribir sus datos: el servidor los toma
        de su cuenta. Quien no la tiene puede encargar igual dando nombre,
        documento y teléfono — lo mínimo para entregar el pedido y emitir el
        comprobante.

        Antes el encargo exigía cuenta, y eso es perder al cliente en el último
        paso: ya había elegido y ya sabía el precio. Se le ofrece registrarse,
        no se le obliga.
      -->
      <div
        v-if="checkoutAbierto"
        ref="panelCheckout"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-neutro-950/60 p-4 backdrop-blur-xs"
        role="dialog"
        aria-modal="true"
        aria-labelledby="titulo-checkout"
        tabindex="-1"
        @click.self="cerrarCheckout"
        @keydown.esc.prevent.stop="cerrarCheckout"
        @keydown.tab="atraparFoco($event, panelCheckout)"
      >
        <div class="my-8 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-borde-sutil bg-superficie-elevada p-6 shadow-xl">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 id="titulo-checkout" class="text-xl font-bold text-texto-primario">
                Finalizar compra
              </h2>
              <p class="cifras-tabulares mt-1 text-sm text-texto-secundario">
                {{ carrito.totalUnidades }} {{ carrito.totalUnidades === 1 ? 'unidad' : 'unidades' }} ·
                <strong class="text-texto-primario">{{ soles(carrito.desglose.total) }}</strong>
              </p>
            </div>
            <button
              type="button"
              class="foco-dentro rounded-lg p-2 text-texto-terciario hover:bg-superficie-interactiva hover:text-texto-primario"
              aria-label="Cerrar checkout"
              :disabled="enviando"
              @click="cerrarCheckout"
            >
              <XIcon class="size-4" aria-hidden="true" />
            </button>
          </div>

          <form class="mt-5 space-y-5" @submit.prevent="enviarPedido">
            <!-- 1. Modalidad de entrega -->
            <fieldset>
              <legend class="mb-2 block text-xs font-semibold uppercase tracking-wider text-texto-secundario">
                Modalidad de entrega
              </legend>
              <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                <label
                  class="foco-dentro flex min-h-14 cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors"
                  :class="formulario.modalidad_entrega === 'recojo'
                    ? 'border-botica-600 bg-botica-50/70 dark:border-botica-400 dark:bg-botica-500/15'
                    : 'border-borde-control bg-superficie-base hover:bg-superficie-hundida'"
                >
                  <input
                    v-model="formulario.modalidad_entrega"
                    type="radio"
                    name="modalidad_entrega"
                    value="recojo"
                    class="mt-1 accent-botica-600"
                  />
                  <span class="text-sm">
                    <span class="flex items-center gap-1.5 font-semibold text-texto-primario">
                      <PackageCheck class="size-4 text-botica-600 dark:text-botica-400" aria-hidden="true" />
                      Recojo en botica
                    </span>
                    <span class="mt-0.5 block text-xs text-texto-secundario">
                      Retira en mostrador sin costo
                    </span>
                  </span>
                </label>

                <label
                  class="foco-dentro flex min-h-14 cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors"
                  :class="formulario.modalidad_entrega === 'entrega'
                    ? 'border-botica-600 bg-botica-50/70 dark:border-botica-400 dark:bg-botica-500/15'
                    : 'border-borde-control bg-superficie-base hover:bg-superficie-hundida'"
                >
                  <input
                    v-model="formulario.modalidad_entrega"
                    type="radio"
                    name="modalidad_entrega"
                    value="entrega"
                    class="mt-1 accent-botica-600"
                  />
                  <span class="text-sm">
                    <span class="flex items-center gap-1.5 font-semibold text-texto-primario">
                      <TruckIcon class="size-4 text-botica-600 dark:text-botica-400" aria-hidden="true" />
                      Entrega a domicilio
                    </span>
                    <span class="mt-0.5 block text-xs text-texto-secundario">
                      Envío a tu dirección en SJL
                    </span>
                  </span>
                </label>
              </div>

              <div v-if="formulario.modalidad_entrega === 'entrega'" class="mt-3">
                <label for="co-direccion" class="block text-xs font-medium text-texto-secundario">
                  Dirección completa de entrega
                </label>
                <input
                  id="co-direccion"
                  v-model="formulario.direccion"
                  type="text"
                  autocomplete="street-address"
                  maxlength="255"
                  placeholder="Ej. Av. Próceres de la Independencia 1840, Dpto 201"
                  class="foco-dentro mt-1 w-full rounded-xl border bg-superficie-base px-3 py-2 text-sm text-texto-primario placeholder:text-texto-terciario"
                  :class="errores.direccion ? 'border-peligro-500' : 'border-borde-control'"
                  :aria-invalid="Boolean(errores.direccion)"
                />
                <p v-if="errores.direccion" class="mt-1 text-xs text-peligro-600 dark:text-peligro-500">
                  {{ errores.direccion }}
                </p>
              </div>
            </fieldset>

            <!-- 2. Tipo de comprobante -->
            <fieldset>
              <legend class="mb-2 block text-xs font-semibold uppercase tracking-wider text-texto-secundario">
                Comprobante electrónico SUNAT
              </legend>
              <div class="grid grid-cols-2 gap-2.5">
                <label
                  class="foco-dentro flex min-h-12 cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2.5 text-sm transition-colors"
                  :class="formulario.tipo_comprobante === 'boleta'
                    ? 'border-botica-600 bg-botica-50/70 dark:border-botica-400 dark:bg-botica-500/15'
                    : 'border-borde-control bg-superficie-base hover:bg-superficie-hundida'"
                >
                  <input
                    v-model="formulario.tipo_comprobante"
                    type="radio"
                    name="tipo_comprobante"
                    value="boleta"
                    class="accent-botica-600"
                  />
                  <span>
                    <strong class="block text-texto-primario">Boleta</strong>
                    <span class="text-xs text-texto-secundario">Con DNI (B001)</span>
                  </span>
                </label>

                <label
                  class="foco-dentro flex min-h-12 cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2.5 text-sm transition-colors"
                  :class="formulario.tipo_comprobante === 'factura'
                    ? 'border-botica-600 bg-botica-50/70 dark:border-botica-400 dark:bg-botica-500/15'
                    : 'border-borde-control bg-superficie-base hover:bg-superficie-hundida'"
                >
                  <input
                    v-model="formulario.tipo_comprobante"
                    type="radio"
                    name="tipo_comprobante"
                    value="factura"
                    class="accent-botica-600"
                  />
                  <span>
                    <strong class="block text-texto-primario">Factura</strong>
                    <span class="text-xs text-texto-secundario">Con RUC (F001)</span>
                  </span>
                </label>
              </div>

              <!-- Cuenta con DNI completo y eligió Boleta -->
              <div
                v-if="autenticado && formulario.tipo_comprobante === 'boleta' && auth.user?.dni"
                class="mt-3 rounded-xl bg-superficie-hundida p-3 text-xs text-texto-secundario"
              >
                <p class="flex items-center gap-1.5 font-medium text-texto-primario">
                  <UserIcon class="size-3.5 text-botica-600 dark:text-botica-400" aria-hidden="true" />
                  {{ auth.user.nombre }} · DNI {{ auth.user.dni }}
                </p>
                <p class="mt-0.5">Se emitirá tu boleta electrónica vinculada a tu cuenta.</p>
              </div>

              <!-- Datos del documento / titular cuando falta DNI, es invitado o pide Factura -->
              <div v-else class="mt-3 space-y-3 rounded-xl bg-superficie-hundida/60 p-3.5">
                <div v-if="!autenticado || formulario.tipo_comprobante === 'factura'">
                  <label for="co-nombre" class="block text-xs font-medium text-texto-secundario">
                    {{ formulario.tipo_comprobante === 'factura' ? 'Razón social' : 'Nombre completo' }}
                  </label>
                  <input
                    id="co-nombre"
                    v-model="formulario.cliente_nombre"
                    type="text"
                    autocomplete="name"
                    required
                    :placeholder="formulario.tipo_comprobante === 'factura' ? 'Nombre o razón social SAC' : 'Nombre y apellidos'"
                    class="foco-dentro mt-1 w-full rounded-xl border bg-superficie-base px-3 py-2 text-sm text-texto-primario"
                    :class="errores.cliente_nombre ? 'border-peligro-500' : 'border-borde-control'"
                    :aria-invalid="Boolean(errores.cliente_nombre)"
                  />
                  <p v-if="errores.cliente_nombre" class="mt-1 text-xs text-peligro-600 dark:text-peligro-500">
                    {{ errores.cliente_nombre }}
                  </p>
                </div>

                <div>
                  <label for="co-doc" class="block text-xs font-medium text-texto-secundario">
                    {{ formulario.tipo_comprobante === 'factura' ? 'RUC (11 dígitos)' : 'DNI (8 dígitos)' }}
                  </label>
                  <input
                    id="co-doc"
                    v-model="formulario.cliente_documento"
                    type="text"
                    inputmode="numeric"
                    :maxlength="formulario.tipo_comprobante === 'factura' ? 11 : 8"
                    :placeholder="formulario.tipo_comprobante === 'factura' ? '20XXXXXXXXX' : '12345678'"
                    required
                    class="foco-dentro mt-1 w-full rounded-xl border bg-superficie-base px-3 py-2 font-mono text-sm text-texto-primario"
                    :class="errores.cliente_documento ? 'border-peligro-500' : 'border-borde-control'"
                    :aria-invalid="Boolean(errores.cliente_documento)"
                  />
                  <p
                    class="mt-1 text-xs"
                    :class="errores.cliente_documento ? 'text-peligro-600 dark:text-peligro-500' : 'text-texto-secundario'"
                  >
                    {{ errores.cliente_documento || pistaComprobante }}
                  </p>
                </div>

                <div v-if="!autenticado">
                  <label for="co-tel" class="block text-xs font-medium text-texto-secundario">
                    Teléfono de contacto
                  </label>
                  <input
                    id="co-tel"
                    v-model="formulario.cliente_telefono"
                    type="tel"
                    autocomplete="tel"
                    required
                    placeholder="9XXXXXXXX"
                    class="foco-dentro mt-1 w-full rounded-xl border bg-superficie-base px-3 py-2 text-sm text-texto-primario"
                    :class="errores.cliente_telefono ? 'border-peligro-500' : 'border-borde-control'"
                    :aria-invalid="Boolean(errores.cliente_telefono)"
                  />
                  <p v-if="errores.cliente_telefono" class="mt-1 text-xs text-peligro-600 dark:text-peligro-500">
                    {{ errores.cliente_telefono }}
                  </p>
                </div>
              </div>
            </fieldset>

            <!-- 3. Medio de pago -->
            <fieldset v-if="autenticado">
              <legend class="mb-2 block text-xs font-semibold uppercase tracking-wider text-texto-secundario">
                Forma de pago
              </legend>
              <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                <label
                  class="foco-dentro flex min-h-14 cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors"
                  :class="metodoPago === 'tarjeta'
                    ? 'border-clinico-600 bg-clinico-50/70 dark:border-clinico-400 dark:bg-clinico-500/15'
                    : 'border-borde-control bg-superficie-base hover:bg-superficie-hundida'"
                >
                  <input
                    v-model="metodoPago"
                    type="radio"
                    name="metodo_pago"
                    value="tarjeta"
                    class="mt-1 accent-clinico-600"
                  />
                  <span class="text-sm">
                    <span class="flex items-center gap-1.5 font-semibold text-texto-primario">
                      <CreditCardIcon class="size-4 text-clinico-600 dark:text-clinico-400" aria-hidden="true" />
                      Tarjeta en línea
                    </span>
                    <span class="mt-0.5 block text-xs text-texto-secundario">
                      Pasarela segura con tarjeta 3D
                    </span>
                  </span>
                </label>

                <label
                  class="foco-dentro flex min-h-14 cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors"
                  :class="metodoPago === 'contraentrega'
                    ? 'border-botica-600 bg-botica-50/70 dark:border-botica-400 dark:bg-botica-500/15'
                    : 'border-borde-control bg-superficie-base hover:bg-superficie-hundida'"
                >
                  <input
                    v-model="metodoPago"
                    type="radio"
                    name="metodo_pago"
                    value="contraentrega"
                    class="mt-1 accent-botica-600"
                  />
                  <span class="text-sm">
                    <span class="font-semibold text-texto-primario">Al recibir / mostrador</span>
                    <span class="mt-0.5 block text-xs text-texto-secundario">
                      Efectivo o POS al recoger
                    </span>
                  </span>
                </label>
              </div>
            </fieldset>

            <div
              v-else
              class="rounded-xl border border-borde-sutil bg-superficie-hundida p-3 text-xs text-texto-secundario"
            >
              Estás encargando como invitado para pagar al recibir.
              <RouterLink
                :to="{ path: '/login', query: { redirect: '/cart' } }"
                class="foco-dentro rounded font-semibold text-texto-marca underline"
              >
                Inicia sesión si deseas pagar ahora con tarjeta 3D
              </RouterLink>
              (tu carrito se conserva).
            </div>

            <p
              v-if="errorEnvio"
              class="rounded-xl bg-peligro-50 p-3 text-sm text-peligro-700 dark:bg-peligro-500/10 dark:text-peligro-500"
              role="alert"
            >
              {{ errorEnvio }}
            </p>

            <div class="flex justify-end gap-3 border-t border-borde-sutil pt-4">
              <button
                type="button"
                class="foco-dentro min-h-11 rounded-xl border border-borde-control px-4 py-2 text-sm font-medium text-texto-primario hover:bg-superficie-interactiva"
                :disabled="enviando"
                @click="cerrarCheckout"
              >
                Cancelar
              </button>
              <button
                type="submit"
                class="foco-dentro inline-flex min-h-11 items-center gap-2 rounded-xl bg-botica-600 px-5 py-2 text-sm font-semibold text-neutro-0 hover:bg-botica-700 disabled:opacity-60"
                :disabled="enviando"
              >
                <LoaderCircleIcon v-if="enviando" class="size-4 animate-spin" aria-hidden="true" />
                <template v-if="enviando">Procesando…</template>
                <template v-else-if="autenticado && metodoPago === 'tarjeta'">
                  Ir a pagar ({{ soles(carrito.desglose.total) }})
                </template>
                <template v-else>Confirmar pedido</template>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Pedido confirmado (contraentrega o invitado) -->
      <div
        v-if="confirmado"
        ref="panelConfirmado"
        class="fixed inset-0 z-50 flex items-center justify-center bg-neutro-950/60 p-4 backdrop-blur-xs"
        role="dialog"
        aria-modal="true"
        aria-labelledby="titulo-confirmado"
        tabindex="-1"
        @click.self="cerrarConfirmado"
        @keydown.esc.prevent.stop="cerrarConfirmado"
        @keydown.tab="atraparFoco($event, panelConfirmado)"
      >
        <div class="w-full max-w-md rounded-2xl border border-borde-sutil bg-superficie-elevada p-6 text-center shadow-xl">
          <CheckCircleIcon class="mx-auto size-12 text-exito-600 dark:text-exito-500" aria-hidden="true" />
          <h2 id="titulo-confirmado" class="mt-4 text-xl font-semibold text-texto-primario">
            Pedido registrado
          </h2>

          <dl class="mt-5 space-y-2 rounded-xl bg-superficie-hundida p-4 text-left text-sm">
            <div class="flex justify-between">
              <dt class="text-texto-secundario">N.º de pedido</dt>
              <dd class="cifras-tabulares font-semibold text-texto-primario">#{{ confirmado.seguimiento.pedido_id }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-texto-secundario">Entrega</dt>
              <dd class="font-medium text-texto-primario">
                {{ confirmado.modalidad_entrega === 'entrega' ? 'A domicilio' : 'Recojo en botica' }}
              </dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-texto-secundario">{{ confirmado.comprobante.tipo === 'factura' ? 'Factura' : 'Boleta' }}</dt>
              <dd class="cifras-tabulares font-semibold text-texto-primario">{{ confirmado.comprobante.identificador }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-texto-secundario">Total</dt>
              <dd class="cifras-tabulares font-semibold text-texto-primario">{{ soles(Number(confirmado.pedido.total)) }}</dd>
            </div>
          </dl>

          <p v-if="confirmado.seguimiento.invitado" class="mt-4 text-sm text-texto-secundario">
            Anota el número de pedido: es con lo que preguntarás por él en la botica.
          </p>
          <p v-else class="mt-4 text-sm text-texto-secundario">
            Puedes seguirlo desde tu panel de cliente o descargar tu comprobante.
          </p>

          <p
            v-if="errorDescargaPdf"
            class="mt-3 rounded-lg bg-peligro-50 p-2.5 text-xs text-peligro-700 dark:bg-peligro-500/10 dark:text-peligro-500"
            role="alert"
          >
            {{ errorDescargaPdf }}
          </p>

          <div class="mt-6 flex flex-wrap justify-center gap-3">
            <button
              v-if="!confirmado.seguimiento.invitado && confirmado.comprobante.id"
              type="button"
              :disabled="descargandoPdfConfirmado"
              class="foco-dentro inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-borde-control px-4 py-2 text-sm font-medium text-texto-primario hover:bg-superficie-interactiva disabled:opacity-60"
              @click="descargarPdfDelConfirmado"
            >
              <LoaderCircleIcon v-if="descargandoPdfConfirmado" class="size-4 animate-spin" aria-hidden="true" />
              <Download v-else class="size-4" aria-hidden="true" />
              Descargar PDF
            </button>
            <RouterLink
              to="/products"
              class="foco-dentro inline-flex min-h-10 items-center rounded-xl border border-borde-control px-4 py-2 text-sm font-medium text-texto-primario hover:bg-superficie-interactiva"
            >
              Seguir comprando
            </RouterLink>
            <RouterLink
              v-if="!confirmado.seguimiento.invitado"
              to="/client/home"
              class="foco-dentro inline-flex min-h-10 items-center rounded-xl bg-botica-600 px-4 py-2 text-sm font-semibold text-neutro-0 hover:bg-botica-700"
            >
              Ver mis pedidos
            </RouterLink>
            <button
              v-else
              type="button"
              class="foco-dentro min-h-10 rounded-xl bg-botica-600 px-4 py-2 text-sm font-semibold text-neutro-0 hover:bg-botica-700"
              @click="confirmado = null"
            >
              Entendido
            </button>
          </div>
        </div>
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
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import Header from '@/components/Header.vue'
import Footer from '@/components/Footer.vue'
import ConsultorInteracciones from '@/components/ConsultorInteracciones.vue'
import { useCartStore, type LineaCotizada } from '@/stores/carrito'
import { useAuthStore } from '@/stores/auth'
import { descargarPdfComprobante } from '@/services/comprobantes'
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
  ReceiptIcon,
  UserIcon,
  LoaderCircleIcon,
  CheckCircleIcon,
  CreditCardIcon,
  Download,
  PackageCheck,
  TruckIcon,
} from 'lucide-vue-next'

const carrito = useCartStore()

/* Identificadores de lo que hay en el carrito, para la revisión de
   interacciones. Se recalcula solo cuando cambian las líneas, de modo que
   ajustar una cantidad no vuelve a pedir la revisión: lo que importa es QUÉ
   se lleva, no cuánto. */
const idsDelCarrito = computed(() => carrito.lineas.map((l) => l.producto_id))
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

/* --- Checkout ------------------------------------------------------------- */

const auth = useAuthStore()
const router = useRouter()
const autenticado = computed(() => auth.isAuthenticated)

const checkoutAbierto = ref(false)
const panelCheckout = ref<HTMLElement | null>(null)
const enviando = ref(false)
const errorEnvio = ref('')
const errores = ref<Record<string, string>>({})
const confirmado = ref<Awaited<ReturnType<typeof carrito.confirmar>> | null>(null)
const panelConfirmado = ref<HTMLElement | null>(null)
const metodoPago = ref<'tarjeta' | 'contraentrega'>('tarjeta')
const descargandoPdfConfirmado = ref(false)
const errorDescargaPdf = ref('')

const SELECTOR_ENFOCABLES =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

let origenFocoCheckout: HTMLElement | null = null
let origenFocoConfirmado: HTMLElement | null = null
let temporizadorCopiado: ReturnType<typeof setTimeout> | null = null

function obtenerEnfocables(contenedor: HTMLElement | null): HTMLElement[] {
  if (!contenedor) return []
  return Array.from(contenedor.querySelectorAll<HTMLElement>(SELECTOR_ENFOCABLES)).filter(
    (el) => el.getAttribute('aria-hidden') !== 'true' && !el.closest('[inert]'),
  )
}

function enfocarInicioDialogo(contenedor: HTMLElement | null) {
  const lista = obtenerEnfocables(contenedor)
  const destino = lista[0] ?? contenedor
  destino?.focus()
}

function atraparFoco(evento: KeyboardEvent, contenedor: HTMLElement | null): void {
  const lista = obtenerEnfocables(contenedor)
  if (lista.length === 0) {
    evento.preventDefault()
    contenedor?.focus()
    return
  }

  const primero = lista[0]!
  const ultimo = lista[lista.length - 1]!
  const activo = document.activeElement as HTMLElement | null

  if (evento.shiftKey && (activo === primero || !contenedor?.contains(activo))) {
    evento.preventDefault()
    ultimo.focus()
  } else if (!evento.shiftKey && (activo === ultimo || !contenedor?.contains(activo))) {
    evento.preventDefault()
    primero.focus()
  }
}

function manejarEscapeGlobal(evento: KeyboardEvent): void {
  if (evento.key !== 'Escape') return
  if (confirmado.value) {
    evento.preventDefault()
    cerrarConfirmado()
    return
  }
  if (checkoutAbierto.value) {
    evento.preventDefault()
    cerrarCheckout()
  }
}

watch(checkoutAbierto, async (abierto) => {
  if (abierto) {
    origenFocoCheckout = document.activeElement as HTMLElement | null
    await nextTick()
    enfocarInicioDialogo(panelCheckout.value)
    return
  }

  await nextTick()
  if (!confirmado.value) {
    origenFocoCheckout?.focus()
    origenFocoCheckout = null
  }
})

watch(confirmado, async (nuevo, anterior) => {
  if (nuevo && !anterior) {
    origenFocoConfirmado = origenFocoCheckout ?? (document.activeElement as HTMLElement | null)
    origenFocoCheckout = null
    await nextTick()
    enfocarInicioDialogo(panelConfirmado.value)
    return
  }

  if (!nuevo && anterior) {
    await nextTick()
    origenFocoConfirmado?.focus()
    origenFocoConfirmado = null
  }
})

watch(
  () => checkoutAbierto.value || Boolean(confirmado.value),
  (hayDialogoAbierto) => {
    if (hayDialogoAbierto) {
      window.addEventListener('keydown', manejarEscapeGlobal)
    } else {
      window.removeEventListener('keydown', manejarEscapeGlobal)
    }
  },
)

onBeforeUnmount(() => {
  window.removeEventListener('keydown', manejarEscapeGlobal)
  if (temporizadorCopiado !== null) {
    clearTimeout(temporizadorCopiado)
    temporizadorCopiado = null
  }
})

const formulario = ref({
  modalidad_entrega: 'recojo' as 'recojo' | 'entrega',
  tipo_comprobante: 'boleta' as 'boleta' | 'factura',
  direccion: '',
  cliente_nombre: '',
  cliente_documento: '',
  cliente_telefono: '',
})

watch(
  () => formulario.value.tipo_comprobante,
  (nuevoTipo) => {
    errores.value.cliente_documento = ''
    if (nuevoTipo === 'factura' && formulario.value.cliente_documento.length !== 11) {
      formulario.value.cliente_documento = ''
      if (autenticado.value && formulario.value.cliente_nombre === auth.user?.nombre) {
        formulario.value.cliente_nombre = ''
      }
    } else if (nuevoTipo === 'boleta' && formulario.value.cliente_documento.length > 8) {
      formulario.value.cliente_documento = formulario.value.cliente_documento.slice(0, 8)
    }
  },
)

/** Qué comprobante saldrá y cuántos dígitos faltan. */
const pistaComprobante = computed(() => {
  const d = formulario.value.cliente_documento.replace(/\D/g, '')
  if (formulario.value.tipo_comprobante === 'factura') {
    return d.length === 11 ? 'Se emitirá factura electrónica (F001).' : 'La factura exige un RUC válido de 11 dígitos.'
  }
  return d.length === 8 ? 'Se emitirá boleta de venta (B001).' : 'Ingresa tu DNI de 8 dígitos para la boleta.'
})

function abrirCheckout() {
  errorEnvio.value = ''
  errores.value = {}
  errorDescargaPdf.value = ''
  if (autenticado.value && auth.user) {
    if (!formulario.value.cliente_nombre) {
      formulario.value.cliente_nombre = auth.user.nombre ?? ''
    }
    if (!formulario.value.cliente_telefono) {
      formulario.value.cliente_telefono = auth.user.telefono ?? ''
    }
  }
  checkoutAbierto.value = true
}

function cerrarCheckout() {
  if (enviando.value) return
  checkoutAbierto.value = false
}

function cerrarConfirmado() {
  confirmado.value = null
}

function validarFormularioCheckout(): boolean {
  const nuevosErrores: Record<string, string> = {}

  if (formulario.value.modalidad_entrega === 'entrega' && formulario.value.direccion.trim().length < 5) {
    nuevosErrores.direccion = 'Ingresa una dirección válida (mínimo 5 caracteres).'
  }

  const docLimpio = formulario.value.cliente_documento.replace(/\D/g, '')
  if (formulario.value.tipo_comprobante === 'factura') {
    if (!/^\d{11}$/.test(docLimpio)) {
      nuevosErrores.cliente_documento = 'El RUC debe tener exactamente 11 dígitos numéricos.'
    }
    if (!formulario.value.cliente_nombre.trim()) {
      nuevosErrores.cliente_nombre = 'Ingresa la razón social para emitir la factura.'
    }
  } else if (!autenticado.value || !auth.user?.dni) {
    if (!/^\d{8}$/.test(docLimpio)) {
      nuevosErrores.cliente_documento = 'El DNI debe tener exactamente 8 dígitos numéricos.'
    }
  }

  if (!autenticado.value) {
    if (!formulario.value.cliente_nombre.trim()) {
      nuevosErrores.cliente_nombre = 'Necesitamos tu nombre completo para preparar el pedido.'
    }
    if (formulario.value.cliente_telefono.trim().length < 6) {
      nuevosErrores.cliente_telefono = 'Necesitamos un teléfono válido para avisarte.'
    }
  }

  errores.value = nuevosErrores
  if (Object.keys(nuevosErrores).length > 0) {
    errorEnvio.value = 'Revisa los campos marcados antes de continuar.'
    return false
  }
  return true
}

async function enviarPedido() {
  if (enviando.value) return
  errorEnvio.value = ''
  errores.value = {}

  if (!validarFormularioCheckout()) return

  enviando.value = true

  try {
    const docLimpio = formulario.value.cliente_documento.replace(/\D/g, '')
    const payload = {
      modalidad_entrega: formulario.value.modalidad_entrega,
      tipo_comprobante: formulario.value.tipo_comprobante,
      direccion: formulario.value.modalidad_entrega === 'entrega' ? formulario.value.direccion.trim() : undefined,
      cliente_nombre:
        !autenticado.value || formulario.value.tipo_comprobante === 'factura'
          ? formulario.value.cliente_nombre.trim()
          : undefined,
      cliente_documento:
        formulario.value.tipo_comprobante === 'factura' || !autenticado.value || !auth.user?.dni
          ? docLimpio
          : undefined,
      cliente_telefono: !autenticado.value ? formulario.value.cliente_telefono.trim() : undefined,
    }

    const respuesta = await carrito.confirmar(payload)
    checkoutAbierto.value = false

    if (autenticado.value && metodoPago.value === 'tarjeta') {
      await router.push({
        path: '/checkout/pago',
        query: { pedido: String(respuesta.pedido.id) },
      })
      return
    }

    confirmado.value = respuesta
  } catch (e) {
    const r = (e as {
      response?: { status?: number; data?: { errors?: Record<string, string[]>; message?: string; sin_stock?: boolean } }
    })?.response

    if (r?.status === 422 && r.data?.errors) {
      errores.value = Object.fromEntries(
        Object.entries(r.data.errors).map(([campo, msgs]) => [campo, msgs[0] ?? '']),
      )
      errorEnvio.value = 'Revisa los campos marcados.'
    } else if (r?.data?.sin_stock) {
      /* Se vuelve a cotizar para que el carrito refleje lo que SÍ hay, en vez
         de dejar al cliente con unas cantidades que el servidor ya rechazó. */
      errorEnvio.value = r.data.message ?? 'Ya no hay stock suficiente.'
      await carrito.cotizar()
    } else {
      errorEnvio.value = 'No pudimos registrar el pedido. Inténtalo de nuevo o llámanos.'
    }
  } finally {
    enviando.value = false
  }
}

async function descargarPdfDelConfirmado() {
  const id = confirmado.value?.comprobante.id
  if (!id || descargandoPdfConfirmado.value) return
  descargandoPdfConfirmado.value = true
  errorDescargaPdf.value = ''

  try {
    await descargarPdfComprobante(id, confirmado.value?.comprobante.identificador)
  } catch {
    errorDescargaPdf.value = 'No pudimos descargar el PDF ahora. Podrás descargarlo desde Mis pedidos.'
  } finally {
    descargandoPdfConfirmado.value = false
  }
}

async function copiarPedido() {
  try {
    await navigator.clipboard.writeText(textoPedido.value)
    copiado.value = true
    if (temporizadorCopiado !== null) {
      clearTimeout(temporizadorCopiado)
    }
    temporizadorCopiado = setTimeout(() => {
      copiado.value = false
      temporizadorCopiado = null
    }, 2000)
  } catch {
    /* El portapapeles puede estar bloqueado por permisos o por no estar en un
       contexto seguro. No hay nada que el visitante pueda arreglar, así que no
       se le interrumpe con un error. */
  }
}
</script>
