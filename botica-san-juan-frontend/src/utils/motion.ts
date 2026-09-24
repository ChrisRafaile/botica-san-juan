/**
 * Tokens de movimiento · Botica San Juan
 * ---------------------------------------------------------------------------
 * Equivalentes en GSAP de las curvas y duraciones declaradas en
 * `styles/design-system.css`. Existe para que una transición hecha en CSS y
 * una hecha en GSAP se muevan exactamente igual: si sólo se define en un lado,
 * la interfaz termina con dos personalidades.
 *
 * Regla: ningún componente escribe duraciones ni easings a mano. Se importan
 * de aquí.
 */

/** Curvas de aceleración. Espejo de las variables --ease-* del CSS. */
export const EASE = {
  /** Sale rápido y frena suave. Entrada de elementos, apertura de paneles. */
  salida: 'power3.out',
  /** Arranca lento y acelera. Salida de elementos que se retiran. */
  entrada: 'power3.in',
  /** Simétrica. Cambios de estado donde nada "entra" ni "sale". */
  suave: 'power2.inOut',
  /** Rebote corto. Confirmaciones y microinteracciones, nunca en navegación. */
  resorte: 'back.out(1.6)',
} as const

/** Duraciones en segundos — GSAP trabaja en segundos, el CSS en milisegundos. */
export const DUR = {
  /** Cambios de color y estados de hover. Casi imperceptible. */
  instante: 0.1,
  /** Microinteracciones: iconos, chevrons, badges. */
  rapida: 0.18,
  /** Estándar: acordeones, desplegables, cambios de vista. */
  normal: 0.26,
  /** Movimientos amplios: paneles laterales, modales grandes. */
  pausada: 0.4,
} as const

/** Desfase entre elementos de una lista animada en cascada. */
export const STAGGER = {
  ajustado: 0.03,
  normal: 0.05,
  amplio: 0.08,
} as const

/**
 * Indica si la persona pidió reducir el movimiento en su sistema operativo.
 *
 * No es un detalle opcional: para alguien con trastorno vestibular una
 * animación de desplazamiento puede provocar mareo real. Cuando devuelve
 * `true`, los componentes deben aplicar el estado final sin transición, no
 * simplemente acelerarla.
 */
export function prefiereMenosMovimiento(): boolean {
  if (typeof window === 'undefined' || !window.matchMedia) return false
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

/**
 * Ajusta una duración según la preferencia de movimiento del usuario.
 * Devuelve 0 si pidió menos movimiento, de modo que GSAP salte al estado final.
 */
export function duracion(valor: number): number {
  return prefiereMenosMovimiento() ? 0 : valor
}
