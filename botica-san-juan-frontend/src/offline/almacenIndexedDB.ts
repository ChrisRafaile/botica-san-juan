import type {
  AlmacenLocal,
  EventoLocal,
  ProductoLocal,
  VentaLocal,
} from './tipos'

/**
 * Almacén local sobre IndexedDB.
 * ---------------------------------------------------------------------------
 * POR QUÉ IndexedDB Y NO localStorage
 *
 * `localStorage` es síncrono: cada escritura bloquea el hilo de la interfaz.
 * En una caja que cobra con el cliente delante, guardar la venta no puede
 * congelar la pantalla. Además guarda texto plano con un tope de unos pocos
 * megas; el catálogo de 884 productos con sus presentaciones lo rozaría.
 *
 * POR QUÉ SIN LIBRERÍA
 *
 * La API nativa es verbosa, pero son tres almacenes y ocho operaciones. Meter
 * una dependencia para esto engorda el paquete que luego va dentro del
 * instalador de Tauri, y este driver tiene fecha de caducidad: cuando entre
 * SQLite dejará de usarse en el escritorio.
 *
 * EL DETALLE QUE ROMPE SI SE OLVIDA
 *
 * Una transacción de IndexedDB se cierra sola en cuanto el hilo de eventos
 * queda libre. Eso significa que **no se puede hacer `await` de otra cosa en
 * medio de una transacción**: al volver, la transacción ya murió y la
 * escritura se pierde con un error que no siempre salta. Por eso cada método
 * de aquí abre su transacción, hace su trabajo y la cierra, sin esperar nada
 * ajeno por el camino.
 */

const BASE = 'botica-san-juan-caja'
const VERSION = 1

const VENTAS = 'ventas'
const EVENTOS = 'eventos'
const CATALOGO = 'catalogo'

const TOPE_EVENTOS = 500

export class AlmacenIndexedDB implements AlmacenLocal {
  readonly nombre = 'indexeddb'

  private bd: IDBDatabase | null = null

  static disponible(): boolean {
    try {
      return typeof indexedDB !== 'undefined' && indexedDB !== null
    } catch {
      /* Acceder a `indexedDB` lanza en algunos navegadores cuando el
         almacenamiento está bloqueado por política. Preguntar también puede
         fallar, así que la pregunta va dentro del try. */
      return false
    }
  }

  abrir(): Promise<void> {
    if (this.bd) return Promise.resolve()

    return new Promise((resolver, rechazar) => {
      const peticion = indexedDB.open(BASE, VERSION)

      peticion.onupgradeneeded = () => {
        const bd = peticion.result

        if (!bd.objectStoreNames.contains(VENTAS)) {
          const almacen = bd.createObjectStore(VENTAS, { keyPath: 'uuid_local' })
          /* El índice por estado es lo que permite sacar la cola pendiente
             sin recorrer todas las ventas del día. */
          almacen.createIndex('estado', 'estado', { unique: false })
          almacen.createIndex('registrada_en', 'registrada_en', { unique: false })
        }

        if (!bd.objectStoreNames.contains(EVENTOS)) {
          bd.createObjectStore(EVENTOS, { keyPath: 'id', autoIncrement: true })
        }

        if (!bd.objectStoreNames.contains(CATALOGO)) {
          bd.createObjectStore(CATALOGO, { keyPath: 'id' })
        }
      }

      peticion.onsuccess = () => {
        this.bd = peticion.result
        resolver()
      }

      peticion.onerror = () => rechazar(peticion.error ?? new Error('No se pudo abrir el almacén local.'))
      peticion.onblocked = () => rechazar(new Error('El almacén local está bloqueado por otra pestaña abierta.'))
    })
  }

  private almacen(nombre: string, modo: IDBTransactionMode): IDBObjectStore {
    if (!this.bd) throw new Error('El almacén local no está abierto. Llama a abrir() primero.')
    return this.bd.transaction(nombre, modo).objectStore(nombre)
  }

  private esperar<T>(peticion: IDBRequest<T>): Promise<T> {
    return new Promise((resolver, rechazar) => {
      peticion.onsuccess = () => resolver(peticion.result)
      peticion.onerror = () => rechazar(peticion.error ?? new Error('Fallo la operación sobre el almacén local.'))
    })
  }

  // ------------------------------------------------------------ ventas

  async guardarVenta(venta: VentaLocal): Promise<void> {
    await this.esperar(this.almacen(VENTAS, 'readwrite').put(venta))
  }

  async ventasPendientes(limite = 50): Promise<VentaLocal[]> {
    const indice = this.almacen(VENTAS, 'readonly').index('estado')
    const todas = await this.esperar(indice.getAll(IDBKeyRange.only('pendiente')))

    /* Se ordenan por fecha de venta y no por orden de inserción: lo primero
       que debe salir hacia el servidor es lo que más tiempo lleva esperando,
       porque es lo que está más cerca del plazo de envío. */
    return todas
      .sort((a, b) => a.registrada_en.localeCompare(b.registrada_en))
      .slice(0, limite)
  }

  async marcarVenta(uuid: string, cambios: Partial<VentaLocal>): Promise<void> {
    const almacen = this.almacen(VENTAS, 'readwrite')
    const actual = await this.esperar<VentaLocal | undefined>(almacen.get(uuid))
    if (!actual) return
    await this.esperar(almacen.put({ ...actual, ...cambios }))
  }

  async pendienteMasAntigua(): Promise<VentaLocal | null> {
    const [primera] = await this.ventasPendientes(1)
    return primera ?? null
  }

  // ----------------------------------------------------------- eventos

  async registrarEvento(evento: EventoLocal): Promise<void> {
    const almacen = this.almacen(EVENTOS, 'readwrite')
    await this.esperar(almacen.add(evento))

    /* Poda perezosa: solo cuando hay bastante acumulado, y en una
       transacción aparte. La bitácora de la caja es un diario de operación
       reciente; el histórico completo vive en el servidor. */
    const total = await this.esperar(this.almacen(EVENTOS, 'readonly').count())
    if (total > TOPE_EVENTOS * 1.2) {
      await this.podar(total - TOPE_EVENTOS)
    }
  }

  private async podar(cuantos: number): Promise<void> {
    const almacen = this.almacen(EVENTOS, 'readwrite')
    const claves = await this.esperar(almacen.getAllKeys())
    for (const clave of claves.slice(0, cuantos)) {
      almacen.delete(clave)
    }
  }

  async eventos(limite = 100): Promise<EventoLocal[]> {
    const todos = await this.esperar(this.almacen(EVENTOS, 'readonly').getAll())
    return todos.slice(-limite).reverse()
  }

  // ---------------------------------------------------------- catálogo

  async guardarCatalogo(productos: ProductoLocal[]): Promise<void> {
    const almacen = this.almacen(CATALOGO, 'readwrite')
    /* Todos los `put` van en la MISMA transacción: 884 transacciones
       separadas tardarían segundos y dejarían el catálogo a medias si algo
       fallara por el camino. */
    for (const producto of productos) almacen.put(producto)

    await new Promise<void>((resolver, rechazar) => {
      almacen.transaction.oncomplete = () => resolver()
      almacen.transaction.onerror = () =>
        rechazar(almacen.transaction.error ?? new Error('No se pudo guardar el catálogo local.'))
    })
  }

  async buscarEnCatalogo(termino: string, limite = 20): Promise<ProductoLocal[]> {
    const aguja = termino.trim().toLowerCase()
    if (aguja.length < 2) return []

    /* Recorrido completo sobre 884 filas en memoria: en la práctica tarda
       menos de un milisegundo y evita mantener índices de texto que
       IndexedDB no sabe hacer bien. Si el catálogo creciera un orden de
       magnitud habría que revisarlo. */
    const todos = await this.esperar(this.almacen(CATALOGO, 'readonly').getAll())

    const resultado: ProductoLocal[] = []
    for (const p of todos) {
      const coincide =
        p.nombre.toLowerCase().includes(aguja) ||
        (p.principio_activo ?? '').toLowerCase().includes(aguja) ||
        p.codigo_barras === termino.trim()

      if (coincide) resultado.push(p)
      if (resultado.length >= limite) break
    }

    return resultado
  }
}
