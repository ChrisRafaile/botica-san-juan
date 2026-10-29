/**
 * Identidad del equipo de caja.
 * ---------------------------------------------------------------------------
 * Vive en su propio archivo porque lo necesitan dos capas que no deberían
 * depender una de otra: el store de contingencia, para marcar de qué caja
 * viene cada venta, y el correlativo de contingencia, para que dos equipos
 * sin red no emitan el mismo papel.
 *
 * El respaldo es `CAJA-PRINCIPAL` y no «sin identificar»: con una sola caja
 * es la descripción correcta, y un valor que dice «no sé» en un campo de
 * trazabilidad no ayuda a nadie. En una instalación de dos equipos basta con
 * fijar `VITE_NOMBRE_CAJA` en el segundo.
 */

const RESPALDO = 'CAJA-PRINCIPAL'

/** Nombre configurado del equipo, o el respaldo. */
export function nombreDelEquipo(): string {
  const configurado = import.meta.env.VITE_NOMBRE_CAJA

  return typeof configurado === 'string' && configurado.trim().length > 0
    ? configurado.trim()
    : RESPALDO
}

/**
 * Dice si alguien puso nombre a este equipo a propósito.
 *
 * Lo usa el correlativo de contingencia: mientras haya una sola caja, el
 * ticket sale como `NV-000001` a secas. Añadirle siglas «por si acaso»
 * cambiaría la numeración de papeles ya entregados a cambio de nada.
 */
export function equipoConNombrePropio(): boolean {
  return nombreDelEquipo() !== RESPALDO
}
