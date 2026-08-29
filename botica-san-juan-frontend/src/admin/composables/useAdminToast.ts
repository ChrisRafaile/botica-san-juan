import { readonly, ref } from 'vue'

export type AdminToastType = 'success' | 'error' | 'info'

export interface AdminToast {
  id: number
  title: string
  message?: string
  type: AdminToastType
}

const toasts = ref<AdminToast[]>([])
let counter = 1

const removeToast = (id: number) => {
  toasts.value = toasts.value.filter(toast => toast.id !== id)
}

const pushToast = (toast: Omit<AdminToast, 'id'>, durationMs = 3200) => {
  const id = counter++
  toasts.value.push({ ...toast, id })

  window.setTimeout(() => {
    removeToast(id)
  }, durationMs)
}

export const useAdminToast = () => {
  const notifySuccess = (title: string, message?: string) => {
    pushToast({ title, message, type: 'success' })
  }

  const notifyError = (title: string, message?: string) => {
    pushToast({ title, message, type: 'error' }, 4200)
  }

  const notifyInfo = (title: string, message?: string) => {
    pushToast({ title, message, type: 'info' })
  }

  return {
    toasts: readonly(toasts),
    notifySuccess,
    notifyError,
    notifyInfo,
    removeToast
  }
}
