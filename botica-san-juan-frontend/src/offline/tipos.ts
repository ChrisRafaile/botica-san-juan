/**
 * Contrato de la capa de persistencia local del punto de venta.
 * ---------------------------------------------------------------------------
 * POR QUÉ HAY UNA INTERFAZ Y NO SE LLAMA A IndexedDB DIRECTAMENTE
 *
 * La aplicación va a empaquetarse con Tauri y entonces la cola de ventas
 * vivirá en SQLite, no en IndexedDB. Si la lógica de negocio hablara con
 * IndexedDB a pelo, ese cambio obligaría a tocar el store, la vista de caja y
 * las pruebas. Con esta interfaz solo se escribe un driver nuevo.
 *
 * No es una abstracción preventiva por si acaso: el segundo driver está
 * decidido y planificado. Esa es la diferencia entre una interfaz útil y una
 * capa de indirección que nadie va a usar nunca.
 *
 * La interfaz es deliberadamente pequeña —ocho métodos— y está escrita en
 * términos del dominio (ventas pendientes, eventos), no del almacenamiento
 * (transacciones, cursores, object stores). Un driver de SQLite no debería
 * tener que fingir que tiene cursores.
 */

/** Estado de una venta dentro de la cola local. */
export type EstadoLocal = 'pendiente' | 'sincronizada' | 'rechazada'

export type NivelEvento = 'info' | 'warning' | 'error'

export interface LineaVentaLocal {
  producto_id: number
  unidad_venta: 'unidad' | 'blister' | 'caja'
  cantidad: number
  /* Se guardan nombre y precio del momento de la venta porque el ticket de
     contingencia hay que poder reimprimirlo sin conexión, y el catálogo local
     podría haberse actualizado desde entonces. El servidor no los usa: vuelve
     a calcular el precio con su propia tabla. */
  nombre: string
  precio_unitario: number
}

export interface PagoLocal {
  medio: string
  monto?: number
  monto_recibido?: number
  referencia?: string
}

export interface VentaLocal {
  /** UUID v4 generado en la caja. Clave de idempotencia de todo el sistema. */
  uuid_local: string
  /** Ticket interno entregado al cliente (NV-000127). No es comprobante de pago. */
  nota_contingencia: string
  /** Momento del cobro en el mostrador, en ISO 8601 con zona horaria. */
  registrada_en: string
  cliente: {
    nombre?: string | null
    documento?: string | null
    tipo_documento?: 'dni' | 'ruc' | 'ce' | 'sin_documento'
  }
  observacion?: string | null
  items: LineaVentaLocal[]
  pagos: PagoLocal[]
  total: number

  estado: EstadoLocal
  /** Cuántas veces se intentó enviar. Sirve para no reintentar en bucle eterno. */
  intentos: number
  /** Motivo devuelto por el servidor cuando el estado es `rechazada`. */
  motivo?: string | null
  /** Correlativo oficial, una vez sincronizada. */
  comprobante?: string | null
  pedido_id?: number | null
}

export interface EventoLocal {
  id?: number
  nivel: NivelEvento
  codigo: string
  mensaje: string
  creado_en: string
  uuid_local?: string | null
  contexto?: Record<string, unknown> | null
}

export interface ProductoLocal {
  id: number
  nombre: string
  concentracion?: string | null
  presentacion?: string | null
  principio_activo?: string | null
  codigo_barras?: string | null
  precio: number
  precio_blister?: number | null
  precio_caja?: number | null
  requiere_receta: boolean
  actualizado_en: string
}

/**
 * Lo que la caja necesita de su almacén, sin saber cuál es.
 *
 * Todos los métodos son asíncronos aunque un driver en memoria podría
 * resolverlos al instante: IndexedDB y SQLite no pueden, y una interfaz que
 * los obligue a fingir que sí acabaría con `await` colados a medias por todo
 * el código de llamada.
 */
export interface AlmacenLocal {
  /** Nombre del driver, para la bitácora y el panel de diagnóstico. */
  readonly nombre: string

  abrir(): Promise<void>

  // --- cola de ventas ---
  guardarVenta(venta: VentaLocal): Promise<void>
  ventasPendientes(limite?: number): Promise<VentaLocal[]>
  marcarVenta(uuid: string, cambios: Partial<VentaLocal>): Promise<void>
  /** La venta más antigua sin sincronizar, para medir el atraso frente a SUNAT. */
  pendienteMasAntigua(): Promise<VentaLocal | null>

  // --- bitácora ---
  registrarEvento(evento: EventoLocal): Promise<void>
  eventos(limite?: number): Promise<EventoLocal[]>

  // --- catálogo maestro ---
  guardarCatalogo(productos: ProductoLocal[]): Promise<void>
  buscarEnCatalogo(termino: string, limite?: number): Promise<ProductoLocal[]>
}
