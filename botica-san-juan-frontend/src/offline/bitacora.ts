import { obtenerAlmacen } from './almacen'
import type { NivelEvento } from './tipos'

/**
 * Bitácora de la caja.
 * ---------------------------------------------------------------------------
 * Espejo local de `eventos_operacion` del servidor. Existe porque cuando no
 * hay conexión tampoco hay forma de contarle nada al servidor, y justo esos
 * son los momentos que interesa poder reconstruir después: cuándo se cayó la
 * red, cuántas ventas se cobraron a ciegas, cuándo volvió.
 *
 * Los códigos son los mismos que usa el backend cuando describen el mismo
 * hecho, para que los dos diarios se puedan cruzar por `codigo` y por
 * `uuid_local` sin traducir nada.
 *
 * QUÉ NO SE ESCRIBE AQUÍ
 *
 * Ni el token de sesión, ni la contraseña, ni el documento del cliente, ni el
 * detalle de lo que compró. El contexto guarda identificadores y cantidades.
 * Esta bitácora se exporta para diagnosticar y acaba en manos de quien
 * revisa; no es sitio para datos personales.
 */

export const CODIGOS = {
  RED_CAIDA: 'CAJA_RED_CAIDA',
  RED_VUELTA: 'CAJA_RED_RESTABLECIDA',
  VENTA_OFFLINE: 'CAJA_VENTA_OFFLINE',
  SYNC_INICIADA: 'CAJA_SYNC_INICIADA',
  SYNC_OK: 'CAJA_SYNC_COMPLETADA',
  SYNC_FALLIDA: 'CAJA_SYNC_FALLIDA',
  VENTA_RECHAZADA: 'CAJA_VENTA_RECHAZADA',
  ATRASO_24H: 'CAJA_ATRASO_24H',
  ATRASO_48H: 'CAJA_ATRASO_48H',
  ALMACEN_DEGRADADO: 'CAJA_ALMACEN_DEGRADADO',
  CATALOGO_DESCARGADO: 'CAJA_CATALOGO_DESCARGADO',
} as const

async function escribir(
  nivel: NivelEvento,
  codigo: string,
  mensaje: string,
  datos: { uuid_local?: string | null; contexto?: Record<string, unknown> } = {},
): Promise<void> {
  try {
    const almacen = await obtenerAlmacen()
    await almacen.registrarEvento({
      nivel,
      codigo,
      mensaje,
      creado_en: new Date().toISOString(),
      uuid_local: datos.uuid_local ?? null,
      contexto: datos.contexto ?? null,
    })
  } catch {
    /* Si no se puede escribir en la bitácora, no pasa nada más: lo último
       que debe hacer un sistema de registro es tumbar la operación que
       estaba registrando. La venta importa; la anotación, menos. */
  }
}

export const bitacora = {
  info: (codigo: string, mensaje: string, datos?: Parameters<typeof escribir>[3]) =>
    escribir('info', codigo, mensaje, datos),

  aviso: (codigo: string, mensaje: string, datos?: Parameters<typeof escribir>[3]) =>
    escribir('warning', codigo, mensaje, datos),

  error: (codigo: string, mensaje: string, datos?: Parameters<typeof escribir>[3]) =>
    escribir('error', codigo, mensaje, datos),

  /** Vuelca la bitácora para adjuntarla a un reporte de incidencia. */
  async exportar(limite = 500): Promise<string> {
    const almacen = await obtenerAlmacen()
    const eventos = await almacen.eventos(limite)
    return JSON.stringify(eventos, null, 2)
  },
}
