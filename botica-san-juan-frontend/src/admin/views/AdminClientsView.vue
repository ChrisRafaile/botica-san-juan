<template>
  <div class="space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-3xl font-bold text-texto-primario">
          Gestion de Usuarios
        </h1>
        <p class="mt-2 text-texto-secundario">
          Administra clientes y administradores desde el panel.
        </p>
      </div>

      <button
        class="inline-flex items-center justify-center rounded-xl bg-botica-700 px-4 py-3 text-white shadow-lg shadow-botica-700/20 transition hover:bg-botica-800"
        @click="openCreateModal"
      >
        <UserPlus class="mr-2 h-5 w-5" />
        Nuevo usuario
      </button>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl bg-botica-700 p-6 text-white shadow-xl">
        <p class="text-sm text-neutro-200">
          Total usuarios
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ totalItems }}
        </p>
      </div>
      <div class="rounded-2xl bg-botica-700 p-6 text-white shadow-xl">
        <p class="text-sm text-botica-100">
          Clientes (pagina)
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ clientsCount }}
        </p>
      </div>
      <div class="rounded-2xl bg-botica-700 p-6 text-white shadow-xl">
        <p class="text-sm text-botica-100">
          Administradores (pagina)
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ adminsCount }}
        </p>
      </div>
      <div class="rounded-2xl bg-botica-700 p-6 text-white shadow-xl">
        <p class="text-sm text-botica-100">
          Nuevos este mes (pagina)
        </p>
        <p class="mt-2 text-3xl font-bold">
          {{ recentUsersCount }}
        </p>
      </div>
    </div>

    <div class="rounded-2xl bg-superficie-elevada p-4 shadow-lg ring-1 ring-borde-sutil/80 sm:p-6">
      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_130px]">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-texto-terciario" />
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Buscar por nombre, DNI o email..."
            class="w-full rounded-xl border border-borde-sutil py-3 pl-10 pr-4 text-texto-primario outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
          />
        </div>

        <select
          v-model="roleFilter"
          class="rounded-xl border border-borde-sutil px-4 py-3 text-texto-primario outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
        >
          <option value="all">
            Todos los roles
          </option>
          <option value="cliente">
            Cliente
          </option>
          <option value="administrador">
            Administrador
          </option>
        </select>

        <select
          v-model.number="perPage"
          class="rounded-xl border border-borde-sutil px-4 py-3 text-texto-primario outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
        >
          <option :value="10">
            10
          </option>
          <option :value="20">
            20
          </option>
          <option :value="30">
            30
          </option>
        </select>
      </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-superficie-elevada shadow-lg ring-1 ring-borde-sutil/80">
      <div
        v-if="loading"
        class="flex items-center justify-center py-16 text-texto-secundario"
      >
        <div class="h-9 w-9 animate-spin rounded-full border-4 border-borde-sutil border-t-blue-600" />
        <span class="ml-3">Cargando usuarios...</span>
      </div>

      <div
        v-else-if="users.length === 0"
        class="px-6 py-16 text-center"
      >
        <Users class="mx-auto h-14 w-14 text-texto-deshabilitado" />
        <h2 class="mt-4 text-lg font-semibold text-texto-primario">
          No hay usuarios
        </h2>
        <p class="mt-2 text-sm text-texto-terciario">
          Ajusta los filtros o crea un nuevo usuario.
        </p>
      </div>

      <div v-else>
        <div class="space-y-3 p-4 md:hidden">
          <article
            v-for="user in users"
            :key="user.id"
            class="rounded-2xl border border-borde-sutil p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-botica-700 text-sm font-bold text-white">
                  {{ initials(user.nombre) }}
                </div>
                <div>
                  <p class="font-semibold text-texto-primario">
                    {{ user.nombre }}
                  </p>
                  <p class="text-xs text-texto-terciario">
                    DNI {{ user.dni }}
                  </p>
                </div>
              </div>
              <span
                :class="roleBadgeClass(user.rol)"
                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
              >
                {{ formatRole(user.rol) }}
              </span>
            </div>

            <div class="mt-3 space-y-1 text-sm">
              <p class="text-texto-secundario">
                {{ user.email }}
              </p>
              <p class="text-texto-terciario">
                {{ user.telefono || 'Sin telefono' }}
              </p>
              <p class="text-xs text-texto-terciario">
                Registro: {{ formatDate(user.created_at) }}
              </p>
            </div>

            <div class="mt-4 flex justify-end gap-2">
              <button
                class="rounded-lg p-2 text-texto-marca transition hover:bg-botica-50"
                @click="openEditModal(user)"
              >
                <Edit class="h-4 w-4" />
              </button>
              <button
                class="rounded-lg p-2 text-peligro-600 transition hover:bg-peligro-50"
                @click="askDelete(user)"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </article>
        </div>

        <div class="hidden overflow-x-auto md:block">
          <table class="min-w-full divide-y divide-borde-sutil">
            <thead class="bg-superficie-hundida">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Usuario
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Contacto
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Rol
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Registro
                </th>
                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-texto-terciario">
                  Acciones
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-borde-sutil bg-superficie-elevada">
              <tr
                v-for="user in users"
                :key="user.id"
                class="transition hover:bg-superficie-hundida"
              >
                <td class="whitespace-nowrap px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-botica-700 text-sm font-bold text-white">
                      {{ initials(user.nombre) }}
                    </div>
                    <div>
                      <p class="font-semibold text-texto-primario">
                        {{ user.nombre }}
                      </p>
                      <p class="text-sm text-texto-terciario">
                        DNI {{ user.dni }}
                      </p>
                    </div>
                  </div>
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <p class="text-sm font-medium text-texto-primario">
                    {{ user.email }}
                  </p>
                  <p class="text-sm text-texto-terciario">
                    {{ user.telefono || 'Sin telefono' }}
                  </p>
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <span
                    :class="roleBadgeClass(user.rol)"
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                  >
                    {{ formatRole(user.rol) }}
                  </span>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-texto-secundario">
                  {{ formatDate(user.created_at) }}
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right">
                  <div class="inline-flex items-center gap-2">
                    <button
                      class="rounded-lg p-2 text-texto-marca transition hover:bg-botica-50"
                      @click="openEditModal(user)"
                    >
                      <Edit class="h-4 w-4" />
                    </button>
                    <button
                      class="rounded-lg p-2 text-peligro-600 transition hover:bg-peligro-50"
                      @click="askDelete(user)"
                    >
                      <Trash2 class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div
      v-if="totalPages > 1"
      class="flex flex-col items-center justify-between gap-3 rounded-2xl bg-superficie-elevada px-4 py-3 shadow ring-1 ring-borde-sutil/80 sm:flex-row"
    >
      <p class="text-sm text-texto-secundario">
        Mostrando {{ users.length }} de {{ totalItems }} usuarios
      </p>
      <div class="inline-flex items-center gap-2">
        <button
          class="rounded-lg border border-borde-sutil px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="currentPage <= 1"
          @click="changePage(currentPage - 1)"
        >
          Anterior
        </button>
        <span class="text-sm font-semibold text-texto-secundario">{{ currentPage }} / {{ totalPages }}</span>
        <button
          class="rounded-lg border border-borde-sutil px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="currentPage >= totalPages"
          @click="changePage(currentPage + 1)"
        >
          Siguiente
        </button>
      </div>
    </div>

    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="showModal"
          class="fixed inset-0 z-50 flex items-center justify-center bg-neutro-950/60 px-4 py-6 backdrop-blur-sm"
          @click.self="closeModal"
        >
          <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-superficie-elevada shadow-2xl ring-1 ring-neutro-950/5">
            <div class="border-b border-borde-sutil px-6 py-5">
              <h2 class="text-2xl font-bold text-texto-primario">
                {{ isEditing ? 'Editar usuario' : 'Nuevo usuario' }}
              </h2>
              <p class="mt-1 text-sm text-texto-terciario">
                Completa los datos del usuario y guarda los cambios.
              </p>
            </div>

            <form
              class="space-y-5 px-6 py-6"
              @submit.prevent="saveUser"
            >
              <div class="grid gap-5 md:grid-cols-2">
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
                  <label class="mb-2 block text-sm font-medium text-texto-secundario">Correo</label>
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
                <div>
                  <label class="mb-2 block text-sm font-medium text-texto-secundario">Rol</label>
                  <select
                    v-model="form.rol"
                    class="w-full rounded-xl border border-borde-sutil px-4 py-3 outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
                  >
                    <option value="cliente">
                      Cliente
                    </option>
                    <option value="administrador">
                      Administrador
                    </option>
                  </select>
                </div>
                <div>
                  <label class="mb-2 block text-sm font-medium text-texto-secundario">Contrasena {{ isEditing ? '(opcional)' : '' }}</label>
                  <input
                    v-model="form.password"
                    type="password"
                    :placeholder="isEditing ? 'Dejar vacio para conservar' : 'Minimo 8 caracteres'"
                    class="w-full rounded-xl border border-borde-sutil px-4 py-3 outline-none transition focus:border-borde-marca focus:ring-4 focus:ring-botica-500/20"
                  />
                </div>
              </div>

              <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                  type="button"
                  class="rounded-xl border border-borde-sutil px-5 py-3 font-semibold text-texto-secundario transition hover:bg-superficie-hundida"
                  @click="closeModal"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  class="inline-flex items-center justify-center rounded-xl bg-botica-700 px-5 py-3 font-semibold text-white shadow-lg shadow-botica-700/20 transition hover:bg-botica-800"
                >
                  <Save class="mr-2 h-4 w-4" />
                  Guardar
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '@/services/api'
import { useAdminToast } from '../composables/useAdminToast'
import { Edit, Save, Search, Trash2, UserPlus, Users } from 'lucide-vue-next'

interface UserRecord {
  id: number
  nombre: string
  email: string
  dni: string
  telefono?: string | null
  foto_perfil?: string | null
  foto_portada?: string | null
  rol?: string | null
  created_at?: string | null
  updated_at?: string | null
}

interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

interface UserForm {
  nombre: string
  dni: string
  email: string
  telefono: string
  rol: 'cliente' | 'administrador'
  password: string
}

const { notifyError, notifySuccess } = useAdminToast()

const users = ref<UserRecord[]>([])
const loading = ref(false)
const searchQuery = ref('')
const roleFilter = ref<'all' | 'cliente' | 'administrador'>('all')
const showModal = ref(false)
const isEditing = ref(false)
const editingId = ref<number | null>(null)

const currentPage = ref(1)
const perPage = ref(10)
const totalPages = ref(1)
const totalItems = ref(0)

let searchTimeout: number | undefined

const form = reactive<UserForm>({
  nombre: '',
  dni: '',
  email: '',
  telefono: '',
  rol: 'cliente',
  password: ''
})

const clientsCount = computed(() => users.value.filter(user => user.rol === 'cliente').length)
const adminsCount = computed(() => users.value.filter(user => user.rol === 'administrador').length)
const recentUsersCount = computed(() => {
  const threshold = Date.now() - 1000 * 60 * 60 * 24 * 30
  return users.value.filter(user => {
    if (!user.created_at) return false
    return new Date(user.created_at).getTime() >= threshold
  }).length
})

const formatDate = (dateValue?: string | null) => {
  if (!dateValue) return 'Sin fecha'
  return new Date(dateValue).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const initials = (name: string) => name.split(' ').filter(Boolean).slice(0, 2).map(part => part[0]?.toUpperCase() ?? '').join('') || 'U'

const formatRole = (role?: string | null) => role === 'administrador' ? 'Administrador' : 'Cliente'

const roleBadgeClass = (role?: string | null) => role === 'administrador'
  ? 'bg-botica-100 text-botica-800'
  : 'bg-botica-50 text-botica-800'

const loadUsers = async (page = currentPage.value) => {
  loading.value = true
  try {
    const response = await api.get<PaginatedResponse<UserRecord>>('/usuarios', {
      params: {
        paginate: 1,
        page,
        per_page: perPage.value,
        q: searchQuery.value.trim() || undefined,
        rol: roleFilter.value
      }
    })

    users.value = response.data.data
    currentPage.value = response.data.current_page
    totalPages.value = response.data.last_page
    totalItems.value = response.data.total
  } catch (error) {
    console.error('Error loading users:', error)
    notifyError('Error de carga', 'No se pudieron cargar los usuarios.')
  } finally {
    loading.value = false
  }
}

const changePage = async (page: number) => {
  if (page < 1 || page > totalPages.value || page === currentPage.value) return
  await loadUsers(page)
}

const openCreateModal = () => {
  isEditing.value = false
  editingId.value = null
  form.nombre = ''
  form.dni = ''
  form.email = ''
  form.telefono = ''
  form.rol = 'cliente'
  form.password = ''
  showModal.value = true
}

const openEditModal = (user: UserRecord) => {
  isEditing.value = true
  editingId.value = user.id
  form.nombre = user.nombre
  form.dni = user.dni
  form.email = user.email
  form.telefono = user.telefono || ''
  form.rol = user.rol === 'administrador' ? 'administrador' : 'cliente'
  form.password = ''
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveUser = async () => {
  const payload: Record<string, string> = {
    nombre: form.nombre.trim(),
    dni: form.dni.trim(),
    email: form.email.trim(),
    telefono: form.telefono.trim(),
    rol: form.rol
  }

  if (!isEditing.value || form.password.trim()) {
    payload.password = form.password.trim()
  }

  try {
    if (isEditing.value && editingId.value !== null) {
      await api.put(`/usuarios/${editingId.value}`, payload)
      notifySuccess('Usuario actualizado', 'Los cambios fueron guardados.')
    } else {
      await api.post('/usuarios', payload)
      notifySuccess('Usuario creado', 'El nuevo usuario fue registrado.')
    }

    showModal.value = false
    await loadUsers(currentPage.value)
  } catch (error) {
    console.error('Error saving user:', error)
    notifyError('No se pudo guardar', 'Revisa los datos ingresados e intenta nuevamente.')
  }
}

const askDelete = async (user: UserRecord) => {
  if (!window.confirm(`Eliminar a ${user.nombre}?`)) return

  try {
    await api.delete(`/usuarios/${user.id}`)
    notifySuccess('Usuario eliminado', `${user.nombre} fue eliminado correctamente.`)
    await loadUsers(currentPage.value)
  } catch (error) {
    console.error('Error deleting user:', error)
    notifyError('No se pudo eliminar', 'El usuario no pudo ser eliminado.')
  }
}

watch([roleFilter, perPage], async () => {
  currentPage.value = 1
  await loadUsers(1)
})

watch(searchQuery, () => {
  if (searchTimeout) {
    window.clearTimeout(searchTimeout)
  }
  searchTimeout = window.setTimeout(async () => {
    currentPage.value = 1
    await loadUsers(1)
  }, 300)
})

onMounted(loadUsers)
</script>
