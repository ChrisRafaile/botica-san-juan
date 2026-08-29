<template>
  <div class="min-h-screen bg-linear-to-br from-blue-50 via-white to-indigo-50">
    <!-- Header with enhanced design -->
    <div class="bg-white shadow-lg border-b border-gray-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4">
            <div class="p-3 bg-linear-to-r from-blue-600 to-indigo-600 rounded-xl shadow-lg">
              <Upload class="h-8 w-8 text-white" />
            </div>
            <div>
              <h1 class="text-3xl font-bold bg-linear-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent">
                Carga Masiva de Productos
              </h1>
              <p class="text-gray-600 mt-1 flex items-center">
                <span class="inline-block w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                Sube archivos CSV o Excel para gestionar tu inventario
              </p>
            </div>
          </div>
          <div class="flex items-center space-x-3">
            <button
              class="px-6 py-3 bg-linear-to-r from-gray-600 to-gray-700 text-white rounded-xl hover:from-gray-700 hover:to-gray-800 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
              @click="$router.back()"
            >
              <ArrowLeft class="w-5 h-5 inline mr-2" />
              Volver al Panel
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      <!-- Statistics Dashboard -->
      <div
        v-if="parsedData.length > 0"
        class="grid grid-cols-1 md:grid-cols-4 gap-6"
      >
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 transform hover:scale-105 transition-all duration-200">
          <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-xl">
              <Package class="h-6 w-6 text-blue-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">
                Total Productos
              </p>
              <p class="text-2xl font-bold text-gray-900">
                {{ parsedData.length }}
              </p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 transform hover:scale-105 transition-all duration-200">
          <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-xl">
              <CheckCircle class="h-6 w-6 text-green-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">
                Válidos
              </p>
              <p class="text-2xl font-bold text-green-600">
                {{ parsedData.length - validationErrors.length }}
              </p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 transform hover:scale-105 transition-all duration-200">
          <div class="flex items-center">
            <div class="p-3 bg-red-100 rounded-xl">
              <AlertTriangle class="h-6 w-6 text-red-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">
                Errores
              </p>
              <p class="text-2xl font-bold text-red-600">
                {{ validationErrors.length }}
              </p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 transform hover:scale-105 transition-all duration-200">
          <div class="flex items-center">
            <div class="p-3 bg-yellow-100 rounded-xl">
              <Copy class="h-6 w-6 text-yellow-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">
                Duplicados
              </p>
              <p class="text-2xl font-bold text-yellow-600">
                {{ Object.keys(duplicates).length }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Enhanced Upload Section -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="bg-linear-to-r from-blue-600 to-indigo-600 px-6 py-4">
          <h2 class="text-xl font-semibold text-white flex items-center">
            <FileText class="w-6 h-6 mr-3" />
            Seleccionar Archivo
          </h2>
        </div>
        <div class="p-8">
          <div class="space-y-6">
            <div>
              <div
                class="relative group"
                :class="{ 'scale-105': isDragOver }"
              >
                <div
                  class="mt-1 flex justify-center px-6 pt-8 pb-10 border-2 border-dashed rounded-2xl transition-all duration-300 group-hover:border-blue-400 group-hover:bg-blue-50/50"
                  :class="{
                    'bg-blue-50 border-blue-400 shadow-lg': isDragOver,
                    'border-gray-300': !isDragOver && !file,
                    'border-green-400 bg-green-50': file
                  }"
                  @dragover.prevent="onDragOver"
                  @dragleave.prevent="onDragLeave"
                  @drop.prevent="handleDrop"
                >
                  <div class="space-y-4 text-center">
                    <div class="mx-auto">
                      <Upload
                        v-if="!file"
                        class="mx-auto h-16 w-16 text-gray-400 group-hover:text-blue-500 transition-colors duration-200"
                      />
                      <CheckCircle
                        v-else
                        class="mx-auto h-16 w-16 text-green-500 animate-bounce"
                      />
                    </div>
                    <div class="flex flex-col items-center space-y-2">
                      <div class="text-lg font-medium">
                        <label
                          for="file-upload"
                          class="cursor-pointer"
                          :class="file ? 'text-green-600' : 'text-blue-600 hover:text-blue-700'"
                        >
                          <span
                            v-if="!file"
                            class="bg-linear-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent font-semibold"
                          >
                            Haz clic para subir
                          </span>
                          <span
                            v-else
                            class="text-green-600 font-semibold"
                          >
                            Archivo seleccionado
                          </span>
                        </label>
                      </div>
                      <p
                        v-if="!file"
                        class="text-gray-500 text-sm"
                      >
                        o arrastra y suelta aquí
                      </p>
                      <p class="text-xs text-gray-400">
                        CSV, XLSX, XLS hasta 10MB
                      </p>
                    </div>
                    <input
                      id="file-upload"
                      name="file-upload"
                      type="file"
                      class="sr-only"
                      accept=".csv,.xlsx,.xls"
                      @change="handleFileSelect"
                    />
                    <div
                      v-if="file"
                      class="flex items-center justify-center space-x-4 bg-white rounded-xl p-4 shadow-md border"
                    >
                      <div class="flex items-center space-x-3">
                        <FileText class="h-8 w-8 text-blue-600" />
                        <div class="text-left">
                          <p class="text-sm font-medium text-gray-900">
                            {{ file.name }}
                          </p>
                          <p class="text-xs text-gray-500">
                            {{ formatFileSize(file.size) }}
                          </p>
                        </div>
                      </div>
                      <button
                        class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors duration-200"
                        @click.prevent="removeFile"
                      >
                        <X class="w-5 h-5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Enhanced Template Download -->
            <div class="bg-linear-to-r from-indigo-50 to-blue-50 rounded-2xl p-6 border border-indigo-100">
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                  <div class="p-3 bg-indigo-100 rounded-xl">
                    <Download class="h-6 w-6 text-indigo-600" />
                  </div>
                  <div>
                    <p class="text-lg font-semibold text-gray-900">
                      ¿No tienes un archivo preparado?
                    </p>
                    <p class="text-gray-600">
                      Descarga nuestras plantillas de ejemplo con datos de muestra
                    </p>
                  </div>
                </div>
                <div class="flex space-x-3">
                  <button
                    class="px-6 py-3 bg-linear-to-r from-blue-600 to-indigo-600 text-white text-sm rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 flex items-center"
                    @click="downloadTemplate('xlsx')"
                  >
                    <FileSpreadsheet class="w-4 h-4 mr-2" />
                    Excel (XLSX)
                  </button>
                  <button
                    class="px-6 py-3 bg-linear-to-r from-green-600 to-emerald-600 text-white text-sm rounded-xl hover:from-green-700 hover:to-emerald-700 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 flex items-center"
                    @click="downloadTemplate('csv')"
                  >
                    <FileText class="w-4 h-4 mr-2" />
                    CSV
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Enhanced Preview Section -->
      <div
        v-if="parsedData.length > 0"
        class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden"
      >
        <div class="bg-linear-to-r from-indigo-600 to-purple-600 px-6 py-4">
          <h3 class="text-xl font-semibold text-white flex items-center">
            <Package class="w-6 h-6 mr-3" />
            Vista Previa de Productos ({{ filteredData.length }} de {{ parsedData.length }})
          </h3>
        </div>

        <!-- Search and Filter Bar -->
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
          <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
            <div class="flex items-center space-x-4 flex-1">
              <div class="relative flex-1 max-w-md">
                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-5 w-5" />
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Buscar productos..."
                  class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <select
                v-model="selectedCategory"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">
                  Todas las categorías
                </option>
                <option
                  v-for="cat in uniqueCategories"
                  :key="cat"
                  :value="cat"
                >
                  {{ cat }}
                </option>
              </select>
              <select
                v-model="selectedLab"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">
                  Todos los laboratorios
                </option>
                <option
                  v-for="lab in uniqueLabs"
                  :key="lab"
                  :value="lab"
                >
                  {{ lab }}
                </option>
              </select>
            </div>
            <div class="flex items-center space-x-3">
              <div class="flex items-center space-x-2">
                <label class="text-sm text-gray-600">Duplicados:</label>
                <select
                  v-model="duplicateStrategy"
                  class="form-input text-sm"
                >
                  <option value="skip">
                    Omitir duplicados
                  </option>
                  <option value="overwrite">
                    Sustituir
                  </option>
                  <option value="update">
                    Actualizar
                  </option>
                </select>
              </div>
              <button
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                @click="exportFilteredData"
              >
                <Download class="w-4 h-4 mr-2" />
                Exportar
              </button>
            </div>
          </div>
        </div>

        <!-- Validation Errors Summary -->
        <div
          v-if="validationErrors.length > 0"
          class="px-6 py-4 bg-red-50 border-b border-red-200"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <AlertTriangle class="w-6 h-6 text-red-500 mr-3" />
              <div>
                <h4 class="text-sm font-medium text-red-800">
                  {{ validationErrors.length }} errores de validación encontrados
                </h4>
                <p class="text-sm text-red-600 mt-1">
                  Revisa las filas marcadas en rojo en la tabla
                </p>
              </div>
            </div>
            <button
              class="px-3 py-1 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200"
              @click="showErrorsModal = true"
            >
              Ver detalles
            </button>
          </div>
        </div>

        <!-- Duplicates Notice -->
        <div
          v-if="Object.keys(duplicates).length > 0"
          class="px-6 py-3 bg-yellow-50 border-b border-yellow-200"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <Copy class="w-5 h-5 text-yellow-600 mr-3" />
              <div class="text-sm text-yellow-800 font-medium">
                {{ Object.keys(duplicates).length }} grupos de duplicados detectados
              </div>
            </div>
            <div class="text-sm text-gray-600">
              Estrategia: <strong>{{ duplicateStrategy }}</strong>
            </div>
          </div>
        </div>

        <!-- Enhanced Data Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  <input
                    type="checkbox"
                    :checked="selectedRows.length === filteredData.length && filteredData.length > 0"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    @change="toggleSelectAll"
                  />
                </th>
                <th
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  @click="sortBy('nombre')"
                >
                  Nombre
                  <ChevronUp
                    v-if="sortField === 'nombre' && sortOrder === 'asc'"
                    class="inline w-4 h-4"
                  />
                  <ChevronDown
                    v-if="sortField === 'nombre' && sortOrder === 'desc'"
                    class="inline w-4 h-4"
                  />
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Concentración
                </th>
                <th
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  @click="sortBy('laboratorio')"
                >
                  Laboratorio
                  <ChevronUp
                    v-if="sortField === 'laboratorio' && sortOrder === 'asc'"
                    class="inline w-4 h-4"
                  />
                  <ChevronDown
                    v-if="sortField === 'laboratorio' && sortOrder === 'desc'"
                    class="inline w-4 h-4"
                  />
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Presentación
                </th>
                <th
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  @click="sortBy('categoria')"
                >
                  Categoría
                  <ChevronUp
                    v-if="sortField === 'categoria' && sortOrder === 'asc'"
                    class="inline w-4 h-4"
                  />
                  <ChevronDown
                    v-if="sortField === 'categoria' && sortOrder === 'desc'"
                    class="inline w-4 h-4"
                  />
                </th>
                <th
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  @click="sortBy('stock')"
                >
                  Stock
                  <ChevronUp
                    v-if="sortField === 'stock' && sortOrder === 'asc'"
                    class="inline w-4 h-4"
                  />
                  <ChevronDown
                    v-if="sortField === 'stock' && sortOrder === 'desc'"
                    class="inline w-4 h-4"
                  />
                </th>
                <th
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  @click="sortBy('precio')"
                >
                  Precio
                  <ChevronUp
                    v-if="sortField === 'precio' && sortOrder === 'asc'"
                    class="inline w-4 h-4"
                  />
                  <ChevronDown
                    v-if="sortField === 'precio' && sortOrder === 'desc'"
                    class="inline w-4 h-4"
                  />
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Estado
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Acciones
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr
                v-for="(product, index) in paginatedData"
                :key="getOriginalIndex(index)"
                :class="{
                  'bg-red-50': isRowInvalid(getOriginalIndex(index)),
                  'bg-blue-50': selectedRows.includes(getOriginalIndex(index))
                }"
                class="hover:bg-gray-50 transition-colors"
              >
                <td class="px-6 py-4 whitespace-nowrap">
                  <input
                    type="checkbox"
                    :checked="selectedRows.includes(getOriginalIndex(index))"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    @change="toggleRowSelection(getOriginalIndex(index))"
                  />
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <div v-if="editingRow === getOriginalIndex(index)">
                    <input
                      v-model="editedRow.nombre"
                      class="form-input text-sm"
                    />
                  </div>
                  <div
                    v-else
                    class="cursor-pointer hover:text-blue-600"
                    @dblclick="startEditRow(getOriginalIndex(index))"
                  >
                    {{ product.nombre || '-' }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ product.concentracion || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ product.laboratorio || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ product.presentacion || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ product.categoria || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <div v-if="editingRow === getOriginalIndex(index)">
                    <input
                      v-model.number="editedRow.stock"
                      class="form-input text-sm"
                      type="number"
                    />
                  </div>
                  <div
                    v-else
                    class="cursor-pointer hover:text-blue-600"
                    @dblclick="startEditRow(getOriginalIndex(index))"
                  >
                    {{ product.stock || 0 }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  S/ {{ product.precio || '0.00' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                    :class="getValidationStatus(product, getOriginalIndex(index))"
                  >
                    {{ getValidationText(product, getOriginalIndex(index)) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <div
                    v-if="editingRow === getOriginalIndex(index)"
                    class="flex items-center space-x-2"
                  >
                    <button
                      class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200"
                      @click.prevent="saveEditedRow(getOriginalIndex(index))"
                    >
                      Guardar
                    </button>
                    <button
                      class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200"
                      @click.prevent="cancelEditRow"
                    >
                      Cancelar
                    </button>
                  </div>
                  <div
                    v-else
                    class="flex items-center space-x-2"
                  >
                    <button
                      class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs hover:bg-gray-200"
                      @click.prevent="startEditRow(getOriginalIndex(index))"
                    >
                      Editar
                    </button>
                    <button
                      class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs hover:bg-blue-200"
                      @click.prevent="previewProduct(getOriginalIndex(index))"
                    >
                      Vista
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-700">
              Mostrando {{ (currentPage - 1) * itemsPerPage + 1 }} a {{ Math.min(currentPage * itemsPerPage, filteredData.length) }} de {{ filteredData.length }} productos
            </span>
            <select
              v-model="itemsPerPage"
              class="text-sm border border-gray-300 rounded px-2 py-1"
            >
              <option :value="10">
                10 por página
              </option>
              <option :value="25">
                25 por página
              </option>
              <option :value="50">
                50 por página
              </option>
              <option :value="100">
                100 por página
              </option>
            </select>
          </div>
          <div class="flex items-center space-x-2">
            <button
              :disabled="currentPage === 1"
              class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50 disabled:opacity-50"
              @click="currentPage--"
            >
              Anterior
            </button>
            <span class="text-sm text-gray-700">
              Página {{ currentPage }} de {{ totalPages }}
            </span>
            <button
              :disabled="currentPage === totalPages"
              class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50 disabled:opacity-50"
              @click="currentPage++"
            >
              Siguiente
            </button>
          </div>
        </div>

        <!-- Action Bar -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
              <span class="text-sm text-gray-600">
                {{ selectedRows.length }} productos seleccionados
              </span>
              <button
                v-if="selectedRows.length > 0"
                class="px-3 py-1 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200"
                @click="deleteSelectedRows"
              >
                Eliminar seleccionados
              </button>
            </div>
            <div class="flex space-x-3">
              <button
                class="px-6 py-3 bg-linear-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:from-green-700 hover:to-emerald-700 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 flex items-center disabled:opacity-50"
                :disabled="uploading || validationErrors.length > 0"
                @click="uploadProducts"
              >
                <Upload class="w-5 h-5 mr-2" />
                {{ uploading ? 'Subiendo...' : `Subir ${filteredData.length} Productos` }}
              </button>
              <button
                class="px-6 py-3 bg-linear-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 flex items-center"
                :disabled="uploading || filteredData.length === 0"
                @click="confirmUpload = true"
              >
                <CheckCircle class="w-5 h-5 mr-2" />
                Confirmar y Subir
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Upload Progress -->
      <div
        v-if="uploading"
        class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
      >
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">
              Subiendo Productos
            </h3>
            <span class="text-sm text-gray-500">{{ uploadProgress }}%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2">
            <div
              class="bg-blue-600 h-2 rounded-full transition-all duration-300"
              :style="{ width: uploadProgress + '%' }"
            ></div>
          </div>
          <p class="text-sm text-gray-600">
            {{ uploadedCount }} de {{ parsedData.length }} productos subidos
          </p>
        </div>
      </div>

      <!-- Success/Error Messages -->
      <div
        v-if="uploadResults.success.length > 0 || uploadResults.errors.length > 0"
        class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
      >
        <h3 class="text-lg font-medium text-gray-900 mb-4">
          Resultados de la Carga
        </h3>

        <!-- Success -->
        <div
          v-if="uploadResults.success.length > 0"
          class="mb-4"
        >
          <div class="flex items-center text-green-600 mb-2">
            <CheckCircle class="w-5 h-5 mr-2" />
            <span class="font-medium">{{ uploadResults.success.length }} productos creados exitosamente</span>
          </div>
        </div>

        <!-- Errors -->
        <div v-if="uploadResults.errors.length > 0">
          <div class="flex items-center text-red-600 mb-2">
            <XCircle class="w-5 h-5 mr-2" />
            <span class="font-medium">{{ uploadResults.errors.length }} errores encontrados</span>
          </div>
          <div class="max-h-40 overflow-y-auto">
            <div
              v-for="error in uploadResults.errors"
              :key="error.index"
              class="text-sm text-red-700 py-1"
            >
              Fila {{ error.index + 1 }}: {{ error.message }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirm Upload Modal -->
    <div
      v-if="confirmUpload"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
    >
      <div class="bg-white max-w-lg w-full rounded-lg p-6">
        <h3 class="text-lg font-medium mb-3">
          Confirmar carga
        </h3>
        <p class="text-sm text-gray-700">
          Vas a subir {{ parsedData.length }} productos. ¿Deseas continuar?
        </p>
        <div class="mt-4 flex justify-end space-x-2">
          <button
            class="px-4 py-2 rounded-lg bg-white border"
            @click="confirmUpload = false"
          >
            Cancelar
          </button>
          <button
            class="px-4 py-2 rounded-lg bg-blue-600 text-white"
            @click="doUploadFromConfirm"
          >
            Sí, subir
          </button>
        </div>
      </div>
    </div>
    <!-- Mapping Modal -->
    <div
      v-if="showMappingModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
    >
      <div class="bg-white max-w-2xl w-full rounded-lg p-6">
        <h3 class="text-lg font-medium mb-3">
          Mapear columnas
        </h3>
        <p class="text-sm text-gray-700">
          Relaciona las columnas del archivo con los campos esperados
        </p>
        <div class="mt-4 grid grid-cols-1 gap-3">
          <div
            v-for="field in requiredFields"
            :key="field"
            class="flex items-center justify-between"
          >
            <div class="w-1/3 text-sm text-gray-700 font-medium">
              {{ field }}
            </div>
            <select
              v-model="fieldMapping[field]"
              class="w-2/3 form-input"
            >
              <option value="">
                -- Ninguno --
              </option>
              <option
                v-for="h in csvHeaders"
                :key="h"
                :value="h"
              >
                {{ h }}
              </option>
            </select>
          </div>
        </div>
        <div class="mt-4 flex justify-between">
          <div>
            <button
              class="px-3 py-1 bg-gray-100 rounded"
              @click.prevent="autoMapHeaders(csvHeaders)"
            >
              Auto map
            </button>
          </div>
          <div class="space-x-2">
            <button
              class="px-4 py-2 rounded bg-white border"
              @click.prevent="showMappingModal=false"
            >
              Cancelar
            </button>
            <button
              class="px-4 py-2 rounded bg-blue-600 text-white"
              @click.prevent="() => { applyMapping(); showMappingModal=false }"
            >
              Aplicar y cerrar
            </button>
          </div>
        </div>
      </div>
    </div>
    <!-- Errors Modal -->
    <div
      v-if="showErrorsModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
    >
      <div class="bg-white max-w-4xl w-full rounded-lg p-6 max-h-[80vh] overflow-hidden">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium flex items-center">
            <AlertTriangle class="w-5 h-5 text-red-500 mr-2" />
            Detalles de Errores de Validación
          </h3>
          <button
            class="p-2 hover:bg-gray-100 rounded-lg"
            @click="showErrorsModal = false"
          >
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="overflow-y-auto max-h-96">
          <div class="space-y-2">
            <div
              v-for="error in validationErrors"
              :key="error.row"
              class="flex items-start space-x-3 p-3 bg-red-50 rounded-lg"
            >
              <AlertTriangle class="w-4 h-4 text-red-500 mt-0.5 shrink-0" />
              <div>
                <p class="text-sm font-medium text-red-800">
                  Fila {{ error.row }}
                </p>
                <p class="text-sm text-red-700">
                  {{ error.message }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Product Preview Modal -->
    <div
      v-if="previewProductData"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
    >
      <div class="bg-white max-w-2xl w-full rounded-lg p-6">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-semibold flex items-center">
            <Package class="w-6 h-6 text-blue-600 mr-3" />
            Vista Previa del Producto
          </h3>
          <button
            class="p-2 hover:bg-gray-100 rounded-lg"
            @click="previewProductData = null"
          >
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Nombre</label>
              <p class="mt-1 text-sm text-gray-900 bg-gray-50 p-2 rounded">
                {{ previewProductData.nombre || '-' }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Concentración</label>
              <p class="mt-1 text-sm text-gray-900 bg-gray-50 p-2 rounded">
                {{ previewProductData.concentracion || '-' }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Laboratorio</label>
              <p class="mt-1 text-sm text-gray-900 bg-gray-50 p-2 rounded">
                {{ previewProductData.laboratorio || '-' }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Presentación</label>
              <p class="mt-1 text-sm text-gray-900 bg-gray-50 p-2 rounded">
                {{ previewProductData.presentacion || '-' }}
              </p>
            </div>
          </div>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Tipo</label>
              <p class="mt-1 text-sm text-gray-900 bg-gray-50 p-2 rounded">
                {{ previewProductData.tipo || '-' }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Categoría</label>
              <p class="mt-1 text-sm text-gray-900 bg-gray-50 p-2 rounded">
                {{ previewProductData.categoria || '-' }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Stock</label>
              <p class="mt-1 text-sm text-gray-900 bg-gray-50 p-2 rounded">
                {{ previewProductData.stock || 0 }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Precio</label>
              <p class="mt-1 text-sm text-gray-900 bg-gray-50 p-2 rounded">
                S/ {{ previewProductData.precio || '0.00' }}
              </p>
            </div>
          </div>
        </div>
        <div class="mt-6 flex justify-end">
          <button
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            @click="previewProductData = null"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import {
  Upload,
  CheckCircle,
  AlertTriangle,
  FileText,
  XCircle,
  Package,
  Copy,
  ArrowLeft,
  Download,
  FileSpreadsheet,
  X,
  Search,
  ChevronUp,
  ChevronDown
} from 'lucide-vue-next'
import api from '@/services/api'
import * as XLSX from 'xlsx'
import Papa from 'papaparse'

// Interfaces
interface ProductData {
  nombre: string
  concentracion: string
  adicional: string
  laboratorio: string
  presentacion: string
  tipo: string
  categoria: string
  stock: number
  precio: string
  imagen?: string
}

interface ValidationError {
  row: number
  message: string
}

interface UploadResult {
  success: ProductData[]
  errors: { index: number; message: string }[]
}

// Estado
const file = ref<File | null>(null)
const parsedData = ref<ProductData[]>([])
const validationErrors = ref<ValidationError[]>([])
const uploading = ref(false)
const uploadProgress = ref(0)
const uploadedCount = ref(0)
const uploadResults = ref<UploadResult>({ success: [], errors: [] })
const parsedRawData = ref<Record<string, unknown>[]>([])
const csvHeaders = ref<string[]>([])
const fieldMapping = reactive<Record<string, string>>({})
const showMappingModal = ref(false)
const requiredFields = ['nombre','concentracion','adicional','laboratorio','presentacion','tipo','categoria','stock','precio','imagen']
const duplicateStrategy = ref<'skip'|'overwrite'|'update'>('skip')
const duplicates = ref<Record<string, number[]>>({})
const CHUNK_SIZE = 100
const isDragOver = ref(false)
const confirmUpload = ref(false)
const editingRow = ref<number | null>(null)
const editedRow = ref<Partial<ProductData>>({})

// Nuevas variables para funcionalidades avanzadas
const searchQuery = ref('')
const selectedCategory = ref('')
const selectedLab = ref('')
const selectedRows = ref<number[]>([])
const currentPage = ref(1)
const itemsPerPage = ref(25)
const sortField = ref<string>('nombre')
const sortOrder = ref<'asc' | 'desc'>('asc')
const showErrorsModal = ref(false)
const previewProductData = ref<ProductData | null>(null)

// Computed properties para funcionalidades avanzadas
const filteredData = computed(() => {
  let data = parsedData.value

  // Aplicar búsqueda
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    data = data.filter(product =>
      product.nombre?.toLowerCase().includes(query) ||
      product.laboratorio?.toLowerCase().includes(query) ||
      product.categoria?.toLowerCase().includes(query) ||
      product.presentacion?.toLowerCase().includes(query)
    )
  }

  // Aplicar filtros
  if (selectedCategory.value) {
    data = data.filter(product => product.categoria === selectedCategory.value)
  }

  if (selectedLab.value) {
    data = data.filter(product => product.laboratorio === selectedLab.value)
  }

  // Aplicar ordenamiento
  data.sort((a, b) => {
    let aVal: any = a[sortField.value as keyof ProductData]
    let bVal: any = b[sortField.value as keyof ProductData]

    if (typeof aVal === 'string') aVal = aVal.toLowerCase()
    if (typeof bVal === 'string') bVal = bVal.toLowerCase()

    if (aVal < bVal) return sortOrder.value === 'asc' ? -1 : 1
    if (aVal > bVal) return sortOrder.value === 'asc' ? 1 : -1
    return 0
  })

  return data
})

const uniqueCategories = computed(() => {
  const cats = new Set(parsedData.value.map(p => p.categoria).filter(Boolean))
  return Array.from(cats).sort()
})

const uniqueLabs = computed(() => {
  const labs = new Set(parsedData.value.map(p => p.laboratorio).filter(Boolean))
  return Array.from(labs).sort()
})

const totalPages = computed(() => Math.ceil(filteredData.value.length / itemsPerPage.value))

const paginatedData = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredData.value.slice(start, end)
})

// Watchers para resetear paginación cuando cambian los filtros
watch([searchQuery, selectedCategory, selectedLab], () => {
  currentPage.value = 1
  selectedRows.value = []
})

watch(filteredData, () => {
  if (currentPage.value > totalPages.value) {
    currentPage.value = 1
  }
})

// Validar datos del producto
const validateProduct = (product: unknown, index: number): ValidationError | null => {
  if (!product || typeof product !== 'object') {
    return { row: index + 1, message: 'Producto inválido' }
  }

  const p = product as Record<string, unknown>

  if (!p.nombre || typeof p.nombre !== 'string' || p.nombre.trim() === '') {
    return { row: index + 1, message: 'Nombre es requerido' }
  }

  if (!p.laboratorio || typeof p.laboratorio !== 'string' || p.laboratorio.trim() === '') {
    return { row: index + 1, message: 'Laboratorio es requerido' }
  }

  if (!p.presentacion || typeof p.presentacion !== 'string' || p.presentacion.trim() === '') {
    return { row: index + 1, message: 'Presentación es requerida' }
  }

  if (!p.tipo || typeof p.tipo !== 'string' || p.tipo.trim() === '') {
    return { row: index + 1, message: 'Tipo es requerido' }
  }

  if (!p.categoria || typeof p.categoria !== 'string' || p.categoria.trim() === '') {
    return { row: index + 1, message: 'Categoría es requerida' }
  }

  const stock = Number(p.stock)
  if (isNaN(stock) || stock < 0) {
    return { row: index + 1, message: 'Stock debe ser un número positivo' }
  }

  const precio = parseFloat(String(p.precio))
  if (isNaN(precio) || precio < 0) {
    return { row: index + 1, message: 'Precio debe ser un número positivo' }
  }

  return null
}

// Auto-map headers to required fields using common heuristics
const autoMapHeaders = (headers: string[]) => {
  csvHeaders.value = headers
  const map: Record<string, string> = {}
  const normalized = headers.map((h: unknown) => String(h).toLowerCase().trim())
  const synonyms: Record<string, string[]> = {
    concentracion: ['concentracion', 'concentración', 'concentration'],
    presentacion: ['presentacion', 'presentación', 'presentation'],
    categoria: ['categoria', 'categoría', 'category'],
    precio: ['precio', 'price'],
    nombre: ['nombre', 'name'],
    laboratorio: ['laboratorio', 'lab', 'manufacturer'],
    tipo: ['tipo', 'type']
  }

  requiredFields.forEach((field) => {
    // Find exact match
    let idx = normalized.indexOf(field.toLowerCase())
    if (idx !== -1) {
      map[field] = headers[idx] ?? ''
      return
    }
    // Find by synonym
    const syns = synonyms[field] || []
    for (const s of syns) {
      idx = normalized.indexOf(s)
      if (idx !== -1) { map[field] = headers[idx] ?? ''; return }
    }
    // Find by includes
    idx = normalized.findIndex(h => h.includes(field.toLowerCase()))
    if (idx !== -1) { map[field] = headers[idx] ?? ''; return }
  })

  // apply to reactive fieldMapping object
  Object.keys(fieldMapping).forEach(k => delete fieldMapping[k])
  Object.entries(map).forEach(([k, v]) => { fieldMapping[k] = v })
}

// Apply mapping to raw data and build parsedData
const applyMapping = () => {
  const data: ProductData[] = parsedRawData.value.map((row) => {
    const product: ProductData = {
      nombre: '', concentracion: '', adicional: '', laboratorio: '', presentacion: '', tipo: '', categoria: '', stock: 0, precio: '0.00'
    }
    requiredFields.forEach((field) => {
      const mappedHeader = fieldMapping[field] || field
      const headerKey = String(mappedHeader)
      const rawValue = (row as Record<string, unknown>)[headerKey] ?? (row as Record<string, unknown>)[headerKey.toLowerCase?.()] ?? (row as Record<string, unknown>)[headerKey.toUpperCase?.()] ?? ''
      const v = rawValue === null || rawValue === undefined ? '' : String(rawValue)
      switch (field) {
        case 'nombre': product.nombre = v || '' ; break
        case 'concentracion': product.concentracion = v || ''; break
        case 'adicional': product.adicional = v || ''; break
        case 'laboratorio': product.laboratorio = v || ''; break
        case 'presentacion': product.presentacion = v || ''; break
        case 'tipo': product.tipo = v || ''; break
        case 'categoria': product.categoria = v || ''; break
        case 'stock': product.stock = parseInt(v) || 0; break
        case 'precio': product.precio = String(v || '0.00'); break
        case 'imagen': product.imagen = v || undefined; break
      }
    })
    return product
  })
  parsedData.value = data
  validationErrors.value = data.map((p, i) => validateProduct(p, i)).filter((e): e is ValidationError => e !== null)
  // detect duplicates
  detectDuplicates()
}

const detectDuplicates = () => {
  const map = new Map<string, number[]>()
  parsedData.value.forEach((p, i) => {
    const key = `${p.nombre.trim().toLowerCase()}|${p.laboratorio?.trim().toLowerCase() || ''}`
    const arr = map.get(key) || []
    arr.push(i)
    map.set(key, arr)
  })
  const dups: Record<string, number[]> = {}
  map.forEach((arr, key) => {
    if (arr.length > 1) dups[key] = arr
  })
  duplicates.value = dups
}

// Parsear archivo CSV
const parseCSV = (content: string): ProductData[] => {
  const result = Papa.parse(content, { header: true, skipEmptyLines: true, dynamicTyping: true })
  const headers = (result.meta.fields || []).map((h: string) => String(h).trim())
  csvHeaders.value = headers
  parsedRawData.value = result.data as Record<string, unknown>[]
  // Do an auto-map attempt
  autoMapHeaders(headers)
  const mappingCompleteCsv = requiredFields.every(f => !!fieldMapping[f])
  if (mappingCompleteCsv) {
    applyMapping()
    return parsedData.value
  }
  // We could return [] for now and let the mapping modal be shown
  return []
}

// Parsear archivo Excel
const parseExcel = async (file: File): Promise<ProductData[]> => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = (e) => {
      try {
        const data = new Uint8Array(e.target?.result as ArrayBuffer)
        const workbook = XLSX.read(data, { type: 'array' })
        const sheetName = workbook.SheetNames[0]
        if (!sheetName) return []

        const worksheet = workbook.Sheets[sheetName]
        if (!worksheet) return []

        const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 })

        if (jsonData.length < 2) {
          resolve([])
          return
        }

          const headers = (jsonData[0] as string[]).map((h: unknown) => String(h).trim())
        csvHeaders.value = headers
        parsedRawData.value = jsonData.slice(1).map((row) => {
          const r = row as unknown[]
          const obj: Record<string, unknown> = {}
          headers.forEach((h, i) => { obj[h] = r[i] ?? '' })
          return obj
        })
        // Auto map headers
        autoMapHeaders(headers)
        const products: ProductData[] = []

        // Use parsedRawData and mapping to construct products
        const mappingCompleteExcel = requiredFields.every(f => !!fieldMapping[f])
        if (mappingCompleteExcel) {
          applyMapping()
          resolve(parsedData.value)
          return
        }
        // Otherwise, build simple products (best-effort) and ask mapping
        for (let i = 0; i < parsedRawData.value.length; i++) {
          const row = parsedRawData.value[i] as Record<string, unknown>
          const product: ProductData = {
            nombre: '',
            concentracion: '',
            adicional: '',
            laboratorio: '',
            presentacion: '',
            tipo: '',
            categoria: '',
            stock: 0,
            precio: '0.00'
          }

          headers.forEach((header) => {
            const rawValue = (row as Record<string, unknown>)[header]
            const value = rawValue === null || rawValue === undefined ? '' : String(rawValue)
            switch (header) {
              case 'nombre':
                product.nombre = value
                break
              case 'concentracion':
              case 'concentración':
                product.concentracion = value
                break
              case 'adicional':
                product.adicional = value
                break
              case 'laboratorio':
                product.laboratorio = value
                break
              case 'presentacion':
              case 'presentación':
                product.presentacion = value
                break
              case 'tipo':
                product.tipo = value
                break
              case 'categoria':
              case 'categoría':
                product.categoria = value
                break
              case 'stock':
                product.stock = parseInt(value) || 0
                break
              case 'precio':
                product.precio = value || '0.00'
                break
              case 'imagen':
                product.imagen = value
                break
            }
          })

          products.push(product)
        }
        // If we didn't apply mapping, populate parsedData with best-effort products
        parsedData.value = products
        validationErrors.value = products.map((p, i) => validateProduct(p, i)).filter((e): e is ValidationError => e !== null)
        detectDuplicates()
        const mappingComplete = requiredFields.every(f => !!fieldMapping[f])
        if (!mappingComplete) showMappingModal.value = true
        resolve(parsedData.value)
      } catch (error) {
        reject(error)
      }
    }
    reader.onerror = () => reject(new Error('Error reading file'))
    reader.readAsArrayBuffer(file)
  })
}

// Manejar selección de archivo
const handleFileSelect = async (event: Event) => {
  const target = event.target as HTMLInputElement
  const selectedFile = target.files?.[0]

  if (!selectedFile) return

  // Validar tipo de archivo
  const allowedTypes = ['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel']
  if (!allowedTypes.includes(selectedFile.type) && !selectedFile.name.endsWith('.csv') && !selectedFile.name.endsWith('.xlsx') && !selectedFile.name.endsWith('.xls')) {
    alert('Por favor selecciona un archivo CSV o Excel válido')
    return
  }

  // Validar tamaño (10MB máximo)
  if (selectedFile.size > 10 * 1024 * 1024) {
    alert('El archivo no puede ser mayor a 10MB')
    return
  }

  file.value = selectedFile
  parsedData.value = []
  validationErrors.value = []

  try {
    let data: ProductData[] = []

    if (selectedFile.name.endsWith('.csv') || selectedFile.type === 'text/csv') {
      const content = await selectedFile.text()
      data = parseCSV(content)
    } else {
      data = await parseExcel(selectedFile)
    }

    // If parsedData is empty but we have raw data, user must map columns
    if (parsedRawData.value.length > 0 && parsedData.value.length === 0) {
      showMappingModal.value = true
      // Do not set parsedData yet; the mapping modal will populate it
    } else {
      parsedData.value = data
      // Validar datos
      validationErrors.value = data
        .map((product, index) => validateProduct(product, index))
        .filter((error): error is ValidationError => error !== null)
      detectDuplicates()
    }

  } catch (error: unknown) {
    console.error('Error parsing file:', error)
    alert('Error al procesar el archivo. Verifica que el formato sea correcto.')
  }
}

const onDragOver = () => {
  isDragOver.value = true
}

const onDragLeave = () => {
  isDragOver.value = false
}

const handleDrop = async (event: DragEvent) => {
  isDragOver.value = false
  const dt = event.dataTransfer
  const droppedFile = dt?.files?.[0]
  if (!droppedFile) return
  // re-use file selection handler
  const fakeEvent = { target: { files: dt?.files } } as unknown as Event
  await handleFileSelect(fakeEvent)
}

const removeFile = () => {
  file.value = null
  parsedData.value = []
  validationErrors.value = []
}

const startEditRow = (index: number) => {
  editingRow.value = index
  editedRow.value = { ...parsedData.value[index] }
}

const saveEditedRow = (index: number) => {
  if (editingRow.value === null) return
  parsedData.value[index] = { ...parsedData.value[index], ...(editedRow.value as ProductData) }
  // revalidate the row
  const err = validateProduct(parsedData.value[index], index)
  validationErrors.value = validationErrors.value.filter(e => e.row !== index + 1)
  if (err) validationErrors.value.push(err)
  editingRow.value = null
  editedRow.value = {}
}

const cancelEditRow = () => {
  editingRow.value = null
  editedRow.value = {}
}

// Nuevas funciones para funcionalidades avanzadas
const sortBy = (field: string) => {
  if (sortField.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortOrder.value = 'asc'
  }
}

const toggleSelectAll = () => {
  if (selectedRows.value.length === filteredData.value.length) {
    selectedRows.value = []
  } else {
    selectedRows.value = filteredData.value.map((_, index) => getOriginalIndex(index))
  }
}

const toggleRowSelection = (index: number) => {
  const idx = selectedRows.value.indexOf(index)
  if (idx > -1) {
    selectedRows.value.splice(idx, 1)
  } else {
    selectedRows.value.push(index)
  }
}

const getOriginalIndex = (paginatedIndex: number): number => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  return start + paginatedIndex
}

const deleteSelectedRows = () => {
  if (confirm('¿Estás seguro de que quieres eliminar las filas seleccionadas?')) {
    // Remove from parsedData in reverse order to maintain indices
    selectedRows.value.sort((a, b) => b - a).forEach(index => {
      parsedData.value.splice(index, 1)
    })
    selectedRows.value = []
    // Re-validate and detect duplicates
    validationErrors.value = parsedData.value.map((p, i) => validateProduct(p, i)).filter((e): e is ValidationError => e !== null)
    detectDuplicates()
  }
}

const exportFilteredData = () => {
  const data = filteredData.value.map(product => ({
    Nombre: product.nombre,
    Concentracion: product.concentracion,
    Adicional: product.adicional,
    Laboratorio: product.laboratorio,
    Presentacion: product.presentacion,
    Tipo: product.tipo,
    Categoria: product.categoria,
    Stock: product.stock,
    Precio: product.precio,
    Imagen: product.imagen
  }))

  const ws = XLSX.utils.json_to_sheet(data)
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Productos Filtrados')
  XLSX.writeFile(wb, 'productos_filtrados.xlsx')
}

const previewProduct = (index: number) => {
  const product = parsedData.value[index]
  if (product) {
    previewProductData.value = product
  }
}

const isRowInvalid = (index: number) => {
  return validationErrors.value.some(e => e.row === index + 1)
}

// Confirm upload from modal
const doUploadFromConfirm = async () => {
  confirmUpload.value = false
  await uploadProducts()
}

// Descargar plantilla
const downloadTemplate = (type: 'xlsx' | 'csv' = 'xlsx') => {
  const headers = ['Nombre', 'Concentracion', 'Adicional', 'Laboratorio', 'Presentacion', 'Tipo', 'Categoria', 'Stock', 'Precio']
  const sampleData = [
    ['Paracetamol', '500mg', '', 'Genfar', 'Tabletas', 'Analgésico', 'Medicamentos', '100', '5.50'],
    ['Ibuprofeno', '400mg', '', 'MK', 'Cápsulas', 'Antiinflamatorio', 'Medicamentos', '50', '8.75']
  ]

  if (type === 'xlsx') {
    const ws = XLSX.utils.aoa_to_sheet([headers, ...sampleData])
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, 'Productos')
    XLSX.writeFile(wb, 'plantilla_productos.xlsx')
  } else {
    const csvRows = [headers.join(','), ...sampleData.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""') }"`).join(','))]
    const csvContent = csvRows.join('\n')
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'plantilla_productos.csv'
    a.click()
    URL.revokeObjectURL(url)
  }
}

// Subir productos
const uploadProducts = async () => {
  if (parsedData.value.length === 0) return
  if (validationErrors.value.length > 0) {
    alert('Por favor corrige los errores de validación antes de subir los productos')
    return
  }

  uploading.value = true
  uploadProgress.value = 0
  uploadedCount.value = 0
  uploadResults.value = { success: [], errors: [] }

  try {
    // Preparar datos para envío bulk
    let productosData = parsedData.value.map(product => ({
      nombre: product.nombre,
      concentracion: product.concentracion,
      adicional: product.adicional || '',
      laboratorio: product.laboratorio,
      presentacion: product.presentacion,
      tipo: product.tipo,
      categoria: product.categoria,
      stock: product.stock,
      precio: product.precio,
      imagen: product.imagen || 'images/default_image.png'
    }))

    // Handle duplicates client-side for 'skip' strategy
    if (duplicateStrategy.value === 'skip') {
      const seen = new Set<string>()
      productosData = productosData.filter((p) => {
        const key = `${p.nombre?.trim().toLowerCase()}|${p.laboratorio?.trim().toLowerCase() || ''}`
        if (seen.has(key)) return false
        seen.add(key)
        return true
      })
    }

    const total = productosData.length
    const chunks: ProductData[][] = []
    for (let i = 0; i < total; i += CHUNK_SIZE) {
      chunks.push(productosData.slice(i, i + CHUNK_SIZE))
    }

    for (let chunkIndex = 0; chunkIndex < chunks.length; chunkIndex++) {
      const chunk = chunks[chunkIndex]!
      // send chunk
      const response = await api.post('/productos/bulk', { productos: chunk })
      // server returns productos created and errors; union results
      const successArr = response.data.productos || []
      const errorsArr: { index?: number; message?: string }[] = response.data.errors || []
      // Append to global results
      uploadResults.value.success.push(...successArr)
      // Map any local errors, adjusting indexes to global index if provided as relative
      if (errorsArr.length > 0) {
        errorsArr.forEach((err) => {
          // if err.index exists and is relative to chunk, add offset
          const offset = chunkIndex * CHUNK_SIZE
          const globalIndex = (typeof err.index === 'number') ? (offset + err.index) : -1
          uploadResults.value.errors.push({ index: globalIndex, message: err.message || 'Error' })
        })
      }
      uploadedCount.value += chunk.length
      uploadProgress.value = Math.round((uploadedCount.value / total) * 100)
    }

  } catch (error: unknown) {
    console.error('Error uploading products:', error)
    uploadResults.value.errors.push({ index: -1, message: error instanceof Error ? error.message : 'Error desconocido al subir productos' })
  } finally {
    uploading.value = false
  }

  // Mostrar resumen
  if (uploadResults.value.errors.length === 0) {
    alert(`¡Éxito! Se han creado ${uploadResults.value.success.length} productos.`)
  } else {
    alert(`Se completó la carga. ${uploadResults.value.success.length} productos creados, ${uploadResults.value.errors.length} errores.`)
  }
}

// Helpers para UI
const getValidationStatus = (product: ProductData, index: number) => {
  const hasError = validationErrors.value.some(error => error.row === index + 1)
  return hasError
    ? 'bg-red-100 text-red-800'
    : 'bg-green-100 text-green-800'
}

const getValidationText = (product: ProductData, index: number) => {
  const hasError = validationErrors.value.some(error => error.row === index + 1)
  return hasError ? 'Error' : 'Válido'
}

const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}
</script>