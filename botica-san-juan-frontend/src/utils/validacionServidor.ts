/**
 * Errores de validación del servidor, traducidos a algo que un formulario
 * pueda pintar debajo del campo que los causó.
 * ---------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *
 * Laravel responde a una validación fallida con 422 y este cuerpo:
 *
 *     { "message": "...", "errors": { "dni": ["El DNI ya está registrado"] } }
 *
 * Es decir, dice EXACTAMENTE qué campo está mal y por qué. El formulario de
 * registro tiraba todo eso y mostraba un único «No se pudo crear la cuenta»:
 * quien repetía un DNI ya registrado no tenía forma de saber cuál de los seis
 * campos corregir, y volvía a pulsar enviar. Es el mismo fallo que ya se
 * corrigió en el formulario de contacto.
 *
 * La lógica vive aquí, suelta y sin dependencias, para poder probarla sin
 * montar un componente ni simular una petición.
 */

/** Error que conserva los mensajes por campo de una respuesta 422. */
export class ErrorDeValidacion extends Error {
  readonly campos: Record<string, string>

  constructor(mensaje: string, campos: Record<string, string>) {
    super(mensaje)
    this.name = 'ErrorDeValidacion'
    this.campos = campos
  }
}

/**
 * Convierte el bloque `errors` de Laravel en un mensaje por campo.
 *
 * Se queda con el PRIMER mensaje de cada campo: Laravel puede devolver varias
 * reglas incumplidas del mismo campo, y apilarlas todas bajo el input
 * desplaza el resto del formulario y abruma a quien solo necesita saber qué
 * escribir. El primero es el más específico.
 */
export function erroresPorCampo(datos: unknown): Record<string, string> {
  if (!datos || typeof datos !== 'object') return {}

  const errores = (datos as { errors?: unknown }).errors
  if (!errores || typeof errores !== 'object') return {}

  const salida: Record<string, string> = {}

  for (const [campo, mensajes] of Object.entries(errores as Record<string, unknown>)) {
    if (Array.isArray(mensajes)) {
      const primero = mensajes.find((m) => typeof m === 'string' && m.trim() !== '')
      if (primero) salida[campo] = String(primero)
    } else if (typeof mensajes === 'string' && mensajes.trim() !== '') {
      salida[campo] = mensajes
    }
  }

  return salida
}

/**
 * Traduce los nombres de campo del servidor a los del formulario.
 *
 * El backend habla en sus términos (`nombre`, `acepta_terminos`) y el
 * formulario en los suyos (`name`, `aceptaTerminos`). Sin esta traducción el
 * mensaje llega pero no encuentra dónde pintarse y se pierde en silencio, que
 * es peor que no mapear nada: parece que funciona.
 */
export function traducirCampos(
  errores: Record<string, string>,
  equivalencias: Record<string, string>,
): { campos: Record<string, string>; sueltos: string[] } {
  const campos: Record<string, string> = {}
  const sueltos: string[] = []

  for (const [campo, mensaje] of Object.entries(errores)) {
    const destino = equivalencias[campo]

    if (destino) campos[destino] = mensaje
    else sueltos.push(mensaje)
  }

  return { campos, sueltos }
}
