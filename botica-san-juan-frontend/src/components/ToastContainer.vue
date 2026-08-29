<template>
  <Teleport to="body">
    <div class="fixed top-4 right-4 z-50 space-y-2">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="flex items-center p-4 rounded-lg shadow-lg max-w-sm"
          :class="toastClasses[toast.type]"
        >
          <div class="shrink-0">
            <component
              :is="toastIcons[toast.type]"
              class="w-5 h-5"
            />
          </div>
          <div class="ml-3 flex-1">
            <p class="text-sm font-medium">
              {{ toast.title }}
            </p>
            <p
              v-if="toast.message"
              class="text-sm opacity-90"
            >
              {{ toast.message }}
            </p>
          </div>
          <div class="ml-4 shrink-0">
            <button
              class="inline-flex text-current hover:opacity-75"
              @click="removeToast(toast.id)"
            >
              <X class="w-4 h-4" />
            </button>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { CheckCircle, XCircle, AlertCircle, Info, X } from 'lucide-vue-next'

interface Toast {
  id: number
  type: 'success' | 'error' | 'warning' | 'info'
  title: string
  message?: string
  duration?: number
}

const toasts = ref<Toast[]>([])
let toastId = 0

const toastClasses = {
  success: 'bg-green-500 text-white',
  error: 'bg-red-500 text-white',
  warning: 'bg-yellow-500 text-white',
  info: 'bg-blue-500 text-white'
}

const toastIcons = {
  success: CheckCircle,
  error: XCircle,
  warning: AlertCircle,
  info: Info
}

const addToast = (type: Toast['type'], title: string, message?: string, duration = 5000) => {
  const id = ++toastId
  const toast: Toast = { id, type, title, message, duration }

  toasts.value.push(toast)

  if (duration > 0) {
    setTimeout(() => {
      removeToast(id)
    }, duration)
  }

  return id
}

const removeToast = (id: number) => {
  const index = toasts.value.findIndex(toast => toast.id === id)
  if (index > -1) {
    toasts.value.splice(index, 1)
  }
}

const showSuccess = (title: string, message?: string) => addToast('success', title, message)
const showError = (title: string, message?: string) => addToast('error', title, message)
const showWarning = (title: string, message?: string) => addToast('warning', title, message)
const showInfo = (title: string, message?: string) => addToast('info', title, message)

// Exponer funciones al componente padre
defineExpose({
  showSuccess,
  showError,
  showWarning,
  showInfo,
  removeToast
})
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>