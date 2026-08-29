<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-300"
      leave-from-class="opacity-0"
      leave-to-class="opacity-100"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm"
        @click="closeOnBackdrop"
      >
        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-4"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition-all duration-200 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-95 translate-y-4"
        >
          <div
            v-if="isOpen"
            class="relative w-full max-w-md mx-4 bg-white rounded-2xl shadow-2xl overflow-hidden"
            @click.stop
          >
            <!-- Header -->
            <div class="relative px-6 py-4 bg-linear-to-r from-blue-600 to-purple-600 text-white">
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                  <div class="p-2 bg-white bg-opacity-20 rounded-lg">
                    <Tag class="w-6 h-6" />
                  </div>
                  <div>
                    <h3 class="text-lg font-semibold">
                      {{ isEditing ? 'Editar Categoría' : 'Nueva Categoría' }}
                    </h3>
                    <p class="text-sm text-blue-100">
                      {{ isEditing ? 'Modifica los detalles de la categoría' : 'Crea una nueva categoría para tus productos' }}
                    </p>
                  </div>
                </div>
                <button
                  class="p-1 hover:bg-white hover:bg-opacity-20 rounded-lg transition-colors"
                  @click="close"
                >
                  <X class="w-5 h-5" />
                </button>
              </div>
            </div>

            <!-- Form -->
            <form
              class="p-6 space-y-6"
              @submit.prevent="handleSubmit"
            >
              <!-- Nombre -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Nombre de la Categoría *
                </label>
                <input
                  v-model="form.name"
                  type="text"
                  required
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                  placeholder="Ej: Medicamentos, Suplementos, Higiene..."
                  :class="{ 'border-red-300 focus:ring-red-500': errors.name }"
                />
                <p
                  v-if="errors.name"
                  class="mt-1 text-sm text-red-600"
                >
                  {{ errors.name }}
                </p>
              </div>

              <!-- Descripción -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Descripción
                </label>
                <textarea
                  v-model="form.description"
                  rows="3"
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                  placeholder="Describe brevemente esta categoría..."
                ></textarea>
              </div>

              <!-- Color -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">
                  Color de Identificación
                </label>
                <div class="grid grid-cols-6 gap-3">
                  <button
                    v-for="color in colorOptions"
                    :key="color.value"
                    type="button"
                    class="w-10 h-10 rounded-full border-2 transition-all hover:scale-110"
                    :class="[
                      form.color === color.value ? 'border-gray-800 scale-110' : 'border-gray-300',
                      color.bgClass
                    ]"
                    @click="form.color = color.value"
                  >
                    <span class="sr-only">{{ color.name }}</span>
                  </button>
                </div>
              </div>

              <!-- Estado -->
              <div class="flex items-center">
                <input
                  v-model="form.isActive"
                  type="checkbox"
                  class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                />
                <label class="ml-2 text-sm font-medium text-gray-700">
                  Categoría activa
                </label>
              </div>

              <!-- Preview -->
              <div class="bg-gray-50 rounded-xl p-4">
                <h4 class="text-sm font-medium text-gray-700 mb-2">
                  Vista Previa
                </h4>
                <div
                  class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                  :class="getPreviewClasses()"
                >
                  <Tag class="w-4 h-4 mr-2" />
                  {{ form.name || 'Nombre de categoría' }}
                </div>
              </div>

              <!-- Actions -->
              <div class="flex space-x-3 pt-4">
                <button
                  type="button"
                  class="flex-1 px-4 py-3 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl font-medium transition-colors"
                  @click="close"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  :disabled="loading"
                  class="flex-1 px-4 py-3 bg-linear-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white rounded-xl font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <span
                    v-if="loading"
                    class="inline-flex items-center"
                  >
                    <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                    Guardando...
                  </span>
                  <span v-else>
                    {{ isEditing ? 'Actualizar' : 'Crear' }} Categoría
                  </span>
                </button>
              </div>
            </form>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { X, Tag } from 'lucide-vue-next'

interface Props {
  isOpen: boolean
  isEditing?: boolean
  initialData?: {
    name: string
    description: string
    color: string
    isActive: boolean
  }
}

const props = withDefaults(defineProps<Props>(), {
  isEditing: false,
  initialData: () => ({
    name: '',
    description: '',
    color: 'blue',
    isActive: true
  })
})

const emit = defineEmits<{
  close: []
  submit: [data: CategoryFormData]
}>()

interface CategoryFormData {
  name: string
  description: string
  color: string
  isActive: boolean
}

const loading = ref(false)
const errors = ref<Record<string, string>>({})

const form = ref<CategoryFormData>({
  name: '',
  description: '',
  color: 'blue',
  isActive: true
})

const colorOptions = [
  { name: 'Azul', value: 'blue', bgClass: 'bg-blue-100 text-blue-800' },
  { name: 'Verde', value: 'green', bgClass: 'bg-green-100 text-green-800' },
  { name: 'Rojo', value: 'red', bgClass: 'bg-red-100 text-red-800' },
  { name: 'Amarillo', value: 'yellow', bgClass: 'bg-yellow-100 text-yellow-800' },
  { name: 'Morado', value: 'purple', bgClass: 'bg-purple-100 text-purple-800' },
  { name: 'Rosa', value: 'pink', bgClass: 'bg-pink-100 text-pink-800' },
  { name: 'Indigo', value: 'indigo', bgClass: 'bg-indigo-100 text-indigo-800' },
  { name: 'Gris', value: 'gray', bgClass: 'bg-gray-100 text-gray-800' },
  { name: 'Naranja', value: 'orange', bgClass: 'bg-orange-100 text-orange-800' },
  { name: 'Teal', value: 'teal', bgClass: 'bg-teal-100 text-teal-800' },
  { name: 'Cyan', value: 'cyan', bgClass: 'bg-cyan-100 text-cyan-800' },
  { name: 'Lime', value: 'lime', bgClass: 'bg-lime-100 text-lime-800' }
]

const getPreviewClasses = () => {
  const colorOption = colorOptions.find(c => c.value === form.value.color)
  return colorOption ? colorOption.bgClass : 'bg-gray-100 text-gray-800'
}

const close = () => {
  emit('close')
}

const closeOnBackdrop = (event: MouseEvent) => {
  if (event.target === event.currentTarget) {
    close()
  }
}

const validateForm = (): boolean => {
  errors.value = {}

  if (!form.value.name.trim()) {
    errors.value.name = 'El nombre de la categoría es obligatorio'
    return false
  }

  if (form.value.name.length < 2) {
    errors.value.name = 'El nombre debe tener al menos 2 caracteres'
    return false
  }

  if (form.value.name.length > 50) {
    errors.value.name = 'El nombre no puede exceder 50 caracteres'
    return false
  }

  return true
}

const handleSubmit = async () => {
  if (!validateForm()) return

  loading.value = true
  try {
    emit('submit', { ...form.value })
  } finally {
    loading.value = false
  }
}

// Reset form when modal opens/closes or editing state changes
watch(() => props.isOpen, (isOpen) => {
  if (isOpen) {
    if (props.isEditing && props.initialData) {
      form.value = { ...props.initialData }
    } else {
      form.value = {
        name: '',
        description: '',
        color: 'blue',
        isActive: true
      }
    }
    errors.value = {}
  }
})

watch(() => props.isEditing, () => {
  if (props.isOpen) {
    if (props.isEditing && props.initialData) {
      form.value = { ...props.initialData }
    } else {
      form.value = {
        name: '',
        description: '',
        color: 'blue',
        isActive: true
      }
    }
    errors.value = {}
  }
})
</script>