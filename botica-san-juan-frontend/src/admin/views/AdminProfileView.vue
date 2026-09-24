<template>
  <div class="space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col gap-2">
      <h1 class="text-3xl font-bold text-texto-primario">
        Mi Perfil
      </h1>
      <p class="text-texto-secundario">
        Actualiza tus datos personales del panel administrativo.
      </p>
    </div>

    <section class="rounded-2xl bg-superficie-elevada p-6 shadow-lg ring-1 ring-borde-sutil/80">
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="mb-2 block text-sm font-medium text-texto-secundario">Nombre</label>
          <input
            v-model="form.nombre"
            type="text"
            class="w-full rounded-xl border border-borde-sutil px-4 py-3 outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
          />
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-texto-secundario">DNI</label>
          <input
            v-model="form.dni"
            type="text"
            maxlength="8"
            class="w-full rounded-xl border border-borde-sutil px-4 py-3 outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
          />
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-texto-secundario">Email</label>
          <input
            v-model="form.email"
            type="email"
            class="w-full rounded-xl border border-borde-sutil px-4 py-3 outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
          />
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-texto-secundario">Telefono</label>
          <input
            v-model="form.telefono"
            type="text"
            class="w-full rounded-xl border border-borde-sutil px-4 py-3 outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
          />
        </div>
      </div>

      <div class="mt-6 flex justify-end">
        <button
          class="inline-flex items-center justify-center rounded-xl bg-botica-700 px-4 py-3 text-white shadow-lg shadow-botica-700/20 transition hover:bg-botica-800 disabled:cursor-not-allowed disabled:opacity-70"
          :disabled="saving"
          @click="saveProfile"
        >
          {{ saving ? 'Guardando...' : 'Guardar perfil' }}
        </button>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import { useAdminToast } from '../composables/useAdminToast'

const authStore = useAuthStore()
const { notifyError, notifySuccess } = useAdminToast()

const form = reactive({
  nombre: authStore.user?.nombre ?? '',
  dni: authStore.user?.dni ?? '',
  email: authStore.user?.email ?? '',
  telefono: authStore.user?.telefono ?? ''
})

const saving = ref(false)

watch(
  () => authStore.user,
  (user) => {
    if (!user) return
    form.nombre = user.nombre ?? ''
    form.dni = user.dni ?? ''
    form.email = user.email ?? ''
    form.telefono = user.telefono ?? ''
  },
  { immediate: true }
)

const saveProfile = async () => {
  if (!authStore.user?.id) {
    notifyError('Perfil', 'No se encontro usuario autenticado.')
    return
  }

  if (!form.nombre.trim() || !form.email.trim() || !form.dni.trim()) {
    notifyError('Perfil', 'Nombre, DNI y email son obligatorios.')
    return
  }

  saving.value = true
  try {
    await api.put(`/usuarios/${authStore.user.id}`, {
      nombre: form.nombre.trim(),
      dni: form.dni.trim(),
      email: form.email.trim(),
      telefono: form.telefono.trim() || null
    })

    await authStore.checkAuth()
    notifySuccess('Perfil', 'Datos de perfil actualizados correctamente.')
  } catch (error) {
    console.error('Error saving profile:', error)
    notifyError('Perfil', 'No se pudo actualizar el perfil.')
  } finally {
    saving.value = false
  }
}
</script>
