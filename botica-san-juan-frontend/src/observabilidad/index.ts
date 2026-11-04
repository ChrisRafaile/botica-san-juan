/**
 * Observabilidad del portal · Sentry y PostHog
 * ---------------------------------------------------------------------------
 * SIN CLAVE NO SE CARGA NADA
 *
 * Las dos herramientas quedan inactivas cuando falta su variable de entorno,
 * que es el estado de desarrollo y el de cualquier entorno que no las haya
 * configurado a propósito. Esto no es una comodidad: significa que el código
 * de terceros no se ejecuta en el navegador de nadie salvo decisión expresa.
 *
 * QUÉ SE APAGA DE LO QUE VIENE ENCENDIDO
 *
 * Las dos traen por omisión comportamientos que en una botica no valen:
 *
 *   - PostHog captura clics automáticamente (`autocapture`), y en esa captura
 *     va el TEXTO del elemento pulsado. En un catálogo de medicamentos eso es
 *     el nombre del producto que alguien acaba de tocar.
 *   - PostHog graba la sesión con el texto visible. Aquí se graba con TODO
 *     enmascarado, y las pantallas de receta, carrito y perfil no se graban
 *     en absoluto.
 *   - Sentry adjunta el cuerpo de las peticiones a las migas de pan. Ese
 *     cuerpo lleva contraseñas y documentos.
 *
 * NADIE SE IDENTIFICA CON SU CORREO
 *
 * A PostHog se le da el identificador numérico de la cuenta y nada más. Con
 * el correo o el DNI, el panel de analítica se convierte en un listado de
 * quién compra qué.
 */
import type { App } from 'vue'
import type { Router } from 'vue-router'

import { limpiarPropiedades, limpiarTexto, limpiarUrl, rutaGrabable } from './depurador'

let posthogListo = false

/** ¿Está PostHog activo? Lo usa el router para cortar la grabación. */
export function analiticaActiva(): boolean {
  return posthogListo
}

export async function iniciarObservabilidad(app: App, router: Router): Promise<void> {
  await Promise.all([iniciarSentry(app, router), iniciarPostHog(router)])
}

// --------------------------------------------------------------------- Sentry

async function iniciarSentry(app: App, router: Router): Promise<void> {
  const dsn = import.meta.env.VITE_SENTRY_DSN as string | undefined

  if (!dsn) return

  const Sentry = await import('@sentry/vue')

  Sentry.init({
    app,
    dsn,
    environment: (import.meta.env.MODE as string) ?? 'production',

    /* `sendDefaultPii` NO se pasa aquí: no existe en el SDK de navegador,
       solo en los de servidor. El navegador no adjunta IP ni datos de usuario
       por su cuenta; lo único que puede identificar a alguien es lo que esta
       aplicación ponga con `setUser`, y eso lo recorta `beforeSend` más
       abajo. Se deja dicho porque la ausencia de la opción invita a pensar
       que falta algo. */

    /* Rendimiento apagado: las trazas llevan URLs con identificadores y
       multiplican el volumen sin que hoy nadie las mire. */
    tracesSampleRate: 0,

    /* La repetición de sesión de Sentry se deja APAGADA del todo. Grabar la
       pantalla ya lo hace PostHog, con su política de enmascarado; tener dos
       grabadores es duplicar el riesgo sin ganar nada. */
    replaysSessionSampleRate: 0,
    replaysOnErrorSampleRate: 0,

    integrations: [
      Sentry.browserTracingIntegration({ router, enableInp: false }),
    ],

    /* Las migas de pan son el rastro de lo que pasó antes del error, y es
       justo donde se cuela lo que no debe salir: el cuerpo de un fetch, el
       texto de un console.log, la URL con el término de búsqueda. */
    beforeBreadcrumb(miga) {
      /* La consola se descarta entera. En desarrollo lleva volcados de
         objetos completos, y nadie revisa qué se imprime pensando en que un
         día eso viajará a un servicio de terceros. */
      if (miga.category === 'console') return null

      if (typeof miga.message === 'string') {
        miga.message = limpiarTexto(miga.message)
      }

      if (miga.data && typeof miga.data === 'object') {
        const datos = miga.data as Record<string, unknown>

        if (typeof datos.url === 'string') datos.url = limpiarUrl(datos.url)

        /* El cuerpo de la petición y el de la respuesta se quitan sin
           mirarlos: llevan contraseñas, documentos y líneas de pedido. */
        delete datos.body
        delete datos.response
        delete datos.request_body_size
      }

      return miga
    },

    beforeSend(evento) {
      if (evento.request) {
        if (evento.request.url) evento.request.url = limpiarUrl(evento.request.url)
        delete evento.request.cookies
        delete evento.request.data
        delete evento.request.headers
        delete evento.request.query_string
      }

      /* El usuario se reduce a su identificador: sin correo, sin nombre y
         sin IP. Con el id basta para saber si un fallo afecta a una cuenta
         o a muchas. */
      if (evento.user) {
        evento.user = { id: evento.user.id }
      }

      if (evento.message) evento.message = limpiarTexto(evento.message)

      for (const excepcion of evento.exception?.values ?? []) {
        if (excepcion.value) excepcion.value = limpiarTexto(excepcion.value)
      }

      return evento
    },
  })
}

// -------------------------------------------------------------------- PostHog

async function iniciarPostHog(router: Router): Promise<void> {
  const clave = import.meta.env.VITE_POSTHOG_KEY as string | undefined

  if (!clave) return

  const { default: posthog } = await import('posthog-js')

  posthog.init(clave, {
    api_host: (import.meta.env.VITE_POSTHOG_HOST as string) ?? 'https://eu.i.posthog.com',

    /* APAGADO. La captura automática de clics incluye el texto del elemento
       pulsado; en un catálogo de medicamentos, eso es el nombre del producto
       que alguien acaba de tocar. Los eventos que hagan falta se mandan a
       mano y se sabe qué llevan. */
    autocapture: false,

    /* También apagado: la vista de página la manda el router después de
       limpiar la URL. Dejarlo automático enviaría la dirección completa, con
       el término de búsqueda dentro. */
    capture_pageview: false,
    capture_pageleave: false,

    /* Sin cookies de terceros ni almacenamiento persistente entre visitas:
       la sesión se identifica mientras dura la pestaña y no deja rastro
       después. */
    persistence: 'memory',
    disable_persistence: true,

    /* No se recoge la dirección IP en el cliente; PostHog la ve de todos
       modos a nivel de red, y eso se corta en el proyecto, no aquí. Queda
       anotado en docs/OBSERVABILIDAD.md. */
    mask_all_text: true,

    session_recording: {
      /* ENMASCARADO RADICAL.
         `maskAllInputs` tapa los campos de formulario. No basta: el texto
         que ya está PINTADO en la pantalla —el nombre del medicamento en la
         ficha, las líneas del carrito, el motivo de rechazo de una receta—
         no es un campo de formulario y se grabaría tal cual.
         `maskTextSelector: '*'` lo tapa todo. */
      maskAllInputs: true,
      maskTextSelector: '*',

      /* Ni siquiera enmascarados: estos elementos no se graban. Lo que se
         marque con `ph-no-capture` desaparece del vídeo. */
      blockSelector: '[data-privado], .ph-no-capture, input[type="file"], img[alt*="eceta"]',

      /* Las peticiones de red no se graban. Llevan el cuerpo entero. */
      recordHeaders: false,
      recordBody: false,
      recordCrossOriginIframes: false,
    },

    /* Última puerta antes del envío: se limpian las propiedades que PostHog
       añade por su cuenta, que son las que llevan la URL y el título. */
    sanitize_properties: (propiedades) => limpiarPropiedades(propiedades),

    loaded: (instancia) => {
      /* Nadie se identifica con su correo. Si hay sesión, el identificador
         es el número de cuenta y no se adjunta ninguna propiedad de persona. */
      instancia.register({ $process_person_profile: false })
    },
  })

  posthogListo = true

  /* La vista de página se manda a mano, con la ruta limpia y solo si esa
     ruta es grabable. En las que no lo son, además, se detiene la grabación
     mientras se esté en ellas. */
  router.afterEach((destino) => {
    if (!rutaGrabable(destino.path)) {
      posthog.stopSessionRecording?.()
      return
    }

    posthog.startSessionRecording?.()
    posthog.capture('$pageview', {
      $current_url: limpiarUrl(window.location.href),
      /* El nombre de la ruta, no su dirección: «producto-detalle» en lugar
         de «/products/217?q=amoxicilina». */
      ruta: destino.name ?? destino.path.replace(/\/\d+(?=\/|$)/g, '/:id'),
    })
  })
}
