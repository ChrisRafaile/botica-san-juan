/**
 * useEstadoConexion · ¿el sistema está respondiendo?
 * ---------------------------------------------------------------------------
 * Un puntito verde que siempre está verde no informa de nada. Este mide algo
 * real: si el servidor contesta y en cuánto tiempo.
 *
 * Por qué importa aquí y no es adorno: la botica cobra en el mostrador con el
 * cliente delante. Si la API se cae a media mañana, el vendedor tiene que
 * enterarse en ese momento —y no al pulsar Cobrar y quedarse la venta a
 * medias—. Ese aviso a tiempo es la diferencia entre "espere un momento" y una
 * venta perdida.
 *
 * Tres detalles que evitan que moleste más de lo que ayuda:
 *
 * - `navigator.onLine` sólo se usa para dar por perdida la conexión al vuelo,
 *   nunca para darla por buena: el navegador dice "en línea" con sólo estar
 *   conectado al router, aunque no haya internet ni servidor detrás.
 * - Con la pestaña oculta no se consulta. Sondear un servidor que nadie está
 *   mirando es gastar batería y peticiones para nada.
 * - Hace falta fallar dos veces seguidas para declarar la caída. Un pico suelto
 *   no debería encender una alarma roja en mitad de una venta.
 */

import { computed, onMounted, onUnmounted, ref } from 'vue'
import api from '@/services/api'
import { prefiereMenosMovimiento } from '@/utils/motion'

export type EstadoConexion = 'en-linea' | 'lenta' | 'sin-conexion' | 'verificando'

/** Por encima de esto la conexión se marca lenta, no caída. */
const MS_LENTA = 1200

/** Cada cuánto se comprueba con la pestaña visible. */
const INTERVALO_MS = 30_000

/** Fallos seguidos antes de declarar la caída. */
const FALLOS_PARA_CAIDA = 2

export function useEstadoConexion() {
  const estado = ref<EstadoConexion>('verificando')
  const latencia = ref<number | null>(null)
  const ultimaComprobacion = ref<Date | null>(null)

  let temporizador: number | undefined
  let fallosSeguidos = 0
  let desmontado = false

  /* La tolerancia a un fallo suelto protege un estado bueno ya conocido. Si
     todavía no ha habido ninguna respuesta correcta, no hay nada que proteger:
     el primer fallo se muestra al momento. De otro modo, abrir el panel con el
     servidor caído dejaría "Verificando…" medio minuto, que es peor que decir
     la verdad enseguida. */
  let huboRespuestaBuena = false

  const etiqueta = computed(() => {
    switch (estado.value) {
      case 'en-linea':
        return 'En línea'
      case 'lenta':
        return 'Conexión lenta'
      case 'sin-conexion':
        return 'Sin conexión'
      default:
        return 'Verificando…'
    }
  })

  const detalle = computed(() => {
    if (estado.value === 'sin-conexion') {
      return 'El sistema no responde. Avisa antes de seguir vendiendo.'
    }

    if (latencia.value === null) return 'Comprobando el servidor…'

    return `Servidor respondió en ${latencia.value} ms`
  })

  /** Para lectores de pantalla: sólo se anuncia lo que es un problema. */
  const esProblema = computed(
    () => estado.value === 'sin-conexion' || estado.value === 'lenta',
  )

  async function comprobar(): Promise<void> {
    if (desmontado) return

    /* Sin red no hay nada que preguntar: se ahorra la petición y el timeout. */
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
      fallosSeguidos = FALLOS_PARA_CAIDA
      estado.value = 'sin-conexion'
      latencia.value = null
      ultimaComprobacion.value = new Date()
      return
    }

    const inicio = performance.now()

    try {
      await api.get('/salud', { timeout: 5000 })

      if (desmontado) return

      const ms = Math.round(performance.now() - inicio)

      fallosSeguidos = 0
      huboRespuestaBuena = true
      latencia.value = ms
      estado.value = ms > MS_LENTA ? 'lenta' : 'en-linea'
    } catch {
      if (desmontado) return

      fallosSeguidos++
      latencia.value = null

      /* Con un estado bueno previo, un fallo aislado no cambia nada hasta
         confirmarlo. Sin él, se informa al primer intento. */
      if (! huboRespuestaBuena || fallosSeguidos >= FALLOS_PARA_CAIDA) {
        estado.value = 'sin-conexion'
      }
    } finally {
      ultimaComprobacion.value = new Date()
    }
  }

  function programar(): void {
    detener()

    if (typeof document !== 'undefined' && document.hidden) return

    temporizador = window.setInterval(comprobar, INTERVALO_MS)
  }

  function detener(): void {
    if (temporizador) {
      window.clearInterval(temporizador)
      temporizador = undefined
    }
  }

  /** Al volver a la pestaña se comprueba ya, sin esperar al siguiente ciclo. */
  function alCambiarVisibilidad(): void {
    if (document.hidden) {
      detener()
      return
    }

    comprobar()
    programar()
  }

  function alPerderRed(): void {
    fallosSeguidos = FALLOS_PARA_CAIDA
    estado.value = 'sin-conexion'
    latencia.value = null
  }

  onMounted(() => {
    comprobar()
    programar()

    document.addEventListener('visibilitychange', alCambiarVisibilidad)
    window.addEventListener('offline', alPerderRed)
    window.addEventListener('online', comprobar)
  })

  onUnmounted(() => {
    desmontado = true
    detener()

    document.removeEventListener('visibilitychange', alCambiarVisibilidad)
    window.removeEventListener('offline', alPerderRed)
    window.removeEventListener('online', comprobar)
  })

  return {
    estado,
    etiqueta,
    detalle,
    latencia,
    esProblema,
    ultimaComprobacion,
    comprobar,
    /* La pulsación del indicador se apaga con movimiento reducido. */
    animar: !prefiereMenosMovimiento(),
  }
}
