import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import api from '@/services/api'
import { obtenerAlmacen } from '@/offline/almacen'
import { CODIGOS, bitacora } from '@/offline/bitacora'
import type { VentaLocal } from '@/offline/tipos'

/**
 * Cola de ventas cobradas sin conexión.
 * ---------------------------------------------------------------------------
 * LO QUE ESTE STORE GARANTIZA, Y LO QUE NO
 *
 * Garantiza que una venta cobrada queda guardada antes de que la pantalla diga
 * «cobrado», y que se enviará al servidor cuando haya red, tantas veces como
 * haga falta hasta que entre. No garantiza que entre a la primera, ni falta:
 * la idempotencia del servidor —índice único sobre `uuid_local`— hace que
 * reintentar sea inofensivo. Ver docs/ARQUITECTURA-OFFLINE.md.
 *
 * EL ORDEN DE LAS OPERACIONES AL COBRAR NO ES NEGOCIABLE
 *
 * Primero se guarda en local, después se intenta enviar. Nunca al revés. Si
 * se intentara enviar primero y el envío se quedara a medias —la petición
 * salió, la respuesta no llegó—, la caja no sabría si la venta entró y
 * tampoco la tendría guardada: se habría perdido una venta real. Guardando
 * primero, el peor caso es un reenvío, que no cuesta nada.
 *
 * UNA SOLA SINCRONIZACIÓN A LA VEZ
 *
 * El escuchador de red, el temporizador y el botón manual pueden dispararse
 * casi a la vez. Sin el cerrojo `sincronizando`, tres envíos simultáneos del
 * mismo lote harían trabajo triple y llenarían la bitácora de duplicados que
 * no son tales.
 */

/** Umbrales de atraso frente al envío a SUNAT, en horas. */
const AVISO_HORAS = 24
const ALERTA_HORAS = 48

/** Tope del lote; el mismo que valida el servidor. */
const TAMANO_LOTE = 50

/** Cada cuánto se reintenta mientras hay cola pendiente. */
const REINTENTO_MS = 60_000

type ResultadoServidor = {
  uuid_local: string
  estado: 'creada' | 'duplicada' | 'rechazada'
  pedido_id?: number
  comprobante?: string | null
  motivo?: string
}

export const useOfflineStore = defineStore('offline', () => {
  const enLinea = ref(typeof navigator === 'undefined' ? true : navigator.onLine)
  const sincronizando = ref(false)
  const pendientes = ref<VentaLocal[]>([])
  const driver = ref<string>('sin abrir')
  const ultimaSincronizacion = ref<string | null>(null)
  const ultimoError = ref<string | null>(null)

  /** Envío en vuelo, si lo hay. Hace de cerrojo y de punto de espera. */
  let enCurso: Promise<void> | null = null
  let temporizador: ReturnType<typeof setInterval> | null = null
  let alVolverLaRed: (() => void) | null = null
  let alCaerLaRed: (() => void) | null = null

  const hayPendientes = computed(() => pendientes.value.length > 0)

  /** Almacenamiento volátil: cerrar la aplicación perdería la cola. */
  const almacenamientoDegradado = computed(() => driver.value === 'memoria')

  /** Horas que lleva esperando la venta más antigua sin sincronizar. */
  const horasDeAtraso = computed(() => {
    const masAntigua = pendientes.value[0]
    if (!masAntigua) return 0
    const transcurrido = Date.now() - new Date(masAntigua.registrada_en).getTime()
    return Math.max(0, transcurrido / 3_600_000)
  })

  /**
   * Semáforo del envío a SUNAT.
   *
   * No decide nada por su cuenta: avisa. Quien decide qué hacer con una cola
   * de 48 horas es la persona que atiende, que puede llamar al contador o
   * mover el equipo a otra red.
   */
  const nivelDeAtraso = computed<'ninguno' | 'aviso' | 'alerta'>(() => {
    if (!hayPendientes.value) return 'ninguno'
    if (horasDeAtraso.value >= ALERTA_HORAS) return 'alerta'
    if (horasDeAtraso.value >= AVISO_HORAS) return 'aviso'
    return 'ninguno'
  })

  async function refrescarPendientes(): Promise<void> {
    const almacen = await obtenerAlmacen()
    pendientes.value = await almacen.ventasPendientes(200)
  }

  /**
   * Guarda una venta cobrada. Devuelve la venta tal como quedó en la cola.
   *
   * El `uuid_local` lo genera la caja con `crypto.randomUUID`, que está en
   * todos los navegadores de destino y en WebView2. No se usa un contador
   * local: se reinicia al reinstalar y dos cajas generarían los mismos
   * números.
   */
  async function encolarVenta(
    datos: Omit<VentaLocal, 'uuid_local' | 'estado' | 'intentos'> & { uuid_local?: string },
  ): Promise<VentaLocal> {
    const venta: VentaLocal = {
      ...datos,
      uuid_local: datos.uuid_local ?? crypto.randomUUID(),
      estado: 'pendiente',
      intentos: 0,
    }

    const almacen = await obtenerAlmacen()
    await almacen.guardarVenta(venta)
    await refrescarPendientes()

    await bitacora.info(
      CODIGOS.VENTA_OFFLINE,
      `Venta ${venta.nota_contingencia} guardada en la cola local.`,
      { uuid_local: venta.uuid_local, contexto: { lineas: venta.items.length, total: venta.total } },
    )

    /* Si hay red se intenta de inmediato, pero sin esperar el resultado: la
       pantalla de caja ya puede decir «cobrado» porque la venta está a
       salvo en local. */
    if (enLinea.value) void sincronizar()

    return venta
  }

  /**
   * Envía la cola pendiente. Seguro de llamar en cualquier momento.
   *
   * Si ya hay un envío en curso devuelve ESE mismo compromiso en vez de
   * salir sin hacer nada. La diferencia importa para quien llama: con un
   * retorno vacío, `await sincronizar()` podría resolverse mientras el envío
   * de verdad sigue a medias, y el código de arriba daría por terminado algo
   * que no lo está. Así, esperar siempre significa esperar.
   */
  function sincronizar(): Promise<void> {
    if (enCurso) return enCurso
    if (!enLinea.value) return Promise.resolve()

    enCurso = enviarLote().finally(() => {
      enCurso = null
    })

    return enCurso
  }

  async function enviarLote(): Promise<void> {
    const almacen = await obtenerAlmacen()
    const lote = await almacen.ventasPendientes(TAMANO_LOTE)
    if (lote.length === 0) return

    sincronizando.value = true
    ultimoError.value = null

    await bitacora.info(CODIGOS.SYNC_INICIADA, `Enviando ${lote.length} ventas pendientes.`)

    try {
      const { data } = await api.post<{ resultados: ResultadoServidor[] }>(
        '/v1/ventas/sincronizar',
        {
          dispositivo: nombreDelEquipo(),
          ventas: lote.map(aPayload),
        },
      )

      for (const resultado of data.resultados ?? []) {
        /* `duplicada` NO es un error: significa que el servidor ya la tenía,
           normalmente porque un envío anterior llegó aunque su respuesta se
           perdiera. Marcarla como sincronizada es exactamente lo correcto;
           tratarla como fallo la dejaría reintentándose para siempre. */
        if (resultado.estado === 'creada' || resultado.estado === 'duplicada') {
          await almacen.marcarVenta(resultado.uuid_local, {
            estado: 'sincronizada',
            comprobante: resultado.comprobante ?? null,
            pedido_id: resultado.pedido_id ?? null,
            motivo: null,
          })
          continue
        }

        await almacen.marcarVenta(resultado.uuid_local, {
          estado: 'rechazada',
          motivo: resultado.motivo ?? 'El servidor rechazó la venta sin indicar el motivo.',
        })

        await bitacora.error(
          CODIGOS.VENTA_RECHAZADA,
          `El servidor rechazó la venta: ${resultado.motivo ?? 'sin motivo'}`,
          { uuid_local: resultado.uuid_local },
        )
      }

      ultimaSincronizacion.value = new Date().toISOString()
      await bitacora.info(CODIGOS.SYNC_OK, `Lote de ${lote.length} ventas procesado por el servidor.`)
    } catch (error) {
      /* El fallo es de red o del servidor, no de las ventas: se quedan
         pendientes y se reintentan. Solo se cuenta el intento, que es lo que
         permite distinguir «la red va mal» de «esta venta nunca va a
         entrar». */
      for (const venta of lote) {
        await almacen.marcarVenta(venta.uuid_local, { intentos: venta.intentos + 1 })
      }

      ultimoError.value = mensajeDeError(error)
      await bitacora.aviso(CODIGOS.SYNC_FALLIDA, `No se pudo enviar el lote: ${ultimoError.value}`)
    } finally {
      sincronizando.value = false
      await refrescarPendientes()
      await avisarDelAtraso()
    }
  }

  /**
   * Anota en la bitácora cuando la cola cruza las 24 y las 48 horas.
   *
   * Se anota una sola vez por umbral y por venta: sin esa guarda, un
   * reintento cada minuto escribiría mil cuatrocientas advertencias al día y
   * la bitácora dejaría de servir para nada.
   */
  const umbralesAvisados = new Set<string>()

  async function avisarDelAtraso(): Promise<void> {
    const masAntigua = pendientes.value[0]
    if (!masAntigua) {
      umbralesAvisados.clear()
      return
    }

    const nivel = nivelDeAtraso.value
    if (nivel === 'ninguno') return

    const marca = `${masAntigua.uuid_local}:${nivel}`
    if (umbralesAvisados.has(marca)) return
    umbralesAvisados.add(marca)

    const horas = Math.floor(horasDeAtraso.value)

    if (nivel === 'alerta') {
      await bitacora.error(
        CODIGOS.ATRASO_48H,
        `Hay ventas sin enviar desde hace ${horas} horas. Avisar al responsable.`,
        { uuid_local: masAntigua.uuid_local, contexto: { pendientes: pendientes.value.length, horas } },
      )
      return
    }

    await bitacora.aviso(
      CODIGOS.ATRASO_24H,
      `Hay ventas sin enviar desde hace ${horas} horas.`,
      { uuid_local: masAntigua.uuid_local, contexto: { pendientes: pendientes.value.length, horas } },
    )
  }

  /**
   * Arranca la vigilancia de red. Devuelve la función de parada.
   *
   * `navigator.onLine` dice si hay interfaz de red, no si el servidor
   * responde: un router encendido sin salida a internet da `true`. Por eso el
   * temporizador sigue existiendo aunque el navegador diga que estamos en
   * línea — el reintento periódico es lo que acaba resolviendo esos casos.
   */
  function vigilar(): () => void {
    alVolverLaRed = () => {
      enLinea.value = true
      void bitacora.info(CODIGOS.RED_VUELTA, 'Conexión restablecida; se reanuda la sincronización.')
      void sincronizar()
    }

    alCaerLaRed = () => {
      enLinea.value = false
      void bitacora.aviso(CODIGOS.RED_CAIDA, 'Sin conexión: las ventas se guardarán en la cola local.')
    }

    window.addEventListener('online', alVolverLaRed)
    window.addEventListener('offline', alCaerLaRed)

    temporizador = setInterval(() => {
      if (enLinea.value && hayPendientes.value) void sincronizar()
    }, REINTENTO_MS)

    return dejarDeVigilar
  }

  /**
   * Suelta escuchadores y temporizador.
   *
   * Que esto exista y se llame no es higiene decorativa: la caja vive abierta
   * durante todo el turno, y un temporizador huérfano por cada montaje de la
   * vista acabaría disparando varias sincronizaciones por minuto.
   */
  function dejarDeVigilar(): void {
    if (alVolverLaRed) window.removeEventListener('online', alVolverLaRed)
    if (alCaerLaRed) window.removeEventListener('offline', alCaerLaRed)
    if (temporizador !== null) clearInterval(temporizador)

    alVolverLaRed = null
    alCaerLaRed = null
    temporizador = null
  }

  /** Abre el almacén y carga la cola. Se llama al montar la caja. */
  async function iniciar(): Promise<void> {
    const almacen = await obtenerAlmacen()
    driver.value = almacen.nombre

    if (almacenamientoDegradado.value) {
      await bitacora.aviso(
        CODIGOS.ALMACEN_DEGRADADO,
        'El almacén local no está disponible; la cola vive solo en memoria.',
      )
    }

    await refrescarPendientes()
    await avisarDelAtraso()

    if (enLinea.value && hayPendientes.value) void sincronizar()
  }

  return {
    enLinea,
    sincronizando,
    pendientes,
    driver,
    ultimaSincronizacion,
    ultimoError,
    hayPendientes,
    almacenamientoDegradado,
    horasDeAtraso,
    nivelDeAtraso,
    iniciar,
    vigilar,
    dejarDeVigilar,
    encolarVenta,
    sincronizar,
    refrescarPendientes,
  }
})

/** Convierte la venta local al payload que espera el servidor. */
function aPayload(venta: VentaLocal) {
  return {
    uuid_local: venta.uuid_local,
    nota_contingencia: venta.nota_contingencia,
    registrada_en: venta.registrada_en,
    cliente: venta.cliente,
    observacion: venta.observacion ?? null,
    /* Solo lo que el servidor necesita. El nombre y el precio que la caja
       guardó son para reimprimir el ticket sin conexión; el precio bueno lo
       pone el servidor con su propia tabla, y mandarlo invitaría a que
       alguien lo tomara por válido. */
    items: venta.items.map((i) => ({
      producto_id: i.producto_id,
      unidad_venta: i.unidad_venta,
      cantidad: i.cantidad,
    })),
    pagos: venta.pagos,
  }
}

function nombreDelEquipo(): string {
  return import.meta.env.VITE_NOMBRE_CAJA ?? 'caja-sin-identificar'
}

function mensajeDeError(error: unknown): string {
  if (typeof error === 'object' && error !== null && 'message' in error) {
    return String((error as { message: unknown }).message)
  }
  return 'Error desconocido'
}
