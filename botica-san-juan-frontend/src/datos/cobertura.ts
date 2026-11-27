/**
 * Zonas de reparto. Fuente única para /coverage.
 *
 * POR QUÉ SOLO SAN JUAN DE LURIGANCHO
 *
 * La página ofrecía delivery a Surco, San Borja, Cercado y Callao, con mapas
 * de rectángulos dibujados a mano. La botica tiene un único local en SJL y
 * reparte con su propio personal: prometer Callao a 60 minutos era vender un
 * servicio que no existe. Se cubre el distrito, zona por zona.
 *
 * DE DÓNDE SALEN TIEMPOS Y TARIFAS
 *
 * Por distancia desde el local (Av. Sta. Rosa de Lima 103), en tramos: la
 * tarifa base de SJL que ya publicaba la página (S/ 5.00, 20-35 min) queda
 * para el tramo medio, y sube o baja con la distancia. El cobro real lo
 * confirma la botica al tomar el pedido; si cambian, se cambian aquí y la
 * página entera se actualiza. Las coordenadas son el centro aproximado de cada
 * zona, solo para situar el marcador.
 */

export interface ZonaReparto {
  id: string
  nombre: string
  referencia: string
  /** Minutos, mínimo y máximo. */
  minutos: [number, number]
  /** Soles. */
  tarifa: number
  coordenadas: [number, number]
}

export const LOCAL: [number, number] = [-11.9669, -76.998]

export const PEDIDO_MINIMO = 15

export const ZONAS: ZonaReparto[] = [
  { id: 'canto-grande', nombre: 'Canto Grande', referencia: 'Av. Canto Grande, Los Pinos y alrededores del local', minutos: [15, 25], tarifa: 3, coordenadas: [-11.9585, -76.9935] },
  { id: 'san-hilarion', nombre: 'San Hilarión', referencia: 'Urb. San Hilarión y Av. Las Lomas', minutos: [20, 30], tarifa: 4, coordenadas: [-11.9845, -77.0005] },
  { id: 'mariscal-caceres', nombre: 'Mariscal Cáceres', referencia: 'Av. Fernando Wiesse, sectores I al III', minutos: [20, 30], tarifa: 4, coordenadas: [-11.948, -76.989] },
  { id: 'bayovar', nombre: 'Bayóvar', referencia: 'Estación Bayóvar y Av. Wiesse norte', minutos: [25, 35], tarifa: 5, coordenadas: [-11.9415, -76.9975] },
  { id: 'las-flores', nombre: 'Las Flores', referencia: 'Urb. Las Flores y Av. Próceres de la Independencia', minutos: [25, 35], tarifa: 5, coordenadas: [-11.999, -76.992] },
  { id: 'mangomarca', nombre: 'Mangomarca', referencia: 'Urb. Mangomarca y Av. Lurigancho', minutos: [30, 45], tarifa: 6, coordenadas: [-12.015, -76.985] },
  { id: 'zarate', nombre: 'Zárate', referencia: 'Urb. Zárate y Av. Gran Chimú', minutos: [35, 45], tarifa: 7, coordenadas: [-12.023, -76.999] },
  { id: 'campoy', nombre: 'Campoy', referencia: 'Urb. Campoy y Av. Malecón Checa', minutos: [35, 50], tarifa: 7, coordenadas: [-12.02, -76.97] },
  { id: 'caja-de-agua', nombre: 'Caja de Agua', referencia: 'Estación Caja de Agua y Av. Chimú', minutos: [40, 50], tarifa: 8, coordenadas: [-12.03, -77.012] },
]

export const soles = (monto: number) => `S/ ${monto.toFixed(2)}`

export const rangoMinutos = (z: ZonaReparto) => `${z.minutos[0]}–${z.minutos[1]} min`

/** Para el titular: «15 a 50 minutos» calculado, no escrito a mano. */
export const MINUTOS_EXTREMOS: [number, number] = [
  Math.min(...ZONAS.map((z) => z.minutos[0])),
  Math.max(...ZONAS.map((z) => z.minutos[1])),
]

export const TARIFA_EXTREMOS: [number, number] = [
  Math.min(...ZONAS.map((z) => z.tarifa)),
  Math.max(...ZONAS.map((z) => z.tarifa)),
]
