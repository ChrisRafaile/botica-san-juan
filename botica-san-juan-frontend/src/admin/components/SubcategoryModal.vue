<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm px-4"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="px-6 py-4 bg-linear-to-r from-indigo-600 to-blue-600 text-white">
          <h3 class="text-lg font-semibold">
            {{ isEditing ? 'Editar Subcategoria' : 'Nueva Subcategoria' }}
          </h3>
          <p class="text-sm text-indigo-100">
            Define una subcategoria asociada a su categoria principal.
          </p>
        </div>

        <form
          class="px-6 py-6 space-y-4"
          @submit.prevent="handleSubmit"
        >
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Categoria *</label>
            <select
              v-model.number="form.categoryId"
              class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option :value="0">
                Selecciona una categoria
              </option>
              <option
                v-for="category in categories"
                :key="category.id"
                :value="category.id"
              >
                {{ category.name }}
              </option>
            </select>
            <p
              v-if="errors.categoryId"
              class="text-xs text-red-600 mt-1"
            >
              {{ errors.categoryId }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre *</label>
            <input
              v-model="form.name"
              type="text"
              class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Ej: Antiinflamatorios"
            />
            <p
              v-if="errors.name"
              class="text-xs text-red-600 mt-1"
            >
              {{ errors.name }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Descripcion</label>
            <textarea
              v-model="form.description"
              rows="3"
              class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input
              v-model="form.isActive"
              type="checkbox"
              class="rounded border-gray-300"
            />
            Subcategoria activa
          </label>

          <div class="flex items-center justify-end gap-3 pt-2">
            <button
              type="button"
              class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"
              @click="emit('close')"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700"
            >
              {{ isEditing ? 'Actualizar' : 'Crear' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { reactive, watch } from 'vue'

interface Category {
  id: number
  name: string
}

interface FormData {
  categoryId: number
  name: string
  description: string
  isActive: boolean
}

interface Props {
  isOpen: boolean
  isEditing?: boolean
  categories: Category[]
  initialData?: FormData
}

const props = withDefaults(defineProps<Props>(), {
  isEditing: false,
  initialData: () => ({
    categoryId: 0,
    name: '',
    description: '',
    isActive: true
  })
})

const emit = defineEmits<{
  close: []
  submit: [data: FormData]
}>()

const form = reactive<FormData>({
  categoryId: 0,
  name: '',
  description: '',
  isActive: true
})

const errors = reactive<{ categoryId?: string; name?: string }>({})

const resetForm = () => {
  form.categoryId = props.initialData?.categoryId || 0
  form.name = props.initialData?.name || ''
  form.description = props.initialData?.description || ''
  form.isActive = props.initialData?.isActive ?? true
  errors.categoryId = undefined
  errors.name = undefined
}

const handleSubmit = () => {
  errors.categoryId = undefined
  errors.name = undefined

  if (!form.categoryId) {
    errors.categoryId = 'Debes seleccionar una categoria.'
  }

  if (!form.name.trim()) {
    errors.name = 'El nombre es obligatorio.'
  }

  if (errors.categoryId || errors.name) return

  emit('submit', {
    categoryId: form.categoryId,
    name: form.name.trim(),
    description: form.description.trim(),
    isActive: form.isActive
  })
}

watch(() => props.isOpen, isOpen => {
  if (isOpen) {
    resetForm()
  }
})

watch(() => props.initialData, () => {
  if (props.isOpen) {
    resetForm()
  }
})
</script>
