/**
 * Mapeo compartido de vencimientos hacia los tonos semánticos de BoticaBadge
 * (`--color-venc-*` definidos en design-system.css).
 */

export type TonoVencimiento = 'vence-critico' | 'vence-alto' | 'vence-medio' | 'vigente'

export function tonoVencimiento(dias: number | null | undefined, nivel?: string | null): TonoVencimiento {
  if (nivel === 'vencido' || nivel === 'critico' || (dias !== null && dias !== undefined && dias <= 30)) {
    return 'vence-critico'
  }
  if (nivel === 'alto' || (dias !== null && dias !== undefined && dias <= 60)) {
    return 'vence-alto'
  }
  if (nivel === 'medio' || (dias !== null && dias !== undefined && dias <= 90)) {
    return 'vence-medio'
  }
  return 'vigente'
}

export function etiquetaVencimiento(dias: number | null | undefined): string {
  if (dias === null || dias === undefined) return 'Sin fecha'
  if (dias < 0) return 'VENCIDO'
  return `${dias}d`
}
