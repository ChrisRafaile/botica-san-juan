/**
 * Depuración de datos sensibles antes de que salgan del navegador.
 * ---------------------------------------------------------------------------
 * ESTE ARCHIVO ES EL CONTRATO DE PRIVACIDAD DEL PORTAL
 *
 * Sentry y PostHog son dos servicios distintos con dos problemas distintos,
 * pero la pregunta es la misma: ¿qué de lo que ocurre en esta pantalla puede
 * salir del navegador?
 *
 * En una botica la respuesta es más estrecha que en una tienda de zapatos.
 * Lo que una persona busca en el catálogo —«amoxicilina», «test de
 * embarazo»— dice algo sobre su salud. El contenido del carrito, también. Y
 * una receta médica lleva el diagnóstico escrito.
 *
 * De ahí la regla que gobierna todo lo que sigue:
 *
 *     NO SE RECOGE NADA QUE PERMITA SABER QUÉ MEDICAMENTO MIRA UNA PERSONA.
 *
 * Eso descarta de entrada tres cosas que las herramientas traen activadas y
 * que casi nadie apaga: la captura automática de clics (lleva el texto del
 * elemento, o sea el nombre del producto), la URL completa (lleva el término
 * de búsqueda en la cadena de consulta) y el título de la página (lleva el
 * nombre del producto abierto).
 */

/** Rutas que no se graban NUNCA, ni enmascaradas. */
const RUTAS_PROHIBIDAS = [
  /* Una receta es un documento clínico: lleva el nombre del paciente, el del
     médico y el diagnóstico implícito en el medicamento. Grabar la pantalla
     donde se ve, aunque sea con el texto tapado, significa grabar la imagen
     del documento. */
  '/auth/google',
  '/restablecer-contrasena',
  '/verificar-correo',
  '/perfil',
  '/cart',
]

/** Partes de la URL que delatan qué se estaba mirando. */
const PARAMETROS_PROHIBIDOS = [
  'q', 'buscar', 'search', 'termino', 'codigo', 'token', 'enlace', 'correo', 'email', 'dni',
]

const PATRONES: Array<[RegExp, string]> = [
  [/[\w.+-]+@[\w-]+\.[\w.-]+/g, '[correo]'],
  [/(?<![\d-])\d{8}(?![\d-])/g, '[dni]'],
  [/(?<![\d-])(?:10|20)\d{9}(?![\d-])/g, '[ruc]'],
  [/\b\d+\|[A-Za-z0-9]{20,}/g, '[token]'],
  [/\bBearer\s+[A-Za-z0-9._-]+/gi, 'Bearer [token]'],
  [/\b(?:re|sk|pk|ghp|gho)_[A-Za-z0-9_-]{10,}/g, '[clave]'],
  [/(?<![\d-])9\d{8}(?![\d-])/g, '[telefono]'],
]

/** Aplica los patrones a cualquier texto. */
export function limpiarTexto(valor: string): string {
  return PATRONES.reduce((texto, [patron, reemplazo]) => texto.replace(patron, reemplazo), valor)
}

/**
 * Deja la URL sin nada que cuente qué se buscaba.
 *
 * Se conserva la ruta porque sin ella no se puede saber dónde falló algo, y
 * se sustituyen los identificadores numéricos: `/pedidos/217` se convierte en
 * `/pedidos/:id`, que agrupa los errores en lugar de dispersarlos en uno por
 * pedido.
 */
export function limpiarUrl(url: string): string {
  try {
    const u = new URL(url, window.location.origin)

    for (const parametro of PARAMETROS_PROHIBIDOS) {
      u.searchParams.delete(parametro)
    }

    /* Lo que quede de la consulta se limpia igual: puede haber un parámetro
       nuevo que nadie añadió a la lista. */
    for (const [clave, valor] of [...u.searchParams.entries()]) {
      u.searchParams.set(clave, limpiarTexto(valor))
    }

    u.pathname = u.pathname.replace(/\/\d+(?=\/|$)/g, '/:id')
    u.hash = ''

    return limpiarTexto(u.toString())
  } catch {
    /* Una URL que no se puede analizar se reduce a nada: es preferible
       perder el dato a dejar pasar una cadena entera sin mirar. */
    return '[url]'
  }
}

/** ¿Esta ruta se puede grabar? */
export function rutaGrabable(ruta: string): boolean {
  return !RUTAS_PROHIBIDAS.some((prohibida) => ruta.startsWith(prohibida))
}

/**
 * Propiedades de un evento de analítica, ya depuradas.
 *
 * PostHog añade por su cuenta `$current_url`, `$pathname`, `$referrer` y
 * `$title`, y los cuatro pueden llevar lo que no debe salir: la URL y el
 * referente llevan el término de búsqueda, y el título el nombre del producto
 * abierto.
 */
export function limpiarPropiedades(
  propiedades: Record<string, unknown>,
): Record<string, unknown> {
  const limpias: Record<string, unknown> = { ...propiedades }

  for (const clave of ['$current_url', '$referrer', '$initial_current_url', '$initial_referrer']) {
    if (typeof limpias[clave] === 'string') {
      limpias[clave] = limpiarUrl(limpias[clave] as string)
    }
  }

  /* El título se descarta entero. En la ficha de un producto es el nombre del
     medicamento, y no hay forma de distinguirlo de un título inocuo. */
  delete limpias['$title']
  delete limpias['title']

  if (typeof limpias['$pathname'] === 'string') {
    limpias['$pathname'] = (limpias['$pathname'] as string).replace(/\/\d+(?=\/|$)/g, '/:id')
  }

  for (const [clave, valor] of Object.entries(limpias)) {
    if (typeof valor === 'string') {
      limpias[clave] = limpiarTexto(valor)
    }
  }

  return limpias
}
