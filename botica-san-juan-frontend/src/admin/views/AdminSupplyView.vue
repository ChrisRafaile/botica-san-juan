<template>
  <div class="space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-3xl font-bold text-texto-primario">
          {{ pageTitle }}
        </h1>
        <p class="text-texto-secundario">
          {{ pageDescription }}
        </p>
      </div>
      <button
        class="inline-flex items-center rounded-xl bg-botica-700 px-4 py-2.5 text-white shadow-lg shadow-botica-700/20 transition hover:bg-botica-800"
        @click="openPrimaryForm"
      >
        <Plus class="mr-2 h-4 w-4" />
        {{ primaryActionLabel }}
      </button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-2xl bg-botica-600 p-5 text-white">
        <p class="text-sm text-botica-100">
          Proveedores activos
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ activeSuppliers }}
        </p>
      </div>
      <div class="rounded-2xl bg-botica-700 p-5 text-white">
        <p class="text-sm text-botica-100">
          Compras registradas
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ purchases.length }}
        </p>
      </div>
      <div class="rounded-2xl bg-exito-700 p-5 text-white">
        <p class="text-sm text-botica-100">
          Registros DIGEMID
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ digemidRecords.length }}
        </p>
      </div>
      <div class="rounded-2xl bg-alerta-700 p-5 text-white">
        <p class="text-sm text-alerta-50">
          Alertas DIGEMID
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ digemidAlertCount }}
        </p>
      </div>
    </div>

    <div class="rounded-2xl bg-superficie-elevada p-4 shadow ring-1 ring-borde-sutil/80 sm:p-6">
      <div class="grid gap-4 md:grid-cols-3">
        <input
          v-model="search"
          type="search"
          :placeholder="searchPlaceholder"
          class="w-full rounded-xl border border-borde-sutil px-4 py-3 outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
        />

        <select
          v-if="isPurchasesRoute"
          v-model="purchaseStatusFilter"
          class="w-full rounded-xl border border-borde-sutil px-4 py-3 outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
        >
          <option value="all">
            Todos los estados
          </option>
          <option value="borrador">
            Borrador
          </option>
          <option value="emitida">
            Emitida
          </option>
          <option value="recibida">
            Recibida
          </option>
          <option value="anulada">
            Anulada
          </option>
        </select>

        <select
          v-if="isDigemidRoute"
          v-model="digemidActiveFilter"
          class="w-full rounded-xl border border-borde-sutil px-4 py-3 outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
        >
          <option value="all">
            Estado (todos)
          </option>
          <option value="active">
            Activos
          </option>
          <option value="inactive">
            Inactivos
          </option>
        </select>
      </div>

      <div
        v-if="isDigemidRoute"
        class="mt-4 flex flex-wrap items-center gap-3"
      >
        <input
          ref="digemidImportInput"
          type="file"
          accept=".csv,.xlsx"
          class="hidden"
          @change="onImportFileSelected"
        />
        <button
          class="rounded-xl bg-exito-700 px-4 py-2 text-white transition hover:bg-exito-700"
          @click="digemidImportInput?.click()"
        >
          Importar CSV/XLSX
        </button>
        <label class="inline-flex items-center gap-2 text-sm text-texto-secundario">
          <input
            v-model="overwriteImport"
            type="checkbox"
            class="h-4 w-4"
          />
          Sobrescribir códigos existentes
        </label>
        <button
          class="rounded-xl bg-neutro-800 px-4 py-2 text-white transition hover:bg-neutro-900"
          @click="loadDigemidAlerts"
        >
          Actualizar alertas
        </button>
      </div>
    </div>

    <div
      v-if="error"
      class="rounded-xl border border-peligro-500/30 bg-peligro-50 p-4 text-sm text-peligro-700"
    >
      {{ error }}
    </div>

    <div
      v-if="loading"
      class="rounded-2xl bg-superficie-elevada p-10 text-center text-texto-secundario shadow ring-1 ring-borde-sutil/80"
    >
      Cargando informacion...
    </div>

    <div
      v-else-if="isSuppliersRoute"
      class="overflow-hidden rounded-2xl bg-superficie-elevada shadow-lg ring-1 ring-borde-sutil/80"
    >
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-borde-sutil">
          <thead class="bg-superficie-hundida">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                Proveedor
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                Contacto
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                Credito
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                Estado
              </th>
              <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-borde-sutil">
            <tr
              v-for="supplier in filteredSuppliers"
              :key="supplier.id"
              class="hover:bg-superficie-hundida"
            >
              <td class="px-6 py-4">
                <p class="font-semibold text-texto-primario">
                  {{ supplier.nombre }}
                </p>
                <p class="text-xs text-texto-terciario">
                  RUC: {{ supplier.ruc || 'No registrado' }}
                </p>
              </td>
              <td class="px-6 py-4 text-sm text-texto-secundario">
                <p>{{ supplier.contacto || 'Sin contacto' }}</p>
                <p class="text-xs text-texto-terciario">
                  {{ supplier.telefono || 'Sin telefono' }}
                </p>
              </td>
              <td class="px-6 py-4 text-sm text-texto-secundario">
                {{ supplier.dias_credito }} dias
              </td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase"
                  :class="supplier.activo ? 'bg-exito-50 text-exito-700' : 'bg-superficie-interactiva text-texto-secundario'"
                >
                  {{ supplier.activo ? 'activo' : 'inactivo' }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <button
                  class="rounded-lg p-2 text-texto-marca transition hover:bg-botica-50"
                  @click="openSupplierForm(supplier)"
                >
                  <Pencil class="h-4 w-4" />
                </button>
                <button
                  class="rounded-lg p-2 text-peligro-700 transition hover:bg-peligro-50"
                  @click="deleteSupplier(supplier)"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </td>
            </tr>
            <tr v-if="filteredSuppliers.length === 0">
              <td
                colspan="5"
                class="px-6 py-8 text-center text-texto-terciario"
              >
                No hay proveedores con esos filtros.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div
      v-else-if="isPurchasesRoute"
      class="overflow-hidden rounded-2xl bg-superficie-elevada shadow-lg ring-1 ring-borde-sutil/80"
    >
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-borde-sutil">
          <thead class="bg-superficie-hundida">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                Compra
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                Proveedor
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                Fecha
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                Estado
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                Total
              </th>
              <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-borde-sutil">
            <tr
              v-for="purchase in filteredPurchases"
              :key="purchase.id"
              class="hover:bg-superficie-hundida"
            >
              <td class="px-6 py-4 text-sm font-semibold text-texto-primario">
                {{ purchase.numero_compra }}
              </td>
              <td class="px-6 py-4 text-sm text-texto-secundario">
                {{ purchase.proveedor?.nombre || 'Sin proveedor' }}
              </td>
              <td class="px-6 py-4 text-sm text-texto-secundario">
                {{ formatDate(purchase.fecha_compra) }}
              </td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase"
                  :class="purchaseStatusClass(purchase.estado)"
                >
                  {{ purchase.estado }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm font-semibold text-texto-primario">
                S/ {{ Number(purchase.total || 0).toFixed(2) }}
              </td>
              <td class="px-6 py-4 text-right">
                <button
                  class="rounded-lg p-2 text-texto-marca transition hover:bg-botica-50"
                  @click="openPurchaseForm(purchase)"
                >
                  <Pencil class="h-4 w-4" />
                </button>
                <button
                  class="rounded-lg p-2 text-peligro-700 transition hover:bg-peligro-50"
                  @click="deletePurchase(purchase)"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </td>
            </tr>
            <tr v-if="filteredPurchases.length === 0">
              <td
                colspan="6"
                class="px-6 py-8 text-center text-texto-terciario"
              >
                No hay compras con esos filtros.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div
      v-else
      class="space-y-4"
    >
      <div class="overflow-hidden rounded-2xl bg-superficie-elevada shadow-lg ring-1 ring-borde-sutil/80">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-borde-sutil">
            <thead class="bg-superficie-hundida">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Código DIGEMID
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Producto
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Precio Máximo
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Estado
                </th>
                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Acciones
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-borde-sutil">
              <tr
                v-for="record in filteredDigemidRecords"
                :key="record.id"
                class="hover:bg-superficie-hundida"
              >
                <td class="px-6 py-4 text-sm font-semibold text-texto-primario">
                  {{ record.codigo_digemid }}
                </td>
                <td class="px-6 py-4">
                  <p class="text-sm font-medium text-texto-primario">
                    {{ record.nombre_producto }}
                  </p>
                  <p class="text-xs text-texto-terciario">
                    {{ record.principio_activo || 'Sin principio activo' }}
                  </p>
                </td>
                <td class="px-6 py-4 text-sm text-texto-secundario">
                  {{ record.precio_maximo_regulado ? `S/ ${Number(record.precio_maximo_regulado).toFixed(2)}` : 'No definido' }}
                </td>
                <td class="px-6 py-4">
                  <span
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase"
                    :class="record.activo ? 'bg-exito-50 text-exito-700' : 'bg-superficie-interactiva text-texto-secundario'"
                  >
                    {{ record.activo ? 'activo' : 'inactivo' }}
                  </span>
                </td>
                <td class="px-6 py-4 text-right">
                  <button
                    class="rounded-lg p-2 text-texto-marca transition hover:bg-botica-50"
                    @click="openDigemidForm(record)"
                  >
                    <Pencil class="h-4 w-4" />
                  </button>
                </td>
              </tr>
              <tr v-if="filteredDigemidRecords.length === 0">
                <td
                  colspan="5"
                  class="px-6 py-8 text-center text-texto-terciario"
                >
                  No hay registros DIGEMID con esos filtros.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="rounded-2xl border border-alerta-50 bg-alerta-50 p-4 text-sm text-alerta-700">
        <p class="font-semibold">
          Alertas de cumplimiento DIGEMID
        </p>
        <p class="mt-1">
          Sin código: {{ digemidAlerts.summary.missing_code }} | Código no encontrado: {{ digemidAlerts.summary.code_not_found }} | Precio excedido: {{ digemidAlerts.summary.price_exceeded }} | Campos faltantes: {{ digemidAlerts.summary.missing_fields }}
        </p>
      </div>

      <div class="grid gap-4 xl:grid-cols-2">
        <div class="rounded-2xl bg-superficie-elevada p-4 shadow ring-1 ring-borde-sutil/80">
          <h3 class="text-sm font-semibold text-texto-primario">
            Productos sin codigo DIGEMID
          </h3>
          <div class="mt-3 max-h-56 overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="text-texto-terciario">
                <tr>
                  <th class="py-1 text-left">
                    Producto
                  </th>
                  <th class="py-1 text-right">
                    Precio
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in digemidAlerts.alerts.missing_code"
                  :key="`missing-${item.id}`"
                  class="border-t border-borde-sutil"
                >
                  <td class="py-2 pr-2 text-texto-secundario">
                    {{ item.nombre }}
                  </td>
                  <td class="py-2 text-right font-medium text-texto-primario">
                    S/ {{ Number(item.precio || 0).toFixed(2) }}
                  </td>
                </tr>
                <tr v-if="digemidAlerts.alerts.missing_code.length === 0">
                  <td
                    colspan="2"
                    class="py-3 text-texto-terciario"
                  >
                    Sin observaciones.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="rounded-2xl bg-superficie-elevada p-4 shadow ring-1 ring-borde-sutil/80">
          <h3 class="text-sm font-semibold text-texto-primario">
            Codigo DIGEMID no encontrado
          </h3>
          <div class="mt-3 max-h-56 overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="text-texto-terciario">
                <tr>
                  <th class="py-1 text-left">
                    Producto
                  </th>
                  <th class="py-1 text-left">
                    Codigo
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in digemidAlerts.alerts.code_not_found"
                  :key="`notfound-${item.id}`"
                  class="border-t border-borde-sutil"
                >
                  <td class="py-2 pr-2 text-texto-secundario">
                    {{ item.nombre }}
                  </td>
                  <td class="py-2 font-medium text-texto-primario">
                    {{ item.codigo_digemid || '-' }}
                  </td>
                </tr>
                <tr v-if="digemidAlerts.alerts.code_not_found.length === 0">
                  <td
                    colspan="2"
                    class="py-3 text-texto-terciario"
                  >
                    Sin observaciones.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="rounded-2xl bg-superficie-elevada p-4 shadow ring-1 ring-borde-sutil/80">
          <h3 class="text-sm font-semibold text-texto-primario">
            Precio regulado excedido
          </h3>
          <div class="mt-3 max-h-56 overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="text-texto-terciario">
                <tr>
                  <th class="py-1 text-left">
                    Producto
                  </th>
                  <th class="py-1 text-right">
                    Actual / Maximo
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in digemidAlerts.alerts.price_exceeded"
                  :key="`price-${item.id}`"
                  class="border-t border-borde-sutil"
                >
                  <td class="py-2 pr-2 text-texto-secundario">
                    {{ item.nombre }}
                  </td>
                  <td class="py-2 text-right font-medium text-peligro-700">
                    S/ {{ Number(item.precio || 0).toFixed(2) }} / S/ {{ Number(item.precio_maximo_regulado || 0).toFixed(2) }}
                  </td>
                </tr>
                <tr v-if="digemidAlerts.alerts.price_exceeded.length === 0">
                  <td
                    colspan="2"
                    class="py-3 text-texto-terciario"
                  >
                    Sin observaciones.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="rounded-2xl bg-superficie-elevada p-4 shadow ring-1 ring-borde-sutil/80">
          <h3 class="text-sm font-semibold text-texto-primario">
            Campos regulatorios incompletos
          </h3>
          <div class="mt-3 max-h-56 overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="text-texto-terciario">
                <tr>
                  <th class="py-1 text-left">
                    Producto
                  </th>
                  <th class="py-1 text-left">
                    Faltante
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in digemidAlerts.alerts.missing_fields"
                  :key="`fields-${item.id}`"
                  class="border-t border-borde-sutil"
                >
                  <td class="py-2 pr-2 text-texto-secundario">
                    {{ item.nombre }}
                  </td>
                  <td class="py-2 font-medium text-alerta-700">
                    {{ missingFieldLabel(item) }}
                  </td>
                </tr>
                <tr v-if="digemidAlerts.alerts.missing_fields.length === 0">
                  <td
                    colspan="2"
                    class="py-3 text-texto-terciario"
                  >
                    Sin observaciones.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="isSupplierFormOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-neutro-950/60 p-4"
      @click.self="closeSupplierForm"
    >
      <div class="w-full max-w-xl rounded-2xl bg-superficie-elevada p-6 shadow-2xl">
        <h2 class="text-xl font-semibold text-texto-primario">
          {{ supplierForm.id ? 'Editar proveedor' : 'Nuevo proveedor' }}
        </h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
          <input
            v-model="supplierForm.nombre"
            type="text"
            placeholder="Nombre o razon social"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
          <input
            v-model="supplierForm.ruc"
            type="text"
            placeholder="RUC (11 digitos)"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
          <input
            v-model="supplierForm.contacto"
            type="text"
            placeholder="Contacto"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
          <input
            v-model="supplierForm.telefono"
            type="text"
            placeholder="Telefono"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
          <input
            v-model="supplierForm.email"
            type="email"
            placeholder="Email"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
          <input
            v-model.number="supplierForm.dias_credito"
            type="number"
            min="0"
            max="90"
            placeholder="Dias de credito"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
          <input
            v-model="supplierForm.direccion"
            type="text"
            placeholder="Direccion"
            class="md:col-span-2 rounded-xl border border-borde-sutil px-4 py-2.5"
          />
        </div>
        <label class="mt-4 inline-flex items-center gap-2 text-sm text-texto-secundario">
          <input
            v-model="supplierForm.activo"
            type="checkbox"
            class="h-4 w-4"
          />
          Proveedor activo
        </label>
        <div class="mt-6 flex justify-end gap-2">
          <button
            class="rounded-xl bg-superficie-interactiva px-4 py-2 text-texto-secundario"
            @click="closeSupplierForm"
          >
            Cancelar
          </button>
          <button
            class="rounded-xl bg-botica-700 px-4 py-2 text-white"
            @click="saveSupplier"
          >
            Guardar
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="isPurchaseFormOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-neutro-950/60 p-4"
      @click.self="closePurchaseForm"
    >
      <div class="w-full max-w-xl rounded-2xl bg-superficie-elevada p-6 shadow-2xl">
        <h2 class="text-xl font-semibold text-texto-primario">
          {{ purchaseForm.id ? 'Editar compra' : 'Nueva compra' }}
        </h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
          <select
            v-model.number="purchaseForm.proveedor_id"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          >
            <option :value="0">
              Selecciona proveedor
            </option>
            <option
              v-for="supplier in suppliers"
              :key="supplier.id"
              :value="supplier.id"
            >
              {{ supplier.nombre }}
            </option>
          </select>
          <input
            v-model="purchaseForm.numero_compra"
            type="text"
            placeholder="Numero compra"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
          <input
            v-model="purchaseForm.fecha_compra"
            type="date"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
          <select
            v-model="purchaseForm.estado"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          >
            <option value="borrador">
              Borrador
            </option>
            <option value="emitida">
              Emitida
            </option>
            <option value="recibida">
              Recibida
            </option>
            <option value="anulada">
              Anulada
            </option>
          </select>
          <input
            v-model.number="purchaseForm.total"
            type="number"
            min="0"
            step="0.01"
            placeholder="Total"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
          <textarea
            v-model="purchaseForm.observaciones"
            rows="3"
            placeholder="Observaciones"
            class="md:col-span-2 rounded-xl border border-borde-sutil px-4 py-2.5"
          />
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button
            class="rounded-xl bg-superficie-interactiva px-4 py-2 text-texto-secundario"
            @click="closePurchaseForm"
          >
            Cancelar
          </button>
          <button
            class="rounded-xl bg-botica-700 px-4 py-2 text-white"
            @click="savePurchase"
          >
            Guardar
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="isDigemidFormOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-neutro-950/60 p-4"
      @click.self="closeDigemidForm"
    >
      <div class="w-full max-w-2xl rounded-2xl bg-superficie-elevada p-6 shadow-2xl">
        <h2 class="text-xl font-semibold text-texto-primario">
          {{ digemidForm.id ? 'Editar registro DIGEMID' : 'Nuevo registro DIGEMID' }}
        </h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
          <input
            v-model="digemidForm.codigo_digemid"
            type="text"
            placeholder="Codigo DIGEMID"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
          <input
            v-model="digemidForm.nombre_producto"
            type="text"
            placeholder="Nombre producto"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
          <input
            v-model="digemidForm.principio_activo"
            type="text"
            placeholder="Principio activo"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
          <input
            v-model="digemidForm.laboratorio_fabricante"
            type="text"
            placeholder="Laboratorio fabricante"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
          <input
            v-model.number="digemidForm.precio_maximo_regulado"
            type="number"
            min="0"
            step="0.01"
            placeholder="Precio maximo regulado"
            class="rounded-xl border border-borde-sutil px-4 py-2.5"
          />
        </div>
        <div class="mt-4 flex flex-wrap gap-4">
          <label class="inline-flex items-center gap-2 text-sm text-texto-secundario">
            <input
              v-model="digemidForm.requiere_receta"
              type="checkbox"
              class="h-4 w-4"
            />
            Requiere receta
          </label>
          <label class="inline-flex items-center gap-2 text-sm text-texto-secundario">
            <input
              v-model="digemidForm.activo"
              type="checkbox"
              class="h-4 w-4"
            />
            Activo
          </label>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button
            class="rounded-xl bg-superficie-interactiva px-4 py-2 text-texto-secundario"
            @click="closeDigemidForm"
          >
            Cancelar
          </button>
          <button
            class="rounded-xl bg-botica-700 px-4 py-2 text-white"
            @click="saveDigemidRecord"
          >
            Guardar
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-2xl border border-botica-200 bg-botica-50 p-4 text-sm text-botica-900">
      Este modulo ya permite importar catalogo DIGEMID local, validar precio regulado y revisar alertas de cumplimiento.
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Pencil, Plus, Trash2 } from 'lucide-vue-next'
import api from '@/services/api'
import { useAdminToast } from '../composables/useAdminToast'

interface Supplier {
  id: number
  nombre: string
  ruc: string | null
  contacto: string | null
  telefono: string | null
  email: string | null
  direccion: string | null
  dias_credito: number
  activo: boolean
}

interface Purchase {
  id: number
  proveedor_id: number
  numero_compra: string
  fecha_compra: string
  estado: 'borrador' | 'emitida' | 'recibida' | 'anulada'
  total: number
  observaciones: string | null
  proveedor?: {
    id: number
    nombre: string
    ruc: string | null
  }
}

interface DigemidRecord {
  id: number
  codigo_digemid: string
  nombre_producto: string
  principio_activo: string | null
  laboratorio_fabricante: string | null
  requiere_receta: boolean
  precio_maximo_regulado: number | null
  activo: boolean
}

interface DigemidAlertSummary {
  missing_code: number
  code_not_found: number
  price_exceeded: number
  missing_fields: number
}

interface DigemidAlertItem {
  id: number
  nombre: string
  precio?: number
  codigo_digemid?: string | null
  precio_maximo_regulado?: number
  principio_activo?: string | null
  laboratorio_fabricante?: string | null
}

interface DigemidAlertsPayload {
  summary: DigemidAlertSummary
  alerts: {
    missing_code: DigemidAlertItem[]
    code_not_found: DigemidAlertItem[]
    price_exceeded: DigemidAlertItem[]
    missing_fields: DigemidAlertItem[]
  }
}

const route = useRoute()
const { notifyError, notifySuccess } = useAdminToast()

const loading = ref(false)
const error = ref('')
const search = ref('')
const purchaseStatusFilter = ref<'all' | Purchase['estado']>('all')
const digemidActiveFilter = ref<'all' | 'active' | 'inactive'>('all')
const overwriteImport = ref(false)
const digemidImportInput = ref<HTMLInputElement>()

const suppliers = ref<Supplier[]>([])
const purchases = ref<Purchase[]>([])
const digemidRecords = ref<DigemidRecord[]>([])
const digemidAlerts = ref<DigemidAlertsPayload>({
  summary: {
    missing_code: 0,
    code_not_found: 0,
    price_exceeded: 0,
    missing_fields: 0
  },
  alerts: {
    missing_code: [],
    code_not_found: [],
    price_exceeded: [],
    missing_fields: []
  }
})

const isSupplierFormOpen = ref(false)
const isPurchaseFormOpen = ref(false)
const isDigemidFormOpen = ref(false)

const supplierForm = ref({
  id: 0,
  nombre: '',
  ruc: '',
  contacto: '',
  telefono: '',
  email: '',
  direccion: '',
  dias_credito: 0,
  activo: true
})

const purchaseForm = ref({
  id: 0,
  proveedor_id: 0,
  numero_compra: '',
  fecha_compra: new Date().toISOString().slice(0, 10),
  estado: 'borrador' as Purchase['estado'],
  total: 0,
  observaciones: ''
})

const digemidForm = ref({
  id: 0,
  codigo_digemid: '',
  nombre_producto: '',
  principio_activo: '',
  laboratorio_fabricante: '',
  requiere_receta: false,
  precio_maximo_regulado: null as number | null,
  activo: true
})

const isSuppliersRoute = computed(() => route.path.endsWith('/supply/suppliers'))
const isPurchasesRoute = computed(() => route.path.endsWith('/supply/purchases'))
const isDigemidRoute = computed(() => route.path.endsWith('/supply/digemid'))

const pageTitle = computed(() => {
  if (isDigemidRoute.value) return 'Gestion DIGEMID'
  if (isPurchasesRoute.value) return 'Gestion de Compras'
  return 'Gestion de Proveedores'
})

const pageDescription = computed(() => {
  if (isDigemidRoute.value) return 'Catalogo regulatorio local, importaciones y alertas de cumplimiento.'
  if (isPurchasesRoute.value) return 'Controla compras, estados y trazabilidad de abastecimiento.'
  return 'Registra y mantiene tus proveedores para operar con un solo local.'
})

const primaryActionLabel = computed(() => {
  if (isDigemidRoute.value) return 'Nuevo registro DIGEMID'
  if (isPurchasesRoute.value) return 'Nueva compra'
  return 'Nuevo proveedor'
})

const searchPlaceholder = computed(() => {
  if (isDigemidRoute.value) return 'Buscar por codigo, producto, principio activo o laboratorio'
  if (isPurchasesRoute.value) return 'Buscar por numero de compra o proveedor'
  return 'Buscar por proveedor, RUC o contacto'
})

const activeSuppliers = computed(() => suppliers.value.filter(item => item.activo).length)
const digemidAlertCount = computed(() => {
  const summary = digemidAlerts.value.summary
  return summary.missing_code + summary.code_not_found + summary.price_exceeded + summary.missing_fields
})

const filteredSuppliers = computed(() => {
  const q = search.value.toLowerCase().trim()
  if (!q) return suppliers.value

  return suppliers.value.filter(item => (
    item.nombre.toLowerCase().includes(q) ||
    (item.ruc || '').toLowerCase().includes(q) ||
    (item.contacto || '').toLowerCase().includes(q)
  ))
})

const filteredPurchases = computed(() => {
  const q = search.value.toLowerCase().trim()
  return purchases.value.filter(item => {
    const searchMatch = !q ||
      item.numero_compra.toLowerCase().includes(q) ||
      (item.proveedor?.nombre || '').toLowerCase().includes(q)

    const statusMatch = purchaseStatusFilter.value === 'all' || item.estado === purchaseStatusFilter.value
    return searchMatch && statusMatch
  })
})

const filteredDigemidRecords = computed(() => {
  const q = search.value.toLowerCase().trim()

  return digemidRecords.value.filter(item => {
    const searchMatch = !q ||
      item.codigo_digemid.toLowerCase().includes(q) ||
      item.nombre_producto.toLowerCase().includes(q) ||
      (item.principio_activo || '').toLowerCase().includes(q) ||
      (item.laboratorio_fabricante || '').toLowerCase().includes(q)

    const activeMatch = digemidActiveFilter.value === 'all' ||
      (digemidActiveFilter.value === 'active' ? item.activo : !item.activo)

    return searchMatch && activeMatch
  })
})

const formatDate = (dateValue: string) => {
  if (!dateValue) return '-'
  const date = new Date(dateValue)
  return Number.isNaN(date.getTime()) ? '-' : date.toLocaleDateString('es-PE')
}

const purchaseStatusClass = (status: Purchase['estado']) => {
  if (status === 'recibida') return 'bg-exito-50 text-exito-700'
  if (status === 'emitida') return 'bg-botica-50 text-botica-800'
  if (status === 'anulada') return 'bg-peligro-50 text-peligro-700'
  return 'bg-alerta-50 text-alerta-700'
}

const missingFieldLabel = (item: DigemidAlertItem) => {
  const missing: string[] = []
  if (!item.principio_activo) missing.push('principio activo')
  if (!item.laboratorio_fabricante) missing.push('laboratorio fabricante')
  return missing.length > 0 ? missing.join(', ') : 'Revisar datos'
}

const loadData = async () => {
  loading.value = true
  error.value = ''
  try {
    const [suppliersRes, purchasesRes, digemidRes] = await Promise.all([
      api.get('/proveedores'),
      api.get('/compras'),
      api.get('/digemid-catalogo')
    ])

    suppliers.value = Array.isArray(suppliersRes.data) ? suppliersRes.data : (suppliersRes.data.data || [])
    purchases.value = Array.isArray(purchasesRes.data) ? purchasesRes.data : (purchasesRes.data.data || [])
    digemidRecords.value = Array.isArray(digemidRes.data) ? digemidRes.data : (digemidRes.data.data || [])

    await loadDigemidAlerts()
  } catch (err) {
    console.error('Error loading supply data:', err)
    error.value = 'No se pudo cargar el modulo de abastecimiento.'
  } finally {
    loading.value = false
  }
}

const loadDigemidAlerts = async () => {
  try {
    const response = await api.get('/digemid-catalogo/alertas-cumplimiento')
    digemidAlerts.value = response.data
  } catch (err) {
    console.error('Error loading DIGEMID alerts:', err)
  }
}

const openPrimaryForm = () => {
  if (isDigemidRoute.value) {
    openDigemidForm()
    return
  }

  if (isPurchasesRoute.value) {
    openPurchaseForm()
    return
  }

  openSupplierForm()
}

const openSupplierForm = (supplier?: Supplier) => {
  if (supplier) {
    supplierForm.value = {
      id: supplier.id,
      nombre: supplier.nombre,
      ruc: supplier.ruc || '',
      contacto: supplier.contacto || '',
      telefono: supplier.telefono || '',
      email: supplier.email || '',
      direccion: supplier.direccion || '',
      dias_credito: supplier.dias_credito || 0,
      activo: supplier.activo
    }
  } else {
    supplierForm.value = {
      id: 0,
      nombre: '',
      ruc: '',
      contacto: '',
      telefono: '',
      email: '',
      direccion: '',
      dias_credito: 0,
      activo: true
    }
  }

  isSupplierFormOpen.value = true
}

const closeSupplierForm = () => {
  isSupplierFormOpen.value = false
}

const saveSupplier = async () => {
  if (!supplierForm.value.nombre.trim()) {
    notifyError('Proveedores', 'El nombre del proveedor es obligatorio.')
    return
  }

  const payload = {
    nombre: supplierForm.value.nombre.trim(),
    ruc: supplierForm.value.ruc.trim() || null,
    contacto: supplierForm.value.contacto.trim() || null,
    telefono: supplierForm.value.telefono.trim() || null,
    email: supplierForm.value.email.trim() || null,
    direccion: supplierForm.value.direccion.trim() || null,
    dias_credito: supplierForm.value.dias_credito,
    activo: supplierForm.value.activo
  }

  try {
    if (supplierForm.value.id) {
      await api.put(`/proveedores/${supplierForm.value.id}`, payload)
      notifySuccess('Proveedores', 'Proveedor actualizado correctamente.')
    } else {
      await api.post('/proveedores', payload)
      notifySuccess('Proveedores', 'Proveedor creado correctamente.')
    }

    closeSupplierForm()
    await loadData()
  } catch (err) {
    console.error('Error saving supplier:', err)
    notifyError('Proveedores', 'No se pudo guardar el proveedor.')
  }
}

const nextPurchaseNumber = () => {
  const next = purchases.value.length + 1
  return `CP-${new Date().getFullYear()}-${String(next).padStart(5, '0')}`
}

const openPurchaseForm = (purchase?: Purchase) => {
  if (purchase) {
    purchaseForm.value = {
      id: purchase.id,
      proveedor_id: purchase.proveedor_id,
      numero_compra: purchase.numero_compra,
      fecha_compra: purchase.fecha_compra,
      estado: purchase.estado,
      total: Number(purchase.total || 0),
      observaciones: purchase.observaciones || ''
    }
  } else {
    purchaseForm.value = {
      id: 0,
      proveedor_id: 0,
      numero_compra: nextPurchaseNumber(),
      fecha_compra: new Date().toISOString().slice(0, 10),
      estado: 'borrador',
      total: 0,
      observaciones: ''
    }
  }

  isPurchaseFormOpen.value = true
}

const closePurchaseForm = () => {
  isPurchaseFormOpen.value = false
}

const savePurchase = async () => {
  if (!purchaseForm.value.proveedor_id) {
    notifyError('Compras', 'Selecciona un proveedor para la compra.')
    return
  }

  if (!purchaseForm.value.numero_compra.trim()) {
    notifyError('Compras', 'El numero de compra es obligatorio.')
    return
  }

  const payload = {
    proveedor_id: purchaseForm.value.proveedor_id,
    numero_compra: purchaseForm.value.numero_compra.trim(),
    fecha_compra: purchaseForm.value.fecha_compra,
    estado: purchaseForm.value.estado,
    total: purchaseForm.value.total,
    observaciones: purchaseForm.value.observaciones.trim() || null
  }

  try {
    if (purchaseForm.value.id) {
      await api.put(`/compras/${purchaseForm.value.id}`, payload)
      notifySuccess('Compras', 'Compra actualizada correctamente.')
    } else {
      await api.post('/compras', payload)
      notifySuccess('Compras', 'Compra registrada correctamente.')
    }

    closePurchaseForm()
    await loadData()
  } catch (err) {
    console.error('Error saving purchase:', err)
    notifyError('Compras', 'No se pudo guardar la compra.')
  }
}

const deleteSupplier = async (supplier: Supplier) => {
  const confirmed = window.confirm(`Eliminar proveedor ${supplier.nombre}? Esta accion no se puede deshacer.`)
  if (!confirmed) return

  try {
    await api.delete(`/proveedores/${supplier.id}`)
    notifySuccess('Proveedores', 'Proveedor eliminado correctamente.')
    await loadData()
  } catch (err) {
    console.error('Error deleting supplier:', err)
    notifyError('Proveedores', 'No se pudo eliminar. Si tiene compras asociadas, elimina primero esas compras.')
  }
}

const deletePurchase = async (purchase: Purchase) => {
  const confirmed = window.confirm(`Eliminar compra ${purchase.numero_compra}? Esta accion no se puede deshacer.`)
  if (!confirmed) return

  try {
    await api.delete(`/compras/${purchase.id}`)
    notifySuccess('Compras', 'Compra eliminada correctamente.')
    await loadData()
  } catch (err) {
    console.error('Error deleting purchase:', err)
    notifyError('Compras', 'No se pudo eliminar la compra.')
  }
}

const openDigemidForm = (record?: DigemidRecord) => {
  if (record) {
    digemidForm.value = {
      id: record.id,
      codigo_digemid: record.codigo_digemid,
      nombre_producto: record.nombre_producto,
      principio_activo: record.principio_activo || '',
      laboratorio_fabricante: record.laboratorio_fabricante || '',
      requiere_receta: Boolean(record.requiere_receta),
      precio_maximo_regulado: record.precio_maximo_regulado !== null ? Number(record.precio_maximo_regulado) : null,
      activo: Boolean(record.activo)
    }
  } else {
    digemidForm.value = {
      id: 0,
      codigo_digemid: '',
      nombre_producto: '',
      principio_activo: '',
      laboratorio_fabricante: '',
      requiere_receta: false,
      precio_maximo_regulado: null,
      activo: true
    }
  }

  isDigemidFormOpen.value = true
}

const closeDigemidForm = () => {
  isDigemidFormOpen.value = false
}

const saveDigemidRecord = async () => {
  if (!digemidForm.value.codigo_digemid.trim() || !digemidForm.value.nombre_producto.trim()) {
    notifyError('DIGEMID', 'Codigo y nombre de producto son obligatorios.')
    return
  }

  const payload = {
    codigo_digemid: digemidForm.value.codigo_digemid.trim(),
    nombre_producto: digemidForm.value.nombre_producto.trim(),
    principio_activo: digemidForm.value.principio_activo.trim() || null,
    laboratorio_fabricante: digemidForm.value.laboratorio_fabricante.trim() || null,
    requiere_receta: digemidForm.value.requiere_receta,
    precio_maximo_regulado: digemidForm.value.precio_maximo_regulado,
    activo: digemidForm.value.activo
  }

  try {
    if (digemidForm.value.id) {
      await api.put(`/digemid-catalogo/${digemidForm.value.id}`, payload)
      notifySuccess('DIGEMID', 'Registro actualizado correctamente.')
    } else {
      await api.post('/digemid-catalogo', payload)
      notifySuccess('DIGEMID', 'Registro creado correctamente.')
    }

    closeDigemidForm()
    await loadData()
  } catch (err) {
    console.error('Error saving DIGEMID record:', err)
    notifyError('DIGEMID', 'No se pudo guardar el registro.')
  }
}

const onImportFileSelected = async (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  const formData = new FormData()
  formData.append('file', file)
  formData.append('overwrite', overwriteImport.value ? '1' : '0')

  try {
    const response = await api.post('/digemid-catalogo/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })

    notifySuccess('DIGEMID', `Importacion completada. Creados: ${response.data.created}, actualizados: ${response.data.updated}.`)
    await loadData()
  } catch (err) {
    console.error('Error importing DIGEMID file:', err)
    notifyError('DIGEMID', 'No se pudo importar el archivo. Verifica formato y columnas.')
  } finally {
    if (target) {
      target.value = ''
    }
  }
}

onMounted(loadData)
</script>
