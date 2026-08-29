<template>
  <Teleport to="body">
    <div class="pointer-events-none fixed right-4 top-4 z-[100] flex w-[min(94vw,380px)] flex-col gap-3 sm:right-6 sm:top-6">
      <TransitionGroup
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0"
      >
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto rounded-2xl border bg-white p-4 shadow-xl ring-1"
          :class="toastClass(toast.type)"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="font-semibold">
                {{ toast.title }}
              </p>
              <p
                v-if="toast.message"
                class="mt-1 text-sm opacity-90"
              >
                {{ toast.message }}
              </p>
            </div>
            <button
              class="rounded-md p-1 opacity-60 transition hover:bg-black/5 hover:opacity-100"
              @click="removeToast(toast.id)"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { X } from 'lucide-vue-next'
import { useAdminToast, type AdminToastType } from '../composables/useAdminToast'

const { toasts, removeToast } = useAdminToast()

const toastClass = (type: AdminToastType) => {
  switch (type) {
    case 'success':
      return 'border-emerald-200 text-emerald-900 ring-emerald-100'
    case 'error':
      return 'border-rose-200 text-rose-900 ring-rose-100'
    default:
      return 'border-blue-200 text-blue-900 ring-blue-100'
  }
}
</script>
