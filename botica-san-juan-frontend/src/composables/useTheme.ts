/**
 * useTheme · Botica San Juan
 * ---------------------------------------------------------------------------
 * Control del modo claro / oscuro del panel.
 *
 * Tres estados, no dos: 'claro', 'oscuro' y 'sistema'. El tercero es el que
 * suele faltar y el que más se agradece — deja que el panel siga la
 * configuración del sistema operativo y cambie solo al anochecer si así lo
 * tiene configurado el equipo.
 *
 * El estado se guarda en localStorage. La escritura va envuelta en try/catch
 * porque en modo incógnito o con cookies bloqueadas, acceder a localStorage
 * lanza excepción en vez de fallar en silencio.
 */

import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

export type PreferenciaTema = 'claro' | 'oscuro' | 'sistema'
export type TemaEfectivo = 'claro' | 'oscuro'

const CLAVE_ALMACEN = 'botica:tema'

/* Estado a nivel de módulo: el tema es único para toda la aplicación, así que
   se comparte entre todos los componentes que llamen al composable. */
const preferencia = ref<PreferenciaTema>('sistema')
const sistemaEsOscuro = ref(false)
let inicializado = false

function leerPreferenciaGuardada(): PreferenciaTema {
  try {
    const guardado = localStorage.getItem(CLAVE_ALMACEN)
    if (guardado === 'claro' || guardado === 'oscuro' || guardado === 'sistema') {
      return guardado
    }
  } catch {
    /* Almacenamiento no disponible — se continúa con el valor por defecto. */
  }
  return 'sistema'
}

function guardarPreferencia(valor: PreferenciaTema): void {
  try {
    localStorage.setItem(CLAVE_ALMACEN, valor)
  } catch {
    /* Sin persistencia: el tema seguirá funcionando durante esta sesión. */
  }
}

function aplicarAlDocumento(esOscuro: boolean): void {
  const raiz = document.documentElement
  raiz.classList.toggle('dark', esOscuro)
  /* Informa al navegador para que pinte controles nativos, scrollbars y el
     color de fondo durante la carga acorde al tema. */
  raiz.style.colorScheme = esOscuro ? 'dark' : 'light'
}

export function useTheme() {
  const consulta =
    typeof window !== 'undefined' && window.matchMedia
      ? window.matchMedia('(prefers-color-scheme: dark)')
      : null

  const alCambiarSistema = (evento: MediaQueryListEvent) => {
    sistemaEsOscuro.value = evento.matches
  }

  /** Tema realmente aplicado, ya resuelto el caso 'sistema'. */
  const temaEfectivo = computed<TemaEfectivo>(() => {
    if (preferencia.value === 'sistema') {
      return sistemaEsOscuro.value ? 'oscuro' : 'claro'
    }
    return preferencia.value
  })

  const esOscuro = computed(() => temaEfectivo.value === 'oscuro')

  function establecerTema(valor: PreferenciaTema): void {
    preferencia.value = valor
    guardarPreferencia(valor)
  }

  /** Alterna claro ↔ oscuro. Al alternar se abandona el seguimiento del sistema. */
  function alternarTema(): void {
    establecerTema(esOscuro.value ? 'claro' : 'oscuro')
  }

  if (!inicializado && typeof window !== 'undefined') {
    inicializado = true
    preferencia.value = leerPreferenciaGuardada()
    sistemaEsOscuro.value = consulta?.matches ?? false
    aplicarAlDocumento(temaEfectivo.value === 'oscuro')
  }

  watch(temaEfectivo, (valor) => {
    if (typeof document !== 'undefined') {
      aplicarAlDocumento(valor === 'oscuro')
    }
  })

  onMounted(() => {
    consulta?.addEventListener('change', alCambiarSistema)
  })

  onUnmounted(() => {
    consulta?.removeEventListener('change', alCambiarSistema)
  })

  return {
    /** Preferencia elegida: 'claro' | 'oscuro' | 'sistema'. */
    preferencia: computed(() => preferencia.value),
    /** Tema aplicado tras resolver 'sistema'. */
    temaEfectivo,
    esOscuro,
    establecerTema,
    alternarTema,
  }
}

/**
 * Script de arranque para evitar el parpadeo blanco.
 *
 * Vue monta después de que el navegador pinta el primer fotograma, así que sin
 * esto un usuario en modo oscuro ve un destello blanco en cada recarga. Este
 * fragmento debe ejecutarse de forma síncrona en el <head> del index.html,
 * antes de cualquier hoja de estilos.
 */
export const SCRIPT_ANTI_PARPADEO = `
(function(){try{var p=localStorage.getItem('${CLAVE_ALMACEN}');var m=window.matchMedia('(prefers-color-scheme: dark)').matches;var d=p==='oscuro'||((!p||p==='sistema')&&m);document.documentElement.classList.toggle('dark',d);document.documentElement.style.colorScheme=d?'dark':'light';}catch(e){}})();
`.trim()
