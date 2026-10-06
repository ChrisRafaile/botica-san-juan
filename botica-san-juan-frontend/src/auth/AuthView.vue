<!--
  Acceso al sistema · pantalla dual deslizante
  ===========================================================================
  QUÉ ES

  Una sola pantalla para iniciar sesión y registrarse. Un panel de marca se
  desliza horizontalmente sobre los dos formularios: cuando cubre la derecha
  se ve el de acceso, cuando se desliza a la izquierda se ve el de registro.

  POR QUÉ ASÍ Y NO CON UN INTERRUPTOR LOCAL

  El estado lo manda la RUTA, no una variable de la vista. `/login` y
  `/register` siguen existiendo y siguen siendo enlazables, el botón atrás del
  navegador funciona y el guardián de rutas no cambia. La animación es el
  efecto visible de navegar, no un sustituto de la navegación.

  ACCESIBILIDAD Y RENDIMIENTO

  · El desplazamiento usa `transform`, que el compositor resuelve sin
    recalcular diseño ni repintar: no compite con el hilo principal.
  · Con `prefers-reduced-motion` el cambio es instantáneo. No es un adorno:
    un desplazamiento horizontal amplio provoca malestar a quien tiene
    sensibilidad vestibular.
  · El formulario oculto queda con `inert`, así que el tabulador no entra en
    campos que no se ven —el fallo clásico de este patrón.
  · Por debajo de 1024 px no hay panel deslizante. Partir 375 px en dos
    columnas no deja sitio para nada; ahí se muestra un solo formulario con
    un conmutador arriba.
-->
<template>
  <div class="relative min-h-dvh overflow-hidden bg-superficie-fondo">
    <!-- Fondo: dos lavados de color muy suaves, sin degradado saturado -->
    <div
      class="pointer-events-none absolute inset-0 opacity-70"
      aria-hidden="true"
    >
      <div class="absolute -left-40 -top-40 h-[32rem] w-[32rem] rounded-full bg-botica-500/10 blur-3xl" />
      <div class="absolute -bottom-40 -right-40 h-[32rem] w-[32rem] rounded-full bg-clinico-500/10 blur-3xl" />
    </div>

    <!-- Volver al inicio -->
    <RouterLink
      to="/"
      class="absolute left-4 top-4 z-30 inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-texto-secundario transition hover:bg-superficie-interactiva hover:text-texto-primario sm:left-6 sm:top-6"
    >
      <ArrowLeft class="h-4 w-4" />
      Volver al inicio
    </RouterLink>

    <div class="relative z-10 flex min-h-dvh items-center justify-center p-4 sm:p-6">
      <div
        ref="tarjeta"
        class="relative w-full max-w-5xl overflow-hidden rounded-3xl bg-superficie-elevada shadow-2xl ring-1 ring-borde-sutil"
      >
        <!-- Conmutador para pantallas estrechas, donde no hay panel deslizante -->
        <div class="border-b border-borde-sutil p-4 lg:hidden">
          <div
            class="grid grid-cols-2 gap-1 rounded-xl bg-superficie-hundida p-1"
            role="tablist"
          >
            <button
              v-for="modo in MODOS"
              :key="modo.nombre"
              role="tab"
              :aria-selected="esRegistro === modo.esRegistro"
              class="rounded-lg px-4 py-2 text-sm font-semibold transition"
              :class="esRegistro === modo.esRegistro
                ? 'bg-superficie-elevada text-texto-primario shadow-xs'
                : 'text-texto-secundario hover:text-texto-primario'"
              @click="irA(modo.nombre)"
            >
              {{ modo.rotulo }}
            </button>
          </div>
        </div>

        <!-- Zona de los dos formularios -->
        <div class="relative lg:grid lg:min-h-[38rem] lg:grid-cols-2">
          <!-- Acceso -->
          <section
            class="p-6 sm:p-10 lg:col-start-1 lg:row-start-1"
            :class="{ 'hidden lg:block': esRegistro }"
            :inert="esRegistro || undefined"
            aria-labelledby="titulo-acceso"
          >
            <form
              class="mx-auto flex h-full max-w-sm flex-col justify-center"
              novalidate
              @submit.prevent="enviarAcceso"
            >
              <!--
                Marca circular de la referencia.

                En la referencia, este círculo cambia a la foto de la persona
                en cuanto reconoce el usuario escrito. Aquí NO se hace, y es
                una decisión deliberada: para pintar esa foto habría que
                preguntarle al servidor si ese DNI existe antes de validar
                ninguna contraseña, y eso convierte la pantalla en un detector
                de DNIs registrados. Cualquiera podría recorrer números y saber
                quién es cliente de la botica. La marca se queda fija.
              -->
              <div
                class="mx-auto flex size-20 items-center justify-center rounded-full bg-linear-to-br from-clinico-500 to-botica-600 text-white shadow-md ring-4 ring-superficie-elevada"
                aria-hidden="true"
              >
                <Pill class="size-9" />
              </div>

              <h1
                id="titulo-acceso"
                class="mt-5 text-center text-2xl font-bold tracking-tight text-texto-primario"
              >
                Iniciar sesión
              </h1>
              <p class="mt-2 text-center text-sm text-texto-secundario">
                Ingresa con tu DNI y tu contraseña.
              </p>

              <div class="mt-7 space-y-4">
                <CampoTexto
                  id="acceso-dni"
                  v-model="acceso.dni"
                  etiqueta="DNI"
                  :icono="IdCard"
                  inputmode="numeric"
                  autocomplete="username"
                  maxlength="8"
                  placeholder="12345678"
                  :error="erroresAcceso.dni"
                  @blur="validarAcceso('dni')"
                />
                <CampoTexto
                  id="acceso-clave"
                  v-model="acceso.password"
                  etiqueta="Contraseña"
                  :icono="Lock"
                  :type="verClaveAcceso ? 'text' : 'password'"
                  autocomplete="current-password"
                  placeholder="••••••••"
                  :error="erroresAcceso.password"
                  :accion-icono="verClaveAcceso ? EyeOff : Eye"
                  :accion-etiqueta="verClaveAcceso ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                  @accion="verClaveAcceso = !verClaveAcceso"
                  @blur="validarAcceso('password')"
                />
              </div>

              <div class="mt-4 flex items-center justify-between gap-3 text-sm">
                <label class="inline-flex cursor-pointer items-center gap-2 text-texto-secundario">
                  <input
                    v-model="acceso.recordar"
                    type="checkbox"
                    class="size-4 rounded border-borde-control text-botica-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
                  />
                  Recordarme
                </label>
                <RouterLink
                  to="/forgot-password"
                  class="font-medium text-texto-marca hover:underline"
                >
                  ¿Olvidaste tu contraseña?
                </RouterLink>
              </div>

              <p
                v-if="errorAcceso"
                class="mt-4 flex items-start gap-2 rounded-xl bg-peligro-50 px-4 py-3 text-sm text-peligro-700 dark:bg-peligro-500/10 dark:text-peligro-500"
                role="alert"
              >
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                {{ errorAcceso }}
              </p>

              <BotonEnvio
                class="mt-6"
                :cargando="enviandoAcceso"
                :icono="LogIn"
              >
                Entrar
              </BotonEnvio>

              <p class="mt-6 text-center text-sm text-texto-secundario lg:hidden">
                ¿No tienes cuenta?
                <button
                  type="button"
                  class="font-semibold text-texto-marca hover:underline"
                  @click="irA('register')"
                >
                  Crear una
                </button>
              </p>
            </form>
          </section>

          <!-- Registro -->
          <section
            class="p-6 sm:p-10 lg:col-start-2 lg:row-start-1"
            :class="{ 'hidden lg:block': !esRegistro }"
            :inert="!esRegistro || undefined"
            aria-labelledby="titulo-registro"
          >
            <form
              class="mx-auto flex h-full max-w-sm flex-col justify-center"
              novalidate
              @submit.prevent="enviarRegistro"
            >
              <h1
                id="titulo-registro"
                class="text-2xl font-bold tracking-tight text-texto-primario"
              >
                Crear cuenta
              </h1>
              <p class="mt-2 text-sm text-texto-secundario">
                Para comprar en línea y seguir tus pedidos.
              </p>

              <div class="mt-6 space-y-3.5">
                <CampoTexto
                  id="registro-nombre"
                  v-model="registro.name"
                  etiqueta="Nombre completo"
                  :icono="User"
                  autocomplete="name"
                  placeholder="María Quispe"
                  :error="erroresRegistro.name"
                  @blur="validarRegistro('name')"
                />
                <CampoTexto
                  id="registro-dni"
                  v-model="registro.dni"
                  etiqueta="DNI"
                  :icono="IdCard"
                  inputmode="numeric"
                  autocomplete="username"
                  maxlength="8"
                  placeholder="12345678"
                  :error="erroresRegistro.dni"
                  @blur="validarRegistro('dni')"
                />
                <CampoTexto
                  id="registro-correo"
                  v-model="registro.email"
                  etiqueta="Correo electrónico"
                  :icono="Mail"
                  type="email"
                  autocomplete="email"
                  placeholder="maria@correo.com"
                  :error="erroresRegistro.email"
                  @blur="validarRegistro('email')"
                />
                <CampoTexto
                  id="registro-telefono"
                  v-model="registro.telefono"
                  etiqueta="Teléfono"
                  :icono="Phone"
                  inputmode="tel"
                  autocomplete="tel"
                  maxlength="9"
                  placeholder="987654321"
                  :error="erroresRegistro.telefono"
                  @blur="validarRegistro('telefono')"
                />
                <div>
                  <CampoTexto
                    id="registro-clave"
                    v-model="registro.password"
                    etiqueta="Contraseña"
                    :icono="Lock"
                    :type="verClaveRegistro ? 'text' : 'password'"
                    autocomplete="new-password"
                    placeholder="Mínimo 8 caracteres"
                    :error="erroresRegistro.password"
                    :accion-icono="verClaveRegistro ? EyeOff : Eye"
                    :accion-etiqueta="verClaveRegistro ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                    @accion="verClaveRegistro = !verClaveRegistro"
                    @blur="validarRegistro('password')"
                  />
                  <!-- Medidor de fortaleza: informa, no bloquea -->
                  <div
                    v-if="registro.password"
                    class="mt-2"
                  >
                    <div class="flex gap-1">
                      <span
                        v-for="tramo in 4"
                        :key="tramo"
                        class="h-1 flex-1 rounded-full transition-colors"
                        :class="tramo <= fortaleza.nivel ? fortaleza.clase : 'bg-superficie-hundida'"
                      />
                    </div>
                    <p class="mt-1.5 text-xs text-texto-terciario">
                      {{ fortaleza.rotulo }}
                    </p>
                  </div>
                </div>
              </div>

              <label class="mt-4 inline-flex cursor-pointer items-start gap-2 text-sm text-texto-secundario">
                <input
                  v-model="registro.aceptaTerminos"
                  type="checkbox"
                  class="mt-0.5 size-4 shrink-0 rounded border-borde-control text-botica-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-anillo-foco"
                />
                <span>
                  Acepto los términos del servicio y el tratamiento de mis datos
                  personales.
                </span>
              </label>

              <p
                v-if="errorRegistro"
                class="mt-4 flex items-start gap-2 rounded-xl bg-peligro-50 px-4 py-3 text-sm text-peligro-700 dark:bg-peligro-500/10 dark:text-peligro-500"
                role="alert"
              >
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                {{ errorRegistro }}
              </p>

              <BotonEnvio
                class="mt-6"
                :cargando="enviandoRegistro"
                :icono="UserPlus"
              >
                Crear cuenta
              </BotonEnvio>

              <p class="mt-6 text-center text-sm text-texto-secundario lg:hidden">
                ¿Ya tienes cuenta?
                <button
                  type="button"
                  class="font-semibold text-texto-marca hover:underline"
                  @click="irA('login')"
                >
                  Inicia sesión
                </button>
              </p>
            </form>
          </section>

          <!--
            Panel de marca deslizante.
            Solo existe a partir de lg. `transform` sobre el eje X: el
            compositor lo resuelve sin recalcular el diseño de los formularios
            que hay debajo.
          -->
          <div
            class="panel-marca absolute inset-y-0 left-1/2 hidden w-1/2 lg:block"
            :class="{ 'panel-marca--registro': esRegistro }"
            aria-hidden="true"
          >
            <div class="relative flex h-full flex-col items-center justify-center overflow-hidden bg-clinico-900 px-10 text-center text-white">
              <!--
                La espiral de la referencia. Va detrás del contenido y con un
                velo encima: sin el velo, el trazo claro de la espiral pasa por
                debajo del texto blanco y lo deja ilegible justo en el tramo
                donde se cruzan. El velo cuesta nada y garantiza el contraste
                pase por donde pase la curva.
              -->
              <div class="pointer-events-none absolute inset-0">
                <EspiralMarca class="absolute inset-0" />
                <div class="absolute inset-0 bg-clinico-950/45" />
              </div>

              <div class="panel-contenido relative">
                <div class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20">
                  <Pill class="size-8" />
                </div>
                <p class="mt-6 text-sm font-semibold uppercase tracking-[0.2em] text-white/75">
                  Botica San Juan
                </p>
                <!--
                  El color va explícito y no heredado: el sistema de diseño
                  fija `h1..h6 { color: var(--texto-primario) }` de forma
                  global, que sobre este fondo verde oscuro dejaba el título
                  en verde oscuro sobre verde oscuro, prácticamente ilegible.
                -->
                <h2 class="mt-3 text-3xl font-bold leading-tight text-white">
                  {{ esRegistro ? '¿Ya tienes cuenta?' : '¿Primera vez aquí?' }}
                </h2>
                <p class="mx-auto mt-4 max-w-xs text-sm leading-relaxed text-white/85">
                  {{ esRegistro
                    ? 'Entra con tu DNI para ver tus pedidos y tu historial de compras.'
                    : 'Crea tu cuenta para comprar en línea y seguir cada pedido desde aquí.' }}
                </p>
                <button
                  type="button"
                  class="mt-8 rounded-xl border-2 border-white/70 px-8 py-3 text-sm font-semibold uppercase tracking-wider transition hover:bg-white hover:text-botica-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                  @click="irA(esRegistro ? 'login' : 'register')"
                >
                  {{ esRegistro ? 'Iniciar sesión' : 'Crear cuenta' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirmación de acceso -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
    >
      <div
        v-if="exito"
        class="fixed inset-0 z-50 flex items-center justify-center bg-superficie-fondo/90 backdrop-blur-sm"
        role="status"
        aria-live="polite"
      >
        <div class="text-center">
          <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-exito-50 dark:bg-exito-500/15">
            <CheckCircle class="size-8 text-exito-700 dark:text-exito-500" />
          </div>
          <p class="mt-4 text-lg font-semibold text-texto-primario">
            {{ exito }}
          </p>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  AlertCircle,
  ArrowLeft,
  CheckCircle,
  Eye,
  EyeOff,
  IdCard,
  Lock,
  LogIn,
  Mail,
  Phone,
  Pill,
  User,
  UserPlus,
} from 'lucide-vue-next'
import { useAuthStore } from '../stores/auth'
import authService from '../services/auth'
import CampoTexto from './components/CampoTexto.vue'
import BotonEnvio from './components/BotonEnvio.vue'
import EspiralMarca from './components/EspiralMarca.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const MODOS = [
  { nombre: 'login', rotulo: 'Iniciar sesión', esRegistro: false },
  { nombre: 'register', rotulo: 'Crear cuenta', esRegistro: true },
] as const

/* El modo sale de la ruta, así que /register enlazado desde fuera abre ya
   deslizado y el botón atrás del navegador deshace el cambio. */
const esRegistro = computed(() => route.name === 'register')

const irA = (nombre: 'login' | 'register') => {
  if (route.name === nombre) return
  router.push({ name: nombre })
}

/* ------------------------------------------------------------------ acceso */

const acceso = ref({ dni: '', password: '', recordar: false })
const erroresAcceso = ref<Record<string, string>>({})
const errorAcceso = ref('')
const enviandoAcceso = ref(false)
const verClaveAcceso = ref(false)

const validarAcceso = (campo: 'dni' | 'password') => {
  const valor = acceso.value[campo].trim()
  const errores = { ...erroresAcceso.value }

  if (campo === 'dni') {
    if (!valor) errores.dni = 'El DNI es obligatorio'
    else if (!/^\d{8}$/.test(valor)) errores.dni = 'El DNI tiene 8 dígitos'
    else delete errores.dni
  } else {
    if (!valor) errores.password = 'La contraseña es obligatoria'
    else delete errores.password
  }

  erroresAcceso.value = errores
}

const enviarAcceso = async () => {
  validarAcceso('dni')
  validarAcceso('password')
  if (Object.keys(erroresAcceso.value).length > 0) return

  enviandoAcceso.value = true
  errorAcceso.value = ''

  try {
    const resultado = await authStore.login(acceso.value.dni, acceso.value.password)

    if (!resultado.success) {
      errorAcceso.value = resultado.message || 'DNI o contraseña incorrectos.'
      return
    }

    await entrar('Bienvenido', destinoSegunRol())
  } catch (e) {
    errorAcceso.value = e instanceof Error ? e.message : 'No se pudo iniciar sesión.'
  } finally {
    enviandoAcceso.value = false
  }
}

/* ---------------------------------------------------------------- registro */

const registro = ref({
  name: '',
  dni: '',
  email: '',
  telefono: '',
  password: '',
  aceptaTerminos: false,
})
const erroresRegistro = ref<Record<string, string>>({})
const errorRegistro = ref('')
const enviandoRegistro = ref(false)
const verClaveRegistro = ref(false)

type CampoRegistro = 'name' | 'dni' | 'email' | 'telefono' | 'password'

const validarRegistro = (campo: CampoRegistro) => {
  const valor = registro.value[campo].trim()
  const errores = { ...erroresRegistro.value }
  delete errores[campo]

  switch (campo) {
    case 'name':
      if (valor.length < 3) errores.name = 'Escribe tu nombre completo'
      break
    case 'dni':
      if (!/^\d{8}$/.test(valor)) errores.dni = 'El DNI tiene 8 dígitos'
      break
    case 'email':
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(valor)) errores.email = 'Correo no válido'
      break
    case 'telefono':
      /* Nueve dígitos que empiezan por 9: es el formato de móvil en Perú y
         el único que sirve para avisar de un pedido. */
      if (!/^9\d{8}$/.test(valor)) errores.telefono = 'Un móvil peruano: 9 dígitos que empiezan por 9'
      break
    case 'password':
      if (valor.length < 8) errores.password = 'Mínimo 8 caracteres'
      break
  }

  erroresRegistro.value = errores
}

/* Informa de la fortaleza sin impedir continuar: el requisito duro es la
   longitud mínima, que sí se valida. */
const fortaleza = computed(() => {
  const clave = registro.value.password
  let nivel = 0
  if (clave.length >= 8) nivel++
  if (clave.length >= 12) nivel++
  if (/[A-Z]/.test(clave) && /[a-z]/.test(clave)) nivel++
  if (/\d/.test(clave) && /[^A-Za-z0-9]/.test(clave)) nivel++

  const escala = [
    { rotulo: 'Muy débil', clase: 'bg-peligro-600' },
    { rotulo: 'Débil', clase: 'bg-peligro-600' },
    { rotulo: 'Aceptable', clase: 'bg-alerta-500' },
    { rotulo: 'Buena', clase: 'bg-exito-600' },
    { rotulo: 'Muy buena', clase: 'bg-exito-600' },
  ]

  return { nivel, ...escala[nivel] }
})

const enviarRegistro = async () => {
  ;(['name', 'dni', 'email', 'telefono', 'password'] as CampoRegistro[]).forEach(validarRegistro)

  if (Object.keys(erroresRegistro.value).length > 0) return

  if (!registro.value.aceptaTerminos) {
    errorRegistro.value = 'Debes aceptar los términos para continuar.'
    return
  }

  enviandoRegistro.value = true
  errorRegistro.value = ''

  try {
    const respuesta = await authService.register({
      nombre: registro.value.name.trim(),
      dni: registro.value.dni.trim(),
      email: registro.value.email.trim(),
      password: registro.value.password,
      telefono: registro.value.telefono.trim(),
      acepta_terminos: registro.value.aceptaTerminos,
    })

    authService.setAuthData(respuesta)
    await entrar('Cuenta creada', '/client/home')
  } catch (e) {
    errorRegistro.value = e instanceof Error ? e.message : 'No se pudo crear la cuenta.'
  } finally {
    enviandoRegistro.value = false
  }
}

/* ------------------------------------------------------------------ salida */

const exito = ref('')

const destinoSegunRol = () => {
  /* Un parámetro `redirect` solo se respeta si es una ruta interna. Aceptar
     una URL absoluta convertiría esta pantalla en un salto abierto hacia
     cualquier dominio, que es el patrón que se usa para suplantar el login. */
  const solicitada = new URLSearchParams(window.location.search).get('redirect')
  if (solicitada && /^\/(?!\/)/.test(solicitada)) return solicitada

  return authStore.user?.rol === 'administrador' ? '/admin/home' : '/client/home'
}

const entrar = async (mensaje: string, destino: string) => {
  exito.value = mensaje
  /* Una pausa breve para que el aviso se lea; no una barra de progreso
     simulada, que alarga la espera sin informar de nada. */
  await new Promise((r) => setTimeout(r, 650))
  router.push(destino)
}

/* Al cambiar de modo se limpian los errores del otro formulario: verlos
   reaparecer al volver resulta desconcertante. */
watch(esRegistro, () => {
  errorAcceso.value = ''
  errorRegistro.value = ''
})
</script>

<style scoped>
/*
  El panel arranca cubriendo la mitad derecha (modo acceso) y se desliza a la
  izquierda para descubrir el registro.

  Se anima `transform` y nada más: es la única propiedad, junto con `opacity`,
  que el navegador puede resolver en el compositor sin volver a calcular el
  diseño de la página en cada fotograma.
*/
.panel-marca {
  transform: translateX(0);
  transition: transform 700ms cubic-bezier(0.65, 0, 0.35, 1);
  will-change: transform;
}

.panel-marca--registro {
  transform: translateX(-100%);
}

/*
  El contenido del panel se desvanece a mitad del recorrido y vuelve. Sin
  esto, el texto viaja legible de un lado a otro y se lee el cambio de
  mensaje antes de llegar, que es lo que delata la animación como truco.
*/
.panel-contenido {
  animation: aparecer-contenido 700ms cubic-bezier(0.65, 0, 0.35, 1);
}

@keyframes aparecer-contenido {
  0%   { opacity: 1; }
  35%  { opacity: 0; transform: translateX(-1.5rem); }
  36%  { transform: translateX(1.5rem); }
  100% { opacity: 1; transform: translateX(0); }
}

/*
  Movimiento reducido: no es una preferencia estética. Un desplazamiento
  horizontal de media pantalla provoca mareo a quien tiene sensibilidad
  vestibular, así que el cambio pasa a ser instantáneo.
*/
@media (prefers-reduced-motion: reduce) {
  .panel-marca {
    transition: none;
  }

  .panel-contenido {
    animation: none;
  }
}
</style>
