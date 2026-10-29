import { obtenerAlmacen } from './almacen'
import { equipoConNombrePropio, nombreDelEquipo } from './dispositivo'

/**
 * Correlativo del ticket interno de contingencia (NV-xxxxxx).
 * ---------------------------------------------------------------------------
 * QUÉ ES Y QUÉ NO ES
 *
 * Es el número del papel que el cliente se lleva cuando no hay conexión, para
 * que la compra sea rastreable y el vendedor pueda cruzarla después con la
 * boleta. **No es un comprobante de pago** y su numeración no tiene nada que
 * ver con la serie oficial: esa la reparte el servidor, que es el único que
 * ve todas las cajas.
 *
 * POR QUÉ EL CONTADOR SE RECONCILIA CON LA COLA
 *
 * El número vive en `localStorage`, que es cómodo pero se borra: basta con
 * que alguien limpie los datos del navegador o reinstale la aplicación. Si el
 * contador volviera a empezar en 1, dos ventas distintas llevarían el mismo
 * papel, y la única forma de distinguirlas sería el UUID, que el cliente no
 * tiene.
 *
 * Por eso, al arrancar, el contador se pone por encima del mayor NV que haya
 * en la cola local. No es infalible —si la cola también se perdió, el número
 * se reutiliza— pero cubre el caso realista, y el UUID sigue garantizando que
 * el servidor nunca duplique la venta.
 *
 * El prefijo lleva el nombre del equipo cuando hay más de una caja, para que
 * dos cajas sin red no emitan el mismo papel.
 */

const CLAVE = 'botica:nv-correlativo'

let siguiente: number | null = null

function leerGuardado(): number {
  try {
    const crudo = localStorage.getItem(CLAVE)
    const valor = crudo === null ? 0 : Number.parseInt(crudo, 10)
    return Number.isFinite(valor) && valor > 0 ? valor : 0
  } catch {
    /* Ventana privada o almacenamiento bloqueado. Se sigue sin contador
       persistente: la reconciliación con la cola hace el resto. */
    return 0
  }
}

function guardar(valor: number): void {
  try {
    localStorage.setItem(CLAVE, String(valor))
  } catch {
    /* Si no se puede guardar, el número sigue siendo correcto durante esta
       sesión. Se pierde al cerrar, y la reconciliación lo recupera. */
  }
}

function numeroDe(nota: string): number {
  const partes = nota.split('-')
  const ultima = partes[partes.length - 1] ?? ''
  const valor = Number.parseInt(ultima, 10)
  return Number.isFinite(valor) ? valor : 0
}

/**
 * Pone el contador por encima de lo que ya se emitió.
 * Se llama una vez al abrir la caja.
 */
export async function reconciliarCorrelativo(): Promise<number> {
  const guardado = leerGuardado()

  let mayorEnCola = 0
  try {
    const almacen = await obtenerAlmacen()
    /* Se miran TODAS las pendientes, no solo el primer lote: una cola larga
       podría tener el número más alto al final. */
    for (const venta of await almacen.ventasPendientes(1000)) {
      mayorEnCola = Math.max(mayorEnCola, numeroDe(venta.nota_contingencia))
    }
  } catch {
    /* Sin almacén solo queda lo guardado. */
  }

  siguiente = Math.max(guardado, mayorEnCola) + 1
  return siguiente
}

/** Reserva el siguiente número y lo deja guardado. */
export async function siguienteNotaContingencia(): Promise<string> {
  if (siguiente === null) await reconciliarCorrelativo()

  const numero = siguiente ?? 1
  siguiente = numero + 1
  guardar(numero)

  return formatearNota(numero)
}

export function formatearNota(numero: number): string {
  /* Con una sola caja el papel sale como NV-000001 a secas. Solo cuando
     alguien le pone nombre propio al equipo se le añaden sus siglas, que es
     cuando hacen falta: dos cajas sin red no pueden emitir el mismo papel. */
  const siglas = equipoConNombrePropio()
    ? nombreDelEquipo().toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 3)
    : ''
  const prefijo = siglas.length > 0 ? `NV${siglas}` : 'NV'

  return `${prefijo}-${String(numero).padStart(6, '0')}`
}

/** Solo para las pruebas. */
export function _reiniciarCorrelativo(): void {
  siguiente = null
  try {
    localStorage.removeItem(CLAVE)
  } catch {
    /* nada que limpiar */
  }
}
