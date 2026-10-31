<script setup lang="ts">
/**
 * Perfil del cliente.
 * ---------------------------------------------------------------------------
 * El menú del portal enlazaba a /profile desde hacía tiempo y esa ruta no
 * existía: el enlace llevaba a la pantalla de «no encontrado». Esta vista lo
 * cierra y, de paso, le da al cliente el mismo control sobre su cuenta que
 * tenía el administrador.
 *
 * La zona de arrastre es el MISMO componente que usa el panel. Si cada rol
 * tuviera el suyo, las reglas de tamaño y los mensajes se separarían a la
 * primera corrección hecha en uno solo.
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { urlDeMedia } from '@/utils/media'
import {
  cambiarContrasena,
  eliminarFoto,
  guardarDatosPersonales,
  subirFoto,
} from '@/services/perfil'
import { erroresPorCampo } from '@/utils/validacionServidor'
import ZonaDeFoto from '@/components/ZonaDeFoto.vue'
import MedidorDeContrasena from '@/components/MedidorDeContrasena.vue'

const auth = useAuthStore()

const usuario = computed(() => auth.user)
const fotoPerfil = computed(
  () => urlDeMedia(usuario.value?.foto_perfil || usuario.value?.foto),
)

const subiendoFoto = ref(false)
const guardando = ref(false)
const cambiandoClave = ref(false)
const aviso = ref<{ tipo: 'exito' | 'error'; texto: string } | null>(null)
const erroresDatos = ref<Record<string, string>>({})
const erroresClave = ref<Record<string, string>>({})

const datos = reactive({ nombre: '', email: '', telefono: '' })
const clave = reactive({
  contrasena_actual: '',
  contrasena: '',
  contrasena_confirmation: '',
})

onMounted(() => {
  datos.nombre = usuario.value?.nombre ?? ''
  datos.email = usuario.value?.email ?? ''
  datos.telefono = usuario.value?.telefono ?? ''
})

function anunciar(tipo: 'exito' | 'error', texto: string) {
  aviso.value = { tipo, texto }
  window.setTimeout(() => { aviso.value = null }, 5000)
}

async function alSeleccionarFoto(archivo: File) {
  subiendoFoto.value = true
  try {
    auth.setUser(await subirFoto(archivo))
    anunciar('exito', 'Foto actualizada.')
  } catch (error) {
    anunciar('error', erroresPorCampo(error).foto ?? 'No se pudo subir la foto.')
  } finally {
    subiendoFoto.value = false
  }
}

async function alQuitarFoto() {
  subiendoFoto.value = true
  try {
    auth.setUser(await eliminarFoto())
    anunciar('exito', 'Foto eliminada.')
  } catch {
    anunciar('error', 'No se pudo eliminar la foto.')
  } finally {
    subiendoFoto.value = false
  }
}

async function guardarDatos() {
  guardando.value = true
  erroresDatos.value = {}
  try {
    auth.setUser(await guardarDatosPersonales({ ...datos }))
    anunciar('exito', 'Datos guardados.')
  } catch (error) {
    erroresDatos.value = erroresPorCampo(error)
    anunciar('error', 'Revisa los campos marcados.')
  } finally {
    guardando.value = false
  }
}

async function guardarClave() {
  cambiandoClave.value = true
  erroresClave.value = {}
  try {
    await cambiarContrasena({ ...clave })
    clave.contrasena_actual = ''
    clave.contrasena = ''
    clave.contrasena_confirmation = ''
    anunciar('exito', 'Contraseña actualizada. Se cerraron tus otras sesiones.')
  } catch (error) {
    erroresClave.value = erroresPorCampo(error)
    anunciar('error', 'No se pudo cambiar la contraseña.')
  } finally {
    cambiandoClave.value = false
  }
}
</script>

<template>
  <section class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6">
    <header class="mb-8">
      <h1 class="text-3xl font-bold text-texto-primario">
        Mi perfil
      </h1>
      <p class="mt-1 text-texto-secundario">
        Tu foto, tus datos de contacto y tu contraseña.
      </p>
    </header>

    <!-- El aviso ocupa su sitio siempre: si apareciera y desapareciera
         empujando el contenido, cada mensaje provocaría un salto. -->
    <div class="mb-6 min-h-10">
      <p
        v-if="aviso"
        class="rounded-lg px-3 py-2.5 text-sm"
        :class="aviso.tipo === 'exito'
          ? 'bg-exito-50 text-exito-700 dark:bg-exito-600/10 dark:text-exito-500'
          : 'bg-peligro-50 text-peligro-700 dark:bg-peligro-600/10 dark:text-peligro-500'"
        role="status"
      >
        {{ aviso.texto }}
      </p>
    </div>

    <div class="space-y-6">
      <!-- Foto -->
      <article class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-5">
        <h2 class="mb-4 text-lg font-semibold text-texto-primario">
          Foto de perfil
        </h2>
        <ZonaDeFoto
          :nombre="usuario?.nombre"
          :foto-actual="fotoPerfil"
          :subiendo="subiendoFoto"
          @seleccionar="alSeleccionarFoto"
          @eliminar="alQuitarFoto"
        />
      </article>

      <!-- Datos personales -->
      <article class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-5">
        <h2 class="mb-4 text-lg font-semibold text-texto-primario">
          Datos personales
        </h2>

        <form
          class="grid gap-4 sm:grid-cols-2"
          @submit.prevent="guardarDatos"
        >
          <label class="block">
            <span class="mb-1 block text-sm font-medium text-texto-secundario">Nombre</span>
            <input
              v-model="datos.nombre"
              type="text"
              class="w-full rounded-lg border border-borde-base bg-superficie-base px-3 py-2 text-texto-primario focus:border-botica-500 focus:outline-none focus:ring-2 focus:ring-botica-500/20"
            >
            <span
              v-if="erroresDatos.nombre"
              class="mt-1 block text-xs text-peligro-600"
            >{{ erroresDatos.nombre }}</span>
          </label>

          <label class="block">
            <span class="mb-1 block text-sm font-medium text-texto-secundario">Teléfono</span>
            <input
              v-model="datos.telefono"
              type="tel"
              inputmode="numeric"
              class="w-full rounded-lg border border-borde-base bg-superficie-base px-3 py-2 text-texto-primario focus:border-botica-500 focus:outline-none focus:ring-2 focus:ring-botica-500/20"
            >
            <span
              v-if="erroresDatos.telefono"
              class="mt-1 block text-xs text-peligro-600"
            >{{ erroresDatos.telefono }}</span>
          </label>

          <label class="block sm:col-span-2">
            <span class="mb-1 block text-sm font-medium text-texto-secundario">Correo</span>
            <input
              v-model="datos.email"
              type="email"
              class="w-full rounded-lg border border-borde-base bg-superficie-base px-3 py-2 text-texto-primario focus:border-botica-500 focus:outline-none focus:ring-2 focus:ring-botica-500/20"
            >
            <span
              v-if="erroresDatos.email"
              class="mt-1 block text-xs text-peligro-600"
            >{{ erroresDatos.email }}</span>
          </label>

          <!-- El DNI se muestra y no se edita: identifica a la persona en los
               comprobantes ya emitidos. Cambiarlo desde aquí dejaría boletas
               a nombre de un documento que ya no es el suyo. -->
          <label class="block sm:col-span-2">
            <span class="mb-1 block text-sm font-medium text-texto-secundario">DNI</span>
            <input
              :value="usuario?.dni"
              type="text"
              readonly
              disabled
              class="w-full cursor-not-allowed rounded-lg border border-borde-sutil bg-superficie-hundida px-3 py-2 text-texto-terciario"
            >
            <span class="mt-1 block text-xs text-texto-terciario">
              Aparece en tus comprobantes, por eso no se cambia desde aquí.
            </span>
          </label>

          <div class="sm:col-span-2">
            <button
              type="submit"
              :disabled="guardando"
              class="rounded-lg bg-botica-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-botica-700 disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
            >
              {{ guardando ? 'Guardando…' : 'Guardar cambios' }}
            </button>
          </div>
        </form>
      </article>

      <!-- Contraseña -->
      <article class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-5">
        <h2 class="mb-1 text-lg font-semibold text-texto-primario">
          Contraseña
        </h2>
        <p class="mb-4 text-sm text-texto-secundario">
          Al cambiarla se cerrarán tus sesiones en otros dispositivos.
        </p>

        <form
          class="grid max-w-md gap-4"
          @submit.prevent="guardarClave"
        >
          <label class="block">
            <span class="mb-1 block text-sm font-medium text-texto-secundario">Contraseña actual</span>
            <input
              v-model="clave.contrasena_actual"
              type="password"
              autocomplete="current-password"
              class="w-full rounded-lg border border-borde-base bg-superficie-base px-3 py-2 text-texto-primario focus:border-botica-500 focus:outline-none focus:ring-2 focus:ring-botica-500/20"
            >
            <span
              v-if="erroresClave.contrasena_actual"
              class="mt-1 block text-xs text-peligro-600"
            >{{ erroresClave.contrasena_actual }}</span>
          </label>

          <label class="block">
            <span class="mb-1 block text-sm font-medium text-texto-secundario">Nueva contraseña</span>
            <input
              v-model="clave.contrasena"
              type="password"
              autocomplete="new-password"
              class="w-full rounded-lg border border-borde-base bg-superficie-base px-3 py-2 text-texto-primario focus:border-botica-500 focus:outline-none focus:ring-2 focus:ring-botica-500/20"
            >
            <MedidorDeContrasena
              :contrasena="clave.contrasena"
              class="mt-2"
            />
            <span
              v-if="erroresClave.contrasena"
              class="mt-1 block text-xs text-peligro-600"
            >{{ erroresClave.contrasena }}</span>
          </label>

          <label class="block">
            <span class="mb-1 block text-sm font-medium text-texto-secundario">Repite la nueva contraseña</span>
            <input
              v-model="clave.contrasena_confirmation"
              type="password"
              autocomplete="new-password"
              class="w-full rounded-lg border border-borde-base bg-superficie-base px-3 py-2 text-texto-primario focus:border-botica-500 focus:outline-none focus:ring-2 focus:ring-botica-500/20"
            >
          </label>

          <div>
            <button
              type="submit"
              :disabled="cambiandoClave"
              class="rounded-lg bg-botica-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-botica-700 disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
            >
              {{ cambiandoClave ? 'Cambiando…' : 'Cambiar contraseña' }}
            </button>
          </div>
        </form>
      </article>
    </div>
  </section>
</template>
