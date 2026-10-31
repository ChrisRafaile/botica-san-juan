/**
 * Indicadores gerenciales del panel.
 *
 * Todo viene agregado desde la base: aquí no se suma ni se ordena nada. Si
 * esta capa empezara a calcular, la pantalla y el informe contable podrían
 * dar cifras distintas del mismo día.
 */
import api from './api'

export interface TramoVencimiento {
  clave: 'vencido' | 't30' | 't60' | 't90' | 'holgado'
  etiqueta: string
  color: 'peligro' | 'alerta' | 'ambar' | 'exito'
  lotes: number
  unidades: number
}

export interface Vencimientos {
  tramos: TramoVencimiento[]
  sin_fecha: { lotes: number; unidades: number }
  lotes_con_existencia: number
  /** Porcentaje de lotes con existencia que tienen fecha capturada. */
  cobertura: number
}

export interface MedioDePago {
  medio: string
  etiqueta: string
  operaciones: number
  total: number
  porcentaje: number
}

export interface MasVendido {
  producto_id: number
  nombre: string
  unidades: number
  importe: number
}

export interface Indicadores {
  vencimientos: Vencimientos
  arqueo: { medios: MedioDePago[]; total: number }
  mas_vendidos: MasVendido[]
  ventana_dias: number
  generado_en: string
}

export async function obtenerIndicadores(dias = 30): Promise<Indicadores> {
  const { data } = await api.get<Indicadores>('/tablero/indicadores', {
    params: { dias },
  })
  return data
}
