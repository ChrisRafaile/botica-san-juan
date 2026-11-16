import api from './api'

export interface RecetaPedidoResumen {
  id: number
  estado: 'pendiente' | 'aprobada' | 'rechazada' | string
  observacion?: string | null
  archivo_url?: string
  tiene_archivo?: boolean
  descarga_url?: string
  subida_en?: string
  revisada_en?: string | null
}

export interface ProductoPedido {
  id: number
  nombre: string
  imagen?: string | null
  concentracion?: string | null
  presentacion?: string | null
  tipo?: string | null
  precio?: number | string
  stock?: number | null
  requiere_receta?: boolean
}

export interface DetallePedido {
  id: number
  producto_id: number
  cantidad: number
  precio: number | string
  precio_unitario?: number | string
  subtotal?: number | string
  producto?: ProductoPedido | null
}

export interface PedidoCliente {
  id: number
  fecha?: string | null
  fecha_pedido?: string | null
  created_at?: string | null
  estado: 'pendiente' | 'confirmado' | 'completado' | 'anulado' | string
  estado_pago?: string | null
  medio_pago?: string | null
  origen?: string | null
  total: number | string
  observacion?: string | null
  requiere_receta?: boolean
  receta_estado?: string | null
  receta?: RecetaPedidoResumen | null
  pedido_detalles?: DetallePedido[]
  expira_en?: string | null
  anulado_en?: string | null
  motivo_anulacion?: string | null
}

export interface ResumenCliente {
  pedidos: {
    abiertos: number
    completados: number
    anulados: number
    total: number
  }
  gasto: {
    total: number
    ultimos_30_dias: number
  }
  ultimo_pedido: {
    id: number
    fecha: string | null
    estado: string
    total: number
  } | null
  comprobantes: {
    id: number
    tipo: string
    serie: string
    numero: string
    correlativo: string
    total: number
    fecha_emision: string | null
    estado_sunat: string | null
  }[]
}

export interface ProductoDestacado {
  id: number
  nombre: string
  concentracion?: string | null
  presentacion?: string | null
  laboratorio?: string | null
  principio_activo?: string | null
  categoria?: string | null
  categoria_slug?: string | null
  precio: number
  moneda?: string
  requiere_receta?: boolean
  disponible: boolean
  imagen?: string | null
}

export type EstadoFiltroPedido = 'todos' | 'pendiente' | 'confirmado' | 'completado' | 'anulado'

const ETIQUETAS_ESTADO: Record<string, string> = {
  pendiente: 'Pendiente',
  confirmado: 'Confirmado',
  completado: 'Entregado',
  anulado: 'Cancelado',
}

const CLASES_ESTADO: Record<string, string> = {
  pendiente: 'bg-ambar-50 text-ambar-700 dark:bg-ambar-500/15 dark:text-ambar-300',
  confirmado: 'bg-clinico-50 text-clinico-700 dark:bg-clinico-500/15 dark:text-clinico-300',
  completado: 'bg-botica-50 text-botica-700 dark:bg-botica-500/15 dark:text-botica-300',
  anulado: 'bg-peligro-50 text-peligro-700 dark:bg-peligro-500/15 dark:text-peligro-400',
}

export function etiquetaEstadoPedido(estado?: string | null): string {
  const clave = String(estado || 'pendiente').toLowerCase()
  return ETIQUETAS_ESTADO[clave] ?? clave
}

export function claseEstadoPedido(estado?: string | null): string {
  const clave = String(estado || 'pendiente').toLowerCase()
  return CLASES_ESTADO[clave] ?? 'bg-superficie-hundida text-texto-secundario'
}

export function pedidoRequierePago(pedido: PedidoCliente): boolean {
  if (pedido.estado === 'anulado') return false
  return pedido.estado_pago !== 'pagado'
}

export function formatearMoneda(valor: number | string | null | undefined): string {
  const numero = Number(valor)
  if (!Number.isFinite(numero)) return 'S/ 0.00'
  return `S/ ${numero.toFixed(2)}`
}

export function formatearFecha(fecha?: string | null): string {
  if (!fecha) return 'Fecha por confirmar'
  const parseada = new Date(fecha)
  if (Number.isNaN(parseada.getTime())) return String(fecha)
  return parseada.toLocaleDateString('es-PE', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

export function etiquetaModalidad(observacion?: string | null): string {
  const texto = (observacion ?? '').trim()
  if (!texto || texto === 'Recojo en botica') return 'Recojo en botica'
  return `Envío: ${texto}`
}

export async function cargarPedidosCliente(usuarioId: number): Promise<PedidoCliente[]> {
  const { data } = await api.get<PedidoCliente[] | { data: PedidoCliente[] }>(
    `/pedidos/usuario/${usuarioId}`,
  )
  const lista = Array.isArray(data) ? data : (data?.data ?? [])
  return [...lista].sort((a, b) => Number(b.id) - Number(a.id))
}

export async function cargarResumenCliente(): Promise<ResumenCliente> {
  const { data } = await api.get<{ data: ResumenCliente }>('/pedidos/mi-resumen')
  return data.data
}

export async function cargarProductosDestacados(limite = 8): Promise<ProductoDestacado[]> {
  const { data } = await api.get<{ datos: ProductoDestacado[] }>('/catalogo/destacados', {
    params: { limite },
  })
  return Array.isArray(data?.datos) ? data.datos : []
}

/**
 * Solicita la anulación controlada de un pedido abierto y sin pago confirmado.
 * El servidor devuelve el stock al lote original bajo FEFO y actualiza el comprobante a anulada.
 */
export async function cancelarPedido(
  id: number | string,
  claveIdempotencia?: string,
): Promise<{ ok: boolean; pedido: PedidoCliente; comprobante?: any }> {
  const headers: Record<string, string> = {}
  if (claveIdempotencia) {
    headers['Idempotency-Key'] = claveIdempotencia
  } else if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    headers['Idempotency-Key'] = crypto.randomUUID()
  }

  const { data } = await api.post<{ ok: boolean; pedido: PedidoCliente; comprobante?: any }>(
    `/pedidos/${id}/cancelar`,
    {},
    { headers },
  )
  return data
}
