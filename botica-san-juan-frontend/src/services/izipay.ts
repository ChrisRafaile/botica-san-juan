import KRGlue from '@lyracom/embedded-form-glue'
import type { RetornoFirmado } from './pagos'

/**
 * Montaje del formulario embebido de la pasarela.
 *
 * Este modulo es la unica pieza del frontend que conoce al proveedor. Su
 * responsabilidad es estrecha a proposito: cargar el cliente del proveedor,
 * pedirle que dibuje su formulario dentro de un contenedor y avisar cuando la
 * operacion termina.
 *
 * Lo que este modulo NO hace, y no debe hacer nunca:
 *
 *   - Leer, tocar o transportar el numero de tarjeta, el codigo de
 *     verificacion o la fecha de vencimiento. Esos campos viven dentro de
 *     marcos aislados que el proveedor sirve desde su propio dominio; el
 *     codigo de la aplicacion no puede acceder a ellos aunque quisiera, y esa
 *     imposibilidad es justamente el mecanismo que mantiene al sistema fuera
 *     del alcance normativo.
 *   - Concluir que un pedido esta pagado. El desenlace lo resuelve el backend
 *     con la notificacion servidor a servidor. Lo que ocurre aqui solo sirve
 *     para encaminar la interfaz.
 */

/** Configuracion publica que el backend entrega al preparar el cobro. */
export interface ConfiguracionCheckout {
  form_token: string | null
  public_key: string | null
  modo: string
  client_url: string
  client_theme: string
}

/**
 * Respuesta del proveedor al crear la transaccion.
 *
 * Se declara solo lo que este modulo usa. Los nombres provienen de las
 * definiciones de tipo que publica la propia biblioteca del proveedor.
 */
interface RespuestaDelFormulario {
  /** Resultado en texto literal. Es el contenido sobre el que se firma. */
  rawClientAnswer: string
  /** Resumen con clave del contenido anterior. */
  hash: string
  /** Algoritmo empleado. */
  hashAlgorithm: string
  /** Cual de las dos claves firmo: la del navegador o la del canal servidor. */
  hashKey: string
  clientAnswer: { orderStatus?: string }
}

/**
 * Forma del retorno firmado, tal como lo espera el backend. Se reexporta el
 * tipo del modulo de pagos para que exista una sola definicion.
 */
export type RetornoVerificable = RetornoFirmado

export interface OpcionesMontaje {
  /** Selector del contenedor donde el proveedor dibujara su formulario. */
  selector: string
  configuracion: ConfiguracionCheckout
  /** Idioma del formulario, en formato de cultura. */
  idioma?: string
  /**
   * Se invoca cuando el proveedor termina de crear la transaccion, con o sin
   * exito. Recibe el mensaje firmado tal como llego, para que el backend lo
   * verifique. No es una confirmacion de cobro: solo encamina la interfaz.
   */
  alTerminar: (retorno: RetornoVerificable) => void
  /** Se invoca ante un error del formulario, para mostrarlo al cliente. */
  alFallar?: (mensaje: string) => void
}

const temasCargados = new Set<string>()

/**
 * Inyecta la hoja de estilos y el script del tema.
 *
 * El cliente JavaScript lo carga la biblioteca del proveedor, pero el tema
 * visual son dos recursos aparte que deben corresponder entre si.
 */
function cargarTema(baseUrl: string, tema: string): Promise<void> {
  const clave = `${baseUrl}|${tema}`

  if (temasCargados.has(clave)) {
    return Promise.resolve()
  }

  const raiz = `${baseUrl.replace(/\/$/, '')}/static/js/krypton-client/V4.0/ext`

  const hoja = document.createElement('link')
  hoja.rel = 'stylesheet'
  hoja.href = `${raiz}/${tema}-reset.min.css`
  document.head.appendChild(hoja)

  return new Promise((resolver, rechazar) => {
    const script = document.createElement('script')
    script.src = `${raiz}/${tema}.js`
    script.onload = () => {
      temasCargados.add(clave)
      resolver()
    }
    script.onerror = () => rechazar(new Error('No se pudo cargar el tema del formulario.'))
    document.head.appendChild(script)
  })
}

/**
 * Carga el cliente del proveedor y dibuja el formulario.
 *
 * Devuelve una funcion de limpieza que conviene invocar al desmontar la vista.
 */
export async function montarFormulario(op: OpcionesMontaje): Promise<() => void> {
  const cfg = op.configuracion

  if (!cfg.public_key || !cfg.form_token) {
    throw new Error('La pasarela no devolvió el token de formulario.')
  }

  await cargarTema(cfg.client_url, cfg.client_theme)

  const { KR } = await KRGlue.loadLibrary(cfg.client_url, cfg.public_key, cfg.form_token)

  await KR.setFormConfig({
    'kr-language': op.idioma ?? 'es-PE',
    // En una aplicacion de una sola pagina el formulario no debe inicializarse
    // solo ni publicar hacia una URL de retorno: la navegacion la controla la
    // aplicacion y el desenlace lo resuelve el backend por su propio canal.
    'kr-spa-mode': true,
    // El codigo de verificacion se limpia tras un rechazo. No se conserva en
    // ningun lado, ni siquiera dentro del marco del proveedor.
    'kr-clear-on-error': false,
  })

  if (op.alFallar) {
    await KR.onError((error) => {
      op.alFallar?.(error?.errorMessage ?? 'No se pudo procesar el pago.')
    })
  }

  await KR.onSubmit((respuesta) => {
    const r = respuesta as unknown as RespuestaDelFormulario

    // Se entrega el mensaje firmado tal cual, sin reserializarlo: la firma se
    // calculo sobre ese texto exacto y cualquier reconstruccion la invalida.
    op.alTerminar({
      'kr-answer': r.rawClientAnswer,
      'kr-hash': r.hash,
      'kr-hash-algorithm': r.hashAlgorithm,
      'kr-hash-key': r.hashKey,
    })

    // Devolver false detiene la publicacion hacia una URL de retorno.
    return false
  })

  await KR.renderElements(op.selector)

  return () => {
    // Desmontar es una cortesia: si el cliente del proveedor ya no esta
    // disponible no hay nada que limpiar, y eso no debe romper la navegacion.
    void KR.removeForms().catch(() => undefined)
  }
}
