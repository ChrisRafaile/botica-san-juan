<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-300"
      leave-active-class="transition-opacity duration-300"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm"
        @click.self="closeModal"
      >
        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-4"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition-all duration-300 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-95 translate-y-4"
        >
          <div
            v-if="isOpen"
            class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden"
          >
            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
              <div>
                <h2 class="text-2xl font-bold text-gray-900">
                  {{ props.readonly ? 'Detalles del Producto' : (isEditing ? 'Editar Producto' : 'Nuevo Producto') }}
                </h2>
                <p class="text-gray-600 mt-1">
                  {{ props.readonly ? 'Información completa del producto' : (isEditing ? 'Modifica la información del producto' : 'Agrega un nuevo producto al catálogo') }}
                </p>
              </div>
              <button
                class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                @click="closeModal"
              >
                <X class="w-6 h-6 text-gray-500" />
              </button>
            </div>

            <!-- Form -->
            <form
              class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]"
              @submit.prevent="handleSubmit"
            >
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Columna Izquierda -->
                <div class="space-y-6">
                  <!-- Nombre -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Nombre del Producto *
                    </label>
                    <input
                      v-model="form.nombre"
                      type="text"
                      required
                      :disabled="props.readonly"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                      placeholder="Ej: Paracetamol 500mg"
                    />
                    <p
                      v-if="errors.nombre"
                      class="mt-1 text-sm text-red-600"
                    >
                      {{ errors.nombre }}
                    </p>
                  </div>

                  <!-- Concentración -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Concentración
                    </label>
                    <input
                      v-model="form.concentracion"
                      type="text"
                      :disabled="props.readonly"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                      placeholder="Ej: 500mg"
                    />
                  </div>

                  <!-- Laboratorio -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Laboratorio *
                    </label>
                    <input
                      v-model="form.laboratorio"
                      type="text"
                      required
                      :disabled="props.readonly"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                      placeholder="Ej: Pfizer"
                    />
                    <p
                      v-if="errors.laboratorio"
                      class="mt-1 text-sm text-red-600"
                    >
                      {{ errors.laboratorio }}
                    </p>
                  </div>

                  <!-- Presentación -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Presentación *
                    </label>
                    <input
                      v-model="form.presentacion"
                      type="text"
                      required
                      :disabled="props.readonly"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                      placeholder="Ej: Tabletas"
                    />
                    <p
                      v-if="errors.presentacion"
                      class="mt-1 text-sm text-red-600"
                    >
                      {{ errors.presentacion }}
                    </p>
                  </div>

                  <!-- Tipo -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Categoría
                    </label>
                    <select
                      v-model="form.categoria_id"
                      :disabled="props.readonly"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                    >
                      <option value="">
                        Sin categoría
                      </option>
                      <option
                        v-for="category in (props.categories || [])"
                        :key="category.id"
                        :value="String(category.id)"
                      >
                        {{ category.name }}
                      </option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Subcategoría
                    </label>
                    <select
                      v-model="form.subcategoria_id"
                      :disabled="props.readonly || !form.categoria_id"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                    >
                      <option value="">
                        {{ form.categoria_id ? 'Sin subcategoría' : 'Primero selecciona una categoría' }}
                      </option>
                      <option
                        v-for="subcategory in filteredSubcategories"
                        :key="subcategory.id"
                        :value="String(subcategory.id)"
                      >
                        {{ subcategory.name }}
                      </option>
                    </select>
                  </div>

                  <!-- Tipo -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Tipo de Producto *
                    </label>
                    <select
                      v-model="form.tipo"
                      required
                      :disabled="props.readonly"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                    >
                      <option value="">
                        Seleccionar tipo
                      </option>
                      <option value="medicamentos">
                        Medicamentos
                      </option>
                      <option value="suplementos">
                        Suplementos
                      </option>
                      <option value="higiene">
                        Higiene Personal
                      </option>
                      <option value="bebes">
                        Productos para Bebés
                      </option>
                      <option value="otros">
                        Otros
                      </option>
                    </select>
                    <p
                      v-if="errors.tipo"
                      class="mt-1 text-sm text-red-600"
                    >
                      {{ errors.tipo }}
                    </p>
                  </div>
                </div>

                <!-- Columna Derecha -->
                <div class="space-y-6">
                  <!-- Precio -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Precio (S/) *
                    </label>
                    <input
                      v-model.number="form.precio"
                      type="number"
                      step="0.01"
                      min="0"
                      required
                      :disabled="props.readonly"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                      placeholder="0.00"
                    />
                    <p
                      v-if="errors.precio"
                      class="mt-1 text-sm text-red-600"
                    >
                      {{ errors.precio }}
                    </p>
                  </div>

                  <div class="rounded-lg border border-gray-200 p-4 space-y-3">
                    <p class="text-sm font-semibold text-gray-800">
                      Unidades Multiples (Fase 4)
                    </p>

                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">
                        Unidad Base
                      </label>
                      <select
                        v-model="form.unidad_base"
                        :disabled="props.readonly"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                      >
                        <option value="unidad">
                          Unidad
                        </option>
                        <option value="blister">
                          Blister
                        </option>
                        <option value="caja">
                          Caja
                        </option>
                      </select>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                      <input
                        v-model="form.venta_fraccionada"
                        type="checkbox"
                        :disabled="props.readonly"
                        class="h-4 w-4"
                      />
                      Permitir venta por fraccion
                    </label>

                    <div
                      v-if="form.venta_fraccionada"
                      class="grid grid-cols-1 md:grid-cols-2 gap-3"
                    >
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                          Unidades por blister
                        </label>
                        <input
                          v-model.number="form.unidades_por_blister"
                          type="number"
                          min="1"
                          :disabled="props.readonly"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                        />
                      </div>

                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                          Blisters por caja
                        </label>
                        <input
                          v-model.number="form.blisters_por_caja"
                          type="number"
                          min="1"
                          :disabled="props.readonly"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                        />
                      </div>

                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                          Precio por blister (S/)
                        </label>
                        <input
                          v-model.number="form.precio_blister"
                          type="number"
                          step="0.01"
                          min="0"
                          :disabled="props.readonly"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                        />
                      </div>

                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                          Precio por caja (S/)
                        </label>
                        <input
                          v-model.number="form.precio_caja"
                          type="number"
                          step="0.01"
                          min="0"
                          :disabled="props.readonly"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                        />
                      </div>
                    </div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Código DIGEMID
                    </label>
                    <input
                      v-model="form.codigo_digemid"
                      type="text"
                      :disabled="props.readonly"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                      placeholder="Código regulatorio"
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Principio Activo
                    </label>
                    <input
                      v-model="form.principio_activo"
                      type="text"
                      :disabled="props.readonly"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                      placeholder="Ej: Paracetamol"
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Laboratorio Fabricante
                    </label>
                    <input
                      v-model="form.laboratorio_fabricante"
                      type="text"
                      :disabled="props.readonly"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                      placeholder="Laboratorio fabricante"
                    />
                  </div>

                  <div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 mt-2">
                      <input
                        v-model="form.requiere_receta"
                        type="checkbox"
                        :disabled="props.readonly"
                        class="h-4 w-4"
                      />
                      Requiere receta médica
                    </label>
                  </div>

                  <!-- Stock -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Stock *
                    </label>
                    <input
                      v-model.number="form.stock"
                      type="number"
                      min="0"
                      required
                      :disabled="props.readonly"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all disabled:bg-gray-100 disabled:cursor-not-allowed"
                      placeholder="0"
                    />
                    <p
                      v-if="errors.stock"
                      class="mt-1 text-sm text-red-600"
                    >
                      {{ errors.stock }}
                    </p>
                  </div>

                  <!-- Imagen -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Imagen del Producto
                    </label>
                    <div class="space-y-3">
                      <!-- Preview de imagen actual -->
                      <div
                        v-if="currentImage"
                        class="relative"
                      >
                        <img
                          :src="currentImage"
                          alt="Producto"
                          class="w-full h-32 object-cover rounded-lg border border-gray-200"
                        />
                        <button
                          type="button"
                          class="absolute top-2 right-2 bg-red-500 text-white p-1 rounded-full hover:bg-red-600 transition-colors"
                          @click="removeImage"
                        >
                          <X class="w-4 h-4" />
                        </button>
                      </div>

                      <!-- Botones de carga de imagen -->
                      <div class="flex gap-2">
                        <div class="flex-1 border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-400 transition-colors">
                          <input
                            ref="fileInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="handleFileChange"
                          />
                          <div
                            class="cursor-pointer"
                            :class="{ 'cursor-not-allowed opacity-50': props.readonly }"
                            @click="props.readonly ? null : fileInput?.click()"
                          >
                            <Upload class="w-6 h-6 text-gray-400 mx-auto mb-2" />
                            <p class="text-xs text-gray-600">
                              Subir archivo
                            </p>
                          </div>
                        </div>
                        <button
                          type="button"
                          class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                          :disabled="props.readonly"
                          @click="props.readonly ? null : openPhotoCapture()"
                        >
                          <Camera class="w-4 h-4" />
                          Tomar Foto
                        </button>
                      </div>
                      <p class="text-xs text-gray-500">
                        PNG, JPG hasta 5MB
                      </p>
                    </div>
                  </div>

                  <!-- Información adicional -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Información Adicional
                    </label>
                    <textarea
                      v-model="form.adicional"
                      rows="3"
                      :disabled="props.readonly"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none disabled:bg-gray-100 disabled:cursor-not-allowed"
                      placeholder="Información adicional del producto..."
                    ></textarea>
                  </div>
                </div>
              </div>

              <!-- Loading state -->
              <div
                v-if="loading"
                class="flex items-center justify-center py-8"
              >
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <span class="ml-3 text-gray-600">
                  {{ isEditing ? 'Actualizando producto...' : 'Creando producto...' }}
                </span>
              </div>
            </form>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-200 bg-gray-50">
              <button
                type="button"
                class="px-6 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors font-medium"
                @click="closeModal"
              >
                {{ props.readonly ? 'Cerrar' : 'Cancelar' }}
              </button>
              <button
                v-if="!props.readonly"
                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium flex items-center gap-2"
                :disabled="loading || !isFormValid"
                @click="handleSubmit"
              >
                <Save
                  v-if="!loading"
                  class="w-4 h-4"
                />
                <div
                  v-else
                  class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"
                ></div>
                {{ isEditing ? 'Actualizar' : 'Crear' }} Producto
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- Photo Capture Modal -->
    <Transition
      enter-active-class="transition-opacity duration-300"
      leave-active-class="transition-opacity duration-300"
    >
      <div
        v-if="showPhotoCapture"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm"
        @click.self="showPhotoCapture = false"
      >
        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-4"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition-all duration-300 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-95 translate-y-4"
        >
          <div
            v-if="showPhotoCapture"
            class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 p-6"
          >
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-semibold text-gray-900">
                Tomar Foto
              </h3>
              <button
                class="p-1 hover:bg-gray-100 rounded-lg transition-colors"
                @click="showPhotoCapture = false"
              >
                <X class="w-5 h-5 text-gray-500" />
              </button>
            </div>
            <PhotoCapture
              @photo-captured="onPhotoCaptured"
              @error="onPhotoCaptureError"
            />
            <!-- Debug: PhotoCapture component rendered -->
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { X, Upload, Save, Camera } from 'lucide-vue-next'
import api from '@/services/api'
import PhotoCapture from '@/components/PhotoCapture.vue'

// Props
interface Props {
  isOpen: boolean
  product?: Product | null
  categories?: Category[]
  subcategories?: Subcategory[]
  readonly?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  product: null,
  readonly: false
})

// Emits
const emit = defineEmits<{
  close: []
  saved: [product: Product]
}>()

// Interfaces
interface Product {
  id: number
  nombre: string
  concentracion: string
  adicional: string
  laboratorio: string
  presentacion: string
  tipo: string
  categoria_id?: number | null
  subcategoria_id?: number | null
  codigo_digemid?: string | null
  principio_activo?: string | null
  requiere_receta?: boolean
  laboratorio_fabricante?: string | null
  unidad_base?: 'unidad' | 'blister' | 'caja'
  venta_fraccionada?: boolean
  unidades_por_blister?: number | null
  blisters_por_caja?: number | null
  precio_blister?: string | number | null
  precio_caja?: string | number | null
  stock: number
  precio: string
  imagen?: string
  created_at?: string
  updated_at?: string
}

interface Category {
  id: number
  name: string
}

interface Subcategory {
  id: number
  categoria_id: number
  name: string
}

// Form state
const form = ref({
  nombre: '',
  concentracion: '',
  adicional: '',
  laboratorio: '',
  presentacion: '',
  categoria_id: '',
  subcategoria_id: '',
  tipo: '',
  codigo_digemid: '',
  principio_activo: '',
  requiere_receta: false,
  laboratorio_fabricante: '',
  unidad_base: 'unidad',
  venta_fraccionada: false,
  unidades_por_blister: 1,
  blisters_por_caja: 1,
  precio_blister: '',
  precio_caja: '',
  stock: 0,
  precio: ''
})

const errors = ref<Record<string, string>>({})
const loading = ref(false)
const fileInput = ref<HTMLInputElement>()
const selectedFile = ref<File | null>(null)
const currentImage = ref<string>('')
const showPhotoCapture = ref(false)
const hydratingForm = ref(false)

// Computed
const isEditing = computed(() => !!props.product)
const isFormValid = computed(() => {
  return form.value.nombre.trim() &&
         form.value.laboratorio.trim() &&
         form.value.presentacion.trim() &&
         form.value.tipo &&
         form.value.stock >= 0 &&
         Number(form.value.precio) > 0
})

const filteredSubcategories = computed(() => {
  if (!form.value.categoria_id) return []
  const categoryId = Number(form.value.categoria_id)
  return (props.subcategories || []).filter(subcategory => subcategory.categoria_id === categoryId)
})

// Methods
const resetForm = () => {
  form.value = {
    nombre: '',
    concentracion: '',
    adicional: '',
    laboratorio: '',
    presentacion: '',
    categoria_id: '',
    subcategoria_id: '',
    tipo: '',
    codigo_digemid: '',
    principio_activo: '',
    requiere_receta: false,
    laboratorio_fabricante: '',
    unidad_base: 'unidad',
    venta_fraccionada: false,
    unidades_por_blister: 1,
    blisters_por_caja: 1,
    precio_blister: '',
    precio_caja: '',
    stock: 0,
    precio: ''
  }
  errors.value = {}
  selectedFile.value = null
  currentImage.value = ''
}

const loadProduct = () => {
  if (props.product) {
    hydratingForm.value = true
    form.value = {
      nombre: props.product.nombre,
      concentracion: props.product.concentracion,
      adicional: props.product.adicional,
      laboratorio: props.product.laboratorio,
      presentacion: props.product.presentacion,
      categoria_id: props.product.categoria_id ? String(props.product.categoria_id) : '',
      subcategoria_id: props.product.subcategoria_id ? String(props.product.subcategoria_id) : '',
      tipo: props.product.tipo,
      codigo_digemid: props.product.codigo_digemid || '',
      principio_activo: props.product.principio_activo || '',
      requiere_receta: Boolean(props.product.requiere_receta),
      laboratorio_fabricante: props.product.laboratorio_fabricante || '',
      unidad_base: props.product.unidad_base || 'unidad',
      venta_fraccionada: Boolean(props.product.venta_fraccionada),
      unidades_por_blister: props.product.unidades_por_blister || 1,
      blisters_por_caja: props.product.blisters_por_caja || 1,
      precio_blister: props.product.precio_blister !== null && props.product.precio_blister !== undefined
        ? String(props.product.precio_blister)
        : '',
      precio_caja: props.product.precio_caja !== null && props.product.precio_caja !== undefined
        ? String(props.product.precio_caja)
        : '',
      stock: props.product.stock,
      precio: props.product.precio
    }
    currentImage.value = props.product.imagen || ''
    nextTick(() => {
      hydratingForm.value = false
    })
  }
}

const validateForm = () => {
  errors.value = {}

  if (!form.value.nombre.trim()) {
    errors.value.nombre = 'El nombre es requerido'
  }

  if (!form.value.laboratorio.trim()) {
    errors.value.laboratorio = 'El laboratorio es requerido'
  }

  if (!form.value.presentacion.trim()) {
    errors.value.presentacion = 'La presentación es requerida'
  }

  if (!form.value.tipo) {
    errors.value.tipo = 'El tipo es requerido'
  }

  if (form.value.stock < 0) {
    errors.value.stock = 'El stock no puede ser negativo'
  }

  if (Number(form.value.precio) <= 0) {
    errors.value.precio = 'El precio debe ser mayor a 0'
  }

  return Object.keys(errors.value).length === 0
}

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]

  if (file) {
    // Validar tamaño (5MB máximo)
    if (file.size > 5 * 1024 * 1024) {
      alert('La imagen no puede superar los 5MB')
      return
    }

    // Validar tipo
    if (!file.type.startsWith('image/')) {
      alert('Solo se permiten archivos de imagen')
      return
    }

    selectedFile.value = file

    // Crear preview
    const reader = new FileReader()
    reader.onload = (e) => {
      currentImage.value = e.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

const removeImage = () => {
  selectedFile.value = null
  currentImage.value = ''
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const onPhotoCaptured = (photoData: string) => {
  // Convertir data URL a File
  fetch(photoData)
    .then(res => res.blob())
    .then(blob => {
      const file = new File([blob], 'captured-photo.jpg', { type: 'image/jpeg' })
      selectedFile.value = file
      currentImage.value = photoData
      showPhotoCapture.value = false
    })
    .catch(err => {
      console.error('Error converting photo data:', err)
      alert('Error al procesar la foto capturada')
    })
}

const onPhotoCaptureError = (error: string) => {
  console.error('Photo capture error:', error)
  alert('Error al capturar foto: ' + error)
}

const openPhotoCapture = () => {
  showPhotoCapture.value = true
}

const handleSubmit = async () => {
  if (!validateForm()) return

  loading.value = true

  try {
    const formData = new FormData()

    // Agregar campos del formulario
    Object.entries(form.value).forEach(([key, value]) => {
      if ((key === 'categoria_id' || key === 'subcategoria_id') && value === '') {
        return
      }
      if (value !== null && value !== undefined) {
        formData.append(key, value.toString())
      }
    })

    // Agregar imagen si existe
    if (selectedFile.value) {
      formData.append('imagen', selectedFile.value)
    }

    let response
    if (isEditing.value) {
      response = await api.put(`/productos/${props.product!.id}`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })
    } else {
      response = await api.post('/productos', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })
    }

    emit('saved', response.data)
    closeModal()
  } catch (error: unknown) {
    console.error('Error saving product:', error)

    const axiosError = error as { response?: { data?: { errors?: Record<string, string> } } }
    if (axiosError.response?.data?.errors) {
      errors.value = axiosError.response.data.errors
    } else {
      alert('Error al guardar el producto. Inténtalo de nuevo.')
    }
  } finally {
    loading.value = false
  }
}

const closeModal = () => {
  emit('close')
}

watch(() => form.value.categoria_id, (newCategory, oldCategory) => {
  if (hydratingForm.value) return
  if (newCategory !== oldCategory) {
    form.value.subcategoria_id = ''
  }
})

// Watchers
watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    if (isEditing.value) {
      loadProduct()
    } else {
      resetForm()
    }
  }
})

watch(() => props.product, () => {
  if (props.isOpen && isEditing.value) {
    loadProduct()
  }
})
</script>