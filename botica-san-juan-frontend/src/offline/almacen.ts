import { AlmacenIndexedDB } from './almacenIndexedDB'
import { AlmacenMemoria } from './almacenMemoria'
import type { AlmacenLocal } from './tipos'

/**
 * Elección del driver de persistencia local.
 * ---------------------------------------------------------------------------
 * ORDEN DE PREFERENCIA
 *
 *   1. SQLite, cuando la aplicación corra dentro de Tauri. Todavía no existe:
 *      el entorno nativo no está inicializado. El hueco está marcado abajo y
 *      es lo único que habrá que tocar.
 *   2. IndexedDB, que es lo que se usa hoy en el navegador.
 *   3. Memoria, como último recurso cuando IndexedDB no está disponible.
 *
 * El tercero es un apaño consciente y la aplicación lo dice en voz alta: con
 * el driver de memoria, cerrar la ventana pierde las ventas sin sincronizar.
 * Degradar en silencio sería peor que fallar.
 */

/**
 * Detecta si estamos dentro del contenedor nativo.
 *
 * Se comprueba la marca que Tauri inyecta en `window`. No se importa
 * `@tauri-apps/api`: ese paquete no está instalado todavía, y un import
 * estático de algo inexistente rompería la compilación del navegador.
 */
export function enTauri(): boolean {
  if (typeof window === 'undefined') return false
  return '__TAURI_INTERNALS__' in window || '__TAURI__' in window
}

let instancia: AlmacenLocal | null = null

/**
 * Devuelve el almacén ya abierto. Idempotente: la segunda llamada reutiliza
 * la misma instancia en vez de volver a abrir la base.
 */
export async function obtenerAlmacen(): Promise<AlmacenLocal> {
  if (instancia) return instancia

  // TODO(tauri): cuando se inicialice el entorno nativo, aquí entra
  // `AlmacenSqlite`, que implementa la misma interfaz. Nada más cambia.
  // if (enTauri()) { instancia = new AlmacenSqlite(); await instancia.abrir(); return instancia }

  if (AlmacenIndexedDB.disponible()) {
    const candidato = new AlmacenIndexedDB()
    try {
      await candidato.abrir()
      instancia = candidato
      return instancia
    } catch {
      /* Cuota agotada, almacenamiento bloqueado, otra pestaña reteniendo una
         versión anterior de la base. Se cae a memoria: la caja tiene que
         poder cobrar igual. Quien avisa al vendedor es el store, que lee
         `nombre` y sabe que está degradado. */
    }
  }

  instancia = new AlmacenMemoria()
  await instancia.abrir()
  return instancia
}

/** Solo para las pruebas: permite inyectar un doble y descartarlo después. */
export function _fijarAlmacen(almacen: AlmacenLocal | null): void {
  instancia = almacen
}
