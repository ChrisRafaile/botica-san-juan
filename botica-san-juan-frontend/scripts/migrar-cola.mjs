/**
 * Tercera pasada: la cola larga.
 *
 *   node scripts/migrar-cola.mjs --dry
 *
 * Lo que queda después de las dos primeras pasadas son casos de uno o dos usos:
 * un degradado cian en una tarjeta, un morado en un badge, un `to-` suelto que
 * quedó sin su `from-`. Ninguno tiene intención propia; son restos de cuando
 * cada pantalla elegía su color.
 *
 * Aquí se normaliza por familia, no clase a clase: todos los azules, índigos,
 * cianes, morados y violetas eran variantes del mismo "acento genérico", así
 * que van al acento de marca. Los verdes y esmeraldas a éxito, los rojos y
 * rosas a peligro, los ámbares y naranjas a alerta.
 *
 * Los degradados que sobrevivan se aplanan: se quita el `from-`/`via-`/`to-` y
 * se deja el fondo sólido que corresponda, por la misma razón que en la
 * segunda pasada.
 */

import { readFileSync, writeFileSync } from 'node:fs'
import { globSync } from 'node:fs'
import { argv } from 'node:process'

/* Primero se aplanan los degradados que queden, porque si se tradujeran clase
   a clase quedarían `from-botica-700 to-botica-700`, que es un degradado
   inútil en lugar de un color sólido. */
const APLANAR = [
  [/bg-linear-to-[a-z]+ from-[a-z]+-[0-9]+ via-[a-z]+-[0-9]+ to-[a-z]+-[0-9]+/g, 'ACENTO'],
  [/bg-linear-to-[a-z]+ from-[a-z]+-[0-9]+ to-[a-z]+-[0-9]+/g, 'ACENTO'],
  [/bg-gradient-to-[a-z]+ from-[a-z]+-[0-9]+ to-[a-z]+-[0-9]+/g, 'ACENTO'],
  /* Restos de hover sobre degradados ya aplanados. */
  [/hover:from-[a-z]+-[0-9]+ hover:to-[a-z]+-[0-9]+/g, 'HOVER'],
  [/\bfrom-[a-z]+-[0-9]+\b/g, ''],
  [/\bvia-[a-z]+-[0-9]+\b/g, ''],
  [/\bto-[a-z]+-[0-9]+\b/g, ''],
]

/** Familia de color → token del design system. */
const FAMILIA = {
  blue: 'botica', indigo: 'botica', violet: 'botica', purple: 'botica',
  fuchsia: 'botica', cyan: 'botica', sky: 'botica', teal: 'botica',
  green: 'exito', emerald: 'exito', lime: 'exito',
  red: 'peligro', rose: 'peligro', pink: 'peligro',
  amber: 'alerta', yellow: 'alerta', orange: 'alerta',
  gray: 'neutro', slate: 'neutro', zinc: 'neutro', neutral: 'neutro', stone: 'neutro',
}

/* Los tokens de estado sólo existen en unos pocos escalones. Se acerca al más
   próximo en lugar de inventar uno que no está definido: una clase que apunta
   a una variable inexistente no falla, simplemente no pinta nada. */
const ESCALONES = {
  exito: [50, 500, 600, 700],
  peligro: [50, 500, 600, 700],
  alerta: [50, 500, 600, 700],
  botica: [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950],
  neutro: [0, 50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950],
}

function escalonMasCercano(token, nivel) {
  const posibles = ESCALONES[token]
  return posibles.reduce(
    (mejor, n) => (Math.abs(n - nivel) < Math.abs(mejor - nivel) ? n : mejor),
    posibles[0],
  )
}

const PROPIEDADES = 'bg|text|border|ring|divide|placeholder|shadow|outline|accent|caret|fill|stroke'
const FAMILIAS = Object.keys(FAMILIA).join('|')

const soloInforme = argv.includes('--dry')
const archivos = globSync('src/admin/**/*.vue')

let total = 0

for (const ruta of archivos) {
  const original = readFileSync(ruta, 'utf8')
  let texto = original

  /* Paso 1: aplanar degradados. */
  texto = texto
    .replace(APLANAR[0][0], 'bg-botica-700')
    .replace(APLANAR[1][0], 'bg-botica-700')
    .replace(APLANAR[2][0], 'bg-botica-700')
    .replace(APLANAR[3][0], 'hover:bg-botica-800')
    .replace(APLANAR[4][0], '')
    .replace(APLANAR[5][0], '')
    .replace(APLANAR[6][0], '')

  /* Paso 2: traducir por familia, conservando prefijos (hover:, dark:, ...)
     y el sufijo de opacidad. */
  const patron = new RegExp(
    `\\b((?:[a-z-]+:)*)(${PROPIEDADES})-(${FAMILIAS})-([0-9]+)(\\/[0-9]+)?\\b`,
    'g',
  )

  texto = texto.replace(patron, (_todo, prefijos, propiedad, familia, nivel, opacidad) => {
    const token = FAMILIA[familia]
    const escalon = escalonMasCercano(token, Number(nivel))

    return `${prefijos}${propiedad}-${token}-${escalon}${opacidad ?? ''}`
  })

  /* Paso 3: limpiar los espacios que dejaron los degradados quitados, PERO
     sólo dentro de atributos de clase. Una limpieza global de espacios tocaría
     cadenas de JavaScript y comentarios, y ensuciaría el diff de archivos que
     no tenían ni un color que migrar. */
  texto = texto.replace(
    /(\s(?:class|:class)=")([^"]*)"/g,
    (_todo, apertura, valor) => `${apertura}${valor.replace(/\s{2,}/g, ' ').trim()}"`,
  )

  /* Sólo se escribe si de verdad cambió alguna clase de color. Si lo único
     distinto fueran espacios, el archivo se deja como estaba. */
  const coloresAntes = (original.match(new RegExp(`\\b(${PROPIEDADES}|from|to|via)-(${FAMILIAS})-`, 'g')) ?? []).length
  const coloresDespues = (texto.match(new RegExp(`\\b(${PROPIEDADES}|from|to|via)-(${FAMILIAS})-`, 'g')) ?? []).length

  if (coloresAntes !== coloresDespues) {
    const cambios = coloresAntes - coloresDespues

    total += cambios

    if (!soloInforme) writeFileSync(ruta, texto, 'utf8')

    console.log(
      `${soloInforme ? '[simulado] ' : ''}${ruta.padEnd(52)} ${String(coloresAntes).padStart(4)} → ${String(coloresDespues).padStart(3)} clases fijas`,
    )
  }
}

console.log(`\n${soloInforme ? 'SIMULACIÓN · ' : ''}Total: ${total}`)

const vivas = new Map()

for (const ruta of archivos) {
  const contenido = soloInforme ? readFileSync(ruta, 'utf8') : readFileSync(ruta, 'utf8')
  const restantes = contenido.match(
    new RegExp(`\\b(${PROPIEDADES}|from|to|via)-(${FAMILIAS}|white|black)(-[0-9]+)?(\\/[0-9]+)?\\b`, 'g'),
  ) ?? []

  for (const clase of restantes) vivas.set(clase, (vivas.get(clase) ?? 0) + 1)
}

if (vivas.size > 0) {
  const suma = [...vivas.values()].reduce((a, b) => a + b, 0)
  console.log(`\nQuedan ${suma} usos en ${vivas.size} clases:`)

  for (const [clase, n] of [...vivas.entries()].sort((a, b) => b[1] - a[1]).slice(0, 20)) {
    console.log(`   ${String(n).padStart(4)}  ${clase}`)
  }
} else {
  console.log('\nLimpio.')
}
