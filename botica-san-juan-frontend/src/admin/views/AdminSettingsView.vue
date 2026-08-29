<template>
  <div class="p-4 sm:p-6 lg:p-8 space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">
          Configuración del Sistema
        </h1>
        <p class="mt-2 text-gray-600">
          Ajustes globales del panel administrativo.
        </p>
      </div>
      <button
        class="inline-flex items-center justify-center rounded-xl bg-linear-to-r from-emerald-600 to-green-600 px-4 py-3 text-white shadow-lg shadow-emerald-600/20 transition hover:from-emerald-700 hover:to-green-700"
        @click="saveSettings"
      >
        <Save class="mr-2 h-5 w-5" /> Guardar cambios
      </button>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <section class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-slate-200/80">
        <h2 class="mb-4 flex items-center text-xl font-bold text-slate-900">
          <Settings class="mr-2 h-6 w-6 text-blue-600" /> General
        </h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div
            v-for="field in generalFields"
            :key="field.key"
          >
            <label class="mb-2 block text-sm font-medium text-slate-700">{{ field.label }}</label>
            <input
              v-model="settings[field.key]"
              :type="field.type"
              class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            />
          </div>
        </div>
      </section>

      <section class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-slate-200/80">
        <h2 class="mb-4 flex items-center text-xl font-bold text-slate-900">
          <DollarSign class="mr-2 h-6 w-6 text-emerald-600" /> Ventas
        </h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div
            v-for="field in salesFields"
            :key="field.key"
          >
            <label class="mb-2 block text-sm font-medium text-slate-700">{{ field.label }}</label>
            <input
              v-model="settings[field.key]"
              :type="field.type"
              class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
            />
          </div>
        </div>
      </section>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <section class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-slate-200/80">
        <h2 class="mb-4 flex items-center text-xl font-bold text-slate-900">
          <Package class="mr-2 h-6 w-6 text-violet-600" /> Inventario
        </h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div
            v-for="field in inventoryFields"
            :key="field.key"
          >
            <label class="mb-2 block text-sm font-medium text-slate-700">{{ field.label }}</label>
            <input
              v-model="settings[field.key]"
              :type="field.type"
              class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100"
            />
          </div>
        </div>
      </section>

      <section class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-slate-200/80">
        <h2 class="mb-4 flex items-center text-xl font-bold text-slate-900">
          <Monitor class="mr-2 h-6 w-6 text-orange-600" /> Sistema
        </h2>
        <div class="space-y-4">
          <div
            v-for="option in systemOptions"
            :key="option.key"
            class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-4"
          >
            <div>
              <p class="font-semibold text-slate-900">
                {{ option.label }}
              </p>
              <p class="text-sm text-slate-500">
                {{ option.description }}
              </p>
            </div>
            <label class="relative inline-flex cursor-pointer items-center">
              <input
                v-model="settings[option.key]"
                type="checkbox"
                class="sr-only peer"
              />
              <div class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-orange-600 peer-checked:after:translate-x-full"></div>
            </label>
          </div>
        </div>
      </section>
    </div>

    <section class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-slate-200/80">
      <h2 class="mb-4 flex items-center text-xl font-bold text-slate-900">
        <Users class="mr-2 h-6 w-6 text-indigo-600" /> Usuarios
      </h2>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="field in userFields"
          :key="field.key"
        >
          <label class="mb-2 block text-sm font-medium text-slate-700">{{ field.label }}</label>
          <input
            v-model="settings[field.key]"
            :type="field.type"
            class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
          />
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import { useAdminToast } from '../composables/useAdminToast'
import { DollarSign, Monitor, Package, Save, Settings, Users } from 'lucide-vue-next'

type SettingsKey =
  | 'storeName'
  | 'ruc'
  | 'address'
  | 'phone'
  | 'email'
  | 'schedule'
  | 'currency'
  | 'vat'
  | 'maxDiscount'
  | 'creditLimit'
  | 'lowStockAlert'
  | 'criticalStockAlert'
  | 'expiryAlertDays'
  | 'valuationMethod'
  | 'emailNotifications'
  | 'automaticBackup'
  | 'maintenanceMode'
  | 'detailedLogs'
  | 'maxLoginAttempts'
  | 'lockMinutes'
  | 'minPasswordLength'
  | 'passwordRotationDays'

const storageKey = 'botica-san-juan-admin-settings'
const { notifyError, notifySuccess } = useAdminToast()

const settings = reactive<Record<SettingsKey, string | boolean>>({
  storeName: 'Botica San Juan',
  ruc: '20123456789',
  address: 'Av. Principal 123, Lima',
  phone: '+51 987 654 321',
  email: 'contacto@boticasanjuan.com',
  schedule: 'Lunes a Domingo: 8:00 AM - 10:00 PM',
  currency: 'PEN',
  vat: '18',
  maxDiscount: '20',
  creditLimit: '5000',
  lowStockAlert: '10',
  criticalStockAlert: '5',
  expiryAlertDays: '30',
  valuationMethod: 'fifo',
  emailNotifications: true,
  automaticBackup: true,
  maintenanceMode: false,
  detailedLogs: true,
  maxLoginAttempts: '5',
  lockMinutes: '30',
  minPasswordLength: '8',
  passwordRotationDays: '90'
})

const generalFields = [
  { key: 'storeName' as const, label: 'Nombre de la botica', type: 'text' },
  { key: 'ruc' as const, label: 'RUC', type: 'text' },
  { key: 'address' as const, label: 'Dirección', type: 'text' },
  { key: 'phone' as const, label: 'Teléfono', type: 'text' },
  { key: 'email' as const, label: 'Correo electrónico', type: 'email' },
  { key: 'schedule' as const, label: 'Horario de atención', type: 'text' }
]

const salesFields = [
  { key: 'currency' as const, label: 'Moneda', type: 'text' },
  { key: 'vat' as const, label: 'IVA (%)', type: 'number' },
  { key: 'maxDiscount' as const, label: 'Descuento máximo (%)', type: 'number' },
  { key: 'creditLimit' as const, label: 'Límite de crédito', type: 'number' }
]

const inventoryFields = [
  { key: 'lowStockAlert' as const, label: 'Alerta stock bajo', type: 'number' },
  { key: 'criticalStockAlert' as const, label: 'Alerta stock crítico', type: 'number' },
  { key: 'expiryAlertDays' as const, label: 'Días para vencimiento', type: 'number' },
  { key: 'valuationMethod' as const, label: 'Método de valuación', type: 'text' }
]

const userFields = [
  { key: 'maxLoginAttempts' as const, label: 'Intentos de login', type: 'number' },
  { key: 'lockMinutes' as const, label: 'Bloqueo (minutos)', type: 'number' },
  { key: 'minPasswordLength' as const, label: 'Longitud mínima', type: 'number' },
  { key: 'passwordRotationDays' as const, label: 'Rotación contraseña', type: 'number' }
]

const systemOptions = [
  { key: 'emailNotifications' as const, label: 'Notificaciones por correo', description: 'Recibir alertas por email' },
  { key: 'automaticBackup' as const, label: 'Backup automático', description: 'Copias de seguridad diarias' },
  { key: 'maintenanceMode' as const, label: 'Modo mantenimiento', description: 'Bloquear el acceso temporalmente' },
  { key: 'detailedLogs' as const, label: 'Logs detallados', description: 'Registrar actividad del sistema' }
]

const saveSettings = () => {
  localStorage.setItem(storageKey, JSON.stringify(settings))
  notifySuccess('Configuracion guardada', 'Los ajustes del sistema fueron persistidos.')
}

const loadSettings = () => {
  const stored = localStorage.getItem(storageKey)
  if (!stored) return
  try {
    const parsed = JSON.parse(stored) as Partial<Record<SettingsKey, string | boolean>>
    Object.assign(settings, parsed)
  } catch (error) {
    console.error('Invalid stored settings', error)
    notifyError('Configuracion invalida', 'No se pudo leer la configuracion guardada.')
  }
}

loadSettings()
</script>
