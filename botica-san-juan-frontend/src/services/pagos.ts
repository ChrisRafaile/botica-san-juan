import api from './api'

/**
 * Estados del cobro tal como los expone el backend.
 *
 * Son los unicos nombres que este modulo acepta desde el servidor. La
 * nomenclatura de la pasarela nunca llega hasta aqui: el backend ya la
 * tradujo.
 */
export type EstadoPago =
  | 'pendiente'
  | 'procesando'
  | 'pagado'
  | 'fallido'
  | 'cancelado'
  | 'reembolsado'

/** Estados visuales de la interfaz. No se mezclan con los del backend. */
export type EstadoVisual =
  | 'PAYMENT_PENDING'
  | 'PAYMENT_PROCESSING'
  | 'PAYMENT_SUCCESS'
  | 'PAYMENT_ERROR'
  | 'PAYMENT_CANCELLED'
  | 'PAYMENT_REFUNDED'

/**
 * Mapeo centralizado entre el estado del backend y el estado visual.
 *
 * Existe un unico lugar donde se decide que pantalla corresponde a que
 * estado. Ningun componente vuelve a comparar cadenas de texto por su cuenta.
 */
const MAPA_VISUAL: Record<EstadoPago, EstadoVisual> = {
  pendiente: 'PAYMENT_PENDING',
  procesando: 'PAYMENT_PROCESSING',
  pagado: 'PAYMENT_SUCCESS',
  fallido: 'PAYMENT_ERROR',
  cancelado: 'PAYMENT_CANCELLED',
  reembolsado: 'PAYMENT_REFUNDED',
}

/** Ruta a la que corresponde cada estado visual. */
const MAPA_RUTA: Record<EstadoVisual, string> = {
  PAYMENT_PENDING: '/checkout/pendiente',
  PAYMENT_PROCESSING: '/checkout/procesando',
  PAYMENT_SUCCESS: '/checkout/exitoso',
  PAYMENT_ERROR: '/checkout/error',
  PAYMENT_CANCELLED: '/checkout/cancelado',
  PAYMENT_REFUNDED: '/checkout/pendiente',
}

export function aEstadoVisual(estado: string | undefined | null): EstadoVisual {
  // Un estado desconocido nunca se interpreta como exito.
  return MAPA_VISUAL[(estado ?? '') as EstadoPago] ?? 'PAYMENT_PROCESSING'
}

export function rutaDelEstado(estado: string | undefined | null): string {
  return MAPA_RUTA[aEstadoVisual(estado)]
}

export interface Pago {
  referencia: string
  estado: EstadoPago
  metodo_pago: string | null
  marca_tarjeta: string | null
  ultimos4: string | null
  monto: number
  moneda: string
  pagado_en: string | null
  mensaje_error: string | null
}

export interface InicioDePago {
  ok: boolean
  message: string | null
  pago: Pago
  checkout: {
    form_token: string | null
    /** Llave publica del proveedor. La privada nunca llega al navegador. */
    public_key: string | null
    modo: string
    /** Dominio publico desde el que se carga el cliente del proveedor. */
    client_url: string
    /** Tema visual del formulario embebido. */
    client_theme: string
  }
}

export interface EstadoDePago {
  pago: Pago
  pedido_id: number
  /** true cuando el cobro ya no admite mas transiciones automaticas. */
  es_final: boolean
}

/** Prepara el cobro de un pedido. El importe lo fija el servidor. */
export async function iniciarPago(pedidoId: number): Promise<InicioDePago> {
  const { data } = await api.post<InicioDePago>(`/pedidos/${pedidoId}/pago`)
  return data
}

/**
 * Consulta el estado real del cobro.
 *
 * Es la unica fuente que la interfaz acepta para dar una compra por exitosa.
 * Que el navegador vuelva del checkout del proveedor no significa nada por si
 * mismo.
 */
export async function consultarEstado(referencia: string): Promise<EstadoDePago> {
  const { data } = await api.get<EstadoDePago>(`/pagos/${referencia}/estado`)
  return data
}

/**
 * Entrega al backend el retorno firmado del formulario del proveedor.
 *
 * Sirve para dos cosas y para ninguna mas: acortar la espera de la pantalla de
 * procesamiento y detectar manipulacion. NO marca el pedido como pagado; el
 * backend responde con el estado persistido, no con el que trae el mensaje.
 */
export interface RetornoFirmado {
  'kr-answer': string
  'kr-hash': string
  'kr-hash-algorithm': string
  'kr-hash-key': string
}

export async function validarRetorno(retorno: RetornoFirmado): Promise<EstadoDePago> {
  const { data } = await api.post<EstadoDePago>('/pagos/validar-retorno', retorno)
  return data
}

/** Formatea un importe en la moneda del pago. */
export function formatearMonto(monto: number, moneda = 'PEN'): string {
  const simbolo = moneda === 'PEN' ? 'S/' : moneda
  return `${simbolo} ${Number(monto).toFixed(2)}`
}

/** Descripcion no sensible del medio de pago, apta para mostrar al cliente. */
export function describirMedio(pago: Pago): string {
  if (pago.marca_tarjeta && pago.ultimos4) {
    return `${pago.marca_tarjeta} terminada en ${pago.ultimos4}`
  }
  if (pago.metodo_pago) {
    return pago.metodo_pago.charAt(0).toUpperCase() + pago.metodo_pago.slice(1)
  }
  return 'Medio de pago no informado'
}
