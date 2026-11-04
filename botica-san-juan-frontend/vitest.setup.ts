/**
 * Preparación del entorno de pruebas.
 * ---------------------------------------------------------------------------
 * POR QUÉ EXISTE ESTE ARCHIVO: Node 26 deja `localStorage` sin funcionar
 *
 * Node 26 define `localStorage` y `sessionStorage` como globales propios y,
 * si no se arranca con `--localstorage-file`, su descriptor de acceso devuelve
 * `undefined`. Ese global tapa el de jsdom —en `window` y en `globalThis`,
 * comprobado: los dos aparecen como descriptores de acceso que devuelven
 * `undefined`—, así que cualquier prueba que toque el almacén falla con
 * «Cannot read properties of undefined (reading 'setItem')» aunque el código
 * sea correcto y funcione perfectamente en el navegador.
 *
 * EL SÍNTOMA ENGAÑABA
 *
 * La prueba que caía era la de la descarga incremental del catálogo —«la
 * segunda descarga solo pide lo que cambió»—, y lo que parecía es que la marca
 * de corte no se guardaba. No era eso: el guardado se iba por el `catch` que
 * `guardarCorte` tiene justo para cuando el navegador bloquea el almacén, de
 * modo que el fallo quedaba mudo y aparecía tres líneas más abajo como una
 * fecha que no coincidía.
 *
 * QUÉ SE PONE EN SU LUGAR
 *
 * Una implementación en memoria del contrato `Storage`. No es la de jsdom
 * —esa ya no es alcanzable— pero cumple lo que el código usa y lo que la
 * especificación obliga en esos puntos: las claves y los valores se guardan
 * como cadenas, `getItem` de algo que no existe devuelve `null` (no
 * `undefined`), y `length`/`key()` recorren en orden de inserción.
 *
 * Es sustituto de pruebas, no de producción: no persiste entre procesos ni
 * tiene límite de cuota. Para lo que estas pruebas comprueban —que una marca
 * escrita se vuelve a leer— es exactamente equivalente.
 */

class AlmacenEnMemoria implements Storage {
  private datos = new Map<string, string>()

  get length(): number {
    return this.datos.size
  }

  key(indice: number): string | null {
    return [...this.datos.keys()][indice] ?? null
  }

  getItem(clave: string): string | null {
    /* `null` y no `undefined`: el código distingue «no hay marca de corte» de
       «hay una vacía», y `undefined` rompería esa distinción. */
    return this.datos.has(String(clave)) ? (this.datos.get(String(clave)) as string) : null
  }

  setItem(clave: string, valor: string): void {
    // La especificación coacciona a cadena; guardar el objeto crudo haría
    // pasar pruebas que en el navegador fallarían.
    this.datos.set(String(clave), String(valor))
  }

  removeItem(clave: string): void {
    this.datos.delete(String(clave))
  }

  clear(): void {
    this.datos.clear()
  }
}

for (const clave of ['localStorage', 'sessionStorage'] as const) {
  const actual = (globalThis as Record<string, unknown>)[clave]

  /* Solo se sustituye si NO hay nada usable. El día que Node o jsdom vuelvan
     a ofrecer un almacén de verdad, este archivo se aparta solo. */
  if (actual && typeof (actual as Storage).setItem === 'function') {
    continue
  }

  const almacen = new AlmacenEnMemoria()

  for (const objetivo of [globalThis, typeof window !== 'undefined' ? window : null]) {
    if (!objetivo) continue

    Object.defineProperty(objetivo, clave, {
      value: almacen,
      configurable: true,
      writable: true,
    })
  }
}
