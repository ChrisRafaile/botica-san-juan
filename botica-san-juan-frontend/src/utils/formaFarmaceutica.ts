/**
 * Ilustración referencial según la forma farmacéutica del producto.
 *
 * POR QUÉ UNA ILUSTRACIÓN Y NO UNA FOTO
 *
 * La botica no tiene fotografías de sus 884 productos, y fotografiarlos uno a
 * uno llevaría semanas. Usar una foto de archivo parecida sería peor que no
 * poner nada: en un medicamento, dar a entender que el envase es ese cuando no
 * lo es puede llevar a que alguien compre lo que no quería.
 *
 * Por eso la imagen representa la FORMA FARMACÉUTICA —tableta, jarabe,
 * inyectable— y no el producto, y la tarjeta lo dice con una etiqueta visible.
 * Es información real y útil (de un vistazo se distingue un jarabe de una
 * caja de tabletas) sin afirmar nada falso.
 *
 * POR QUÉ SVG Y NO AVIF/WEBP
 *
 * Son dibujos planos de doce formas, no fotografías. En SVG cada uno pesa
 * entre 250 y 700 bytes —los doce suman 5 kB—, se ven nítidos en cualquier
 * pantalla y a cualquier tamaño, y no hace falta generar variantes por
 * densidad. Rasterizarlos a AVIF daría archivos más pesados y borrosos en
 * pantallas de alta densidad: el formato comprimido existe para fotografías,
 * que es justamente lo que aquí no hay.
 *
 * El salto de maquetación se evita igual, con la relación de aspecto fija del
 * contenedor; no depende del formato del archivo.
 *
 * EL MAPEO
 *
 * Los 22 valores de `tipo` del catálogo se agrupan en doce dibujos. Varios
 * comparten envase de verdad: una solución oftálmica y una ótica se presentan
 * las dos en frasco cuentagotas, y lo que las distingue es la vía, no el
 * envase. Dibujar un ojo o una oreja obligaba a una silueta que no se
 * reconocía al tamaño de la tarjeta.
 *
 * Lo que no esté en la tabla cae en `generico`. Eso incluye el valor
 * "medicamentos" que trae un producto del catálogo heredado, que no es una
 * forma farmacéutica sino un residuo de la importación.
 */

const RUTA = '/formas'

/** Dibujos disponibles en public/formas/. */
export type FormaIlustrada =
  | 'tableta' | 'capsula' | 'jarabe' | 'inyectable' | 'crema' | 'aerosol'
  | 'gotas' | 'ovulo' | 'polvo' | 'solucion' | 'anillo' | 'generico'

/**
 * Tipo del catálogo → dibujo. Las claves son los 22 valores reales que
 * devuelve `SELECT DISTINCT tipo FROM productos`, en mayúsculas como los
 * guarda la base.
 */
const POR_TIPO: Record<string, FormaIlustrada> = {
  TABLETA: 'tableta',
  CAPSULA: 'capsula',

  /* Líquidos para tomar: frasco con dosificador. */
  SUSPENSION: 'jarabe',
  EMULSION: 'jarabe',
  JALEA: 'jarabe',

  /* Líquidos en frasco sin dosificador. El enema es un líquido y sólo tiene
     tres productos: no justifica un dibujo propio. */
  SOLUCION: 'solucion',
  ENEMA: 'solucion',
  LOCION: 'solucion',

  INYECTABLE: 'inyectable',

  /* Semisólidos en tubo. */
  CREMA: 'crema',
  UNGUENTO: 'crema',
  'UNGUENTO OFTALMICO': 'crema',

  AEROSOL: 'aerosol',

  /* Frasco cuentagotas: lo mismo para la vía oftálmica y la ótica. */
  'SOLUCION OFTALMICA': 'gotas',
  'SUSPENSION OFTALMICA': 'gotas',
  'SOLUCION OTICA': 'gotas',

  /* Formas sólidas moldeadas. */
  OVULO: 'ovulo',
  SUPOSITORIO: 'ovulo',

  POLVO: 'polvo',
  GRANULOS: 'polvo',

  ANILLO: 'anillo',
}

/** Qué forma le corresponde a un producto. Nunca devuelve null. */
export function formaDe(tipo?: string | null): FormaIlustrada {
  const clave = tipo?.trim().toUpperCase()
  if (!clave) return 'generico'
  return POR_TIPO[clave] ?? 'generico'
}

/** Ruta del archivo que hay que cargar. */
export function ilustracionDe(tipo?: string | null): string {
  return `${RUTA}/${formaDe(tipo)}.svg`
}

/**
 * Texto alternativo.
 *
 * Dice que es una ilustración de la forma farmacéutica, no el producto: quien
 * use un lector de pantalla tiene derecho a la misma advertencia que quien ve
 * la etiqueta en la tarjeta.
 */
export function textoAlternativoDe(tipo?: string | null): string {
  const t = tipo?.trim()
  return t
    ? `Ilustración referencial de la forma farmacéutica: ${t.toLowerCase()}`
    : 'Ilustración referencial genérica de producto'
}
