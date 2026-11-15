import api from './api'

/**
 * Descarga la representación impresa en PDF de un comprobante electrónico
 * usando Axios para adjuntar el token Bearer en la cabecera Authorization.
 */
export async function descargarPdfComprobante(id: number, correlativo?: string): Promise<void> {
  const respuesta = await api.get<Blob>(`/facturacion/documentos/${id}/pdf`, {
    responseType: 'blob',
  })

  const blob =
    respuesta.data instanceof Blob
      ? respuesta.data
      : new Blob([respuesta.data], { type: 'application/pdf' })

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
