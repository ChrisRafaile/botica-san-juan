import api from './api'

export interface ComprobanteCliente {
  id: number
  tipo: 'boleta' | 'factura' | string
  serie: string
  numero: number | string
  correlativo: string
  total: number
  fecha_emision: string | null
  estado_sunat: string | null
  pedido: {
    id: number
    fecha: string | null
    estado: string | null
    origen?: string | null
  } | null
}

export interface DetalleImpresionComprobante {
  comprobante: {
    tipo: string
    serie: string
    numero: string
    identificador: string
    fecha_emision: string | null
    estado_sunat: string | null
  }
  cliente: {
    nombre: string
    documento: string
    tipo_documento?: string | null
    telefono?: string | null
  }
  items: {
    descripcion: string
    unidad_venta?: string | null
    cantidad: number
    precio_unitario: number
    subtotal: number
    afectacion_igv?: string | null
  }[]
  totales: {
    subtotal_gravado: number
    subtotal_exonerado: number
    subtotal_inafecto: number
    igv: number
    tasa_igv: number
    total: number
  }
  leyenda: string
}

/** Lista los comprobantes emitidos para la cuenta en sesión. */
export async function listarMisComprobantes(): Promise<ComprobanteCliente[]> {
  const { data } = await api.get<{ data: ComprobanteCliente[]; total: number }>('/comprobantes/mios')
  return Array.isArray(data?.data) ? data.data : []
}

/** Obtiene los datos estructurados para previsualizar o imprimir un comprobante. */
export async function obtenerDetalleComprobante(id: number): Promise<DetalleImpresionComprobante> {
  const { data } = await api.get<{ data: DetalleImpresionComprobante }>(`/comprobantes/${id}`)
  return data.data
}

/** Solicita el archivo PDF autenticado como Blob usando el interceptor Bearer. */
export async function obtenerPdfBlobComprobante(id: number): Promise<Blob> {
  const respuesta = await api.get<Blob>(`/facturacion/documentos/${id}/pdf`, {
    responseType: 'blob',
  })

  return respuesta.data instanceof Blob
    ? respuesta.data
    : new Blob([respuesta.data], { type: 'application/pdf' })
}

/**
 * Descarga la representación impresa en PDF de un comprobante electrónico
 * usando Axios para adjuntar el token Bearer en la cabecera Authorization.
 */
export async function descargarPdfComprobante(id: number, correlativo?: string): Promise<void> {
  const blob = await obtenerPdfBlobComprobante(id)
  const urlObjeto = URL.createObjectURL(blob)
  const enlace = document.createElement('a')
  const nombreLimpio = (correlativo || `comprobante-${id}`).replace(/[^A-Za-z0-9_-]/g, '-')

  enlace.href = urlObjeto
  enlace.download = `${nombreLimpio}.pdf`
  enlace.style.display = 'none'
  document.body.appendChild(enlace)
  enlace.click()
  enlace.remove()

  URL.revokeObjectURL(urlObjeto)
}
