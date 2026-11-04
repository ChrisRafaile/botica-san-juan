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
  desvincularGoogle,
  eliminarFoto,
  guardarDatosPersonales,
  subirFoto,
} from '@/services/perfil'
import { reenviarVerificacion } from '@/services/correos'
import { erroresPorCampo } from '@/utils/validacionServidor'
import { MailWarning } from 'lucide-vue-next'
import ZonaDeFoto from '@/components/ZonaDeFoto.vue'
import MedidorDeContrasena from '@/components/MedidorDeContrasena.vue'
import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'

const auth = useAuthStore()

const usuario = computed(() => auth.user)
const fotoPerfil = computed(
  () => urlDeMedia(usuario.value?.foto_perfil || usuario.value?.foto),
)

/* Verificación del correo. El aviso aparece solo cuando falta, porque un
   banner permanente que diga «tu correo está confirmado» es ruido: la persona
   no tiene nada que hacer con esa información. */
const correoVerificado = computed(() => Boolean(usuario.value?.email_verified_at))
const reenviando = ref(false)
const avisoVerificacion = ref('')

async function pedirVerificacion() {
  reenviando.value = true
  avisoVerificacion.value = ''

  try {
    const { message } = await reenviarVerificacion()
    avisoVerificacion.value = message
  } catch (error: unknown) {
    /* El 429 del limitador tiene su propio texto: hay un correo por minuto, y
       «ha ocurrido un error» haría pulsar el botón otra vez. */
    const estado = (error as { response?: { status?: number } }).response?.status

    avisoVerificacion.value =
      estado === 429
        ? 'Ya te enviamos uno hace poco. Revisa tu bandeja y la carpeta de no deseados antes de pedir otro.'
        : 'No pudimos enviar el correo. Inténtalo de nuevo en un momento.'
  } finally {
    reenviando.value = false
  }
}

const subiendoFoto = ref(false)
const guardando = ref(false)
const cambiandoClave = ref(false)
const aviso = ref<{ tipo: 'exito' | 'error'; texto: string } | null>(null)
const erroresDatos = ref<Record<string, string>>({})
const erroresClave = ref<Record<string, string>>({})

const datos = reactive({ nombre: '', email: '', telefono: '', dni: '' })

/* Quien entró con Google no tiene DNI. Se mira el usuario de la sesión y no
   una bandera aparte: la fuente de verdad es la cuenta. */
const faltaDni = computed(() => !usuario.value?.dni)
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
    /* El DNI solo se manda si falta y se escribió algo. Enviarlo vacío lo
       haría fallar por obligatorio, y enviarlo cuando ya existe lo haría
       fallar porque no se puede cambiar: en los dos casos el formulario
       rechazaría un dato que la persona ni tocó. */
    const { dni, ...resto } = datos
    const cuerpo = faltaDni.value && dni.trim() !== '' ? { ...resto, dni: dni.trim() } : resto

    auth.setUser(await guardarDatosPersonales(cuerpo))
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

/* --------------------------------------------------------------------------
 * Acceso con Google
 *
 * Quitar Google es la única acción de esta pantalla que puede dejar a alguien
 * FUERA de su cuenta, así que es la única con un diálogo de confirmación. Y
 * por eso tampoco se ofrece el botón sin más: el backend dice en
 * `puede_desvincular_google` si la cuenta tiene otra vía de entrada, y cuando
 * no la tiene se explica qué falta en lugar de dejar pulsar y fallar.
 *
 * La regla NO se reimplementa aquí. Si el portal decidiera por su cuenta,
 * habría dos versiones de la misma condición y una de las dos se quedaría
 * atrás.
 */
const tieneGoogle = computed(() => Boolean(usuario.value?.tiene_google))
const puedeDesvincular = computed(() => Boolean(usuario.value?.puede_desvincular_google))

const confirmandoDesvinculacion = ref(false)
const desvinculando = ref(false)

async function confirmarDesvinculacion() {
  confirmandoDesvinculacion.value = false
  desvinculando.value = true

  try {
    auth.setUser(await desvincularGoogle())
    anunciar('exito', 'Google desvinculado. A partir de ahora entra con tu DNI y contraseña.')
  } catch (error) {
    /* El 422 del backend trae el motivo concreto. Se muestra tal cual: es el
       único sitio donde vive la explicación de qué falta. */
    const mensaje = (error as { response?: { data?: { message?: string } } })
      .response?.data?.message

    anunciar('error', mensaje ?? 'No se pudo desvincular Google.')
  } finally {
    desvinculando.value = false
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

    <!-- Correo sin confirmar. No se enseña nada cuando ya está confirmado:
         un cartel permanente diciendo «todo en orden» es ruido, porque no hay
         nada que la persona pueda hacer con esa información. -->
    <div
      v-if="usuario && !correoVerificado"
      class="mb-6 rounded-2xl border border-alerta-500/40 bg-alerta-50 p-4 dark:bg-alerta-500/10"
    >
      <div class="flex items-start gap-3">
        <MailWarning class="mt-0.5 size-5 shrink-0 text-alerta-700 dark:text-alerta-500" />
        <div class="min-w-0">
          <p class="font-medium text-texto-primario">
            Tu correo todavía no está confirmado
          </p>
          <p class="mt-1 text-sm text-texto-secundario">
            Confirmarlo nos permite avisarte del estado de tus pedidos y
            recuperar tu cuenta si olvidas la contraseña.
          </p>
          <button
            type="button"
            :disabled="reenviando"
            class="mt-3 rounded-lg bg-clinico-600 px-3.5 py-2 text-sm font-medium text-white transition-colors hover:bg-clinico-700 disabled:bg-clinico-400"
            @click="pedirVerificacion"
          >
            {{ reenviando ? 'Enviando…' : 'Enviarme el correo de confirmación' }}
          </button>
          <!-- Hueco reservado: el mensaje aparece sin empujar las tarjetas
               que vienen debajo. -->
          <p
            class="mt-2 min-h-5 text-sm text-texto-secundario"
            role="status"
          >
            {{ avisoVerificacion }}
          </p>
        </div>
      </div>
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

          <!-- El DNI no se CAMBIA: identifica a la persona en los
               comprobantes ya emitidos, y corregirlo desde aquí dejaría
               boletas a nombre de un documento que ya no es el suyo.

               Pero sí se COMPLETA cuando está vacío, que es el caso de quien
               entró con Google: Google acredita un correo y no sabe el
               documento de nadie. Sin esta puerta habría que teclearlo en
               cada compra, para siempre. -->
          <label class="block sm:col-span-2">
            <span class="mb-1 block text-sm font-medium text-texto-secundario">DNI</span>
            <input
              v-if="faltaDni"
              v-model="datos.dni"
              type="text"
              inputmode="numeric"
              maxlength="8"
              autocomplete="off"
              placeholder="8 dígitos"
              class="w-full rounded-lg border px-3 py-2 text-texto-primario"
              :class="erroresDatos.dni ? 'border-peligro-600' : 'border-borde-control'"
            >
            <input
              v-else
              :value="usuario?.dni"
              type="text"
              readonly
              disabled
              class="w-full cursor-not-allowed rounded-lg border border-borde-sutil bg-superficie-hundida px-3 py-2 text-texto-terciario"
            >
            <span
              v-if="erroresDatos.dni"
              class="mt-1 block text-xs text-peligro-700"
            >{{ erroresDatos.dni }}</span>
            <span
              v-else
              class="mt-1 block text-xs text-texto-terciario"
            >
              {{
                faltaDni
                  ? 'Lo necesitamos para emitir tus comprobantes. Solo se escribe una vez.'
                  : 'Aparece en tus comprobantes, por eso no se cambia desde aquí.'
              }}
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

      <!-- Acceso con Google. La tarjeta solo aparece si la cuenta está
           vinculada: a quien nunca usó Google no le dice nada. -->
      <article
        v-if="tieneGoogle"
        class="rounded-2xl border border-borde-sutil bg-superficie-elevada p-5"
      >
        <h2 class="mb-1 text-lg font-semibold text-texto-primario">
          Acceso con Google
        </h2>
        <p class="mb-4 text-sm text-texto-secundario">
          Tu cuenta está vinculada con Google, así que puedes entrar con un solo toque.
        </p>

        <!-- Cuando NO se puede desvincular, lo que hace falta no es un botón
             desactivado —que no explica nada— sino decir qué falta y por qué.
             El caso típico es una cuenta creada con Google: no tiene ninguna
             contraseña, así que quitar Google la dejaría sin entrada. -->
        <div
          v-if="!puedeDesvincular"
          class="rounded-xl border border-alerta-500/40 bg-alerta-50 p-4 text-sm dark:bg-alerta-500/10"
        >
          <p class="font-medium text-texto-primario">
            Todavía no puedes desvincular Google
          </p>
          <p class="mt-1 text-texto-secundario">
            <template v-if="!usuario?.tiene_contrasena">
              Esta cuenta se creó con Google y no tiene contraseña propia. Si la
              desvinculáramos ahora, te quedarías sin forma de entrar. Pide una
              contraseña desde «olvidé mi contraseña»: el enlace llega al correo
              que Google ya verificó.
            </template>
            <template v-else>
              Antes necesitas confirmar tu correo. Sin eso no podrías recuperar
              la contraseña si la olvidas, y quitar Google te dejaría sin salida.
            </template>
          </p>
        </div>

        <div v-else>
          <p class="mb-3 text-sm text-texto-secundario">
            Al desvincularlo seguirás entrando con tu DNI y tu contraseña.
          </p>
          <button
            type="button"
            :disabled="desvinculando"
            class="rounded-lg border border-peligro-500/50 px-4 py-2 text-sm font-semibold text-peligro-600 transition-colors hover:bg-peligro-50 disabled:opacity-60 dark:hover:bg-peligro-600/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
            @click="confirmandoDesvinculacion = true"
          >
            {{ desvinculando ? 'Desvinculando…' : 'Desvincular Google' }}
          </button>
        </div>
      </article>
    </div>

    <DialogoConfirmacion
      id="desvincular-google"
      :abierto="confirmandoDesvinculacion"
      titulo="¿Desvincular Google?"
      texto="El botón de Google dejará de funcionar para esta cuenta. Seguirás entrando con tu DNI y tu contraseña, y puedes volver a vincularlo cuando quieras."
      etiqueta-confirmar="Desvincular"
      peligroso
      @confirmar="confirmarDesvinculacion"
      @cancelar="confirmandoDesvinculacion = false"
    />
  </section>
</template>
