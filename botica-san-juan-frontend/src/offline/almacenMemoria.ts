import type {
  AlmacenLocal,
  EventoLocal,
  ProductoLocal,
  VentaLocal,
} from './tipos'

/**
 * Almacén en memoria.
 * ---------------------------------------------------------------------------
 * NO ES SOLO PARA LAS PRUEBAS
 *
 * Sirve de red de seguridad en ejecución. IndexedDB puede no estar disponible:
 * ventana privada en algunos navegadores, almacenamiento bloqueado por
 * política del equipo, cuota agotada. En esos casos la caja no debe quedarse
 * sin poder cobrar.
 *
 * Pero conviene no engañarse sobre lo que ofrece: **lo que se guarda aquí se
 * pierde al cerrar la aplicación**. Por eso el store marca este driver como
 * degradado y la pantalla avisa al vendedor de que no apague el equipo con
 * ventas sin sincronizar. Un respaldo silencioso que pierde ventas sería peor
 * que no tener respaldo, porque nadie se enteraría.
 */
export class AlmacenMemoria implements AlmacenLocal {
  readonly nombre = 'memoria'

  private ventas = new Map<string, VentaLocal>()
  private registro: EventoLocal[] = []
  private catalogo: ProductoLocal[] = []
  private siguienteId = 1

  async abrir(): Promise<void> {
    /* No hay nada que abrir. El método existe porque la interfaz lo pide y
       los otros drivers sí lo necesitan. */
  }

  async guardarVenta(venta: VentaLocal): Promise<void> {
    this.ventas.set(venta.uuid_local, { ...venta })
  }

  async ventasPendientes(limite = 50): Promise<VentaLocal[]> {
    return [...this.ventas.values()]
      .filter((v) => v.estado === 'pendiente')
      .sort((a, b) => a.registrada_en.localeCompare(b.registrada_en))
      .slice(0, limite)
      .map((v) => ({ ...v }))
  }

  async marcarVenta(uuid: string, cambios: Partial<VentaLocal>): Promise<void> {
    const actual = this.ventas.get(uuid)
    if (!actual) return
    this.ventas.set(uuid, { ...actual, ...cambios })
  }

  async pendienteMasAntigua(): Promise<VentaLocal | null> {
    const [primera] = await this.ventasPendientes(1)
    return primera ?? null
  }

  async registrarEvento(evento: EventoLocal): Promise<void> {
    this.registro.push({ ...evento, id: this.siguienteId++ })
    /* Tope duro: la bitácora de la caja es un diario de operación, no un
       almacén histórico. El histórico está en el servidor. */
    if (this.registro.length > 500) {
      this.registro = this.registro.slice(-500)
    }
  }

  async eventos(limite = 100): Promise<EventoLocal[]> {
    return this.registro.slice(-limite).reverse()
  }

  async guardarCatalogo(productos: ProductoLocal[]): Promise<void> {
    const porId = new Map(this.catalogo.map((p) => [p.id, p]))
    for (const producto of productos) porId.set(producto.id, producto)
    this.catalogo = [...porId.values()]
  }

  async buscarEnCatalogo(termino: string, limite = 20): Promise<ProductoLocal[]> {
    const aguja = termino.trim().toLowerCase()
    if (aguja.length < 2) return []

    return this.catalogo
      .filter(
        (p) =>
          p.nombre.toLowerCase().includes(aguja) ||
          (p.principio_activo ?? '').toLowerCase().includes(aguja) ||
          p.codigo_barras === termino.trim(),
      )
      .slice(0, limite)
  }
}
