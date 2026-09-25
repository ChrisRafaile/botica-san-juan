/**
 * Resuelve las rutas de archivos que sirve la API.
 * ---------------------------------------------------------------------------
 * POR QUÉ HACE FALTA
 * La base guarda rutas relativas como `images/default_image.png`. Puestas tal
 * cual en un `src`, el navegador las resuelve contra el origen de la PÁGINA, no
 * contra el de la API. En desarrollo son dos orígenes distintos (5173 y 8083),
 * así que todas las imágenes salían rotas mostrando el texto alternativo.
 *
 * El mismo error apareció con la foto de perfil del usuario y volvería a
 * aparecer con cada imagen nueva, así que vive aquí y no dentro de cada vista.
 */

/** Rutas que ya son absolutas o llevan su propio esquema: se dejan intactas. */
const YA_RESUELTA = /^(https?:|data:|blob:|\/\/)/

/**
 * Origen de la API sin el sufijo `/api`.
 *
 * `VITE_API_URL` vale `/api` en desarrollo (a través del proxy de Vite) y una
 * URL absoluta en la aplicación de escritorio, donde no hay proxy posible.
 */
function origenDeLaApi(): string {
  const base = (import.meta.env.VITE_API_URL as string | undefined) ?? '/api'

  return base.replace(/\/api\/?$/, '')
}

/**
 * Convierte la ruta guardada en la base en una URL que el navegador pueda
 * cargar. Devuelve null cuando no hay imagen, para que la vista decida qué
 * mostrar en su lugar en vez de pedir una URL vacía.
 */
export function urlDeMedia(ruta?: string | null): string | null {
  const limpia = ruta?.trim()

  if (!limpia) return null
  if (YA_RESUELTA.test(limpia)) return limpia

  /* Las rutas de `storage` ya vienen con su prefijo en unos casos y sin él en
     otros, según por dónde se subiera el archivo. Se normaliza para no acabar
     con `/storage/storage/...`. */
  const relativa = limpia.replace(/^\/+/, '')

  return `${origenDeLaApi()}/${relativa}`
}

/**
 * Manejador de `@error` para imágenes.
 *
 * Oculta el elemento en lugar de dejar el icono de imagen rota, que es peor que
 * no mostrar nada: la vista puede poner debajo un respaldo con `v-else` o un
 * fondo neutro. Ocurre cuando el archivo existe en la base pero ya no está en
 * el servidor, algo habitual tras una migración de datos.
 */
export function ocultarSiFalla(evento: Event): void {
  const img = evento.target as HTMLImageElement | null

  if (img) img.style.display = 'none'
}
